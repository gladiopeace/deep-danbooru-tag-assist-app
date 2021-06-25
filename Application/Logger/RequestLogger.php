<?php declare(strict_types=1);


namespace Ramsterhad\DeepDanbooruTagAssist\Application\Logger;


class RequestLogger extends Logger
{
    public static function getDefaultDestinationFile(): string
    {
        return 'request.log';
    }
}