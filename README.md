# UltraTelegram

A modular PHP SDK for the Telegram Bot API, built on top of **PHP Ultra Request**.

> This repository is the reviewed source package. See the source archive and documentation before using it in production.

## Requirements

- PHP 8.0+
- `ext-curl`
- Composer
- `amirxd/php-ultra-request` (currently consumed from `dev-main`)

## Features

- Telegram Bot API method access through a generic client call
- Typed response and API/transport exceptions
- Multipart uploads, including nested `attach://` references
- File download support
- Concurrent request support where the transport supports it
- Long polling and update dispatching
- Webhook request and secret-token validation
- Keyboard helpers
- Examples, architecture notes, and a lightweight test runner

## Installation

The package is being prepared for Packagist publication. Until then, install it from this repository using Composer's VCS repository support after a tagged release is available.

## Quick start

```php
use Amirxd\\UltraTelegram\\UltraTelegram;

$bot = new UltraTelegram(getenv('TELEGRAM_BOT_TOKEN'));

$response = $bot->sendMessage([
    'chat_id' => 123456789,
    'text' => 'Hello from UltraTelegram!',
]);

$message = $response->getResultObject();
echo $message?->get('message_id');
```

## Documentation

- [Persian documentation](README_FA.md)
- [Architecture](docs/ARCHITECTURE.md)
- [API compatibility notes](docs/API-COMPATIBILITY.md)
- [Changelog](CHANGELOG.md)
- [Review report](REVIEW.md)

## Tests

Run the included test runner:

```bash
php tests/run.php
```

The recorded local test run passed 46 tests. This is a mocked-transport test suite; it does not constitute a live Telegram API or real cURL upload test.

## Security

Never commit bot tokens, webhook secrets, or other credentials. Load them from environment variables.

## License

MIT. See [LICENSE](LICENSE).
