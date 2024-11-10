<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer\Server\Response;

use Riki137\AmphpChannelServer\Server\Response\ChannelResponse;

final class ChannelResponseMessage
{
    public function __construct(private readonly string $requestId, private readonly ChannelResponse $response)
    {
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getResponse(): ChannelResponse
    {
        return $this->response;
    }
}
