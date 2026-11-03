# UltraTelegram

**A modern, extensible PHP client for the Telegram Bot API.** Powered by [PHP Ultra Request](https://github.com/amirxdcy4455/UltraRequest).

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.0+">
  <img src="https://img.shields.io/badge/Telegram%20Bot%20API-10.3-26A5E4?logo=telegram&logoColor=white" alt="Telegram Bot API 10.3">
  <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="MIT License">
</p>

UltraTelegram provides a lightweight, structured interface to Telegram's Bot API while delegating HTTP transport, middleware and concurrent requests to PHP Ultra Request.

## Highlights

- Dynamic access to Bot API methods, including newly added endpoints.
- JSON requests and multipart uploads, including nested attach:// media.
- File downloads through a streaming downloader.
- Long polling, update dispatching and webhook request validation.
- Concurrent API calls with callMany().
- Read-only response objects and dedicated API/transport exceptions.
- PHP 8.0+ and PSR-4 autoloading.

## Requirements

- PHP 8.0+
- ext-curl and ext-json
- amirxd/php-ultra-request

## Installation

Until a stable Packagist release is available, add both GitHub VCS repositories to your application's composer.json:

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

Then run composer update.

## Quick start

~~~php
use Amirxd\UltraTelegram\UltraTelegram;

$bot = new UltraTelegram(getenv('TELEGRAM_BOT_TOKEN'));

$response = $bot->sendMessage([
    'chat_id' => 123456789,
    'text' => 'Hello from UltraTelegram!'
]);

$messageId = $response->getResultObject()?->get('message_id');
~~~

Keep your bot token in an environment variable or secret manager. Never commit it to source control.

## Documentation

The README is intentionally short. Detailed API usage and implementation notes are maintained separately:

- [Documentation index](docs/INDEX.md)
- [Getting started](docs/wiki/Getting-Started.md)
- [API methods and requests](docs/wiki/API-Methods.md)
- [Responses and errors](docs/wiki/Responses-and-Errors.md)
- [File uploads and downloads](docs/wiki/Files.md)
- [Polling and update dispatching](docs/wiki/Polling-and-Updates.md)
- [Webhooks](docs/wiki/Webhooks.md)
- [Keyboard helpers](docs/wiki/Keyboards.md)
- [Architecture and security](docs/ARCHITECTURE.md)
- [Telegram Bot API compatibility](docs/API-COMPATIBILITY.md)

The docs/wiki directory contains standalone Wiki-ready pages. GitHub Wiki uses a separate Git repository; these source pages are kept here for version control.

## Examples

Runnable examples are available in [examples/](examples/).

## Tests

~~~bash
composer test
~~~

## License

MIT. See [LICENSE](LICENSE).
