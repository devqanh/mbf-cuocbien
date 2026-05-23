<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    protected $fillable = [
        'task_id', 'user_id', 'body', 'mentioned_user_ids',
    ];

    protected function casts(): array
    {
        return [
            'mentioned_user_ids' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Render body với @tên-user được wrap trong <span class="mention">.
     * Escape HTML để chống XSS, sau đó replace token đã được mark thành thẻ.
     */
    public function renderedBody(): string
    {
        $body = e($this->body);

        $userIds = $this->mentioned_user_ids ?? [];
        if (empty($userIds)) return $body;

        $users = User::whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');

        foreach ($users as $u) {
            $needle = '@' . $u->name;
            $needleEsc = e($needle);
            $replacement = '<span class="mention" data-user-id="' . $u->id . '">' . $needleEsc . '</span>';
            $body = str_replace($needleEsc, $replacement, $body);
        }

        return nl2br($body);
    }
}
