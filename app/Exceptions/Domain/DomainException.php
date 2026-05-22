<?php

namespace App\Exceptions\Domain;

use RuntimeException;

/**
 * Base cho các exception nghiệp vụ. Controller catch và map sang HTTP code phù hợp.
 */
abstract class DomainException extends RuntimeException
{
    abstract public function httpStatus(): int;

    /** Dữ liệu bổ sung để render response (vd: server_version cho conflict) */
    public function context(): array
    {
        return [];
    }
}
