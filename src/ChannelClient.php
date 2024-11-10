<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer\Client;

use Amp\DeferredFuture;
use Amp\Future;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;
use Psr\Log\LoggerInterface;
use Riki137\AmphpChannelServer\Client\Exception\ChannelClientException;
use Riki137\AmphpChannelServer\Client\Exception\ChannelResponseException;
use Riki137\AmphpChannelServer\Server\Request\ChannelRequest;
use Riki137\AmphpChannelServer\Server\Request\ChannelRequestMessage;
use Riki137\AmphpChannelServer\Server\Request\PidUniqidRequestIdFactory;
use Riki137\AmphpChannelServer\Server\Response\ChannelResponse;
use Riki137\AmphpChannelServer\Server\Response\ChannelResponseMessage;
use Riki137\AmphpChannelServer\Server\Response\ExceptionResponse;
use Riki137\AmphpChannelServer\Server\Request\RequestIdFactory;
use Throwable;
use function Amp\async;

/**
 * ChannelClient handles bidirectional communication over an Amp Channel.
 *
 * This class provides a robust implementation for sending requests and handling responses
 * asynchronously, with built-in error handling, retry logic, and logging capabilities.
 */
final class ChannelClient
{

    /** @var array<string, DeferredFuture<ChannelResponse>> */
    private array $pendingRequests = [];
    private bool $isListening = false;
    private bool $isShuttingDown = false;

    public function __construct(
        private readonly Channel $channel,
        ?RequestIdFactory $requestIdFactory = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->requestIdFactory = $requestIdFactory ?? new PidUniqidRequestIdFactory();
    }

    public function startListening(): Future
    {
        if ($this->isListening) {
            throw new ChannelClientException('Channel client is already listening');
        }

        $this->isListening = true;
        return async($this->listener(...));
    }

    public function isListening(): bool
    {
        return $this->isListening;
    }

    /**
     * @param ChannelRequest $request
     * @return Future<ChannelResponse>
     * @throws ChannelClientException
     */
    public function send(ChannelRequest $request): Future
    {
        if ($this->isShuttingDown) {
            throw new ChannelClientException('Cannot send requests during shutdown');
        }

        if (!$this->isListening) {
            throw new ChannelClientException('Channel client is not listening');
        }

        try {
            $requestId = $this->requestIdFactory->generate();
            $deferred = new DeferredFuture();
            $this->pendingRequests[$requestId] = $deferred;

            $requestMessage = new ChannelRequestMessage($requestId, $request);
            $this->channel->send($requestMessage);

            $this->logger?->debug('Request sent', [
                'requestId' => $requestId,
                'requestType' => get_class($request)
            ]);

            return $deferred->getFuture();
        } catch (Throwable $e) {
            $this->logger?->error('Failed to send request', [
                'error' => $e->getMessage(),
                'requestType' => get_class($request)
            ]);
            throw new ChannelClientException('Failed to send request', 0, $e);
        }
    }

    private function listener(): void
    {
        $this->logger?->info('Channel client started listening');
        while (!$this->isShuttingDown && !$this->channel->isClosed()) {
            try {
                /** @var ChannelResponseMessage $response */
                $response = $this->channel->receive();
                $requestId = $response->getRequestId();

                if (!isset($this->pendingRequests[$requestId])) {
                    $this->logger?->warning('Received response for unknown request', ['requestId' => $requestId]);
                    continue;
                }

                $deferred = $this->pendingRequests[$requestId];
                unset($this->pendingRequests[$requestId]);

                if ($response->getResponse() instanceof ExceptionResponse) {
                    $error = new ChannelResponseException(
                        $response->getResponse()->getMessage(),
                        $response->getResponse()
                    );
                    $deferred->error($error);
                    $this->logger?->error('Received exception response', [
                        'requestId' => $requestId,
                        'error' => $error->getMessage()
                    ]);
                } else {
                    $deferred->complete($response->getResponse());
                    $this->logger?->debug('Received successful response', ['requestId' => $requestId]);
                }
            } catch (Throwable $e) {
                $this->logger?->error('Error in listener loop', ['error' => $e->getMessage()]);
                $this->shutdown(new ChannelClientException('Channel error occurred', 0, $e));
                break;
            }
        }
    }

    public function shutdown(?Throwable $error = null): void
    {
        if ($this->isShuttingDown) {
            return;
        }

        $this->isShuttingDown = true;
        $this->isListening = false;

        $exception = $error ?? new ChannelException('Channel client shutdown');
        foreach ($this->pendingRequests as $deferred) {
            $deferred->error($exception);
        }

        $this->pendingRequests = [];

        if (!$this->channel->isClosed()) {
            $this->channel->close();
        }

        $this->logger?->info('Channel client shut down', [
            'error' => $error?->getMessage()
        ]);
    }

    public function __destruct()
    {
        $this->shutdown();
    }
}
