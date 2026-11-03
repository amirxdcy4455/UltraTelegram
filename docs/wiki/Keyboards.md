# Keyboard Helpers

Keyboard helpers create regular PHP arrays accepted by Telegram's reply_markup parameter.

## Inline keyboard

~~~php
use Amirxd\UltraTelegram\Keyboard\Keyboard;

$replyMarkup = Keyboard::inline([
    [
        Keyboard::button('Open', ['url' => 'https://example.com']),
        Keyboard::button('Confirm', ['callback_data' => 'confirm'])
    ]
]);

$bot->sendMessage([
    'chat_id' => 123456789,
    'text' => 'Choose an action:',
    'reply_markup' => $replyMarkup
]);
~~~

Other helpers include Keyboard::reply(), Keyboard::removeReply() and Keyboard::forceReply().

See Telegram's official Bot API documentation for supported button fields and constraints.
