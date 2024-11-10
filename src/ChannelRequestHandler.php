<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer;

interface ChannelRequestHandler
{
    public function handle(ChannelRequest $request): ?ChannelResponse;
}
