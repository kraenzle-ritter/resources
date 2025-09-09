<?php

namespace KraenzleRitter\Resources\Tests;

use KraenzleRitter\Resources\Tests\TestCase;
use KraenzleRitter\Resources\Helpers\UserAgent;

class UserAgentTest extends TestCase
{
    public function test_user_agent_returns_array()
    {
        $userAgent = UserAgent::get();

        $this->assertIsArray($userAgent);
        $this->assertArrayHasKey('User-Agent', $userAgent);
    }

    public function test_user_agent_header_value_is_string()
    {
        $userAgent = UserAgent::get();
        $headerValue = $userAgent['User-Agent'];

        $this->assertIsString($headerValue);
        $this->assertNotEmpty($headerValue);
    }

    public function test_user_agent_contains_package_info()
    {
        $userAgent = UserAgent::get();
        $headerValue = $userAgent['User-Agent'];

        // Der UserAgent sollte "resources/" enthalten
        $this->assertStringContainsString('resources/', $headerValue);
    }

    public function test_user_agent_contains_version()
    {
        $userAgent = UserAgent::get();
        $headerValue = $userAgent['User-Agent'];

        // Der UserAgent sollte eine Versionsnummer oder dev-main enthalten
        $this->assertTrue(
            preg_match('/\d+\.\d+/', $headerValue) ||
            str_contains($headerValue, 'dev-main'),
            "UserAgent should contain version number or 'dev-main': {$headerValue}"
        );
    }

    public function test_user_agent_is_consistent()
    {
        $userAgent1 = UserAgent::get();
        $userAgent2 = UserAgent::get();

        // Der UserAgent sollte bei mehrfachen Aufrufen gleich bleiben
        $this->assertEquals($userAgent1, $userAgent2);
    }

    public function test_user_agent_contains_github_url()
    {
        $userAgent = UserAgent::get();
        $headerValue = $userAgent['User-Agent'];

        // UserAgent sollte GitHub URL enthalten
        $this->assertStringContainsString('github.com/kraenzle-ritter/resources', $headerValue);
    }
}
