<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer\Server\Response;

use Riki137\AmphpChannelServer\Server\Response\ChannelResponse;

class ErrorResponse implements ChannelResponse
{
    public function __construct(private readonly string $message)
    {
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
