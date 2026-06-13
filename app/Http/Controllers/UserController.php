<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use App\Notifications\BroadcastTestNotification;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function index(Request $request)
    {
        $q = $request->get('q');

        $users = User::with('roles')
            ->when($q, fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%");
            }))
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles', 'q'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'roles'    => ['array'],
            'roles.*'  => ['string', 'exists:roles,name'],
        ]);

        $user = $this->users->create($data, $data['roles'] ?? []);

        return back()->with('success', "Đã thêm thành viên: {$user->name}");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:6'],
            'roles'    => ['array'],
            'roles.*'  => ['string', 'exists:roles,name'],
        ]);

        $user = $this->users->update($user, $data, $data['roles'] ?? []);

        return back()->with('success', "Đã cập nhật thành viên: {$user->name}");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        try {
            $this->users->delete($user, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Đã xoá: {$user->name}");
    }

    /**
     * Debug: gửi 1 notification TEST cho TẤT CẢ users để kiểm tra Reverb hoạt động.
     * Chỉ admin (có quyền users.view) được dùng.
     */
    public function broadcastTest(Request $request): RedirectResponse
    {
        $sender = $request->user();
        $users  = User::all();

        Notification::send($users, new BroadcastTestNotification($sender));

        return back()->with('success', sprintf(
            'Đã đẩy thông báo TEST cho %d user. Mở các tab khác để kiểm tra toast realtime.',
            $users->count()
        ));
    }
}
