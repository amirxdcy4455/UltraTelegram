<?php

declare(strict_types=1);

namespace Amirxd\UltraRequest\Request;

class Request implements RequestInterface
{
    private string $method;
    private string $url;
    private array $headers = [];
    private mixed $body = null;
    private bool $followRedirects = true;
    private ?int $maxRedirects = 10;
    private array $queryParams = [];
    private ?array $urlMethod = null;

    public function __construct(string $method, string $url)
    {
        $method = strtoupper(trim($method));
        $parts = parse_url($url);

        if (!preg_match('/^[!#$%&\'*+.^_`|~0-9A-Z-]+$/', $method)) {
            throw new \InvalidArgumentException('Invalid HTTP method.');
        }

        if (
            $parts === false
            || empty($parts['host'])
            || !isset($parts['scheme'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || preg_match('/[\r\n\x00-\x20]/', $url)
        ) {
            throw new \InvalidArgumentException('Invalid HTTP URL.');
        }

        $this->method = $method;
        $this->url = $url;
    }

    public static function get(string $url): self
    {
        return new self('GET', $url);
    }

    public static function post(string $url): self
    {
        return new self('POST', $url);
    }

    public static function put(string $url): self
    {
        return new self('PUT', $url);
    }

    public static function delete(string $url): self
    {
        return new self('DELETE', $url);
    }

    public static function patch(string $url): self
    {
        return new self('PATCH', $url);
    }

    public static function head(string $url): self
    {
        return new self('HEAD', $url);
    }

    public function withHeader(string $name, string $value): self
    {
        if (
            $name === ''
            || preg_match('/[^!#$%&\'*+.^_`|~0-9A-Za-z-]/', $name)
            || preg_match('/[\r\n]/', $value)
        ) {
            throw new \InvalidArgumentException('Invalid HTTP header.');
        }

        foreach (array_keys($this->headers) as $existing) {
            if (strcasecmp($existing, $name) === 0) {
                unset($this->headers[$existing]);
            }
        }

        $this->headers[$name] = $value;

        return $this;
    }

    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->withHeader((string) $name, (string) $value);
        }

        return $this;
    }

    public function withBody(mixed $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function withJson(array|object $data, int $flags = 0): self
    {
        $this->withHeader('Content-Type', 'application/json');
        $this->body = json_encode($data, $flags | JSON_THROW_ON_ERROR);

        return $this;
    }

    public function withQueryParams(array $params): self
    {
        $this->queryParams = array_replace($this->queryParams, $params);

        return $this;
    }

    public function withoutRedirects(): self
    {
        $this->followRedirects = false;

        return $this;
    }

    public function withRedirects(bool $follow = true): self
    {
        $this->followRedirects = $follow;

        return $this;
    }

    public function withMaxRedirects(int $max): self
    {
        if ($max < 0) {
            throw new \InvalidArgumentException('Maximum redirects cannot be negative.');
        }

        $this->maxRedirects = $max;

        return $this;
    }

    public function withUrlMethod(string $name, string $value): self
    {
        $this->urlMethod = [
            'name' => $name,
            'value' => $value,
        ];

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    public function followsRedirects(): bool
    {
        return $this->followRedirects;
    }

    public function getMaxRedirects(): ?int
    {
        return $this->maxRedirects;
    }

    public function getUrlMethod(): ?array
    {
        return $this->urlMethod;
    }

    public function getUrl(): string
    {
        $params = $this->queryParams;

        if ($this->urlMethod !== null) {
            $params[$this->urlMethod['name']] = $this->urlMethod['value'];
        }

        if ($params === []) {
            return $this->url;
        }

        $parts = parse_url($this->url);

        if ($parts === false) {
            throw new \InvalidArgumentException('Invalid URL.');
        }

        $existing = [];

        parse_str($parts['query'] ?? '', $existing);

        $query = http_build_query(
            array_replace($existing, $params),
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $authority = $parts['scheme'] . '://' . ($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        $path = $parts['path'] ?? '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $authority . $path . ($query !== '' ? '?' . $query : '') . $fragment;
    }
}
