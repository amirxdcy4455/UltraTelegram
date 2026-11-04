# Changelog

## Unreleased

- Validate webhook Update envelopes, requiring a non-negative integer `update_id` and exactly one object-valued event field while preserving forward compatibility with unknown event names.


## 1.0.0 - 2026-11-03

- Initial Telegram Bot API client built on PHP Ultra Request.
- Generic method forwarding for current and future Bot API methods.
- JSON requests, multipart uploads and nested `attach://` file references.
- File downloads through Ultra Request's streaming Downloader.
- Telegram API and transport exception types.
- Telegram response and object wrappers.
- Long polling, update dispatching and webhook request validation.
- Keyboard construction helpers.
