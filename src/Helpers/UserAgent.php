<?php

namespace KraenzleRitter\Resources\Helpers;

use Composer\InstalledVersions;

class UserAgent
{
    public static function get(): array
    {
        $version = InstalledVersions::getPrettyVersion('kraenzle-ritter/resources');
        return ['User-Agent' => config(
                    'resources.user_agent',
                    env('RESOURCES_USER_AGENT', 'resources/'.$version.' (+https://github.com/kraenzle-ritter/resources)')
                )];
    }
}
