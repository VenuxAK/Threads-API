<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WebApplicationFirewall
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if WAF is disabled
        if (! config('waf.enabled')) {
            return $next($request);
        }

        // Check for bypass token
        if ($this->hasValidBypassToken($request)) {
            return $next($request);
        }

        // Check IP whitelist/blacklist
        $ipCheck = $this->checkIpAddress($request);
        if ($ipCheck !== null) {
            return $ipCheck;
        }

        // Apply rate limiting
        $rateLimitCheck = $this->checkRateLimits($request);
        if ($rateLimitCheck !== null) {
            return $rateLimitCheck;
        }

        // Check for malicious patterns
        $patternChecks = [
            'sql_injection' => $this->checkSqlInjection($request),
            'xss' => $this->checkXss($request),
            'path_traversal' => $this->checkPathTraversal($request),
            'user_agent' => $this->checkUserAgent($request),
        ];

        foreach ($patternChecks as $checkType => $result) {
            if ($result !== null) {
                $this->logViolation($request, $checkType, $result['pattern']);

                if (config('waf.mode') === 'protect') {
                    return $this->blockResponse($checkType, $result['pattern']);
                }
            }
        }

        // Check request size limits
        $sizeCheck = $this->checkRequestSize($request);
        if ($sizeCheck !== null) {
            $this->logViolation($request, 'request_size', 'Request too large');

            if (config('waf.mode') === 'protect') {
                return $this->blockResponse('request_size', 'Request exceeds size limits');
            }
        }

        // Check file uploads if present
        if ($request->hasFile('file') || $request->hasFile('files')) {
            $uploadCheck = $this->checkFileUploads($request);
            if ($uploadCheck !== null) {
                $this->logViolation($request, 'file_upload', $uploadCheck['reason']);

                if (config('waf.mode') === 'protect') {
                    return $this->blockResponse('file_upload', $uploadCheck['reason']);
                }
            }
        }

        return $next($request);
    }

    /**
     * Check if request has valid bypass token.
     */
    private function hasValidBypassToken(Request $request): bool
    {
        $bypassTokens = config('waf.bypass_tokens', []);
        if (empty($bypassTokens)) {
            return false;
        }

        $token = $request->header('X-WAF-Bypass');

        return $token && in_array($token, $bypassTokens, true);
    }

    /**
     * Check IP address against whitelist and blacklist.
     */
    private function checkIpAddress(Request $request): ?Response
    {
        $clientIp = $request->ip();

        // Check blacklist first
        $blacklist = config('waf.ip_blacklist', []);
        if (in_array($clientIp, $blacklist, true)) {
            $this->logViolation($request, 'ip_blacklist', $clientIp);

            return $this->blockResponse('ip_blacklist', 'IP address is blacklisted');
        }

        // Check whitelist
        $whitelist = config('waf.ip_whitelist', []);
        if (! empty($whitelist) && ! in_array($clientIp, $whitelist, true)) {
            $this->logViolation($request, 'ip_not_whitelisted', $clientIp);

            if (config('waf.mode') === 'protect') {
                return $this->blockResponse('ip_not_whitelisted', 'IP address not in whitelist');
            }
        }

        return null;
    }

    /**
     * Apply rate limiting based on configuration.
     */
    private function checkRateLimits(Request $request): ?Response
    {
        $rateLimits = config('waf.rate_limits', []);
        $path = $request->path();

        foreach ($rateLimits as $pattern => $limits) {
            if (Str::is($pattern, $path)) {
                $key = 'waf:'.$pattern.':'.$request->ip();

                if (RateLimiter::tooManyAttempts($key, $limits['max_attempts'])) {
                    $seconds = RateLimiter::availableIn($key);
                    $this->logViolation($request, 'rate_limit', $pattern);

                    if (config('waf.mode') === 'protect') {
                        return response()->json([
                            'message' => 'Too many requests',
                            'retry_after' => $seconds,
                        ], 429);
                    }
                }

                RateLimiter::hit($key, $limits['decay_minutes'] * 60);
                break;
            }
        }

        return null;
    }

    /**
     * Check for SQL injection patterns.
     */
    private function checkSqlInjection(Request $request): ?array
    {
        if (! config('waf.sql_injection.enabled')) {
            return null;
        }

        $patterns = config('waf.sql_injection.patterns', []);

        return $this->checkPatterns($request, $patterns, 'sql_injection');
    }

    /**
     * Check for XSS patterns.
     */
    private function checkXss(Request $request): ?array
    {
        if (! config('waf.xss.enabled')) {
            return null;
        }

        $patterns = config('waf.xss.patterns', []);

        return $this->checkPatterns($request, $patterns, 'xss');
    }

    /**
     * Check for path traversal patterns.
     */
    private function checkPathTraversal(Request $request): ?array
    {
        if (! config('waf.path_traversal.enabled')) {
            return null;
        }

        $patterns = config('waf.path_traversal.patterns', []);

        return $this->checkPatterns($request, $patterns, 'path_traversal');
    }

    /**
     * Check for suspicious user agents.
     */
    private function checkUserAgent(Request $request): ?array
    {
        if (! config('waf.user_agent_blocking.enabled')) {
            return null;
        }

        $userAgent = $request->userAgent() ?? '';
        $patterns = config('waf.user_agent_blocking.patterns', []);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return [
                    'type' => 'user_agent',
                    'pattern' => $pattern,
                    'value' => $userAgent,
                ];
            }
        }

        return null;
    }

    /**
     * Check request size against limits.
     */
    private function checkRequestSize(Request $request): ?array
    {
        $limits = config('waf.request_limits', []);

        // Check POST size
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $contentLength = $request->header('Content-Length', 0);
            $maxPostSize = $limits['max_post_size'] ?? 10240; // Default 10MB in KB

            if ($contentLength > ($maxPostSize * 1024)) {
                return [
                    'type' => 'request_size',
                    'reason' => 'POST size exceeds limit',
                ];
            }
        }

        // Check URI length
        $uriLength = strlen($request->getRequestUri());
        $maxUriLength = $limits['max_uri_length'] ?? 2048;

        if ($uriLength > $maxUriLength) {
            return [
                'type' => 'request_size',
                'reason' => 'URI length exceeds limit',
            ];
        }

        return null;
    }

    /**
     * Check file uploads for security.
     */
    private function checkFileUploads(Request $request): ?array
    {
        if (! config('waf.file_upload.enabled')) {
            return null;
        }

        $config = config('waf.file_upload', []);
        $files = $request->allFiles();

        foreach ($files as $fileOrArray) {
            // Handle both single file and array of files
            $fileList = is_array($fileOrArray) ? $fileOrArray : [$fileOrArray];

            foreach ($fileList as $file) {
                // Check file size
                $maxSize = ($config['max_size'] ?? 10240) * 1024; // Convert KB to bytes
                if ($file->getSize() > $maxSize) {
                    return [
                        'type' => 'file_upload',
                        'reason' => 'File size exceeds limit',
                    ];
                }

                // Check file extension
                $extension = strtolower($file->getClientOriginalExtension());
                $blockedExtensions = $config['blocked_extensions'] ?? [];

                if (in_array($extension, $blockedExtensions, true)) {
                    return [
                        'type' => 'file_upload',
                        'reason' => 'File extension is blocked',
                    ];
                }

                // Check allowed extensions if specified
                $allowedExtensions = $config['allowed_extensions'] ?? [];
                if (! empty($allowedExtensions) && ! in_array($extension, $allowedExtensions, true)) {
                    return [
                        'type' => 'file_upload',
                        'reason' => 'File extension not allowed',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Check request data against patterns.
     */
    private function checkPatterns(Request $request, array $patterns, string $type): ?array
    {
        // Check all request data
        $dataToCheck = array_merge(
            $request->all(),
            $request->headers->all(),
            ['uri' => $request->getRequestUri()]
        );

        foreach ($dataToCheck as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }

            $value = (string) $value;

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return [
                        'type' => $type,
                        'pattern' => $pattern,
                        'key' => $key,
                        'value' => substr($value, 0, 100), // Log only first 100 chars
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Log WAF violations.
     */
    private function logViolation(Request $request, string $violationType, string $details): void
    {
        if (! config('waf.logging.enabled')) {
            return;
        }

        $logData = [
            'type' => $violationType,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'user_agent' => $request->userAgent(),
            'details' => $details,
            'timestamp' => now()->toISOString(),
        ];

        $level = config('waf.logging.level', 'warning');
        $channel = config('waf.logging.channel', 'daily');

        Log::channel($channel)->$level('WAF Violation', $logData);
    }

    /**
     * Generate block response.
     */
    private function blockResponse(string $violationType, string $details): Response
    {
        $responseConfig = config('waf.response', []);
        $statusCode = $responseConfig['status_code'] ?? 403;
        $message = $responseConfig['message'] ?? 'Request blocked by Web Application Firewall';

        $responseData = ['message' => $message];

        if ($responseConfig['include_reason'] ?? false) {
            $responseData['reason'] = $violationType;
            $responseData['details'] = $details;
        }

        return response()->json($responseData, $statusCode);
    }
}
