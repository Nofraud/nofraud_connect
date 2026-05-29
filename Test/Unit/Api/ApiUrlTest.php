<?php

namespace NoFraud\Connect\Test\Unit\Api;

use NoFraud\Connect\Api\ApiUrl;
use NoFraud\Connect\Helper\Config;
use NoFraud\Connect\Logger\Logger;
use PHPUnit\Framework\TestCase;

class ApiUrlTest extends TestCase
{
    private $apiUrl;

    protected function setUp(): void
    {
        $configHelper = $this->createMock(Config::class);
        $configHelper->method('getSandboxMode')->willReturn('https://api.nofraud.com/');

        $logger = $this->createMock(Logger::class);

        $this->apiUrl = new ApiUrl($configHelper, $logger);
    }

    public function testBuildOrderApiUrlReturnsCleanUrl(): void
    {
        $url = $this->apiUrl->buildOrderApiUrl('status');

        $this->assertEquals('https://api.nofraud.com/status', $url);
    }

    public function testBuildOrderApiUrlOnlyAcceptsOneParameter(): void
    {
        $method = new \ReflectionMethod(ApiUrl::class, 'buildOrderApiUrl');

        $this->assertEquals(1, $method->getNumberOfParameters());
    }

    public function testBuildOrderApiUrlHasNoExtraPathSegments(): void
    {
        $url = $this->apiUrl->buildOrderApiUrl('status');

        $pathSegments = array_filter(explode('/', parse_url($url, PHP_URL_PATH)));
        $this->assertCount(1, $pathSegments, 'URL should only contain the endpoint segment, no token');
    }

    public function testGetProductionUrlIsHttps(): void
    {
        $url = $this->apiUrl->getProductionUrl();

        $this->assertStringStartsWith('https://', $url);
    }
}
