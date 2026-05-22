<?php

namespace App\Exceptions\Domain;

/**
 * Vi phạm quy tắc nghiệp vụ (vd: xoá super_admin, tự xoá chính mình).
 * Mặc định trả 422; có thể override với $status.
 */
class BusinessRuleException extends DomainException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->status;
    }
}
