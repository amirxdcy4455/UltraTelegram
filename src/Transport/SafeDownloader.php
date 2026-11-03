<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Transport;

use Amirxd\UltraRequest\File\Downloader;
use Amirxd\UltraRequest\Request\Request;

class SafeDownloader extends Downloader
{
    public function getRequest(): Request
    {
        $request = parent::getRequest();
        $request->withoutRedirects();

        return $request;
    }
}
