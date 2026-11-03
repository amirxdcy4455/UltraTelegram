<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Contracts;

use Amirxd\UltraRequest\Request\RequestInterface;

interface ConcurrentTransportInterface extends TransportInterface
{
    /**
     * @param array<int|string, RequestInterface> $requests
     * @return array<int|string, mixed>
     */
    public function sendMany(array $requests, int $concurrency = 5): array;
}
