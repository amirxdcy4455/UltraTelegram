<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Response;

use Amirxd\UltraRequest\Response\HttpResponse;
use Amirxd\UltraTelegram\Objects\TelegramObject;

class TelegramResponse
{
    private array $payload;
    private HttpResponse $httpResponse;
    private string $method;

    public function __construct(array $payload, HttpResponse $httpResponse, string $method)
    {
        $this->payload = $payload;
        $this->httpResponse = $httpResponse;
        $this->method = $method;
    }

    public function isOk(): bool
    {
        return ($this->payload['ok'] ?? false) === true;
    }

    public function getResult(): mixed
    {
        return $this->payload['result'] ?? null;
    }

    public function getResultObject(): ?TelegramObject
    {
        $result = $this->getResult();

        return is_array($result) && !self::isList($result)
            ? new TelegramObject($result)
            : null;
    }

    public function getDescription(): ?string
    {
        $description = $this->payload['description'] ?? null;

        return is_string($description) ? $description : null;
    }

    public function getErrorCode(): ?int
    {
        $code = $this->payload['error_code'] ?? null;

        return is_numeric($code) ? (int) $code : null;
    }

    public function getParameters(): array
    {
        $parameters = $this->payload['parameters'] ?? null;

        return is_array($parameters) ? $parameters : [];
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getHttpResponse(): HttpResponse
    {
        return $this->httpResponse;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpResponse->getStatusCode();
    }

    public function toArray(): array
    {
        return $this->payload;
    }

    private static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
