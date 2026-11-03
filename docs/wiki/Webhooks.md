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

The helper validates the POST method, JSON content type, Telegram secret header, body size and Update payload structure.

The web server, HTTPS certificate, reverse proxy and endpoint routing remain the application's responsibility. Keep the secret token outside source control.
