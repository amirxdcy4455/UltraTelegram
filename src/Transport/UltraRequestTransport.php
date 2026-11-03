<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Transport;

use Amirxd\UltraRequest\Client\Client;
use Amirxd\UltraRequest\Request\RequestInterface;
use Amirxd\UltraRequest\Response\HttpResponse;
use Amirxd\UltraTelegram\Contracts\ConcurrentTransportInterface;

class UltraRequestTransport implements ConcurrentTransportInterface
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public function send(RequestInterface $request): HttpResponse
    {
        return $this->client->send($request);
    }

    public function sendMany(array $requests, int $concurrency = 5): array
    {
        return $this->client->sendMany($requests, $concurrency);
    }

    public function download(string $url, string $destination, bool $overwrite = false): bool
    {
        $downloader = new SafeDownloader($url, $destination);
        $downloader->withOverwrite($overwrite);
        $downloader->withResume(true);
        $downloader->withRetries(3, 1);

        return $this->client->download($downloader);
    }

    public function withTimeout(int $seconds): self
    {
        $this->client->withTimeout($seconds);

        return $this;
    }

    public function withConnectTimeout(int $seconds): self
    {
        $this->client->withConnectTimeout($seconds);

        return $this;
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
