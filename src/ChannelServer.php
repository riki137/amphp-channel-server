<?php

declare(strict_types=1);

namespace Riki137\AmphpChannelServer\Server;

use Amp\Sync\Channel;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Riki137\AmphpChannelServer\Client\Exception\ChannelServerException;
use Riki137\AmphpChannelServer\Server\Request\ChannelRequestHandler;
use Riki137\AmphpChannelServer\Server\Request\ChannelRequestMessage;
use Riki137\AmphpChannelServer\Server\Response\ChannelResponseMessage;
use Riki137\AmphpChannelServer\Server\Response\ErrorResponse;
use Riki137\AmphpChannelServer\Server\Response\ExceptionResponse;
use RuntimeException;
use Throwable;

final class ChannelServer
{
    /** @var array<ChannelRequestHandler> */
    private array $handlers = [];

    private bool $isRunning = false;

    public function __construct(
        private readonly Channel $channel,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Registers a new message handler.
     *
     * @throws RuntimeException If server is already running
     */
    public function registerHandler(ChannelRequestHandler $handler): void
    {
        if ($this->isRunning) {
            throw new ChannelServerException('Cannot register handlers while server is running');
        }

        $this->handlers[] = $handler;
    }

    /**
     * Removes a handler by its instance.
     *
     * @return bool True if handler was found and removed, false otherwise
     * @throws RuntimeException If server is already running
     */
    public function removeHandler(ChannelRequestHandler $handler): bool
    {
        if ($this->isRunning) {
            throw new ChannelServerException('Cannot modify handlers while server is running');
        }

        $key = array_search($handler, $this->handlers, true);
        if ($key !== false) {
            unset($this->handlers[$key]);
            $this->handlers = array_values($this->handlers); // Reindex array
            return true;
        }
        return false;
    }

    /**
     * Replaces an existing handler with a new one.
     *
     * @throws RuntimeException If server is already running
     * @throws InvalidArgumentException If old handler is not found
     */
    public function replaceHandler(ChannelRequestHandler $oldHandler, ChannelRequestHandler $newHandler): void
    {
        if ($this->isRunning) {
            throw new ChannelServerException('Cannot modify handlers while server is running');
        }

        $key = array_search($oldHandler, $this->handlers, true);
        if ($key === false) {
            throw new InvalidArgumentException('Handler to replace not found');
        }

        $this->handlers[$key] = $newHandler;
    }

    /**
     * Removes all registered handlers.
     *
     * @throws RuntimeException If server is already running
     */
    public function clearHandlers(): void
    {
        if ($this->isRunning) {
            throw new ChannelServerException('Cannot modify handlers while server is running');
        }

        $this->handlers = [];
    }

    /**
     * Starts the channel server.
     *
     * @throws ChannelServerException If server is already running
     */
    public function run(): void
    {
        if ($this->isRunning) {
            throw new ChannelServerException('Channel server is already running');
        }

        if (empty($this->handlers)) {
            throw new ChannelServerException('No handlers registered');
        }

        $this->isRunning = true;
        $this->logger->info('Channel server started');

        try {
            $this->processMessages();
        } finally {
            $this->isRunning = false;
            $this->logger->info('Channel server stopped');
        }
    }

    /**
     * Processes a single message and returns a response.
     * Use this if you process channel messages in your own code for different purposes.
     */
    public function respond(ChannelRequestMessage $message): ChannelResponseMessage
    {
        $this->logger->debug('Processing message', [
            'messageType' => get_class($message->getRequest()),
            'requestId' => $message->getRequestId(),
        ]);

        $response = null;

        foreach ($this->handlers as $handler) {
            try {
                $response = $handler->handle($message->getRequest());
                if ($response !== null) {
                    break;
                }
            } catch (Throwable $e) {
                $this->logger->error('Handler error', [
                    'error' => $e->getMessage(),
                    'handler' => get_class($handler),
                ]);
                $response = new ExceptionResponse($e);
                break;
            }
        }

        if ($response === null) {
            $this->logger->warning('No handler found for message', [
                'messageType' => get_class($message->getRequest())
            ]);
            $response = new ErrorResponse(
                sprintf('No handler found for message type: %s', get_class($message->getRequest()))
            );
        }

        return new ChannelResponseMessage($message->getRequestId(), $response);
    }

    /**
     * @throws ChannelServerException
     */
    private function processMessages(): void
    {
        while (!$this->channel->isClosed()) {
            try {
                $message = $this->channel->receive();

                if (!$message instanceof ChannelRequestMessage) {
                    throw new ChannelServerException(
                        sprintf('Invalid message type received: %s', get_class($message))
                    );
                }

                $response = $this->respond($message);
                $this->channel->send($response);
            } catch (ChannelServerException $e) {
                if (!$this->channel->isClosed()) {
                    $this->logger->critical('Channel error', ['error' => $e->getMessage()]);
                    throw $e;
                }
                break;
            } catch (Throwable $e) {
                $this->logger->error('Unexpected error', ['error' => $e->getMessage()]);
                try {
                    if ($message instanceof ChannelRequestMessage) {
                        $this->channel->send(new ChannelResponseMessage(
                            $message->getRequestId(),
                            new ExceptionResponse($e)
                        ));
                    }
                } catch (Throwable $sendError) {
                    throw new ChannelServerException('Failed to send error response', previous: $sendError);
                }
            }
        }
    }

    public function isRunning(): bool
    {
        return $this->isRunning;
    }
}
