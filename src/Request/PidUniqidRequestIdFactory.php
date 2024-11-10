<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer\Server\Request;

use RuntimeException;

/**
 * Generates unique request IDs using process ID and uniqid.
 *
 * This factory creates unique request identifiers by combining the process ID
 * with PHP's uniqid function for distributed request tracing.
 */
final class PidUniqidRequestIdFactory implements RequestIdFactory
{
    private readonly string $prefix;

    /**
     * Initializes the factory with a process-specific prefix.
     *
     * @param bool $strictPid Whether to throw an exception if getting PID fails
     * @param bool $moreEntropy Whether to add more entropy to the generated IDs
     * @throws RuntimeException If strictPid is true and unable to get process ID
     */
    public function __construct(bool $strictPid = false, private readonly bool $moreEntropy = true)
    {
        $pid = getmypid();

        if ($pid === false) {
            if ($strictPid) {
                throw new RuntimeException('Unable to get process ID');
            }
            $pid = mt_rand(1, PHP_INT_MAX);
        }

        $this->prefix = $pid . ':';
    }

    /**
     * Generates a unique request identifier.
     *
     * @return string A unique identifier in format "pid:uniqid"
     */
    public function generate(): string
    {
        return uniqid($this->prefix, $this->moreEntropy);
    }
}
