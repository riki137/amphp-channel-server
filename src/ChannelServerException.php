<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer;

use Exception;

class ChannelServerException extends Exception
{
    public function __construct(public readonly ErrorResponse $response)
    {
        parent::__construct($response->message);
    }
}
