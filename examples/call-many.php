<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Amirxd\UltraTelegram\UltraTelegram;

$token = getenv('TELEGRAM_BOT_TOKEN');

if (!is_string($token) || $token === '') {
    throw new RuntimeException('Set the TELEGRAM_BOT_TOKEN environment variable.');
}

$bot = new UltraTelegram($token);

$results = $bot->callMany([
    'identity' => [
        'method' => 'getMe'
    ],
    'chat' => [
        'method' => 'getChat',
        'parameters' => [
            'chat_id' => '@example_channel'
        ]
    ],
    'commands' => [
        'method' => 'getMyCommands'
    ]
], concurrency: 3);

foreach ($results as $key => $result) {
    if ($result instanceof Throwable) {
        fwrite(STDERR, $key . ': ' . $result->getMessage() . PHP_EOL);
        continue;
    }

    echo $key . ': HTTP ' . $result->getHttpStatusCode() . PHP_EOL;
}
