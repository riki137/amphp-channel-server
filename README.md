# AMPHP Channel Server

AMPHP Channel Server is a PHP library that enables request/response communication between processes using AMPHP's Channel system. 
It provides a reliable way to handle inter-process communication (IPC) within AMPHP applications.

> Perfect for scenarios where you need HTTP-like request/response patterns between worker processes or threads in an AMPHP environment.

## Installation

This package can be installed via Composer:

```bash
composer require riki137/amphp-channel-server
```

## Getting Started

The package provides two main classes: `ChannelClient` and `ChannelServer`, which enable asynchronous request-response communication over an `Amp\Sync\Channel`.

### Quick Start

Here's a complete example showing how to set up a channel server and client:

```php
use Amp\Sync\Channel;
use Riki137\AmphpChannelServer\Client\ChannelClient;
use Riki137\AmphpChannelServer\Server\ChannelServer;
use Riki137\AmphpChannelServer\Server\Request\ChannelRequestHandler;
use Riki137\AmphpChannelServer\Server\Response\ChannelResponse;
use Riki137\AmphpChannelServer\Server\Request\ChannelRequest;
use Riki137\AmphpChannelServer\Server\Request\PidCounterRequestIdFactory;

// 1. Define your custom request and response classes
class EchoRequest implements ChannelRequest {
    public function __construct(private string $message) {}
    public function getMessage(): string {
        return $this->message;
    }
}

class EchoResponse implements ChannelResponse {
    public function __construct(private string $message) {}
    public function getMessage(): string {
        return $this->message;
    }
}

// 2. Create a request handler
class EchoHandler implements ChannelRequestHandler {
    public function handle(ChannelRequest $request): ?ChannelResponse {
        if (!$request instanceof EchoRequest) {
            return null; // Skip handling non-echo requests
        }
        
        return new EchoResponse($request->getMessage());
    }
}

// 3. Define a worker task
class EchoWorkerTask extends \Amp\Parallel\Worker\Task {
    public function run(Channel $channel): void {
        // Create request ID factory (should be singleton per process)
        $requestIdFactory = new PidCounterRequestIdFactory();
        
        // Initialize client
        $client = new ChannelClient($channel, $requestIdFactory);
        $client->startListening()->ignore();
        
        // Send a request and await response
        $request = new EchoRequest('Hello, world!');
        $response = $client->send($request)->await();
        
        // Process response...
    }
}

// 4. Set up and run the server
/** @var \Amp\Parallel\Worker\Worker $worker */
$worker = createWorker();
$exec = $worker->submit(new EchoWorkerTask());

$server = new ChannelServer($exec->getChannel());
$server->addHandler(new EchoHandler());
$server->run();
```

### Error Handling

The library includes exceptions and error handling. For example, if a request fails, you can catch `ChannelClientException` and `ChannelResponseException` to gracefully handle errors:

```php
try {
    $request = new EchoRequest('Hello, world!');
    /** @var \Riki137\AmphpChannelServer\Client\ChannelClient $client */
    $response = $client->send($request)->await();
    // Process response
} catch (ChannelClientException $e) {
    // Handle error related to sending requests
} catch (ChannelResponseException $e) {
    // Handle errors that occurred while server was processing the request
}
```

## Advanced Features

- **Logging**: Both client and server accept a PSR-3 logger instance for debugging and monitoring purposes.
- **Unique Request IDs**: The `PidCounterRequestIdFactory` ensures each request is traceable using unique identifiers.
- **Handler Management**: The server allows registration, removal, and replacement of message handlers at runtime, provided it is not running at that moment.

## Contributing

This project is open to contributions. Feel free to submit issues and pull requests to improve the library.

## License

AMPHP Channel Server is licensed under the Apache License, Version 2.0.
