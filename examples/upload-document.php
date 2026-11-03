<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Amirxd\UltraTelegram\UltraTelegram;
use Amirxd\UltraTelegram\Input\InputFile;

$token = getenv('TELEGRAM_BOT_TOKEN');
$chatId = getenv('TELEGRAM_CHAT_ID');
$filePath = $argv[1] ?? null;

if (!is_string($token) || $token === '' || !is_string($chatId) || $chatId === '' || $filePath === null) {
    throw new RuntimeException('Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID, then pass a file path.');
}

$bot = new UltraTelegram($token);

$response = $bot->sendDocument([
    'chat_id' => $chatId,
    'document' => InputFile::fromPath($filePath)
]);

echo 'Document sent.' . PHP_EOL;
