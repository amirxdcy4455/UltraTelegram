<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Keyboard;

use InvalidArgumentException;

class Keyboard
{
    public static function inline(array $rows): array
    {
        self::validateRows($rows);

        return ['inline_keyboard' => $rows];
    }

    public static function reply(array $rows, array $options = []): array
    {
        self::validateRows($rows);

        return array_replace($options, [
            'keyboard' => $rows
        ]);
    }

    public static function removeReply(array $options = []): array
    {
        return array_replace($options, [
            'remove_keyboard' => true
        ]);
    }

    public static function forceReply(array $options = []): array
    {
        return array_replace($options, [
            'force_reply' => true
        ]);
    }

    public static function button(string $text, array $options = []): array
    {
        if ($text === '') {
            throw new InvalidArgumentException('Keyboard button text cannot be empty.');
        }

        return array_replace($options, ['text' => $text]);
    }

    private static function validateRows(array $rows): void
    {
        foreach ($rows as $row) {
            if (!is_array($row) || $row === []) {
                throw new InvalidArgumentException('Keyboard rows must be non-empty arrays.');
            }

            foreach ($row as $button) {
                if (!is_array($button) || !isset($button['text']) || !is_string($button['text'])) {
                    throw new InvalidArgumentException('Every keyboard button must be an array containing a text field.');
                }
            }
        }
    }
}
