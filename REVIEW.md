# UltraTelegram Review

Review date: 2026-11-04

## Validation results

- 48 automated tests passed; 0 failed in GitHub Actions.
- `composer.json` parsed successfully and its PSR-4 production mapping points to `src/`.
- No `clone $this` use was found in production source.
- GitHub Actions completed successfully on PHP 8.0, 8.1, 8.2, 8.3, 8.4, and 8.5 for commit cdeccaf8dae267269e0da2fc6dd2b0542ebc642f.
- Request building, API errors, transport errors, dynamic Bot API methods, multipart uploads, file download paths, polling, dispatching, and webhook validation are covered by the test suite.
- Webhook validation failures expose explicit HTTP status codes for malformed input, invalid secrets, unsupported methods and media types, and oversized bodies.
- Recursive multipart payload inspection is bounded to 64 nested levels and fails with a controlled InvalidArgumentException.
- Token-bearing transport errors are redacted both in the public exception message and in the retained previous exception.

## Test execution

With Composer dependencies installed:

```bash
composer install
composer test
```

Without an installed `vendor/` directory, the test runner uses minimal contract-compatible Ultra Request fixtures under `tests/Fixtures/UltraRequest`. To test against a local Ultra Request checkout directly, set `ULTRA_REQUEST_PATH` to its repository root or `src/` directory:

```bash
ULTRA_REQUEST_PATH=/path/to/UltraRequest composer test
```

The fixtures are test-only and are never included by the production PSR-4 autoloader.

## Limitations

- CI tests use mocked transports; they do not replace live Telegram API, production webhook, or real file-transfer verification.
- Live Telegram API calls were not performed because no bot token was supplied.
- Real cURL networking and file transfer were not tested because `ext-curl` was unavailable in the review environment.
- The package currently depends on `amirxd/php-ultra-request: dev-main` until that dependency publishes a stable version tag.
- UltraTelegram itself is not yet registered on Packagist; README installation instructions use the GitHub VCS repository.

The current review addressed the webhook error-classification and recursive multipart traversal edge cases described above. Live Telegram behavior remains unverified.
