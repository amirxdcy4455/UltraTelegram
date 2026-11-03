<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Amirxd\UltraTelegram\UltraTelegram;
use Amirxd\UltraTelegram\Keyboard\Keyboard;

$token = getenv('TELEGRAM_BOT_TOKEN');
$chatId = getenv('TELEGRAM_CHAT_ID');

if (!is_string($token) || $token === '' || !is_string($chatId) || $chatId === '') {
    throw new RuntimeException('Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID first.');
}

$bot = new UltraTelegram($token);

$response = $bot->sendMessage([
    'chat_id' => $chatId,
    'text' => 'Hello from UltraTelegram.',
    'reply_markup' => Keyboard::inline([
        [
            Keyboard::button('Open website', ['url' => 'https://example.com'])
        ]
    ])
]);

$message = $response->getResultObject();

echo 'Sent message ID: ' . $message?->get('message_id') . PHP_EOL;
