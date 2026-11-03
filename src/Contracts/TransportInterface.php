<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Contracts;

use Amirxd\UltraRequest\Request\RequestInterface;
use Amirxd\UltraRequest\Response\HttpResponse;

interface TransportInterface
{
    public function send(RequestInterface $request): HttpResponse;
    public function download(string $url, string $destination, bool $overwrite = false): bool;
}
