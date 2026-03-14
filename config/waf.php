<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WAF Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the Web Application Firewall.
    |
    */

    'enabled' => env('WAF_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | WAF Mode
    |--------------------------------------------------------------------------
    |
    | 'monitor' - Only log violations without blocking
    | 'protect' - Block requests that violate rules
    |
    */

    'mode' => env('WAF_MODE', 'protect'),

    /*
    |--------------------------------------------------------------------------
    | Bypass Tokens
    |--------------------------------------------------------------------------
    |
    | Secret tokens that can bypass WAF protection when provided in headers.
    | Format: 'X-WAF-Bypass: your-secret-token'
    |
    */

    'bypass_tokens' => array_filter(explode(',', env('WAF_BYPASS_TOKENS', ''))),

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist
    |--------------------------------------------------------------------------
    |
    | IP addresses that are exempt from WAF rules.
    |
    */

    'ip_whitelist' => array_filter(explode(',', env('WAF_IP_WHITELIST', ''))),

    /*
    |--------------------------------------------------------------------------
    | IP Blacklist
    |--------------------------------------------------------------------------
    |
    | IP addresses that are always blocked.
    |
    */

    'ip_blacklist' => array_filter(explode(',', env('WAF_IP_BLACKLIST', ''))),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Rules
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for different endpoints.
    | Format: 'endpoint-pattern' => ['max_attempts' => X, 'decay_minutes' => Y]
    |
    */

    'rate_limits' => [
        'api/*' => [
            'max_attempts' => env('WAF_RATE_LIMIT_API', 100),
            'decay_minutes' => 1,
        ],
        'api/auth/*' => [
            'max_attempts' => env('WAF_RATE_LIMIT_AUTH', 10),
            'decay_minutes' => 1,
        ],
        'api/register' => [
            'max_attempts' => env('WAF_RATE_LIMIT_REGISTER', 3),
            'decay_minutes' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SQL Injection Protection
    |--------------------------------------------------------------------------
    |
    | Rules to detect and prevent SQL injection attempts.
    |
    */

    'sql_injection' => [
        'enabled' => true,
        'patterns' => [
            '/union.*select/i',
            '/select.*from/i',
            '/insert.*into/i',
            '/update.*set/i',
            '/delete.*from/i',
            '/drop.*table/i',
            '/or.*1=1/i',
            '/\'.*--/i',
            '/\/\*.*\*\//i',
            '/benchmark\(/i',
            '/sleep\(/i',
            '/waitfor.*delay/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | XSS Protection
    |--------------------------------------------------------------------------
    |
    | Rules to detect and prevent Cross-Site Scripting attacks.
    |
    */

    'xss' => [
        'enabled' => true,
        'patterns' => [
            '/<script/i',
            '/javascript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/onclick=/i',
            '/alert\(/i',
            '/document\./i',
            '/window\./i',
            '/eval\(/i',
            '/expression\(/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Path Traversal Protection
    |--------------------------------------------------------------------------
    |
    | Rules to detect and prevent directory traversal attacks.
    |
    */

    'path_traversal' => [
        'enabled' => true,
        'patterns' => [
            '/\.\.\//',
            '/\.\.\\\/',
            '/\/etc\/passwd/',
            '/\/etc\/shadow/',
            '/\/proc\/self/',
            '/\.env/',
            '/\.git/',
            '/\.ssh/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Protection
    |--------------------------------------------------------------------------
    |
    | Rules for file upload validation.
    |
    */

    'file_upload' => [
        'enabled' => true,
        'max_size' => env('WAF_MAX_UPLOAD_SIZE', 10240), // 10MB in KB
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt',
        ],
        'blocked_extensions' => [
            'php', 'php3', 'php4', 'php5', 'php7', 'phtml',
            'exe', 'bat', 'cmd', 'sh', 'bash', 'py', 'pl',
            'js', 'html', 'htm', 'hta', 'jar', 'war',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Size Limits
    |--------------------------------------------------------------------------
    |
    | Maximum allowed request sizes.
    |
    */

    'request_limits' => [
        'max_post_size' => env('WAF_MAX_POST_SIZE', 10240), // 10MB in KB
        'max_headers_size' => env('WAF_MAX_HEADERS_SIZE', 8192), // 8KB
        'max_uri_length' => env('WAF_MAX_URI_LENGTH', 2048), // 2KB
    ],

    /*
    |--------------------------------------------------------------------------
    | User Agent Blocking
    |--------------------------------------------------------------------------
    |
    | Block requests from suspicious user agents.
    |
    */

    'user_agent_blocking' => [
        'enabled' => true,
        'patterns' => [
            '/sqlmap/i',
            '/nikto/i',
            '/nessus/i',
            '/metasploit/i',
            '/hydra/i',
            '/wget/i',
            '/curl/i',
            '/python-requests/i',
            '/go-http-client/i',
            '/java/i',
            '/libwww-perl/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure WAF logging behavior.
    |
    */

    'logging' => [
        'enabled' => env('WAF_LOGGING_ENABLED', true),
        'channel' => env('WAF_LOG_CHANNEL', 'daily'),
        'level' => env('WAF_LOG_LEVEL', 'warning'),
        'log_blocked_only' => env('WAF_LOG_BLOCKED_ONLY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Configuration
    |--------------------------------------------------------------------------
    |
    | Configure responses for blocked requests.
    |
    */

    'response' => [
        'status_code' => 403,
        'message' => 'Request blocked by Web Application Firewall',
        'include_reason' => env('WAF_RESPONSE_INCLUDE_REASON', false),
    ],

];
