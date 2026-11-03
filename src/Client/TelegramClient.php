<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Client;

use Amirxd\UltraRequest\Client\Client as UltraRequestClient;
use Amirxd\UltraRequest\Request\Request;
use Amirxd\UltraRequest\Response\HttpResponse;
use Amirxd\UltraTelegram\Contracts\ConcurrentTransportInterface;
use Amirxd\UltraTelegram\Contracts\TransportInterface;
use Amirxd\UltraTelegram\Exception\TelegramApiException;
use Amirxd\UltraTelegram\Exception\TelegramTransportException;
use Amirxd\UltraTelegram\Input\InputFile;
use Amirxd\UltraTelegram\Polling\UpdatePoller;
use Amirxd\UltraTelegram\Response\TelegramResponse;
use Amirxd\UltraTelegram\Support\MultipartPayload;
use Amirxd\UltraTelegram\Transport\UltraRequestTransport;
use Amirxd\UltraTelegram\Updates\UpdateDispatcher;
use JsonException;
use InvalidArgumentException;
use Throwable;

class TelegramClient
{
    private string $token;
    private string $apiBaseUrl = 'https://api.telegram.org';
    private TransportInterface $transport;
    private ?UltraRequestClient $httpClient;

    public function __construct(
        string $token,
        ?UltraRequestClient $httpClient = null,
        ?TransportInterface $transport = null
    ) {
        $token = trim($token);

        if (!preg_match('/^[0-9]+:[A-Za-z0-9_-]{10,}$/D', $token)) {
            throw new InvalidArgumentException('Invalid Telegram bot token format.');
        }

        $this->token = $token;
        $this->httpClient = $httpClient;

        if ($transport !== null) {
            $this->transport = $transport;
        } else {
            $this->httpClient = $httpClient ?? new UltraRequestClient();
            $this->transport = new UltraRequestTransport($this->httpClient);
        }
    }

    public function withApiBaseUrl(string $baseUrl, bool $allowInsecure = false): self
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $parts = parse_url($baseUrl);

        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            throw new InvalidArgumentException('API base URL must be an absolute HTTP or HTTPS URL.');
        }

        $scheme = strtolower($parts['scheme']);

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('API base URL must use HTTP or HTTPS.');
        }

        if ($scheme !== 'https' && !$allowInsecure) {
            throw new InvalidArgumentException(
                'HTTPS is required. Set allowInsecure=true only for a trusted local Bot API server.'
            );
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException(
                'API base URL cannot contain credentials, query parameters, or a fragment.'
            );
        }

        $this->apiBaseUrl = $baseUrl;

        return $this;
    }

    public function withTimeout(int $seconds): self
    {
        if (!$this->transport instanceof UltraRequestTransport) {
            throw new InvalidArgumentException(
                'Timeout configuration is available only with the default Ultra Request transport.'
            );
        }

        $this->transport->withTimeout($seconds);

        return $this;
    }

    public function withConnectTimeout(int $seconds): self
    {
        if (!$this->transport instanceof UltraRequestTransport) {
            throw new InvalidArgumentException(
                'Connect timeout configuration is available only with the default Ultra Request transport.'
            );
        }

        $this->transport->withConnectTimeout($seconds);

        return $this;
    }

    public function ensureRequestTimeout(int $seconds): self
    {
        if ($seconds < 1) {
            throw new InvalidArgumentException('Request timeout must be positive.');
        }

        if ($this->transport instanceof UltraRequestTransport) {
            $this->transport->withTimeout($seconds);
        }

        return $this;
    }

    public function getApiBaseUrl(): string
    {
        return $this->apiBaseUrl;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function getHttpClient(): ?UltraRequestClient
    {
        return $this->httpClient;
    }

    public function call(
        string $method,
        array $parameters = [],
        string $httpMethod = 'POST'
    ): TelegramResponse {
        $response = $this->callRaw($method, $parameters, $httpMethod);

        if (!$response->isOk()) {
            throw new TelegramApiException($method, $response);
        }

        return $response;
    }

    public function callRaw(
        string $method,
        array $parameters = [],
        string $httpMethod = 'POST'
    ): TelegramResponse {
        $method = $this->validateMethodName($method);
        $request = $this->createRequest($method, $parameters, $httpMethod);

        try {
            $httpResponse = $this->transport->send($request);
        } catch (Throwable $error) {
            throw new TelegramTransportException(
                'Telegram transport failed while calling ' . $method . ': ' . $this->redactToken($error->getMessage()),
                $method,
                null,
                $this->createSafePrevious($error)
            );
        }

        return $this->parseResponse($method, $httpResponse);
    }

    /**
     * @param array<int|string, array{method: string, parameters?: array, http_method?: string}> $calls
     * @return array<int|string, TelegramResponse|Throwable>
     */
    public function callMany(array $calls, int $concurrency = 5): array
    {
        if ($concurrency < 1) {
            throw new InvalidArgumentException('Concurrency must be at least one.');
        }

        if (!$this->transport instanceof ConcurrentTransportInterface) {
            throw new InvalidArgumentException('The configured transport does not support concurrent requests.');
        }

        $requests = [];
        $methods = [];

        foreach ($calls as $key => $call) {
            if (!is_array($call) || !isset($call['method']) || !is_string($call['method'])) {
                throw new InvalidArgumentException('Each concurrent call must contain a method string.');
            }

            $method = $this->validateMethodName($call['method']);
            $parameters = $call['parameters'] ?? [];
            $httpMethod = $call['http_method'] ?? 'POST';

            if (!is_array($parameters) || !is_string($httpMethod)) {
                throw new InvalidArgumentException('Concurrent call parameters or HTTP method are invalid.');
            }

            $requests[$key] = $this->createRequest($method, $parameters, $httpMethod);
            $methods[$key] = $method;
        }

        $transportResults = $this->transport->sendMany($requests, $concurrency);
        $results = [];

        foreach ($methods as $key => $method) {
            $transportResult = $transportResults[$key] ?? null;

            if ($transportResult instanceof Throwable) {
                $results[$key] = new TelegramTransportException(
                    'Telegram transport failed while calling ' . $method . ': '
                    . $this->redactToken($transportResult->getMessage()),
                    $method,
                    null,
                    $this->createSafePrevious($transportResult)
                );
                continue;
            }

            if (!$transportResult instanceof HttpResponse) {
                $results[$key] = new TelegramTransportException(
                    'Concurrent transport returned an invalid result for ' . $method . '.',
                    $method
                );
                continue;
            }

            try {
                $response = $this->parseResponse($method, $transportResult);
                $results[$key] = $response->isOk()
                    ? $response
                    : new TelegramApiException($method, $response);
            } catch (Throwable $error) {
                $results[$key] = $error;
            }
        }

        return $results;
    }

    private function createRequest(string $method, array $parameters, string $httpMethod): Request
    {
        $method = $this->validateMethodName($method);
        $httpMethod = strtoupper(trim($httpMethod));

        if (!in_array($httpMethod, ['GET', 'POST'], true)) {
            throw new InvalidArgumentException('Telegram Bot API supports GET and POST requests only.');
        }

        $hasFiles = MultipartPayload::containsFile($parameters);

        if ($hasFiles && $httpMethod !== 'POST') {
            throw new InvalidArgumentException('File uploads require the POST method.');
        }

        $url = $this->buildMethodUrl($method);
        $request = $httpMethod === 'GET'
            ? Request::get($url)
            : Request::post($url);

        $request->withoutRedirects();

        if ($httpMethod === 'GET') {
            $request->withQueryParams($this->normalizeQueryParameters($parameters));
        } elseif ($hasFiles) {
            $request->withBody(MultipartPayload::build($parameters));
        } else {
            $request->withJson(
                $parameters,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        return $request;
    }

    public function downloadFile(string $fileId, string $destination, bool $overwrite = false): bool
    {
        $fileResponse = $this->call('getFile', ['file_id' => $fileId]);
        $result = $fileResponse->getResult();
        $filePath = is_array($result) ? ($result['file_path'] ?? null) : null;

        if (!is_string($filePath) || $filePath === '') {
            throw new TelegramTransportException(
                'Telegram did not return a file_path for the requested file.',
                'getFile'
            );
        }

        return $this->downloadFilePath($filePath, $destination, $overwrite);
    }

    public function downloadFilePath(string $filePath, string $destination, bool $overwrite = false): bool
    {
        if ($filePath === '' || preg_match('/[\r\n\x00]/', $filePath)) {
            throw new InvalidArgumentException('Invalid Telegram file path.');
        }

        if ($this->isAbsolutePath($filePath)) {
            if (!is_file($filePath) || !is_readable($filePath)) {
                throw new TelegramTransportException(
                    'The local Bot API file path is not readable on this machine.',
                    'downloadFile'
                );
            }

            return $this->copyLocalFile($filePath, $destination, $overwrite);
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', ltrim($filePath, '/'))));
        $url = $this->apiBaseUrl . '/file/bot' . $this->token . '/' . $encodedPath;

        try {
            return $this->transport->download($url, $destination, $overwrite);
        } catch (Throwable $error) {
            throw new TelegramTransportException(
                'Telegram file download failed: ' . $this->redactToken($error->getMessage()),
                'downloadFile',
                null,
                $this->createSafePrevious($error)
            );
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1;
    }

    private function copyLocalFile(string $source, string $destination, bool $overwrite): bool
    {
        if ($destination === '') {
            throw new InvalidArgumentException('Destination path cannot be empty.');
        }

        if (file_exists($destination) && !$overwrite) {
            throw new TelegramTransportException(
                'Destination file already exists.',
                'downloadFile'
            );
        }

        $directory = dirname($destination);

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new TelegramTransportException(
                'Unable to create the local download directory.',
                'downloadFile'
            );
        }

        $temporaryPath = tempnam($directory, '.ultra-telegram-');

        if ($temporaryPath === false) {
            throw new TelegramTransportException(
                'Unable to create a temporary destination file.',
                'downloadFile'
            );
        }

        if (!@copy($source, $temporaryPath)) {
            @unlink($temporaryPath);
            throw new TelegramTransportException(
                'Unable to copy the local Bot API file.',
                'downloadFile'
            );
        }

        $backupPath = null;

        if (file_exists($destination)) {
            $backupPath = $destination . '.backup-' . bin2hex(random_bytes(5));

            if (!@rename($destination, $backupPath)) {
                @unlink($temporaryPath);
                throw new TelegramTransportException(
                    'Unable to replace the existing destination file safely.',
                    'downloadFile'
                );
            }
        }

        if (!@rename($temporaryPath, $destination)) {
            @unlink($temporaryPath);

            if ($backupPath !== null && is_file($backupPath)) {
                @rename($backupPath, $destination);
            }

            throw new TelegramTransportException(
                'Unable to finalize the local Bot API file.',
                'downloadFile'
            );
        }

        if ($backupPath !== null && is_file($backupPath)) {
            @unlink($backupPath);
        }

        return true;
    }

    public function createPoller(): UpdatePoller
    {
        return new UpdatePoller($this);
    }

    public function createDispatcher(): UpdateDispatcher
    {
        return new UpdateDispatcher($this);
    }

    public function __call(string $name, array $arguments): TelegramResponse
    {
        if (count($arguments) === 0) {
            return $this->call($name);
        }

        if (array_keys($arguments) === [0] && is_array($arguments[0])) {
            return $this->call($name, $arguments[0]);
        }

        $namedArguments = [];

        foreach ($arguments as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException(
                    'Dynamic Telegram methods accept one parameter array or PHP named arguments.'
                );
            }

            $namedArguments[$key] = $value;
        }

        return $this->call($name, $namedArguments);
    }

    private function parseResponse(string $method, HttpResponse $httpResponse): TelegramResponse
    {
        $body = $httpResponse->getBody();

        if ($body === null || $body === '') {
            throw new TelegramTransportException(
                'Telegram returned an empty response for ' . $method . '.',
                $method,
                $httpResponse->getStatusCode()
            );
        }

        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new TelegramTransportException(
                'Telegram returned invalid JSON for ' . $method . '.',
                $method,
                $httpResponse->getStatusCode(),
                $error
            );
        }

        if (!is_array($payload) || !array_key_exists('ok', $payload) || !is_bool($payload['ok'])) {
            throw new TelegramTransportException(
                'Telegram returned an unexpected response structure for ' . $method . '.',
                $method,
                $httpResponse->getStatusCode()
            );
        }

        return new TelegramResponse($payload, $httpResponse, $method);
    }

    private function buildMethodUrl(string $method): string
    {
        return $this->apiBaseUrl . '/bot' . $this->token . '/' . $method;
    }

    private function validateMethodName(string $method): string
    {
        $method = trim($method);

        if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $method)) {
            throw new InvalidArgumentException('Invalid Telegram Bot API method name.');
        }

        return $method;
    }

    private function redactToken(string $message): string
    {
        return str_replace($this->token, '[REDACTED]', $message);
    }

    /**
     * Keep useful transport diagnostics without retaining an exception whose
     * message or nested trace may contain the token-bearing request URL.
     */
    private function createSafePrevious(Throwable $error): \RuntimeException
    {
        return new \RuntimeException(
            $this->redactToken($error->getMessage()),
            is_int($error->getCode()) ? $error->getCode() : 0
        );
    }

    private function normalizeQueryParameters(array $parameters): array
    {
        $normalized = [];

        foreach ($parameters as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $normalized[$key] = json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
            } elseif (is_bool($value)) {
                $normalized[$key] = $value ? 'true' : 'false';
            } elseif ($value !== null) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}