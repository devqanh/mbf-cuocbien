<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SheetUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sheetKey,
        public int $version,
        public int $editorId,
        public string $editorName,
        public int $savedRows,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('items-sheet')];
    }

    public function broadcastAs(): string
    {
        return 'sheet.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'sheetKey'   => $this->sheetKey,
            'version'    => $this->version,
            'editorId'   => $this->editorId,
            'editorName' => $this->editorName,
            'savedRows'  => $this->savedRows,
            'at'         => now()->toIso8601String(),
        ];
    }
}
