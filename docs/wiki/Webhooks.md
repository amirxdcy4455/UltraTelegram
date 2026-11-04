# Webhooks

Use Telegram webhooks when Telegram should deliver updates to an HTTPS endpoint.

## Register a webhook

~~~php
$secret = bin2hex(random_bytes(32));

$bot->setWebhook([
    'url' => 'https://example.com/telegram/webhook',
    'secret_token' => $secret,
    'allowed_updates' => ['message', 'callback_query']
]);
~~~

## Validate an incoming request

~~~php
use Amirxd\UltraTelegram\Webhook\WebhookRequest;

$webhook = WebhookRequest::fromGlobals($secret, 1024 * 1024);
$dispatcher->dispatch($webhook->getUpdate());

http_response_code(200);
echo 'OK';
~~~

The helper validates the POST method, JSON content type, Telegram secret header, body size and Update payload structure. It also requires a non-negative integer `update_id` and exactly one object-valued update field. Unknown future update field names remain accepted.

The web server, HTTPS certificate, reverse proxy and endpoint routing remain the application's responsibility. Keep the secret token outside source control.

## Rejection status codes

Invalid incoming requests raise WebhookRequestException, which extends UnexpectedValueException and exposes the HTTP status through getHttpStatusCode():

- 400: malformed JSON or an invalid Update payload.
- 403: missing or incorrect configured Telegram secret.
- 405: request method is not POST.
- 413: request body exceeds the configured limit.
- 415: content type is not application/json.

A failure while reading the request stream remains an internal runtime error. Keep request validation separate from update processing so application or Telegram API failures can return a 5xx response and be retried by Telegram.
