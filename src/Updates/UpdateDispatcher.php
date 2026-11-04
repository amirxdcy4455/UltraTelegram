<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Updates;

use Amirxd\UltraTelegram\Client\TelegramClient;
use Amirxd\UltraTelegram\Objects\TelegramUpdate;
use InvalidArgumentException;

class UpdateDispatcher
{
    private TelegramClient $client;
    private array $handlers = [];

    public function __construct(TelegramClient $client)
    {
        $this->client = $client;
    }

    public function on(string $updateType, callable $handler): self
    {
        $updateType = trim($updateType);

        if ($updateType === '') {
            throw new InvalidArgumentException('Update type cannot be empty.');
        }

        $this->handlers[$updateType][] = $handler;
        return $this;
    }

    public function onChannelPost(callable $handler): self { return $this->on('channel_post', $handler); }
    public function onEditedChannelPost(callable $handler): self { return $this->on('edited_channel_post', $handler); }
    public function onBusinessConnection(callable $handler): self { return $this->on('business_connection', $handler); }
    public function onEditedBusinessMessage(callable $handler): self { return $this->on('edited_business_message', $handler); }
    public function onDeletedBusinessMessages(callable $handler): self { return $this->on('deleted_business_messages', $handler); }
    public function onMessageReactionCount(callable $handler): self { return $this->on('message_reaction_count', $handler); }
    public function onChosenInlineResult(callable $handler): self { return $this->on('chosen_inline_result', $handler); }
    public function onShippingQuery(callable $handler): self { return $this->on('shipping_query', $handler); }
    public function onPreCheckoutQuery(callable $handler): self { return $this->on('pre_checkout_query', $handler); }
    public function onPurchasedPaidMedia(callable $handler): self { return $this->on('purchased_paid_media', $handler); }
    public function onPoll(callable $handler): self { return $this->on('poll', $handler); }
    public function onPollAnswer(callable $handler): self { return $this->on('poll_answer', $handler); }
    public function onChatBoost(callable $handler): self { return $this->on('chat_boost', $handler); }
    public function onRemovedChatBoost(callable $handler): self { return $this->on('removed_chat_boost', $handler); }
    public function onManagedBot(callable $handler): self { return $this->on('managed_bot', $handler); }
    public function onMessage(callable $handler): self { return $this->on('message', $handler); }
    public function onEditedMessage(callable $handler): self { return $this->on('edited_message', $handler); }
    public function onCallbackQuery(callable $handler): self { return $this->on('callback_query', $handler); }
    public function onInlineQuery(callable $handler): self { return $this->on('inline_query', $handler); }
    public function onChatMember(callable $handler): self { return $this->on('chat_member', $handler); }
    public function onMyChatMember(callable $handler): self { return $this->on('my_chat_member', $handler); }
    public function onBusinessMessage(callable $handler): self { return $this->on('business_message', $handler); }
    public function onGuestMessage(callable $handler): self { return $this->on('guest_message', $handler); }
    public function onMessageReaction(callable $handler): self { return $this->on('message_reaction', $handler); }
    public function onChatJoinRequest(callable $handler): self { return $this->on('chat_join_request', $handler); }
    public function onSubscription(callable $handler): self { return $this->on('subscription', $handler); }
    public function onStoppedMessageGeneration(callable $handler): self { return $this->on('stopped_message_generation', $handler); }
    public function onAny(callable $handler): self { return $this->on('*', $handler); }

    public function remove(string $updateType): self
    {
        unset($this->handlers[$updateType]);
        return $this;
    }

    public function clear(): self
    {
        $this->handlers = [];
        return $this;
    }

    public function dispatch(array|TelegramUpdate $update, ?TelegramClient $client = null): int
    {
        $telegramUpdate = $update instanceof TelegramUpdate ? $update : new TelegramUpdate($update);
        $type = $telegramUpdate->getUpdateType();

        if ($type === null) {
            return 0;
        }

        $payload = $telegramUpdate->getUpdatePayload();
        $handlers = array_merge($this->handlers[$type] ?? [], $this->handlers['*'] ?? []);

        foreach ($handlers as $handler) {
            $handler($payload, $telegramUpdate, $client ?? $this->client);
        }

        return count($handlers);
    }

    public function getRegisteredTypes(): array
    {
        return array_keys($this->handlers);
    }
}
