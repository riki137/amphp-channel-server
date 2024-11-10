<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer\Server\Request;

use Riki137\AmphpChannelServer\Server\Request\ChannelRequest;

final class ChannelRequestMessage
{
    public function __construct(private readonly string $requestId, private readonly ChannelRequest $request)
    {
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getRequest(): ChannelRequest
    {
        return $this->request;
    }
}
