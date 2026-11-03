<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Polling;

use Amirxd\UltraTelegram\Client\TelegramClient;
use Amirxd\UltraTelegram\Objects\TelegramUpdate;
use InvalidArgumentException;
use Throwable;

class UpdatePoller
{
    private TelegramClient $client;
    private int $timeout = 30;
    private int $limit = 100;
    private ?int $offset = null;
    private ?array $allowedUpdates = null;
    private bool $running = false;
    private $errorHandler = null;

    public function __construct(TelegramClient $client) { $this->client = $client; }

    public function withTimeout(int $seconds): self
    {
        if ($seconds < 0 || $seconds > 50) throw new InvalidArgumentException('Long polling timeout must be between 0 and 50 seconds.');
        $this->timeout = $seconds;
        return $this;
    }

    public function withLimit(int $limit): self
    {
        if ($limit < 1 || $limit > 100) throw new InvalidArgumentException('Update limit must be between 1 and 100.');
        $this->limit = $limit;
        return $this;
    }

    public function withOffset(?int $offset): self { $this->offset = $offset; return $this; }
    public function withAllowedUpdates(?array $updates): self { $this->allowedUpdates = $updates; return $this; }
    public function onError(?callable $handler): self { $this->errorHandler = $handler; return $this; }
    public function stop(): self { $this->running = false; return $this; }
    public function isRunning(): bool { return $this->running; }
    public function getOffset(): ?int { return $this->offset; }

    public function start(callable $handler, int $maxBatches = 0): void
    {
        if ($maxBatches < 0) throw new InvalidArgumentException('Maximum batch count cannot be negative.');
        if ($this->running) throw new InvalidArgumentException('This poller is already running.');
        $this->client->ensureRequestTimeout(max(30, $this->timeout + 10));
        $this->running = true;
        $batches = 0;

        try {
            while ($this->running && ($maxBatches === 0 || $batches < $maxBatches)) {
                try {
                    $parameters = ['timeout' => $this->timeout, 'limit' => $this->limit];
                    if ($this->offset !== null) $parameters['offset'] = $this->offset;
                    if ($this->allowedUpdates !== null) $parameters['allowed_updates'] = $this->allowedUpdates;
                    $response = $this->client->call('getUpdates', $parameters);
                    $updates = $response->getResult();
                    if (!is_array($updates)) throw new \UnexpectedValueException('Telegram getUpdates result must be an array.');

                    foreach ($updates as $updateData) {
                        if (!$this->running) break;
                        if (!is_array($updateData)) continue;
                        $update = new TelegramUpdate($updateData);
                        $handler($update, $this->client);
                        $updateId = $update->getUpdateId();
                        if ($updateId !== null) $this->offset = $updateId + 1;
                    }
                    $batches++;
                } catch (Throwable $error) {
                    if ($this->errorHandler === null) throw $error;
                    $shouldContinue = ($this->errorHandler)($error, $this);
                    if ($shouldContinue !== true) break;
                    usleep(500000);
                }
            }
        } finally {
            $this->running = false;
        }
    }
}
