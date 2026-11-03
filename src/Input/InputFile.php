<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Input;

use CURLFile;
use InvalidArgumentException;
use RuntimeException;

class InputFile
{
    private string $path;
    private string $filename;
    private ?string $mimeType;
    private bool $temporary;

    private function __construct(string $path, string $filename, ?string $mimeType, bool $temporary)
    {
        $this->path = $path;
        $this->filename = $filename;
        $this->mimeType = $mimeType;
        $this->temporary = $temporary;
    }

    public static function fromPath(string $path, ?string $filename = null, ?string $mimeType = null): self
    {
        if (!is_file($path) || !is_readable($path)) throw new InvalidArgumentException('Upload file must exist and be readable.');
        $resolvedPath = realpath($path);
        if ($resolvedPath === false) throw new InvalidArgumentException('Unable to resolve upload file path.');
        $resolvedFilename = $filename ?? basename($resolvedPath);
        self::validateMetadata($resolvedFilename, $mimeType);
        return new self($resolvedPath, $resolvedFilename, $mimeType, false);
    }

    public static function fromContent(string $content, string $filename, ?string $mimeType = null): self
    {
        self::validateMetadata($filename, $mimeType);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'ultra-telegram-');
        if ($temporaryPath === false) throw new RuntimeException('Unable to create a temporary upload file.');
        $written = file_put_contents($temporaryPath, $content, LOCK_EX);
        if ($written === false || $written !== strlen($content)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to write temporary upload file.');
        }
        return new self($temporaryPath, $filename, $mimeType, true);
    }

    private static function validateMetadata(string $filename, ?string $mimeType): void
    {
        if ($filename === '' || preg_match('/[\r\n\x00]/', $filename)) throw new InvalidArgumentException('Upload filename cannot be empty or contain control characters.');
        if ($mimeType !== null && preg_match('/[\r\n\x00]/', $mimeType)) throw new InvalidArgumentException('Upload MIME type cannot contain control characters.');
    }

    public function toCurlFile(): CURLFile
    {
        if (!class_exists(CURLFile::class)) throw new RuntimeException('The PHP cURL extension is required for file uploads.');
        if (!is_file($this->path) || !is_readable($this->path)) throw new RuntimeException('Upload file is no longer available.');
        return new CURLFile($this->path, $this->mimeType, $this->filename);
    }

    public function getPath(): string { return $this->path; }
    public function getFilename(): string { return $this->filename; }
    public function getMimeType(): ?string { return $this->mimeType; }

    public function __destruct()
    {
        if ($this->temporary && is_file($this->path)) @unlink($this->path);
    }
}
