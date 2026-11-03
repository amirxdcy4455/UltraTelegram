<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Webhook;

use Amirxd\UltraTelegram\Objects\TelegramUpdate;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

class WebhookRequest
{
    private TelegramUpdate $update;
    private int $bodySize;

    private function __construct(TelegramUpdate $update, int $bodySize)
    {
        $this->update = $update;
        $this->bodySize = $bodySize;
    }

    public static function fromGlobals(string $secretToken, int $maxBodyBytes = 1048576, ?array $server = null, ?string $rawBody = null): self
    {
        if ($maxBodyBytes < 1) throw new InvalidArgumentException('Maximum webhook body size must be positive.');
        if ($secretToken !== '' && (strlen($secretToken) > 256 || preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $secretToken) !== 1)) {
            throw new InvalidArgumentException('Webhook secret token must contain 1-256 letters, numbers, underscores, or hyphens.');
        }

        $server = $server ?? $_SERVER;
        if (strtoupper((string)($server['REQUEST_METHOD'] ?? '')) !== 'POST') {
            throw new RuntimeException('Telegram webhook requests must use POST.');
        }

        $contentType = strtolower(trim((string)($server['CONTENT_TYPE'] ?? '')));
        $mediaType = trim(explode(';', $contentType, 2)[0]);
        if ($mediaType !== 'application/json') {
            throw new RuntimeException('Telegram webhook requests must use application/json.');
        }

        $providedSecret = (string)($server['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
        if ($secretToken !== '' && ($providedSecret === '' || !hash_equals($secretToken, $providedSecret))) {
            throw new RuntimeException('Telegram webhook secret token is invalid.');
        }

        $contentLength = $server['CONTENT_LENGTH'] ?? null;
        if (is_numeric($contentLength) && (int)$contentLength > $maxBodyBytes) {
            throw new RuntimeException('Telegram webhook payload exceeds the configured size limit.');
        }

        if ($rawBody === null) {
            $stream = fopen('php://input', 'rb');
            if ($stream === false) throw new RuntimeException('Unable to read webhook request body.');
            try {
                $rawBody = stream_get_contents($stream, $maxBodyBytes + 1);
            } finally {
                fclose($stream);
            }
        }

        if ($rawBody === false || strlen($rawBody) > $maxBodyBytes) {
            throw new RuntimeException('Telegram webhook payload exceeds the configured size limit.');
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new UnexpectedValueException('Telegram webhook payload is not valid JSON.', 0, $error);
        }

        if (!is_array($payload) || !isset($payload['update_id'])) {
            throw new UnexpectedValueException('Telegram webhook payload is not a valid Update object.');
        }

        return new self(new TelegramUpdate($payload), strlen($rawBody));
    }

    public function getUpdate(): TelegramUpdate { return $this->update; }
    public function getBodySize(): int { return $this->bodySize; }
}