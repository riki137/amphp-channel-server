<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer\Server\Response;

use Riki137\AmphpChannelServer\Server\Response\ErrorResponse;

class ExceptionResponse extends ErrorResponse
{
    private readonly string $message;
    private readonly int $code;
    private readonly array $trace;

    public function __construct(\Throwable $exception)
    {
        parent::__construct($exception->getMessage());
        $this->code = $exception->getCode();
        $this->trace = $exception->getTrace();
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getTrace(): array
    {
        return $this->trace;
    }
}
