<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class ProcessDueReminders extends Command
{
    protected $signature = 'tasks:remind';
    protected $description = 'Tìm các task tới hạn nhắc và gửi notification cho assignee + creator';

    public function handle(): int
    {
        $now   = now();
        $count = 0;

        // Tìm task chưa done, có due_at, chưa từng gửi reminder, và đã tới thời điểm nhắc.
        // remind_before nullable → coalesce về 0 (nhắc đúng giờ hạn).
        Task::query()
            ->whereNull('reminder_sent_at')
            ->where('status', '!=', Task::STATUS_DONE)
            ->whereNotNull('due_at')
            ->whereRaw('DATE_SUB(due_at, INTERVAL COALESCE(remind_before, 0) MINUTE) <= ?', [$now])
            ->with(['assignees:id', 'creator:id'])
            ->chunkById(100, function ($tasks) use (&$count) {
                foreach ($tasks as $task) {
                    // Recipients: assignee + creator (theo lựa chọn user)
                    $recipientIds = $task->assignees->pluck('id')
                        ->push($task->created_by)
                        ->unique()
                        ->values()
                        ->all();

                    if (! empty($recipientIds)) {
                        $recipients = User::whereIn('id', $recipientIds)->get();
                        Notification::send($recipients, new TaskReminderNotification($task));
                    }

                    $task->reminder_sent_at = now();
                    $task->saveQuietly();
                    $count++;
                }
            });

        $this->info("Đã gửi reminder cho {$count} task.");
        return self::SUCCESS;
    }
}
