# API Methods and Requests

UltraTelegram does not generate a dedicated PHP wrapper for every Telegram endpoint. This keeps the client usable when Telegram adds new methods.

## Dynamic method calls

~~~php
$response = $bot->sendMessage([
    'chat_id' => 123456789,
    'text' => 'Hello'
]);
~~~

PHP named arguments are also supported:

~~~php
$response = $bot->sendMessage(
    chat_id: 123456789,
    text: 'Hello'
);
~~~

Or use the generic method:

~~~php
$response = $bot->call('sendMessage', [
    'chat_id' => 123456789,
    'text' => 'Hello'
]);
~~~

## GET and POST

POST with JSON is the default. GET can be selected explicitly:

~~~php
$response = $bot->call('getMe', [], 'GET');
~~~

Only GET and POST are accepted. File uploads require POST.

## Concurrent calls

The default Ultra Request transport supports concurrent calls:

~~~php
$results = $bot->callMany([
    'identity' => ['method' => 'getMe'],
    'chat' => [
        'method' => 'getChat',
        'parameters' => ['chat_id' => '@example_channel']
    ]
], concurrency: 2);
~~~

Input keys are preserved. Each result is either a TelegramResponse or a Throwable. A custom transport must implement ConcurrentTransportInterface.

## Timeouts

~~~php
$bot->withTimeout(45);
$bot->withConnectTimeout(10);
~~~

Timeout configuration is available when using the default Ultra Request transport.

## Validation

The client validates the method-name format and request encoding. Telegram validates endpoint existence and endpoint-specific parameter schemas. See [API Compatibility](../API-COMPATIBILITY.md).
