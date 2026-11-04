<?php

declare(strict_types=1);

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';

if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $ultraRequestPath = getenv('ULTRA_REQUEST_PATH');

        if (!is_string($ultraRequestPath) || $ultraRequestPath === '') {
            $ultraRequestPath = dirname(__DIR__) . '/tests/Fixtures/UltraRequest';
        }

        $maps = [
            'Amirxd\\UltraTelegram\\' => dirname(__DIR__) . '/src/',
            'Amirxd\\UltraRequest\\' => rtrim($ultraRequestPath, '/\\') . '/' . (str_ends_with($ultraRequestPath, '/src') ? '' : 'src/')
        ];

        foreach ($maps as $prefix => $basePath) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $path = rtrim($basePath, '/\\') . '/' . str_replace('\\', '/', $relative) . '.php';

            if (is_file($path)) {
                require $path;
            }

            return;
        }
    });
}

use Amirxd\UltraRequest\Request\RequestInterface;
use Amirxd\UltraRequest\Response\HttpResponse;
use Amirxd\UltraTelegram\Client\TelegramClient;
use Amirxd\UltraTelegram\Contracts\ConcurrentTransportInterface;
use Amirxd\UltraTelegram\Contracts\TransportInterface;
use Amirxd\UltraTelegram\Exception\TelegramApiException;
use Amirxd\UltraTelegram\Exception\TelegramTransportException;
use Amirxd\UltraTelegram\Input\InputFile;
use Amirxd\UltraTelegram\Keyboard\Keyboard;
use Amirxd\UltraTelegram\Objects\TelegramObject;
use Amirxd\UltraTelegram\Objects\TelegramUpdate;
use Amirxd\UltraTelegram\Polling\UpdatePoller;
use Amirxd\UltraTelegram\Support\MultipartPayload;
use Amirxd\UltraTelegram\Updates\UpdateDispatcher;
use Amirxd\UltraTelegram\Webhook\WebhookRequest;
use Amirxd\UltraTelegram\Webhook\WebhookRequestException;

if (!class_exists(CURLFile::class)) {
    class CURLFile
    {
        public string $filename;
        public ?string $mimeType;
        public ?string $postedFilename;

        public function __construct(
            string $filename,
            ?string $mimeType = null,
            ?string $postedFilename = null
        ) {
            $this->filename = $filename;
            $this->mimeType = $mimeType;
            $this->postedFilename = $postedFilename;
        }
    }
}

class FakeTransport implements TransportInterface
{
    public array $requests = [];
    public array $responses = [];
    public array $downloads = [];

    public function send(RequestInterface $request): HttpResponse
    {
        $this->requests[] = $request;

        if ($this->responses === []) {
            throw new RuntimeException('Fake transport has no queued response.');
        }

        return array_shift($this->responses);
    }

    public function download(string $url, string $destination, bool $overwrite = false): bool
    {
        $this->downloads[] = compact('url', 'destination', 'overwrite');

        return true;
    }

    public function queue(array $payload, int $statusCode = 200): self
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $headers = "HTTP/1.1 {$statusCode} Test\r\nContent-Type: application/json\r\n\r\n";
        $this->responses[] = new HttpResponse($body, $headers, $statusCode);

        return $this;
    }
}

class FakeConcurrentTransport extends FakeTransport implements ConcurrentTransportInterface
{
    public int $lastConcurrency = 0;

    public function sendMany(array $requests, int $concurrency = 5): array
    {
        $this->lastConcurrency = $concurrency;
        $results = [];

        foreach ($requests as $key => $request) {
            try {
                $results[$key] = $this->send($request);
            } catch (Throwable $error) {
                $results[$key] = $error;
            }
        }

        return $results;
    }
}

$tests = [];

$test = static function (string $name, callable $callback) use (&$tests): void {
    $tests[] = [$name, $callback];
};

$assert = static function (bool $condition, string $message = 'Assertion failed.'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$test('Reject invalid bot token', static function () use ($assert): void {
    try {
        new TelegramClient('invalid-token');
        $assert(false, 'Invalid token was accepted.');
    } catch (InvalidArgumentException) {
        $assert(true);
    }
});

$test('Build a standard JSON POST request', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue(['ok' => true, 'result' => ['message_id' => 42]]);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    $response = $bot->call('sendMessage', ['chat_id' => 123, 'text' => 'Hello']);
    $request = $transport->requests[0];

    $assert($response->isOk());
    $assert($response->getResult()['message_id'] === 42);
    $assert($request->getMethod() === 'POST');
    $assert(str_ends_with($request->getUrl(), '/sendMessage'));
    $assert(str_contains((string)$request->getBody(), '"chat_id":123'));
    $assert(!$request->followsRedirects());
});

$test('Support dynamic method calls with parameter arrays', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue(['ok' => true, 'result' => true]);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    $response = $bot->sendMessage([
        'chat_id' => 99,
        'text' => 'Dynamic call'
    ]);

    $assert($response->getResult() === true);
    $assert(str_ends_with($transport->requests[0]->getUrl(), '/sendMessage'));
});

$test('Support PHP named arguments for dynamic methods', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue(['ok' => true, 'result' => true]);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    $bot->answerCallbackQuery(callback_query_id: 'query-1', text: 'Done');
    $body = json_decode((string)$transport->requests[0]->getBody(), true, 512, JSON_THROW_ON_ERROR);

    $assert($body['callback_query_id'] === 'query-1');
    $assert($body['text'] === 'Done');
});

$test('Support GET query parameters', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue(['ok' => true, 'result' => []]);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    $bot->call('getUpdates', ['offset' => 100, 'timeout' => 0], 'GET');
    $request = $transport->requests[0];

    $assert($request->getMethod() === 'GET');
    $assert(str_contains($request->getUrl(), 'offset=100'));
    $assert(str_contains($request->getUrl(), 'timeout=0'));
});

$test('Serialize nested query parameters as JSON', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue(['ok' => true, 'result' => true]);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    $bot->call('setMyCommands', ['commands' => [['command' => 'start', 'description' => 'Start']]], 'GET');
    $assert(str_contains($transport->requests[0]->getUrl(), 'commands=%5B'));
});

$test('Throw API exception with retry parameters', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue([
        'ok' => false,
        'error_code' => 429,
        'description' => 'Too Many Requests',
        'parameters' => ['retry_after' => 7]
    ], 429);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    try {
        $bot->call('sendMessage', ['chat_id' => 1, 'text' => 'Hello']);
        $assert(false, 'Expected TelegramApiException.');
    } catch (TelegramApiException $error) {
        $assert($error->getApiErrorCode() === 429);
        $assert($error->getRetryAfter() === 7);
        $assert($error->getMethod() === 'sendMessage');
    }
});

$test('Expose raw Telegram API errors without throwing', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request'], 400);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    $response = $bot->callRaw('sendMessage', ['chat_id' => 1, 'text' => 'x']);

    $assert(!$response->isOk());
    $assert($response->getErrorCode() === 400);
});

$test('Send concurrent Bot API calls and preserve keyed results', static function () use ($assert): void {
    $transport = new FakeConcurrentTransport();
    $transport->queue(['ok' => true, 'result' => ['id' => 1]]);
    $transport->queue(['ok' => false, 'error_code' => 429, 'description' => 'Limited', 'parameters' => ['retry_after' => 2]], 429);
    $transport->responses[] = new RuntimeException('Simulated connection failure');

    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);
    $results = $bot->callMany([
        'identity' => ['method' => 'getMe'],
        'limited' => ['method' => 'sendMessage', 'parameters' => ['chat_id' => 1, 'text' => 'Hello']],
        'network' => ['method' => 'getChat', 'parameters' => ['chat_id' => 1]]
    ], 3);

    $assert(array_keys($results) === ['identity', 'limited', 'network']);
    $assert($results['identity'] instanceof \Amirxd\UltraTelegram\Response\TelegramResponse);
    $assert($results['identity']->getResult()['id'] === 1);
    $assert($results['limited'] instanceof TelegramApiException);
    $assert($results['limited']->getRetryAfter() === 2);
    $assert($results['network'] instanceof TelegramTransportException);
    $assert($transport->lastConcurrency === 3);
});

$test('Reject callMany on non-concurrent transports', static function () use ($assert): void {
    $transport = new FakeTransport();
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    try {
        $bot->callMany(['me' => ['method' => 'getMe']]);
        $assert(false, 'Non-concurrent transport was accepted.');
    } catch (InvalidArgumentException) {
        $assert(true);
    }
});

$test('Reject invalid concurrent call definitions', static function () use ($assert): void {
    $transport = new FakeConcurrentTransport();
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    try {
        $bot->callMany(['invalid' => ['parameters' => []]]);
        $assert(false, 'Invalid concurrent call was accepted.');
    } catch (InvalidArgumentException) {
        $assert(true);
    }
});

$test('Reject invalid method names', static function () use ($assert): void {
    $transport = new FakeTransport();
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    try {
        $bot->call('../getMe');
        $assert(false, 'Invalid method name was accepted.');
    } catch (InvalidArgumentException) {
        $assert(true);
    }
});

$test('Reject unsupported HTTP verbs', static function () use ($assert): void {
    $transport = new FakeTransport();
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    try {
        $bot->call('getMe', [], 'DELETE');
        $assert(false, 'Unsupported HTTP method was accepted.');
    } catch (InvalidArgumentException) {
        $assert(true);
    }
});

$test('Reject HTTP API base URL unless explicitly allowed', static function () use ($assert): void {
    $transport = new FakeTransport();
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    try {
        $bot->withApiBaseUrl('http://127.0.0.1:8081');
        $assert(false, 'Insecure base URL was accepted.');
    } catch (InvalidArgumentException) {
        $assert(true);
    }

    $bot->withApiBaseUrl('http://127.0.0.1:8081', true);
    $assert($bot->getApiBaseUrl() === 'http://127.0.0.1:8081');
});

$test('Reject credentials and query string in API base URL', static function () use ($assert): void {
    $transport = new FakeTransport();
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    try {
        $bot->withApiBaseUrl('https://user:pass@example.com?x=1');
        $assert(false, 'Unsafe API base URL was accepted.');
    } catch (InvalidArgumentException) {
        $assert(true);
    }
});

$test('Wrap nested Telegram result objects', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue([
        'ok' => true,
        'result' => [
            'message_id' => 12,
            'chat' => ['id' => 900, 'type' => 'private']
        ]
    ]);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    $object = $bot->call('sendMessage')->getResultObject();

    $assert($object instanceof TelegramObject);
    $assert($object->get('message_id') === 12);
    $assert($object->get('chat') instanceof TelegramObject);
    $assert($object->get('chat')->get('id') === 900);
});

$test('Telegram object supports read-only array access', static function () use ($assert): void {
    $object = new TelegramObject(['id' => 1, 'name' => 'Test']);
    $assert($object['id'] === 1);
    $assert(isset($object['name']));

    try {
        $object['id'] = 2;
        $assert(false, 'Telegram object unexpectedly allowed mutation.');
    } catch (LogicException) {
        $assert(true);
    }
});

$test('Detect update type and identifier', static function () use ($assert): void {
    $update = new TelegramUpdate([
        'update_id' => 445,
        'message' => ['text' => 'hello', 'chat' => ['id' => 1]]
    ]);

    $assert($update->getUpdateId() === 445);
    $assert($update->getUpdateType() === 'message');
    $assert($update->getUpdatePayload() instanceof TelegramObject);
    $assert($update->getUpdatePayload()->get('text') === 'hello');
});

$test('Dispatch typed and wildcard update handlers', static function () use ($assert): void {
    $transport = new FakeTransport();
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);
    $dispatcher = new UpdateDispatcher($bot);
    $called = [];

    $dispatcher->onMessage(static function ($message, $update, $client) use (&$called): void {
        $called[] = ['message', $message->get('text'), $update->getUpdateId(), $client];
    });
    $dispatcher->onAny(static function ($payload, $update) use (&$called): void {
        $called[] = ['any', $update->getUpdateType()];
    });

    $count = $dispatcher->dispatch([
        'update_id' => 10,
        'message' => ['text' => 'test']
    ]);

    $assert($count === 2);
    $assert($called[0][0] === 'message');
    $assert($called[0][1] === 'test');
    $assert($called[1] === ['any', 'message']);
});

$test('Polling advances offset only after handler success', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue(['ok' => true, 'result' => [
        ['update_id' => 5, 'message' => ['text' => 'one']],
        ['update_id' => 7, 'callback_query' => ['id' => 'c1']]
    ]]);
    $transport->queue(['ok' => true, 'result' => []]);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);
    $poller = new UpdatePoller($bot);
    $received = [];

    $poller->withTimeout(0)->withLimit(10)->start(
        static function (TelegramUpdate $update) use (&$received): void {
            $received[] = $update->getUpdateId();
        },
        2
    );

    $assert($received === [5, 7]);
    $assert($poller->getOffset() === 8);
    $assert(!$poller->isRunning());
});

$test('Polling retries through configured error callback', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue(['ok' => true, 'result' => []]);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);
    $poller = new UpdatePoller($bot);
    $poller->onError(static fn (): bool => true);
    $poller->start(static function (): void {}, 1);

    $assert(!$poller->isRunning());
});

$test('Validate webhook method, secret, and JSON payload', static function () use ($assert): void {
    $request = WebhookRequest::fromGlobals(
        'secret-value',
        1024,
        [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'application/json; charset=utf-8',
            'CONTENT_LENGTH' => '34',
            'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'secret-value'
        ],
        '{"update_id":44,"message":{"text":"hi"}}'
    );

    $assert($request->getUpdate()->getUpdateId() === 44);
    $assert($request->getUpdate()->getUpdateType() === 'message');
    $assert($request->getBodySize() > 0);
});

$test('Validate webhook Update identifiers and event payloads', static function () use ($assert): void {
    $server = [
        'REQUEST_METHOD' => 'POST',
        'CONTENT_TYPE' => 'application/json'
    ];

    $futureUpdate = WebhookRequest::fromGlobals(
        '',
        1024,
        $server,
        '{"update_id":44,"future_update":{"value":true}}'
    );
    $assert($futureUpdate->getUpdate()->getUpdateId() === 44);
    $assert($futureUpdate->getUpdate()->getUpdateType() === 'future_update');

    $invalidBodies = [
        '[{"update_id":44,"message":{}}]',
        '{"update_id":"44","message":{}}',
        '{"update_id":44.5,"message":{}}',
        '{"update_id":-1,"message":{}}',
        '{"update_id":44}',
        '{"update_id":44,"message":{},"callback_query":{}}',
        '{"update_id":44,"message":null}',
        '{"update_id":44,"message":"not-an-object"}'
    ];

    foreach ($invalidBodies as $body) {
        try {
            WebhookRequest::fromGlobals('', 1024, $server, $body);
            $assert(false, 'Malformed Telegram Update payload was accepted: ' . $body);
        } catch (WebhookRequestException $error) {
            $assert($error->getHttpStatusCode() === 400);
        }
    }
});

$test('Reject invalid webhook secret', static function () use ($assert): void {
    try {
        WebhookRequest::fromGlobals(
            'expected',
            1024,
            [
                'REQUEST_METHOD' => 'POST',
                'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'wrong'
            ],
            '{"update_id":1,"message":{}}'
        );
        $assert(false, 'Invalid webhook secret was accepted.');
    } catch (RuntimeException) {
        $assert(true);
    }
});

$test('Reject non-POST webhook requests', static function () use ($assert): void {
    try {
        WebhookRequest::fromGlobals('', 1024, ['REQUEST_METHOD' => 'GET'], '{}');
        $assert(false, 'GET webhook was accepted.');
    } catch (RuntimeException) {
        $assert(true);
    }
});

$test('Reject non-JSON webhook content type', static function () use ($assert): void {
    try {
        WebhookRequest::fromGlobals('', 1024, [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'text/plain'
        ], '{}');
        $assert(false, 'Non-JSON webhook was accepted.');
    } catch (RuntimeException) {
        $assert(true);
    }
});

$test('Reject webhook payload above size limit', static function () use ($assert): void {
    try {
        WebhookRequest::fromGlobals('', 5, [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '100'
        ], '{"update_id":1}');
        $assert(false, 'Oversized webhook was accepted.');
    } catch (RuntimeException) {
        $assert(true);
    }
});

$test('Reject missing webhook content type', static function () use ($assert): void {
    try {
        WebhookRequest::fromGlobals('', 1024, [
            'REQUEST_METHOD' => 'POST'
        ], '{"update_id":1}');
        $assert(false, 'Webhook request without Content-Type was accepted.');
    } catch (RuntimeException) {
        $assert(true);
    }
});

$test('Reject malformed application JSON media type', static function () use ($assert): void {
    try {
        WebhookRequest::fromGlobals('', 1024, [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'application/jsonp'
        ], '{"update_id":1}');
        $assert(false, 'Invalid JSON media type was accepted.');
    } catch (RuntimeException) {
        $assert(true);
    }
});

$test('Reject invalid webhook JSON', static function () use ($assert): void {
    try {
        WebhookRequest::fromGlobals('', 1024, [
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'application/json'
        ], '{broken');
        $assert(false, 'Invalid JSON was accepted.');
    } catch (UnexpectedValueException) {
        $assert(true);
    }
});

$test('Download file by Telegram file identifier', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue(['ok' => true, 'result' => ['file_path' => 'photos/file one.jpg']]);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    $result = $bot->downloadFile('file-id', '/tmp/telegram-photo.jpg', true);

    $assert($result);
    $assert(count($transport->downloads) === 1);
    $assert(str_contains($transport->downloads[0]['url'], '/file/bot123456:'));
    $assert(str_contains($transport->downloads[0]['url'], 'file%20one.jpg'));
    $assert($transport->downloads[0]['overwrite'] === true);
});

$test('Download file directly by Telegram file path', static function () use ($assert): void {
    $transport = new FakeTransport();
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);
    $bot->downloadFilePath('documents/a.txt', '/tmp/a.txt');

    $assert(str_ends_with($transport->downloads[0]['url'], '/documents/a.txt'));
});

$test('Validate InputFile path', static function () use ($assert): void {
    try {
        InputFile::fromPath('/path/that/does/not/exist');
        $assert(false, 'Invalid file path was accepted.');
    } catch (InvalidArgumentException) {
        $assert(true);
    }
});

$test('Create temporary InputFile from content', static function () use ($assert): void {
    $file = InputFile::fromContent('payload', 'sample.txt', 'text/plain');
    $assert(is_file($file->getPath()));
    $assert(file_get_contents($file->getPath()) === 'payload');
    $assert($file->getFilename() === 'sample.txt');
    unset($file);
});

$test('Detect files nested in media payloads', static function () use ($assert): void {
    $filePath = tempnam(sys_get_temp_dir(), 'ultra-test-');
    file_put_contents($filePath, 'x');
    $file = InputFile::fromPath($filePath);

    $assert(MultipartPayload::containsFile([
        'chat_id' => 1,
        'media' => [['type' => 'photo', 'media' => $file]]
    ]));

    @unlink($filePath);
});

$test('Copy local Bot API file paths without HTTP download', static function () use ($assert): void {
    $source = tempnam(sys_get_temp_dir(), 'ultra-local-source-');
    $destinationDirectory = sys_get_temp_dir() . '/ultra-telegram-destination-' . bin2hex(random_bytes(4));
    $destination = $destinationDirectory . '/copied.txt';
    file_put_contents($source, 'local-file-content');

    $transport = new FakeTransport();
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);
    $bot->downloadFilePath($source, $destination);

    $assert(file_get_contents($destination) === 'local-file-content');
    $assert($transport->downloads === []);

    try {
        $bot->downloadFilePath($source, $destination);
        $assert(false, 'Existing destination was overwritten without permission.');
    } catch (TelegramTransportException) {
        $assert(true);
    }

    file_put_contents($source, 'replacement-content');
    $bot->downloadFilePath($source, $destination, true);
    $assert(file_get_contents($destination) === 'replacement-content');

    @unlink($source);
    @unlink($destination);
    @rmdir($destinationDirectory);
});

$test('Build direct and nested multipart file fields without attachment name collisions', static function () use ($assert): void {
    $filePath = tempnam(sys_get_temp_dir(), 'ultra-multipart-');
    file_put_contents($filePath, 'upload-data');
    $file = InputFile::fromPath($filePath, 'sample.txt', 'text/plain');

    $payload = MultipartPayload::build([
        'document' => $file,
        'ultra_file_1' => 'reserved-field',
        'media' => [
            ['type' => 'document', 'media' => $file]
        ],
        'chat_id' => 123
    ]);

    $media = json_decode($payload['media'], true, 512, JSON_THROW_ON_ERROR);

    $assert($payload['document'] instanceof CURLFile);
    $assert($payload['ultra_file_1'] === 'reserved-field');
    $assert($payload['ultra_file_2'] instanceof CURLFile);
    $assert($media[0]['media'] === 'attach://ultra_file_2');
    $assert($payload['chat_id'] === '123');

    unset($file);
    @unlink($filePath);
});

$test('Keyboard builders generate Telegram structures', static function () use ($assert): void {
    $inline = Keyboard::inline([[Keyboard::button('Open', ['callback_data' => 'open'])]]);
    $reply = Keyboard::reply([[Keyboard::button('Share', ['request_contact' => true])]], ['resize_keyboard' => true]);
    $remove = Keyboard::removeReply();
    $force = Keyboard::forceReply();

    $assert($inline['inline_keyboard'][0][0]['callback_data'] === 'open');
    $assert($reply['resize_keyboard'] === true);
    $assert($remove['remove_keyboard'] === true);
    $assert($force['force_reply'] === true);
});

$test('Reject malformed keyboard rows', static function () use ($assert): void {
    try {
        Keyboard::inline([['invalid-button']]);
        $assert(false, 'Malformed keyboard row was accepted.');
    } catch (InvalidArgumentException) {
        $assert(true);
    }
});

$test('Redact bot token from transport exception messages', static function () use ($assert): void {
    $token = '123456:abcdefghijklmnopqrstuvwxyz123456';
    $transport = new class($token) implements TransportInterface {
        private string $token;

        public function __construct(string $token)
        {
            $this->token = $token;
        }

        public function send(RequestInterface $request): HttpResponse
        {
            throw new RuntimeException('Request failed for ' . $this->token);
        }

        public function download(string $url, string $destination, bool $overwrite = false): bool
        {
            return false;
        }
    };

    $bot = new TelegramClient($token, transport: $transport);

    try {
        $bot->call('getMe');
        $assert(false, 'Expected transport exception.');
    } catch (TelegramTransportException $error) {
        $assert(!str_contains($error->getMessage(), $token));
        $assert(str_contains($error->getMessage(), '[REDACTED]'));
        $assert($error->getPrevious() instanceof RuntimeException);
        $assert(!str_contains($error->getPrevious()->getMessage(), $token));
    }
});

$test('Wrap invalid JSON response as transport exception', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->responses[] = new HttpResponse('{invalid', "HTTP/1.1 502 Bad Gateway\r\n\r\n", 502);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    try {
        $bot->call('getMe');
        $assert(false, 'Invalid JSON response was accepted.');
    } catch (TelegramTransportException $error) {
        $assert($error->getHttpStatusCode() === 502);
    }
});

$test('Wrap empty HTTP response as transport exception', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->responses[] = new HttpResponse('', "HTTP/1.1 204 No Content\r\n\r\n", 204);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    try {
        $bot->call('getMe');
        $assert(false, 'Empty response was accepted.');
    } catch (TelegramTransportException $error) {
        $assert($error->getHttpStatusCode() === 204);
    }
});

$test('Preserve Telegram parameters object', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue([
        'ok' => false,
        'error_code' => 400,
        'description' => 'Migrated',
        'parameters' => ['migrate_to_chat_id' => -100123]
    ], 400);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    try {
        $bot->call('sendMessage');
        $assert(false, 'Expected API exception.');
    } catch (TelegramApiException $error) {
        $assert($error->getMigrateToChatId() === -100123);
    }
});

$test('Support arbitrary future Bot API methods', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue(['ok' => true, 'result' => ['accepted' => true]]);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    $response = $bot->call('sendFutureTelegramMethod', ['value' => 1]);

    $assert($response->getResult()['accepted'] === true);
    $assert(str_ends_with($transport->requests[0]->getUrl(), '/sendFutureTelegramMethod'));
});


$test('Forward Bot API 10.3 rich message methods without wrappers', static function () use ($assert): void {
    $transport = new FakeTransport();
    $transport->queue(['ok' => true, 'result' => ['message_id' => 77]]);
    $bot = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $transport);

    $response = $bot->sendRichMessage([
        'chat_id' => 123,
        'text' => 'A rich message'
    ]);

    $assert($response->isOk());
    $assert(str_ends_with($transport->requests[0]->getUrl(), '/sendRichMessage'));
});

$test('Recognize current Bot API 10.3 update event fields', static function () use ($assert): void {
    $fields = [
        'guest_message',
        'managed_bot',
        'subscription',
        'stopped_message_generation'
    ];

    foreach ($fields as $field) {
        $update = new TelegramUpdate([
            'update_id' => 10,
            $field => ['id' => 1]
        ]);

        $assert($update->getUpdateType() === $field, 'Unknown update event: ' . $field);
    }
});

$test('Allow dispatcher client override', static function () use ($assert): void {
    $defaultTransport = new FakeTransport();
    $overrideTransport = new FakeTransport();
    $defaultClient = new TelegramClient('123456:abcdefghijklmnopqrstuvwxyz123456', transport: $defaultTransport);
    $overrideClient = new TelegramClient('654321:abcdefghijklmnopqrstuvwxyz123456', transport: $overrideTransport);
    $dispatcher = new UpdateDispatcher($defaultClient);
    $receivedClient = null;

    $dispatcher->onMessage(static function ($message, $update, $client) use (&$receivedClient): void {
        $receivedClient = $client;
    });

    $dispatcher->dispatch([
        'update_id' => 11,
        'message' => ['text' => 'test']
    ], $overrideClient);

    $assert($receivedClient === $overrideClient);
});


$test('Expose HTTP status codes for webhook validation failures', static function () use ($assert): void {
    $cases = [
        ['', 1024, ['REQUEST_METHOD' => 'GET'], '{}', 405],
        ['', 1024, ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'text/plain'], '{}', 415],
        ['expected', 1024, ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json', 'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'wrong'], '{}', 403],
        ['', 5, ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'], '{"update_id":1}', 413],
        ['', 1024, ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'], '{broken', 400]
    ];

    foreach ($cases as [$secret, $limit, $server, $body, $expectedStatus]) {
        try {
            WebhookRequest::fromGlobals($secret, $limit, $server, $body);
            $assert(false, 'Invalid webhook request was accepted.');
        } catch (WebhookRequestException $error) {
            $assert($error->getHttpStatusCode() === $expectedStatus);
        }
    }
});

$test('Reject recursive multipart payloads with a controlled error', static function () use ($assert): void {
    $recursive = new stdClass();
    $recursive->self = $recursive;

    try {
        MultipartPayload::containsFile(['media' => $recursive]);
        $assert(false, 'Recursive multipart payload was accepted.');
    } catch (InvalidArgumentException $error) {
        $assert(str_contains($error->getMessage(), 'maximum supported depth'));
    }
});

$passed = 0;
$failed = 0;

foreach ($tests as [$name, $callback]) {
    try {
        $callback();
        $passed++;
        echo "[PASS] {$name}\n";
    } catch (Throwable $error) {
        $failed++;
        echo "[FAIL] {$name}: {$error->getMessage()}\n";
    }
}

echo "\nTests: {$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);
