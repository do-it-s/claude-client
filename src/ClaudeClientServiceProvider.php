<?php

namespace DoITs\ClaudeClient;

use DoITs\ClaudeClient\Contracts\MessagesTransport;
use DoITs\ClaudeClient\Transport\HttpTransport;
use Illuminate\Support\ServiceProvider;

class ClaudeClientServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/claude-client.php', 'claude-client');

        $this->app->bind(MessagesTransport::class, HttpTransport::class);
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/claude-client.php' => config_path('claude-client.php'),
        ], 'claude-client-config');
    }
}
