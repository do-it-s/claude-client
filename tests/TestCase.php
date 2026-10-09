<?php

namespace DoITs\ClaudeClient\Tests;

use DoITs\ClaudeClient\ClaudeClientServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ClaudeClientServiceProvider::class];
    }
}
