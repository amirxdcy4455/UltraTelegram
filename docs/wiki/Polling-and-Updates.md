# Long Polling and Update Dispatching

Telegram does not allow getUpdates polling and webhook delivery to be active simultaneously.

## Long polling

~~~php
$poller = $bot->createPoller();
$poller->withTimeout(30);
$poller->withAllowedUpdates(['message', 'callback_query']);

$poller->start(static function ($update, $bot): void {
    if ($update->getUpdateType() !== 'message') {
        return;
    }

    $message = $update->getUpdatePayload();
    $chatId = $message?->get('chat')?->get('id');
    $text = $message?->get('text');

    if ($chatId !== null && is_string($text)) {
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => 'You said: ' . $text
        ]);
    }
});
~~~

The poller blocks the current process. A batch limit of zero means it runs until stop() is called. The update offset advances only after the handler returns successfully.

## Error handling

~~~php
$poller->onError(static function (Throwable $error): bool {
    error_log($error->getMessage());
    return true;
});
~~~

## Update dispatcher

~~~php
$dispatcher = $bot->createDispatcher();

$dispatcher->onMessage(static function ($message, $update, $bot): void {
    // Handle a message update.
});

$dispatcher->onCallbackQuery(static function ($query, $update, $bot): void {
    // Handle a callback query.
});
~~~

See the examples directory for complete polling and dispatching examples.
