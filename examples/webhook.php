<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Amirxd\UltraTelegram\UltraTelegram;
use Amirxd\UltraTelegram\Objects\TelegramObject;
use Amirxd\UltraTelegram\Objects\TelegramUpdate;
use Amirxd\UltraTelegram\Updates\UpdateDispatcher;
use Amirxd\UltraTelegram\Webhook\WebhookRequest;
use Amirxd\UltraTelegram\Webhook\WebhookRequestException;

$token = getenv('TELEGRAM_BOT_TOKEN');
$secret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: '';

if (!is_string($token) || $token === '') {
    http_response_code(500);
    exit('Missing bot token.');
}

try {
    $webhook = WebhookRequest::fromGlobals($secret);
} catch (WebhookRequestException $error) {
    http_response_code($error->getHttpStatusCode());

    if ($error->getHttpStatusCode() === 405) {
        header('Allow: POST');
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid webhook request.';
    exit;
}

try {
    $bot = new UltraTelegram($token);
    $dispatcher = new UpdateDispatcher($bot);

    $dispatcher->onMessage(static function (
        ?TelegramObject $message,
        TelegramUpdate $update,
        UltraTelegram $bot
    ): void {
        if ($message === null) {
            return;
        }

        $chat = $message->get('chat');
        $chatId = $chat instanceof TelegramObject ? $chat->get('id') : null;

        if ($chatId !== null) {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Webhook update received.'
            ]);
        }
    });

    $dispatcher->dispatch($webhook->getUpdate());
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'OK';
} catch (Throwable $error) {
    error_log('[UltraTelegram webhook] ' . $error->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Webhook processing failed.';
}
