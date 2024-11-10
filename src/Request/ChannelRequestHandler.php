<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer\Server\Request;

use Riki137\AmphpChannelServer\Server\Response\ChannelResponse;
use Riki137\AmphpChannelServer\Server\Request\ChannelRequest;

interface ChannelRequestHandler
{
    public function handle(ChannelRequest $message): ?ChannelResponse;
}
