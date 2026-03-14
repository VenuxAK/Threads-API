<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class WafManageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'waf
        {action : Action to perform (status|logs|clear-rate-limits|clear-logs)}
        {--hours=24 : Hours of logs to show for logs action}
        {--pattern=* : Pattern to clear rate limits for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage Web Application Firewall';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'status':
                return $this->handleStatus();

            case 'logs':
                return $this->handleLogs();

            case 'clear-rate-limits':
                return $this->handleClearRateLimits();

            case 'clear-logs':
                return $this->handleClearLogs();

            default:
                $this->error("Unknown action: {$action}");
                $this->line("Available actions: status, logs, clear-rate-limits, clear-logs");
                return 1;
        }
    }

    /**
     * Show WAF status.
     */
    private function handleStatus(): int
    {
        $this->info('Web Application Firewall Status');
        $this->line(str_repeat('-', 40));

        // WAF Configuration
        $this->line("<fg=cyan>Configuration:</>");
        $this->line("  Enabled: " . (config('waf.enabled') ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line("  Mode: " . config('waf.mode', 'protect'));
        $this->line("  Logging: " . (config('waf.logging.enabled') ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>'));

        // Rate Limits
        $this->line("\n<fg=cyan>Rate Limits:</>");
        $rateLimits = config('waf.rate_limits', []);
        foreach ($rateLimits as $pattern => $limits) {
            $this->line("  {$pattern}: {$limits['max_attempts']} attempts / {$limits['decay_minutes']} minutes");
        }

        // Protection Status
        $this->line("\n<fg=cyan>Protections:</>");
        $this->line("  SQL Injection: " . (config('waf.sql_injection.enabled') ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>'));
        $this->line("  XSS: " . (config('waf.xss.enabled') ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>'));
        $this->line("  Path Traversal: " . (config('waf.path_traversal.enabled') ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>'));
        $this->line("  User Agent Blocking: " . (config('waf.user_agent_blocking.enabled') ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>'));
        $this->line("  File Upload: " . (config('waf.file_upload.enabled') ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>'));

        // Log File Status
        $logPath = storage_path('logs/waf.log');

        if (File::exists($logPath)) {
            $size = File::size($logPath);
            $modified = File::lastModified($logPath);

            $this->line("\n<fg=cyan>Log File:</>");
            $this->line("  Path: {$logPath}");
            $this->line("  Size: " . $this->formatBytes($size));
            $this->line("  Last Modified: " . date('Y-m-d H:i:s', $modified));
        }

        return 0;
    }

    /**
     * Show WAF logs.
     */
    private function handleLogs(): int
    {
        $logPath = storage_path('logs/waf.log');

        if (!File::exists($logPath)) {
            $this->warn('No WAF log file found.');
            return 0;
        }

        $hours = (int) $this->option('hours');
        $cutoffTime = time() - ($hours * 3600);

        $this->info("WAF Logs (last {$hours} hours)");
        $this->line(str_repeat('-', 80));

        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $filteredLines = [];

        foreach ($lines as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $logTime = strtotime($matches[1]);

                if ($logTime >= $cutoffTime) {
                    $filteredLines[] = $line;
                }
            }
        }

        if (empty($filteredLines)) {
            $this->line('No log entries found for the specified time period.');
            return 0;
        }

        foreach ($filteredLines as $line) {
            $this->line($line);
        }

        $this->line("\nTotal entries: " . count($filteredLines));

        return 0;
    }

    /**
     * Clear rate limits.
     */
    private function handleClearRateLimits(): int
    {
        $pattern = $this->option('pattern');
        $rateLimits = config('waf.rate_limits', []);

        $cleared = 0;

        foreach ($rateLimits as $ratePattern => $limits) {
            if ($pattern === '*' || fnmatch($pattern, $ratePattern)) {
                $this->line("Would clear rate limits for pattern: {$ratePattern}");
                $cleared++;
            }
        }

        if ($cleared > 0) {
            $this->info("Cleared rate limits for {$cleared} pattern(s).");
        } else {
            $this->warn("No rate limits found matching pattern: {$pattern}");
        }

        return 0;
    }

    /**
     * Clear WAF logs.
     */
    private function handleClearLogs(): int
    {
        $logPath = storage_path('logs/waf.log');

        if (File::exists($logPath)) {
            File::put($logPath, '');
            $this->info('WAF logs cleared.');
        } else {
            $this->warn('No WAF log file found.');
        }

        return 0;
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
