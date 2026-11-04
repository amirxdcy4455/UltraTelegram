<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Exception;

use Amirxd\UltraTelegram\Response\TelegramResponse;
use RuntimeException;

class TelegramApiException extends RuntimeException
{
    private string $method;
    private int $apiErrorCode;
    private array $parameters;
    private TelegramResponse $response;

    public function __construct(string $method, TelegramResponse $response)
    {
        $this->method = $method;
        $this->apiErrorCode = $response->getErrorCode() ?? 0;
        $this->parameters = $response->getParameters();
        $this->response = $response;

        $message = $response->getDescription() ?? 'Telegram Bot API request failed.';

        parent::__construct($message, $this->apiErrorCode);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getApiErrorCode(): int
    {
        return $this->apiErrorCode;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getRetryAfter(): ?int
    {
        $value = $this->parameters['retry_after'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    public function getMigrateToChatId(): int|string|null
    {
        $value = $this->parameters['migrate_to_chat_id'] ?? null;

        return is_int($value) || is_string($value) ? $value : null;
    }

    public function getResponse(): TelegramResponse
    {
        return $this->response;
    }
}
