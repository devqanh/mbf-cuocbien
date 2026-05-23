<?php

namespace App\Http\Controllers;

use App\Models\PayableReport;
use App\Models\Shipment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskUpdatedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * Map alias loại entity (gửi từ form) → class polymorphic.
     * Mở rộng thêm ở đây nếu muốn link Task vào entity khác.
     */
    private const LINKABLE_MAP = [
        'shipment' => Shipment::class,
        'report'   => PayableReport::class,
    ];

    public function index(Request $request)
    {
        $user   = $request->user();
        $view   = $request->get('view', 'mine');   // mine|today|overdue|upcoming|created|done|all
        $q      = trim((string) $request->get('q', ''));

        $query = Task::query()
            ->with(['assignees:id,name', 'creator:id,name', 'linkable'])
            ->visibleTo($user);

        $query = match ($view) {
            'today'    => $query->dueToday(),
            'overdue'  => $query->overdue(),
            'upcoming' => $query->dueWithin(7),
            'created'  => $query->where('created_by', $user->id)->open(),
            'done'     => $query->where('status', Task::STATUS_DONE),
            'all'      => $user->can('tasks.manage_all') ? $query : $query->open(),
            default    => $query->assignedTo($user->id)->open(),  // 'mine'
        };

        if ($q !== '') {
            $query->where(fn ($w) => $w->where('title', 'like', "%$q%")
                                       ->orWhere('body', 'like', "%$q%"));
        }

        $tasks = $query
            ->orderByRaw('CASE WHEN status = "done" THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Counters cho sidebar
        $counters = $this->counters($user);

        $users = User::orderBy('name')->get(['id', 'name']);

        return view('tasks.index', compact('tasks', 'view', 'q', 'counters', 'users'));
    }

    public function show(Request $request, Task $task)
    {
        $this->authorizeView($request->user(), $task);

        // Eager load relation thông thường
        $task->load([
            'assignees:id,name',
            'watchers:id,name',
            'creator:id,name',
            'linkable',
        ]);

        // ===== Load comments theo PAGINATION (scale) =====
        $showAll = $request->boolean('all_comments');
        $pageSize = Task::COMMENTS_PAGE_SIZE;
        $totalTopLevel = $task->topLevelComments()->count();
        $hiddenCount = 0;

        $topLevelQuery = $task->topLevelComments()
            ->with([
                'author:id,name',
                'replies.author:id,name',     // load all replies, view sẽ tự collapse
            ]);

        if ($showAll || $totalTopLevel <= $pageSize) {
            $comments = $topLevelQuery->oldest()->get();
        } else {
            // Lấy LATEST N theo created_at DESC, sau đó reverse cho chronological order
            $comments = $topLevelQuery->latest()->limit($pageSize)->get()->reverse()->values();
            $hiddenCount = $totalTopLevel - $pageSize;
        }
        $task->setRelation('comments', $comments);

        // Prime mention cache 1 lần — tránh N+1 trong renderedBody()
        \App\Models\TaskComment::primeMentionCache($task->comments);

        $users = User::orderBy('name')->get(['id', 'name']);
        return view('tasks.show', compact('task', 'users', 'hiddenCount', 'totalTopLevel'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('tasks.create'), 403);

        $data = $this->validatePayload($request);

        $task = Task::create([
            'title'         => $data['title'],
            'body'          => $data['body'] ?? null,
            'status'        => Task::STATUS_TODO,
            'priority'      => $data['priority'] ?? Task::PRIORITY_NORMAL,
            'due_at'        => $data['due_at'] ?? null,
            'remind_before' => $data['remind_before'] ?? null,
            'created_by'    => $request->user()->id,
            'linkable_type' => $data['linkable_type'] ?? null,
            'linkable_id'   => $data['linkable_id'] ?? null,
        ]);

        $assigneeIds = $this->resolveAssigneeIds($request, $data['assignees'] ?? []);

        if (! empty($assigneeIds)) {
            $task->assignees()->sync(
                collect($assigneeIds)->mapWithKeys(fn ($id) => [$id => ['role' => 'assignee']])->all()
            );

            $assignees = User::whereIn('id', $assigneeIds)->get();
            Notification::send($assignees, new TaskAssignedNotification($task, $request->user()));
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'task' => $task->load('assignees:id,name')]);
        }

        return redirect()->route('tasks.show', $task)->with('success', 'Đã tạo task.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeEdit($request->user(), $task);

        $data = $this->validatePayload($request);

        // Tính diff để báo cho watcher/assignee biết cái gì đổi
        $changes = [];
        foreach (['title', 'body', 'status', 'priority', 'due_at', 'remind_before'] as $f) {
            if (! array_key_exists($f, $data)) continue;
            $old = $task->{$f};
            $new = $data[$f];
            if ((string) $old !== (string) $new) {
                $changes[$f] = [$old, $new];
            }
        }

        $task->fill($data);

        // Khi đổi status sang done → set completed_at; ngược lại → null
        if (isset($data['status'])) {
            $task->completed_at = $data['status'] === Task::STATUS_DONE ? now() : null;
        }

        // Nếu đổi due_at → reset reminder_sent_at để hệ thống nhắc lại theo lịch mới
        if (array_key_exists('due_at', $data) && isset($changes['due_at'])) {
            $task->reminder_sent_at = null;
        }

        $task->save();

        // Đồng bộ assignees nếu form có gửi
        if (array_key_exists('assignees', $data)) {
            $oldIds = $task->assignees->pluck('id')->all();
            $newIds = $this->resolveAssigneeIds($request, $data['assignees']);

            $task->assignees()->sync(
                collect($newIds)->mapWithKeys(fn ($id) => [$id => ['role' => 'assignee']])->all()
            );

            // Người mới được assign → nhận TaskAssigned
            $added = array_diff($newIds, $oldIds);
            if (! empty($added)) {
                $newAssignees = User::whereIn('id', $added)->get();
                Notification::send($newAssignees, new TaskAssignedNotification($task->fresh(), $request->user()));
            }
        }

        if (! empty($changes)) {
            // Notify tất cả người liên quan (assignee + creator + watcher), trừ chính editor
            $recipients = User::whereIn('id', $task->recipients())
                ->where('id', '!=', $request->user()->id)
                ->get();
            Notification::send($recipients, new TaskUpdatedNotification($task->fresh(), $request->user(), $changes));
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Đã cập nhật task.');
    }

    /** Đổi status nhanh (todo↔doing↔done) qua action button trong list. */
    public function toggleStatus(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeEdit($request->user(), $task);

        $next = $request->validate([
            'status' => ['required', Rule::in([Task::STATUS_TODO, Task::STATUS_DOING, Task::STATUS_DONE])],
        ])['status'];

        $old = $task->status;
        if ($old !== $next) {
            $task->status       = $next;
            $task->completed_at = $next === Task::STATUS_DONE ? now() : null;
            $task->save();

            $recipients = User::whereIn('id', $task->recipients())
                ->where('id', '!=', $request->user()->id)
                ->get();
            Notification::send($recipients, new TaskUpdatedNotification(
                $task->fresh(), $request->user(), ['status' => [$old, $next]]
            ));
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'status' => $next]);
        }

        return back()->with('success', 'Đã đổi trạng thái.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeEdit($request->user(), $task, requireOwnerOrManager: true);
        $task->delete();
        return back()->with('success', 'Đã xoá task.');
    }

    // ===== Helpers =====

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'body'          => ['nullable', 'string', 'max:5000'],
            'status'        => ['nullable', Rule::in([Task::STATUS_TODO, Task::STATUS_DOING, Task::STATUS_DONE])],
            'priority'      => ['nullable', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH, Task::PRIORITY_URGENT])],
            'due_at'        => ['nullable', 'date'],
            'remind_before' => ['nullable', 'integer', 'min:0', 'max:43200'],   // tối đa 30 ngày
            'assignees'     => ['nullable', 'array'],
            'assignees.*'   => ['integer', 'exists:users,id'],
            'linkable_type' => ['nullable', Rule::in(array_keys(self::LINKABLE_MAP))],
            'linkable_id'   => ['nullable', 'integer'],
        ], [], [
            'title' => 'Tiêu đề',
        ]) + $this->resolveLinkable($request);
    }

    /** Convert alias 'shipment'/'report' về class polymorphic + verify entity tồn tại. */
    private function resolveLinkable(Request $request): array
    {
        $alias = $request->input('linkable_type');
        $id    = $request->input('linkable_id');
        if (! $alias || ! $id) return [];

        $class = self::LINKABLE_MAP[$alias] ?? null;
        if (! $class || ! $class::find($id)) return [];

        return ['linkable_type' => $class, 'linkable_id' => (int) $id];
    }

    private function resolveAssigneeIds(Request $request, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        // Không có quyền giao cho người khác → chỉ tự gán mình
        if (! $request->user()->can('tasks.assign_others')) {
            return [$request->user()->id];
        }

        // Mặc định: nếu danh sách rỗng → tự gán mình để task luôn có người chịu trách nhiệm
        if (empty($ids)) return [$request->user()->id];

        return $ids;
    }

    private function authorizeView(User $user, Task $task): void
    {
        if ($user->can('tasks.manage_all')) return;

        $allowed = $task->created_by === $user->id
            || $task->assignees->contains('id', $user->id)
            || $task->watchers->contains('id', $user->id);

        abort_unless($allowed, 403, 'Bạn không có quyền xem task này.');
    }

    private function authorizeEdit(User $user, Task $task, bool $requireOwnerOrManager = false): void
    {
        abort_unless($user->can('tasks.create') || $user->can('tasks.manage_all'), 403);

        if ($user->can('tasks.manage_all')) return;

        if ($requireOwnerOrManager) {
            abort_unless($task->created_by === $user->id, 403, 'Chỉ người tạo hoặc admin mới được thực hiện thao tác này.');
            return;
        }

        $allowed = $task->created_by === $user->id
            || $task->assignees->contains('id', $user->id);

        abort_unless($allowed, 403);
    }

    private function counters(User $user): array
    {
        $base = Task::query()->visibleTo($user);

        return [
            'mine'     => (clone $base)->assignedTo($user->id)->open()->count(),
            'today'    => (clone $base)->dueToday()->count(),
            'overdue'  => (clone $base)->overdue()->count(),
            'upcoming' => (clone $base)->dueWithin(7)->count(),
            'created'  => (clone $base)->where('created_by', $user->id)->open()->count(),
            'done'     => (clone $base)->where('status', Task::STATUS_DONE)->count(),
            'all'      => $user->can('tasks.manage_all')
                ? Task::count()
                : (clone $base)->open()->count(),
        ];
    }
}
