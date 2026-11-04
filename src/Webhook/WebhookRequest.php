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

    public static function fromGlobals(
        string $secretToken,
        int $maxBodyBytes = 1048576,
        ?array $server = null,
        ?string $rawBody = null
    ): self {
        if ($maxBodyBytes < 1) {
            throw new InvalidArgumentException('Maximum webhook body size must be positive.');
        }

        if (
            $secretToken !== ''
            && (
                strlen($secretToken) > 256
                || preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $secretToken) !== 1
            )
        ) {
            throw new InvalidArgumentException(
                'Webhook secret token must contain 1-256 letters, numbers, underscores, or hyphens.'
            );
        }

        $server = $server ?? $_SERVER;

        if (strtoupper((string) ($server['REQUEST_METHOD'] ?? '')) !== 'POST') {
            throw new WebhookRequestException('Telegram webhook requests must use POST.', 405);
        }

        $contentType = strtolower(trim((string) ($server['CONTENT_TYPE'] ?? '')));
        $mediaType = trim(explode(';', $contentType, 2)[0]);

        if ($mediaType !== 'application/json') {
            throw new WebhookRequestException(
                'Telegram webhook requests must use application/json.',
                415
            );
        }

        $providedSecret = (string) ($server['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');

        if ($secretToken !== '' && ($providedSecret === '' || !hash_equals($secretToken, $providedSecret))) {
            throw new WebhookRequestException('Telegram webhook secret token is invalid.', 403);
        }

        $contentLength = $server['CONTENT_LENGTH'] ?? null;

        if (is_numeric($contentLength) && (int) $contentLength > $maxBodyBytes) {
            throw new WebhookRequestException(
                'Telegram webhook payload exceeds the configured size limit.',
                413
            );
        }

        if ($rawBody === null) {
            $stream = fopen('php://input', 'rb');

            if ($stream === false) {
                throw new RuntimeException('Unable to read webhook request body.');
            }

            try {
                $rawBody = stream_get_contents($stream, $maxBodyBytes + 1);
            } finally {
                fclose($stream);
            }
        }

        if ($rawBody === false) {
            throw new RuntimeException('Unable to read webhook request body.');
        }

        if (strlen($rawBody) > $maxBodyBytes) {
            throw new WebhookRequestException(
                'Telegram webhook payload exceeds the configured size limit.',
                413
            );
        }

        try {
            $decodedPayload = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new WebhookRequestException('Telegram webhook payload is not valid JSON.', 400, $error);
        }

        if (!$decodedPayload instanceof \stdClass || !is_array($payload)) {
            throw new WebhookRequestException('Telegram webhook payload must be a JSON object.', 400);
        }

        $properties = get_object_vars($decodedPayload);
        $updateId = $properties['update_id'] ?? null;
        $eventFields = array_values(array_filter(
            array_keys($properties),
            static fn (int|string $field): bool => $field !== 'update_id'
        ));

        if (
            !is_int($updateId)
            || $updateId < 0
            || count($eventFields) !== 1
            || !($properties[$eventFields[0]] instanceof \stdClass)
        ) {
            throw new WebhookRequestException(
                'Telegram webhook payload must contain a non-negative integer update_id and exactly one object update field.',
                400
            );
        }

        return new self(new TelegramUpdate($payload), strlen($rawBody));
    }

    public function getUpdate(): TelegramUpdate
    {
        return $this->update;
    }

    public function getBodySize(): int
    {
        return $this->bodySize;
    }
}
