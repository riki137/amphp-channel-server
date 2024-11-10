<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer;

class OKResponse extends ChannelResponse
{
    public function __construct(public readonly ?string $message = null)
    {
    }
}
