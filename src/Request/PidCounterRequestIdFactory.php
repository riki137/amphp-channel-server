<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer\Server\Request;

use Riki137\AmphpChannelServer\Server\Request\RequestIdFactory;

final class PidCounterRequestIdFactory implements RequestIdFactory
{
    private int $pid;

    private int $idCounter = 0;

    public function __construct()
    {
        $this->pid = getmypid();
        if ($this->pid === false) {
            $this->pid = mt_rand(1, PHP_INT_MAX);
        }
    }

    public function generate(): string
    {
        if ($this->idCounter === PHP_INT_MAX - 1) {
            $this->idCounter = 0;
        }

        return sprintf('%d:%d', $this->pid, ++$this->idCounter);
    }
}
