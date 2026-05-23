<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskComment extends Model
{
    /**
     * Cache user mentioned (id → User) — share giữa tất cả comments trong 1 request.
     * Tránh N+1 khi render mention: prime cache 1 lần thay vì query cho từng comment.
     */
    private static array $mentionCache = [];

    protected $fillable = [
        'task_id', 'user_id', 'parent_id', 'body', 'mentioned_user_ids',
    ];

    protected function casts(): array
    {
        return [
            'mentioned_user_ids' => 'array',
        ];
    }

    /**
     * Prime cache user cho danh sách comments (kèm replies eager-loaded).
     * Gọi 1 lần ở controller trước khi render view → renderedBody() không query nữa.
     */
    public static function primeMentionCache(EloquentCollection $comments): void
    {
        $allIds = [];
        foreach ($comments as $c) {
            if (! empty($c->mentioned_user_ids)) {
                $allIds = array_merge($allIds, $c->mentioned_user_ids);
            }
            if ($c->relationLoaded('replies')) {
                foreach ($c->replies as $r) {
                    if (! empty($r->mentioned_user_ids)) {
                        $allIds = array_merge($allIds, $r->mentioned_user_ids);
                    }
                }
            }
        }

        $allIds = array_values(array_unique(array_map('intval', $allIds)));
        if (empty($allIds)) return;

        // CHỈ query những ID chưa có trong cache
        $missing = array_diff($allIds, array_keys(self::$mentionCache));
        if (empty($missing)) return;

        $users = User::whereIn('id', $missing)->get(['id', 'name']);
        foreach ($users as $u) {
            self::$mentionCache[$u->id] = $u;
        }
    }

    /** Clear cache — gọi giữa các test, không cần ở production. */
    public static function clearMentionCache(): void
    {
        self::$mentionCache = [];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'parent_id')->oldest();
    }

    public function scopeTopLevel(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Render body với @tên-user được wrap trong <span class="mention">.
     * Đọc từ static mentionCache (đã prime ở controller) → KHÔNG query DB.
     * Fallback: nếu cache chưa có id → query 1 lần và cache lại (defensive).
     */
    public function renderedBody(): string
    {
        $body = e($this->body);

        $userIds = $this->mentioned_user_ids ?? [];
        if (empty($userIds)) return $body;

        // Tìm ID nào chưa có trong cache → query bù (chỉ xảy ra nếu quên prime)
        $missing = array_diff(array_map('intval', $userIds), array_keys(self::$mentionCache));
        if (! empty($missing)) {
            $fetched = User::whereIn('id', $missing)->get(['id', 'name']);
            foreach ($fetched as $u) self::$mentionCache[$u->id] = $u;
        }

        foreach ($userIds as $id) {
            $u = self::$mentionCache[(int) $id] ?? null;
            if (! $u) continue;
            $needle = '@' . $u->name;
            $needleEsc = e($needle);
            $replacement = '<span class="mention" data-user-id="' . $u->id . '">' . $needleEsc . '</span>';
            $body = str_replace($needleEsc, $replacement, $body);
        }

        return nl2br($body);
    }
}
