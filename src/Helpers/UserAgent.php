<?php

namespace KraenzleRitter\Resources\Helpers;

use Composer\InstalledVersions;

class UserAgent
{
    public static function get(): array
    {
        try {
            $version = InstalledVersions::getPrettyVersion('kraenzle-ritter/resources');
        } catch (\OutOfBoundsException $e) {
            $version = 'dev-main';
        }
        
        return [
            'User-Agent' => config(
                'kraenzle-ritter-resources.user_agent',
                'resources/' . $version . ' (+https://github.com/kraenzle-ritter/resources)'
            )
        ];
    }
}
