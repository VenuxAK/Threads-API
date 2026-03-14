<?php

namespace App\Providers;

use App\Console\Commands\WafManageCommand;
use Illuminate\Support\ServiceProvider;

class WafServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge WAF configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/waf.php',
            'waf'
        );

        // Register WAF command
        $this->commands([
            WafManageCommand::class,
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../../config/waf.php' => config_path('waf.php'),
            __DIR__ . '/../../config/waf.env.example' => base_path('waf.env.example'),
        ], 'waf-config');

        // Publish middleware
        $this->publishes([
            __DIR__ . '/../Http/Middleware/WebApplicationFirewall.php'
            => app_path('Http/Middleware/WebApplicationFirewall.php'),
        ], 'waf-middleware');

        // Ensure WAF log directory exists
        $logPath = storage_path('logs');

        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }
    }
}
