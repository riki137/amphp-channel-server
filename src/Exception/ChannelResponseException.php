<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer\Client\Exception;

use Exception;
use Riki137\AmphpChannelServer\Server\Response\ChannelResponse;
use Throwable;

class ChannelResponseException extends Exception implements ChannelExceptionInterface
{
    public function __construct(
        string $message,
        private readonly ?ChannelResponse $response = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getResponse(): ?ChannelResponse
    {
        return $this->response;
    }
}
