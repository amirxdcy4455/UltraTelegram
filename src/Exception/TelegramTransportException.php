<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Exception;

use RuntimeException;
use Throwable;

class TelegramTransportException extends RuntimeException
{
    private string $method;
    private ?int $httpStatusCode;

    public function __construct(string $message, string $method, ?int $httpStatusCode = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->method = $method;
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getMethod(): string { return $this->method; }
    public function getHttpStatusCode(): ?int { return $this->httpStatusCode; }
}
