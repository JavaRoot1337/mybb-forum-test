<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Exception;

use RuntimeException;

final class InvalidRequestException extends RuntimeException
{
    public function __construct(private int $status = 400)
    {
        parent::__construct();
    }

    public function status(): int
    {
        return $this->status;
    }
}
