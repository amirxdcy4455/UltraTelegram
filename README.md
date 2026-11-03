# UltraTelegram

**A clean, extensible PHP client for the Telegram Bot API, powered by PHP Ultra Request.**

UltraTelegram is a small and structured SDK for building Telegram bots with PHP. It uses `amirxd/php-ultra-request` as its HTTP transport, keeps request construction explicit, avoids cloning mutable objects, and forwards Bot API methods without requiring a new library release whenever Telegram adds an endpoint.

- PHP 8.0+
- Telegram Bot API 10.3 compatibility baseline
- Ultra Request transport (cURL, middleware, redirects disabled for token-bearing Bot API calls)
- JSON requests and multipart file uploads
- File download through Ultra Request's streaming Downloader
- Long polling and webhook input helpers
- Typed API and transport exceptions
- Generic Telegram object wrappers
- No additional runtime dependency beyond PHP Ultra Request and PHP extensions

> Telegram's Bot API changes over time. The generic `call()` and dynamic method interface can invoke newly added API methods as soon as Telegram exposes them, even before UltraTelegram adds convenience helpers for them.

## Installation

Until the package is published on Packagist, add the GitHub VCS repository to the consuming application's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/amirxdcy4455/UltraTelegram"
        }
    ],
    "require": {
        "amirxd/ultra-telegram": "dev-main"
    }
}
```

Then run:

```bash
composer update amirxd/ultra-telegram
```

UltraTelegram depends on `amirxd/php-ultra-request` (`dev-main` until Ultra Request publishes a stable version tag). If you keep both repositories checked out locally, you may replace the VCS repository with a Composer `path` repository for your Ultra Request checkout.

Required extensions:

- `ext-curl`
- `ext-json`

## Create a client

```php
use Amirxd\UltraTelegram\UltraTelegram;

$token = getenv('TELEGRAM_BOT_TOKEN');

$bot = new UltraTelegram($token);
```

Do not commit bot tokens to source control. Prefer environment variables or a secrets manager.

## Send messages

API method names are available dynamically. Pass Telegram's parameter names as an associative array:

```php
$response = $bot->sendMessage([
    'chat_id' => 123456789,
    'text' => 'Hello from UltraTelegram.'
]);

$message = $response->getResultObject();
$messageId = $message?->get('message_id');
```

You can also use PHP named arguments for dynamic calls:

```php
$response = $bot->sendMessage(
    chat_id: 123456789,
    text: 'Hello from UltraTelegram.'
);
```

Or call the generic API explicitly:

```php
$response = $bot->call('sendMessage', [
    'chat_id' => 123456789,
    'text' => 'Hello from UltraTelegram.'
]);
```

Use GET if a particular integration requires it:

```php
$response = $bot->call('getMe', [], 'GET');
```

POST with JSON is the default. Telegram API error responses throw `TelegramApiException`; transport and malformed-response failures throw `TelegramTransportException`.

## Read the result

```php
$result = $response->getResult();

if (is_array($result)) {
    $messageId = $result['message_id'] ?? null;
}

$message = $response->getResultObject();
$chat = $message?->get('chat');
$chatId = $chat?->get('id');
```

`TelegramObject` is a read-only wrapper. It supports `get()`, `getRaw()`, `has()`, `toArray()`, `toJson()`, property access and read-only array access. Lists remain PHP arrays and associative nested objects are wrapped automatically. Raw arrays are also available from `TelegramResponse::getResult()`.

## Handle API errors

```php
use Amirxd\UltraTelegram\Exception\TelegramApiException;
use Amirxd\UltraTelegram\Exception\TelegramTransportException;

try {
    $bot->sendMessage([
        'chat_id' => 123456789,
        'text' => 'Hello'
    ]);
} catch (TelegramApiException $error) {
    echo $error->getApiErrorCode();
    echo $error->getMessage();

    if ($error->getRetryAfter() !== null) {
        sleep($error->getRetryAfter());
    }
} catch (TelegramTransportException $error) {
    // Network, HTTP transport, empty response or invalid JSON failure.
    error_log($error->getMessage());
}
```

`TelegramApiException` also exposes `getParameters()`, `getMigrateToChatId()`, `getMethod()` and `getResponse()`.

To inspect an API error without throwing, use `callRaw()`:

```php
$response = $bot->callRaw('sendMessage', [
    'chat_id' => 123456789,
    'text' => 'Hello'
]);

if (!$response->isOk()) {
    echo $response->getDescription();
}
```

## Concurrent Bot API calls

The default transport exposes Ultra Request's `curl_multi` engine through `callMany()`. Results preserve the input keys. Each entry is either a `TelegramResponse` or a `Throwable`; an API error for one method does not cancel successful sibling requests.

```php
$results = $bot->callMany([
    'identity' => [
        'method' => 'getMe'
    ],
    'chat' => [
        'method' => 'getChat',
        'parameters' => ['chat_id' => '@example_channel']
    ],
    'commands' => [
        'method' => 'getMyCommands'
    ]
], concurrency: 3);

foreach ($results as $key => $result) {
    if ($result instanceof Throwable) {
        error_log($key . ': ' . $result->getMessage());
        continue;
    }

    $data = $result->getResult();
}
```

Concurrent calls use the same configured Ultra Request client and its Middleware pipeline. A custom transport must implement `ConcurrentTransportInterface` to support `callMany()`.

## Upload files

Use `InputFile::fromPath()` for local files or `InputFile::fromContent()` for generated content. File uploads switch the request to `multipart/form-data`; UltraTelegram does not set the multipart Content-Type manually, allowing cURL to add the correct boundary.

```php
use Amirxd\UltraTelegram\Input\InputFile;

$bot->sendDocument([
    'chat_id' => 123456789,
    'document' => InputFile::fromPath(__DIR__ . '/report.pdf')
]);
```

Generated file content:

```php
$file = InputFile::fromContent(
    "Report generated by the bot\n",
    'report.txt',
    'text/plain'
);

$bot->sendDocument([
    'chat_id' => 123456789,
    'document' => $file
]);
```

Files nested inside JSON-serialized fields are converted to Telegram `attach://` references automatically. This supports media arrays such as `sendMediaGroup`:

```php
$bot->sendMediaGroup([
    'chat_id' => 123456789,
    'media' => [
        [
            'type' => 'photo',
            'media' => InputFile::fromPath(__DIR__ . '/one.jpg'),
            'caption' => 'First image'
        ],
        [
            'type' => 'photo',
            'media' => InputFile::fromPath(__DIR__ . '/two.jpg')
        ]
    ]
]);
```

A Telegram `file_id` or an HTTP URL can be passed as a normal string when the Bot API method supports it.

## Download files

```php
$bot->downloadFile(
    'TELEGRAM_FILE_ID',
    __DIR__ . '/downloads/document.pdf'
);
```

Or download by a `file_path` already returned by `getFile`:

```php
$bot->downloadFilePath(
    'documents/document.pdf',
    __DIR__ . '/downloads/document.pdf'
);
```

The default transport uses Ultra Request's streaming Downloader and disables redirects for file downloads so the token-bearing URL is not forwarded to another origin. When a local Bot API server returns an absolute local `file_path`, UltraTelegram copies that local file directly instead of making another HTTP request. Set `$overwrite` to `true` explicitly to replace an existing destination file.

## Keyboards

Keyboard helpers return regular arrays accepted by Telegram:

```php
use Amirxd\UltraTelegram\Keyboard\Keyboard;

$replyMarkup = Keyboard::inline([
    [
        Keyboard::button('Open', ['url' => 'https://example.com']),
        Keyboard::button('Confirm', ['callback_data' => 'confirm'])
    ]
]);

$bot->sendMessage([
    'chat_id' => 123456789,
    'text' => 'Choose an action:',
    'reply_markup' => $replyMarkup
]);
```

Other helpers: `Keyboard::reply()`, `Keyboard::removeReply()` and `Keyboard::forceReply()`.

## Long polling

`getUpdates` and webhook delivery are mutually exclusive in Telegram. Use long polling when you are not receiving updates through a webhook.

```php
use Amirxd\UltraTelegram\Objects\TelegramObject;
use Amirxd\UltraTelegram\Objects\TelegramUpdate;

$poller = $bot->createPoller();
$poller->withTimeout(30);
$poller->withAllowedUpdates(['message', 'callback_query']);

$poller->start(static function (
    TelegramUpdate $update,
    $bot
): void {
    if ($update->getUpdateType() !== 'message') {
        return;
    }

    $message = $update->getUpdatePayload();
    $chat = $message?->get('chat');
    $chatId = $chat instanceof TelegramObject ? $chat->get('id') : null;
    $text = $message?->get('text');

    if ($chatId !== null && is_string($text)) {
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => 'You said: ' . $text
        ]);
    }
});
```

`start($handler, $maxBatches = 0)` blocks the current process. A batch limit of `0` means run until `stop()` is called. The poller advances its offset only after the handler successfully processes an update. Configure your PHP process and infrastructure to allow long-running requests. The HTTP timeout is automatically raised to at least the long-poll timeout plus ten seconds when using the default Ultra Request transport.

An optional error callback can decide whether polling should continue:

```php
$poller->onError(static function (Throwable $error): bool {
    error_log($error->getMessage());

    return true;
});
```

## Update dispatcher

```php
$dispatcher = $bot->createDispatcher();

$dispatcher->onMessage(static function ($message, $update, $bot): void {
    $chat = $message?->get('chat');
    $chatId = $chat?->get('id');

    if ($chatId !== null) {
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Message received.'
        ]);
    }
});

$dispatcher->onCallbackQuery(static function ($query, $update, $bot): void {
    $bot->answerCallbackQuery([
        'callback_query_id' => $query->get('id'),
        'text' => 'Done'
    ]);
});

$poller->start([$dispatcher, 'dispatch']);
```

Available shortcuts include `onEditedMessage()`, `onInlineQuery()`, `onChatMember()`, `onMyChatMember()` and `onAny()`. Use `on($updateType, $handler)` for any other Update field added by Telegram.

## Webhook input

Configure Telegram's webhook using the regular API method:

```php
$secret = bin2hex(random_bytes(32));

$bot->setWebhook([
    'url' => 'https://example.com/telegram/webhook',
    'secret_token' => $secret,
    'allowed_updates' => ['message', 'callback_query'],
    'max_connections' => 40
]);
```

Validate the incoming request before dispatching it:

```php
use Amirxd\UltraTelegram\Webhook\WebhookRequest;

$webhook = WebhookRequest::fromGlobals($secret, 1024 * 1024);
$dispatcher->dispatch($webhook->getUpdate());

http_response_code(200);
echo 'OK';
```

The helper verifies POST, JSON content type, the `X-Telegram-Bot-Api-Secret-Token` header, JSON structure and the configured request-body limit. It does not create an HTTP server or configure your reverse proxy; expose the PHP endpoint through your web server and return an appropriate HTTP status.

Delete the webhook before switching to long polling:

```php
$bot->deleteWebhook(['drop_pending_updates' => false]);
```

## Local Bot API server

```php
$bot->withApiBaseUrl('http://127.0.0.1:8081', allowInsecure: true);
```

Only opt into plain HTTP for a trusted local Bot API server. The default endpoint is HTTPS.

## Timeouts and Ultra Request

```php
$bot->withTimeout(45);
$bot->withConnectTimeout(10);

$httpClient = $bot->getHttpClient();
```

You can pass a configured Ultra Request client to the constructor:

```php
use Amirxd\UltraRequest\Client\Client as HttpClient;
use Amirxd\UltraTelegram\UltraTelegram;

$http = new HttpClient();
$http->withTimeout(45);
$http->withConnectTimeout(10);

$bot = new UltraTelegram($token, $http);
```

All Bot API calls use POST with JSON by default. File uploads use multipart. Redirects are disabled on token-bearing API calls and file downloads to avoid forwarding the bot token to another host.

## Using all Bot API methods

Every Bot API method can be invoked in either form:

```php
$bot->call('setChatMenuButton', [
    'chat_id' => 123456789,
    'menu_button' => ['type' => 'commands']
]);

$bot->setChatMenuButton([
    'chat_id' => 123456789,
    'menu_button' => ['type' => 'commands']
]);
```

The second form uses `__call()` and forwards the method name and parameter array. This means methods for Business features, payments, stickers, games, Mini Apps, rich messages, ephemeral messages, guest mode, managed bots and future Bot API releases do not need hand-written wrappers. API method names are checked for a safe method-name format, but Telegram remains the authority for method availability and parameter validation.

Complex Telegram objects are passed as arrays or `JsonSerializable` objects. This keeps the SDK aligned with Telegram's evolving Bot API schema without freezing the package to generated model classes.

## Tests

```bash
composer test
```

The test suite uses an injectable transport and does not require a live Telegram bot token. If Composer dependencies are not installed, the runner uses minimal contract-compatible Ultra Request fixtures from `tests/Fixtures/UltraRequest`; these fixtures are test-only and are not part of the production autoloader. The production transport uses PHP Ultra Request and requires `ext-curl`.

## Examples

- `examples/send-message.php`
- `examples/upload-document.php`
- `examples/polling.php`
- `examples/webhook.php`
- `examples/call-many.php`

## Version compatibility

The package targets PHP 8.0+ and follows Telegram Bot API 10.3 as its documented baseline. Generic method forwarding allows newer API method names and fields to be passed immediately, but that does not imply the package can validate new Telegram-side rules before Telegram responds.

## License

MIT. See [LICENSE](LICENSE).
