# UltraTelegram Review

Review date: 2026-11-03

## Validation results

- 46 automated tests passed; 0 failed.
- PHP syntax validation passed on PHP 8.4.24.
- `composer.json` parsed successfully and its PSR-4 production mapping points to `src/`.
- No `clone $this` use was found in production source.
- Request building, API errors, transport errors, dynamic Bot API methods, multipart uploads, file download paths, polling, dispatching, and webhook validation are covered by the test suite.
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

- Validation was performed on PHP 8.4.24. The CI workflow is configured for PHP 8.0 through 8.5, but those remote matrix jobs have not yet been observed in this review.
- Live Telegram API calls were not performed because no bot token was supplied.
- Real cURL networking and file transfer were not tested because `ext-curl` was unavailable in the review environment.
- The package currently depends on `amirxd/php-ultra-request: dev-main` until that dependency publishes a stable version tag.
- UltraTelegram itself is not yet registered on Packagist; README installation instructions use the GitHub VCS repository.

No additional reproducible functional defect was found in the mocked test suite.
