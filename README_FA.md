# UltraTelegram

**یک کتابخانه PHP برای کار با Telegram Bot API با استفاده از PHP Ultra Request.**

UltraTelegram برای ساخت ربات تلگرام با PHP طراحی شده است. درخواست‌های HTTP توسط `amirxd/php-ultra-request` ارسال می‌شوند، ساختار پروژه ماژولار است، متدهای تنظیماتی همان Instance را تغییر می‌دهند و هیچ `clone`ای برای زنجیره‌ای‌کردن متدها استفاده نمی‌شود.

## ویژگی‌ها

- سازگار با PHP 8.0 به بالا
- مبنای مستندات: Telegram Bot API 10.3
- ارسال درخواست‌های JSON و multipart
- آپلود فایل از مسیر یا محتوای حافظه
- پشتیبانی از فایل‌های تو در تو و `attach://`
- دانلود فایل با Downloader جریانی Ultra Request
- مدیریت خطاهای API و خطاهای HTTP به‌صورت جداگانه
- Long Polling با مدیریت offset
- دریافت و اعتبارسنجی Webhook
- Update Dispatcher برای Message، Callback Query و سایر Updateها
- Wrapper عمومی برای آبجکت‌های Telegram
- امکان فراخوانی تمام متدهای Bot API و متدهای آینده، بدون نیاز به Wrapper جداگانه
- پشتیبانی از قابلیت‌های Bot API 10.3 مانند Guest Message، Business، Managed Bot و Rich Messages از طریق API عمومی
- جلوگیری از نشت Token با غیرفعال‌کردن Redirect
- پشتیبانی از Local Bot API Server و مسیر فایل محلی

## نصب

تا زمانی که پکیج در Packagist منتشر نشده، مخزن GitHub را به `composer.json` پروژه مصرف‌کننده اضافه کن:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/amirxdcy4455/UltraTelegram"
        }
    ],
    "require": {
        "amirxd/ultra-telegram": "dev-main"
    }
}
```

سپس اجرا کن:

```bash
composer update amirxd/ultra-telegram
```

این کتابخانه به `amirxd/php-ultra-request` وابسته است و فعلاً از شاخه `dev-main` آن استفاده می‌کند. اگر هر دو پروژه را محلی نگه می‌داری، می‌توانی برای Ultra Request از Path Repository استفاده کنی.

## ساخت کلاینت

```php
use Amirxd\UltraTelegram\UltraTelegram;

$bot = new UltraTelegram(getenv('TELEGRAM_BOT_TOKEN'));
```

## ارسال پیام

```php
$response = $bot->sendMessage([
    'chat_id' => 123456789,
    'text' => 'Hello from UltraTelegram.'
]);

$message = $response->getResultObject();
$messageId = $message?->get('message_id');
```

همچنین می‌توان از متد عمومی استفاده کرد:

```php
$response = $bot->call('sendMessage', [
    'chat_id' => 123456789,
    'text' => 'Hello'
]);
```

تمام متدهای Telegram Bot API از طریق `call()` یا فراخوانی مستقیم با `__call()` در دسترس هستند؛ بنابراین برای اضافه‌شدن متد جدید تلگرام لازم نیست منتظر انتشار نسخه جدید کتابخانه بمانی.

## مدیریت خطا

```php
use Amirxd\UltraTelegram\Exception\TelegramApiException;
use Amirxd\UltraTelegram\Exception\TelegramTransportException;

try {
    $bot->sendMessage([
        'chat_id' => 123456789,
        'text' => 'Hello'
    ]);
} catch (TelegramApiException $error) {
    echo $error->getApiErrorCode();

    if ($error->getRetryAfter() !== null) {
        sleep($error->getRetryAfter());
    }
} catch (TelegramTransportException $error) {
    error_log($error->getMessage());
}
```

برای بررسی پاسخ خطا بدون Exception از `callRaw()` استفاده کن.

## درخواست‌های هم‌زمان

با `callMany()` چند متد Bot API با موتور `curl_multi` کتابخانه Ultra Request اجرا می‌شوند. کلیدهای ورودی حفظ می‌شوند و نتیجه هر درخواست یا `TelegramResponse` است یا `Throwable`؛ خطای یک درخواست، بقیه را متوقف نمی‌کند.

```php
$results = $bot->callMany([
    'identity' => ['method' => 'getMe'],
    'chat' => [
        'method' => 'getChat',
        'parameters' => ['chat_id' => '@example_channel']
    ]
], concurrency: 3);
```

## آپلود فایل

```php
use Amirxd\UltraTelegram\Input\InputFile;

$bot->sendDocument([
    'chat_id' => 123456789,
    'document' => InputFile::fromPath(__DIR__ . '/report.pdf')
]);
```

برای ساخت فایل موقت:

```php
$file = InputFile::fromContent('Report content', 'report.txt', 'text/plain');

$bot->sendDocument([
    'chat_id' => 123456789,
    'document' => $file
]);
```

فایل‌های داخل آرایه‌هایی مثل `media` به `attach://` تبدیل می‌شوند و به‌صورت multipart ارسال خواهند شد.

## دانلود فایل

```php
$bot->downloadFile(
    'TELEGRAM_FILE_ID',
    __DIR__ . '/downloads/file.pdf'
);
```

اگر از Local Bot API Server استفاده کنی و `file_path` یک مسیر مطلق محلی باشد، کتابخانه فایل را مستقیماً از همان مسیر کپی می‌کند.

## Long Polling

```php
$poller = $bot->createPoller();
$poller->withTimeout(30);
$poller->withAllowedUpdates(['message', 'callback_query']);

$poller->start(static function ($update, $bot): void {
    if ($update->getUpdateType() !== 'message') {
        return;
    }

    $message = $update->getUpdatePayload();
    $chat = $message?->get('chat');
    $chatId = $chat?->get('id');
    $text = $message?->get('text');

    if ($chatId !== null && is_string($text)) {
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $text
        ]);
    }
});
```

این متد مسدودکننده است و تا فراخوانی `stop()` یا رسیدن به محدودیت تعداد Batchها اجرا می‌شود.

## Webhook

```php
$secret = bin2hex(random_bytes(32));

$bot->setWebhook([
    'url' => 'https://example.com/telegram/webhook',
    'secret_token' => $secret,
    'allowed_updates' => ['message', 'callback_query']
]);
```

در Endpoint:

```php
use Amirxd\UltraTelegram\Webhook\WebhookRequest;

$webhook = WebhookRequest::fromGlobals($secret, 1024 * 1024);
$dispatcher->dispatch($webhook->getUpdate());

http_response_code(200);
echo 'OK';
```

این Helper متد POST، نوع محتوای JSON، هدر `X-Telegram-Bot-Api-Secret-Token`، ساختار JSON و اندازه بدنه را بررسی می‌کند. راه‌اندازی وب‌سرور و Reverse Proxy بر عهده برنامه است.

## تنظیمات HTTP

```php
$bot->withTimeout(45);
$bot->withConnectTimeout(10);
```

همچنین می‌توان یک نمونه تنظیم‌شده از Ultra Request را به سازنده داد.

## سازگاری با Bot API

نام تمام متدهای Bot API را می‌توان با `call()` یا فراخوانی مستقیم استفاده کرد؛ پارامترها نیز مطابق مستندات رسمی تلگرام به‌صورت آرایه یا Named Argument ارسال می‌شوند. این طراحی متدهای جدید تلگرام را هم بدون نیاز به انتشار نسخه جدید قابل استفاده می‌کند، اما اعتبارسنجی Schema پارامترها بر عهده تلگرام است.

مبنای مستندات این نسخه Bot API 10.3 است. جزئیات معماری در `docs/ARCHITECTURE.md` و سازگاری API در `docs/API-COMPATIBILITY.md` آمده است.

## تست‌ها

```bash
composer test
```

## مجوز

MIT. فایل `LICENSE` را ببین.
