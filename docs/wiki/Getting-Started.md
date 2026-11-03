# Getting Started

## Requirements

- PHP 8.0+
- ext-curl and ext-json
- amirxd/php-ultra-request

## Installation

Until a stable Packagist release is available, add both GitHub VCS repositories to Composer:

~~~json
{
  "repositories": [
    {"type": "vcs", "url": "https://github.com/amirxdcy4455/UltraTelegram"},
    {"type": "vcs", "url": "https://github.com/amirxdcy4455/UltraRequest"}
  ],
  "require": {
    "amirxd/ultra-telegram": "dev-main",
    "amirxd/php-ultra-request": "dev-main"
  }
}
~~~

Run composer update.

## Create a client

~~~php
use Amirxd\UltraTelegram\UltraTelegram;

$bot = new UltraTelegram(getenv('TELEGRAM_BOT_TOKEN'));
~~~

Keep the bot token outside source control.

## First API call

~~~php
$response = $bot->sendMessage([
    'chat_id' => 123456789,
    'text' => 'Hello from UltraTelegram!'
]);
~~~

See [API Methods](API-Methods.md) for dynamic method calls and parameter handling.
