# Responses and Errors

## Reading results

~~~php
$response = $bot->sendMessage([
    'chat_id' => 123456789,
    'text' => 'Hello'
]);

$result = $response->getResult();
$message = $response->getResultObject();
$messageId = $message?->get('message_id');
~~~

TelegramResponse exposes Telegram's response envelope. TelegramObject provides read-only access to nested associative objects through get(), getRaw(), has(), toArray(), toJson() and property/array access.

## API errors

call() throws TelegramApiException when Telegram responds with ok: false.

~~~php
use Amirxd\UltraTelegram\Exception\TelegramApiException;

try {
    $bot->sendMessage(['chat_id' => 123456789, 'text' => 'Hello']);
} catch (TelegramApiException $error) {
    echo $error->getApiErrorCode();
    echo $error->getMessage();

    if ($error->getRetryAfter() !== null) {
        sleep($error->getRetryAfter());
    }
}
~~~

The exception also provides getParameters(), getMigrateToChatId(), getMethod() and getResponse().

## Transport errors

Network errors, empty responses and malformed JSON produce TelegramTransportException.

## Inspect errors without throwing

Use callRaw() when the application needs to inspect an unsuccessful Telegram response itself:

~~~php
$response = $bot->callRaw('sendMessage', [
    'chat_id' => 123456789,
    'text' => 'Hello'
]);

if (!$response->isOk()) {
    error_log($response->getDescription() ?? 'Telegram API error');
}
~~~
