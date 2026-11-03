# UltraTelegram

**یک کلاینت مدرن و قابل توسعه PHP برای Telegram Bot API، مبتنی بر PHP Ultra Request.**

UltraTelegram یک SDK سبک و ماژولار برای ساخت ربات تلگرام با PHP است. ارسال درخواست‌های HTTP، Middleware و اجرای هم‌زمان درخواست‌ها توسط [PHP Ultra Request](https://github.com/amirxdcy4455/UltraRequest) انجام می‌شود.

## قابلیت‌ها

- دسترسی پویا به متدهای Telegram Bot API، حتی متدهای تازه اضافه‌شده
- درخواست‌های JSON و آپلود Multipart، شامل فایل‌های تو در تو و attach://
- دانلود فایل با Downloader جریانی
- Long Polling، مدیریت Updateها و اعتبارسنجی درخواست Webhook
- اجرای هم‌زمان درخواست‌ها با callMany()
- آبجکت‌های فقط‌خواندنی برای پاسخ‌ها و Exceptionهای مجزا
- پشتیبانی از PHP 8.0+ و PSR-4

## پیش‌نیازها

- PHP نسخه 8.0 یا بالاتر
- افزونه‌های ext-curl و ext-json
- کتابخانه amirxd/php-ultra-request

## نصب

تا زمان انتشار نسخه پایدار در Packagist، مخازن GitHub را در composer.json پروژه معرفی کنید:

~~~json
{
  "repositories": [
    {"type": "vcs", "url": "https://github.com/amirxdcy4455/UltraTelegram"},
    {"type": "vcs", "url": "https://github.com/amirxdcy4455/UltraRequest"}
  ],
  "require": {
    "amirxd/ultra-telegram": "dev-main",
    "amirxd/php-ultra-request": "dev-main"
  }
}
~~~

سپس composer update را اجرا کنید.

## شروع سریع

~~~php
use Amirxd\UltraTelegram\UltraTelegram;

$bot = new UltraTelegram(getenv('TELEGRAM_BOT_TOKEN'));

$response = $bot->sendMessage([
    'chat_id' => 123456789,
    'text' => 'Hello from UltraTelegram!'
]);

$messageId = $response->getResultObject()?->get('message_id');
~~~

توکن ربات را در Environment Variable یا Secret Manager نگه دارید و هرگز آن را در Git ذخیره نکنید.

## مستندات

صفحه اصلی عمداً خلاصه نگه داشته شده است. راهنمای کامل API و جزئیات فنی در صفحات جداگانه قرار دارند:

- [فهرست مستندات](docs/INDEX.md)
- [شروع کار](docs/wiki/Getting-Started.md)
- [متدهای API و درخواست‌ها](docs/wiki/API-Methods.md)
- [پاسخ‌ها و خطاها](docs/wiki/Responses-and-Errors.md)
- [آپلود و دانلود فایل](docs/wiki/Files.md)
- [Long Polling و Updateها](docs/wiki/Polling-and-Updates.md)
- [Webhook](docs/wiki/Webhooks.md)
- [Keyboardها](docs/wiki/Keyboards.md)
- [معماری و امنیت](docs/ARCHITECTURE.md)
- [سازگاری با Telegram Bot API](docs/API-COMPATIBILITY.md)

پوشه docs/wiki شامل صفحه‌های مستقل و آماده انتقال به GitHub Wiki است. Wiki گیت‌هاب مخزن Git جداگانه‌ای دارد و فایل‌های این پوشه برای نگهداری نسخه‌بندی‌شدهٔ مستندات در مخزن اصلی هستند.

## مثال‌ها

مثال‌های قابل اجرا در پوشه [examples/](examples/) قرار دارند.

## تست‌ها

~~~bash
composer test
~~~

## مجوز

MIT. فایل [LICENSE](LICENSE) را ببینید.
