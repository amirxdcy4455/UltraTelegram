<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Amirxd\UltraTelegram\UltraTelegram;
use Amirxd\UltraTelegram\Objects\TelegramObject;
use Amirxd\UltraTelegram\Objects\TelegramUpdate;
use Amirxd\UltraTelegram\Updates\UpdateDispatcher;

$token = getenv('TELEGRAM_BOT_TOKEN');

if (!is_string($token) || $token === '') {
    throw new RuntimeException('Set TELEGRAM_BOT_TOKEN first.');
}

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
    $text = $message->get('text');

    if ($chatId !== null && is_string($text)) {
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => 'You said: ' . $text
        ]);
    }
});

$poller = $bot->createPoller();
$poller->withTimeout(30);
$poller->withAllowedUpdates(['message', 'callback_query']);
$poller->start([$dispatcher, 'dispatch']);
