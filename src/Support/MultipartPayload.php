<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Support;

use Amirxd\UltraTelegram\Input\InputFile;
use JsonSerializable;
use InvalidArgumentException;

class MultipartPayload
{
    public static function containsFile(array $parameters): bool
    {
        foreach ($parameters as $value) {
            if (self::containsInputFile($value)) return true;
        }
        return false;
    }

    public static function build(array $parameters): array
    {
        $body = [];
        $attachments = [];
        $attachmentIndex = 0;
        $reservedNames = array_fill_keys(array_map('strval', array_keys($parameters)), true);

        foreach ($parameters as $name => $value) {
            if ($value === null) continue;
            if (!is_string($name)) {
                throw new InvalidArgumentException('Telegram multipart parameter names must be strings.');
            }
            if ($value instanceof InputFile) {
                $body[$name] = $value->toCurlFile();
                continue;
            }
            $normalized = self::normalizeNested($value, $attachments, $attachmentIndex, $reservedNames);
            $body[$name] = self::encodeField($normalized);
        }

        foreach ($attachments as $name => $file) {
            $body[$name] = $file->toCurlFile();
        }

        return $body;
    }

    private static function normalizeNested(mixed $value, array &$attachments, int &$attachmentIndex, array $reservedNames): mixed
    {
        if ($value instanceof InputFile) {
            do {
                $attachmentIndex++;
                $attachmentName = 'ultra_file_' . $attachmentIndex;
            } while (array_key_exists($attachmentName, $attachments) || isset($reservedNames[$attachmentName]));
            $attachments[$attachmentName] = $value;
            return 'attach://' . $attachmentName;
        }
        if ($value instanceof JsonSerializable) {
            return self::normalizeNested($value->jsonSerialize(), $attachments, $attachmentIndex, $reservedNames);
        }
        if (is_object($value)) {
            return self::normalizeNested(get_object_vars($value), $attachments, $attachmentIndex, $reservedNames);
        }
        if (!is_array($value)) return $value;

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = self::normalizeNested($item, $attachments, $attachmentIndex, $reservedNames);
        }
        return $normalized;
    }

    private static function encodeField(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        if (is_bool($value)) return $value ? 'true' : 'false';
        if ($value === null) return '';
        if (is_scalar($value)) return (string)$value;
        throw new InvalidArgumentException('Unsupported Telegram multipart parameter value.');
    }

    private static function containsInputFile(mixed $value): bool
    {
        if ($value instanceof InputFile) return true;
        if ($value instanceof JsonSerializable) return self::containsInputFile($value->jsonSerialize());
        if (is_object($value)) return self::containsInputFile(get_object_vars($value));
        if (!is_array($value)) return false;
        foreach ($value as $item) {
            if (self::containsInputFile($item)) return true;
        }
        return false;
    }
}
