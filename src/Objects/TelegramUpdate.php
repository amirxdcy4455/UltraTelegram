<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Objects;

class TelegramUpdate extends TelegramObject
{
    private const UPDATE_FIELDS = [
        'message', 'edited_message', 'channel_post', 'edited_channel_post',
        'business_connection', 'business_message', 'edited_business_message',
        'deleted_business_messages', 'guest_message', 'message_reaction',
        'message_reaction_count', 'inline_query', 'chosen_inline_result',
        'callback_query', 'shipping_query', 'pre_checkout_query',
        'purchased_paid_media', 'poll', 'poll_answer', 'my_chat_member',
        'chat_member', 'chat_join_request', 'chat_boost', 'removed_chat_boost',
        'managed_bot', 'subscription', 'stopped_message_generation'
    ];

    public function getUpdateId(): ?int
    {
        $value = $this->getRaw('update_id');
        return is_numeric($value) ? (int)$value : null;
    }

    public function getUpdateType(): ?string
    {
        foreach (self::UPDATE_FIELDS as $field) if (array_key_exists($field, $this->data)) return $field;
        foreach ($this->data as $field => $_) if ($field !== 'update_id') return (string)$field;
        return null;
    }

    public function getUpdatePayload(): ?TelegramObject
    {
        $type = $this->getUpdateType();
        $value = $type === null ? null : $this->get($type);
        return $value instanceof TelegramObject ? $value : null;
    }
}
