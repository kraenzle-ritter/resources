<?php

namespace KraenzleRitter\Resources\Helpers;

class UserAgent
{
    /**
     * The User-Agent header sent with every provider request.
     *
     * The value comes from config only. Reading env() here would return null
     * once the host application has run `php artisan config:cache`, which would
     * send an empty User-Agent to APIs that require one.
     */
    public static function get(): array
    {
        return ['User-Agent' => config(
            'resources.user_agent',
            'resources/dev (+https://github.com/kraenzle-ritter/resources)'
        )];
    }
}
