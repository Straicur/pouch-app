<?php

declare(strict_types = 1);

namespace App\ExceptionManagement\Exceptions;

use App\ExceptionManagement\Exceptions\Model\ExceptionModelInterface;
use Exception;
use Throwable;

class ServerException extends Exception
{
    public function __construct(
        private readonly ExceptionModelInterface $model,
        string $message = 'Internal system error',
        int $code = 500,
        ?Throwable $previous = null,
    ) {
        parent::__construct(message: $message, code: $code, previous: $previous);
    }

    public function getModel(): ExceptionModelInterface
    {
        return $this->model;
    }
}
