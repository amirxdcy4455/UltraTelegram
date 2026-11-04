<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Support;

use Amirxd\UltraTelegram\Input\InputFile;
use JsonSerializable;
use InvalidArgumentException;

class MultipartPayload
{
    private const MAX_NESTED_DEPTH = 64;
    public static function containsFile(array $parameters): bool
    {
        foreach ($parameters as $value) {
            if (self::containsInputFile($value, 0)) return true;
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
            $normalized = self::normalizeNested($value, $attachments, $attachmentIndex, $reservedNames, 0);
            $body[$name] = self::encodeField($normalized);
        }

        foreach ($attachments as $name => $file) {
            $body[$name] = $file->toCurlFile();
        }

        return $body;
    }

    private static function normalizeNested(mixed $value, array &$attachments, int &$attachmentIndex, array $reservedNames, int $depth): mixed
    {
        self::assertNestingDepth($depth);
        if ($value instanceof InputFile) {
            do {
                $attachmentIndex++;
                $attachmentName = 'ultra_file_' . $attachmentIndex;
            } while (array_key_exists($attachmentName, $attachments) || isset($reservedNames[$attachmentName]));
            $attachments[$attachmentName] = $value;
            return 'attach://' . $attachmentName;
        }
        if ($value instanceof JsonSerializable) {
            return self::normalizeNested($value->jsonSerialize(), $attachments, $attachmentIndex, $reservedNames, $depth + 1);
        }
        if (is_object($value)) {
            return self::normalizeNested(get_object_vars($value), $attachments, $attachmentIndex, $reservedNames, $depth + 1);
        }
        if (!is_array($value)) return $value;

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = self::normalizeNested($item, $attachments, $attachmentIndex, $reservedNames, $depth + 1);
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

    private static function containsInputFile(mixed $value, int $depth): bool
    {
        self::assertNestingDepth($depth);
        if ($value instanceof InputFile) return true;
        if ($value instanceof JsonSerializable) return self::containsInputFile($value->jsonSerialize(), $depth + 1);
        if (is_object($value)) return self::containsInputFile(get_object_vars($value), $depth + 1);
        if (!is_array($value)) return false;
        foreach ($value as $item) {
            if (self::containsInputFile($item, $depth + 1)) return true;
        }
        return false;
    }

    private static function assertNestingDepth(int $depth): void
    {
        if ($depth > self::MAX_NESTED_DEPTH) {
            throw new InvalidArgumentException(sprintf(
                'Telegram parameter nesting exceeds the maximum supported depth of %d.',
                self::MAX_NESTED_DEPTH
            ));
        }
    }
}
