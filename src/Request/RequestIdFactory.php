<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer\Server\Request;

interface RequestIdFactory
{
    public function generate(): string;
}
