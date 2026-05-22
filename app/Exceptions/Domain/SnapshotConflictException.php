<?php

namespace App\Exceptions\Domain;

class SnapshotConflictException extends DomainException
{
    public function __construct(
        public readonly int $serverVersion,
        public readonly int $clientVersion,
    ) {
        parent::__construct(
            "Dữ liệu đã được cập nhật bởi người khác (v{$serverVersion}). Vui lòng tải lại."
        );
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function context(): array
    {
        return [
            'conflict'       => true,
            'server_version' => $this->serverVersion,
            'client_version' => $this->clientVersion,
        ];
    }
}
