# Architecture

UltraTelegram is an SDK layer over PHP Ultra Request. It does not implement a second HTTP stack.

## Layers

- `Client/TelegramClient`: validates the method identifier, serializes parameters, selects JSON or multipart, sends the request and parses Telegram's response envelope.
- `Transport/UltraRequestTransport`: adapts the Ultra Request client to the small transport contract used by the SDK.
- `Contracts`: allow deterministic tests and alternate transports without coupling the API client to cURL internals.
- `Input/InputFile` and `Support/MultipartPayload`: describe local files and translate nested Telegram `attach://` references into multipart fields.
- `Response/TelegramResponse` and `Objects/TelegramObject`: preserve the raw Telegram result while providing convenient read-only access to nested objects.
- `Polling/UpdatePoller`: long-polls `getUpdates`, handles batches and advances the offset after a successful handler call.
- `Webhook/WebhookRequest`: validates the incoming HTTP method, media type, secret header, body size and Update envelope.
- `Updates/UpdateDispatcher`: routes a Telegram Update to type-specific handlers and wildcard handlers.

## Mutable configuration style

Configuration methods mutate the current instance and return `$this`. No `clone $this` pattern is used. `TelegramObject` and `TelegramResponse` are read-only result objects because response payloads represent data received from Telegram.

## Complete method access

The SDK intentionally does not generate a separate PHP wrapper for each Bot API endpoint. `call()` and `__call()` forward endpoint names and parameter arrays directly. This keeps endpoint coverage current when Telegram adds methods. It also means Telegram is the final authority for method availability and parameter validation; this SDK does not generate a compile-time schema for every Telegram object.

## Transport safety

The bot token is part of the Telegram API URL. Redirects are disabled for Bot API calls and file downloads to prevent forwarding that token to another origin. Transport exception messages redact the token. HTTPS is required by default; plain HTTP is only allowed when explicitly enabled for a trusted local Bot API server.

## Update delivery

Telegram supports webhook delivery or `getUpdates` long polling, not both at once. The SDK provides helpers for either mode but does not automatically switch the bot between them.
