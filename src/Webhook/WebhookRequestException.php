<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Webhook;

use UnexpectedValueException;
use Throwable;

class WebhookRequestException extends UnexpectedValueException
{
    private int $httpStatusCode;

    public function __construct(string $message, int $httpStatusCode, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }
}
