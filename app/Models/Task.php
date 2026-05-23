<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends Model
{
    public const STATUS_TODO  = 'todo';
    public const STATUS_DOING = 'doing';
    public const STATUS_DONE  = 'done';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'title', 'body',
        'status', 'priority',
        'due_at', 'remind_before', 'reminder_sent_at',
        'completed_at',
        'created_by',
        'linkable_type', 'linkable_id',
    ];

    protected function casts(): array
    {
        return [
            'due_at'           => 'datetime',
            'reminder_sent_at' => 'datetime',
            'completed_at'     => 'datetime',
            'remind_before'    => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user')
            ->wherePivot('role', 'assignee')
            ->withTimestamps();
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user')
            ->wherePivot('role', 'watcher')
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->with('author:id,name')->oldest();
    }

    /** Tất cả user liên quan (assignee + watcher + creator) — dùng để broadcast. */
    public function recipients(): array
    {
        $ids = $this->assignees->pluck('id')
            ->concat($this->watchers->pluck('id'))
            ->push($this->created_by)
            ->unique()
            ->values();

        return $ids->all();
    }

    // ---------- Scopes ----------

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', '!=', self::STATUS_DONE);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->open()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    public function scopeDueToday(Builder $q): Builder
    {
        return $q->open()
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]);
    }

    public function scopeDueWithin(Builder $q, int $days): Builder
    {
        return $q->open()
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->addDays($days)]);
    }

    public function scopeAssignedTo(Builder $q, int $userId): Builder
    {
        return $q->whereHas('assignees', fn ($a) => $a->where('users.id', $userId));
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->can('tasks.manage_all')) {
            return $q;
        }
        return $q->where(function ($w) use ($user) {
            $w->where('created_by', $user->id)
              ->orWhereHas('assignees', fn ($a) => $a->where('users.id', $user->id))
              ->orWhereHas('watchers',  fn ($a) => $a->where('users.id', $user->id));
        });
    }

    // ---------- Helpers ----------

    public function isOverdue(): bool
    {
        return $this->due_at && $this->status !== self::STATUS_DONE && $this->due_at->isPast();
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    /** Thời điểm nên bắn reminder (due_at - remind_before phút). null nếu không có due. */
    public function reminderAt(): ?\Carbon\Carbon
    {
        if (! $this->due_at) return null;
        return $this->remind_before
            ? $this->due_at->copy()->subMinutes($this->remind_before)
            : $this->due_at->copy();
    }
}
