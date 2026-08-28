# Web Application Firewall (WAF) Implementation

## Overview
This document describes the Web Application Firewall (WAF) implementation for the Laravel API project. The WAF provides multiple layers of security protection against common web application attacks.

## Components

### 1. WAF Middleware (`WebApplicationFirewall.php`)
The core WAF middleware that inspects incoming requests and applies security rules.

**Features:**
- IP whitelisting/blacklisting
- Rate limiting per endpoint pattern
- SQL injection detection
- XSS attack detection
- Path traversal prevention
- Suspicious user agent blocking
- File upload validation
- Request size limits

### 2. Security Headers Middleware (`SecurityHeaders.php`)
Adds HTTP security headers to responses for additional protection.

**Headers Added:**
- HSTS (HTTP Strict Transport Security)
- X-Frame-Options
- X-Content-Type-Options
- Referrer-Policy
- Permissions-Policy
- X-XSS-Protection
- Content-Security-Policy (in production)
- Cache-Control for API responses

### 3. Configuration (`config/waf.php`)
Centralized configuration for all WAF settings.

### 4. WAF Service Provider (`WafServiceProvider.php`)
Registers WAF services and provides configuration merging.

### 5. Management Command (`WafManageCommand.php`)
Console command for managing and monitoring the WAF.

## Configuration

### Environment Variables
Copy settings from `config/waf.env.example` to your `.env` file:

```bash
# Enable/Disable WAF
WAF_ENABLED=true

# WAF Mode: 'monitor' (log only) or 'protect' (block)
WAF_MODE=protect

# Bypass tokens (comma-separated)
WAF_BYPASS_TOKENS=

# IP Whitelist (comma-separated)
WAF_IP_WHITELIST=127.0.0.1,::1

# IP Blacklist (comma-separated)
WAF_IP_BLACKLIST=

# Rate Limiting
WAF_RATE_LIMIT_API=100
WAF_RATE_LIMIT_AUTH=10
WAF_RATE_LIMIT_REGISTER=3

# File Upload Limits (in KB)
WAF_MAX_UPLOAD_SIZE=10240

# Request Size Limits
WAF_MAX_POST_SIZE=10240
WAF_MAX_HEADERS_SIZE=8192
WAF_MAX_URI_LENGTH=2048

# Logging
WAF_LOGGING_ENABLED=true
WAF_LOG_CHANNEL=waf
WAF_LOG_LEVEL=warning
WAF_LOG_DAYS=30
WAF_LOG_BLOCKED_ONLY=false

# Response Configuration
WAF_RESPONSE_INCLUDE_REASON=false
```

### Rate Limiting Configuration
Rate limits are configured in `config/waf.php` under the `rate_limits` section:

```php
'rate_limits' => [
    'api/*' => [
        'max_attempts' => 100,  // 100 requests per minute
        'decay_minutes' => 1,
    ],
    'api/auth/*' => [
        'max_attempts' => 10,   // 10 authentication attempts per minute
        'decay_minutes' => 1,
    ],
    'api/register' => [
        'max_attempts' => 3,    // 3 registration attempts per hour
        'decay_minutes' => 60,
    ],
],
```

## Usage

### Console Commands

**View WAF Status:**
```bash
php artisan waf status
```

**View WAF Logs (last 24 hours):**
```bash
php artisan waf logs
```

**View WAF Logs (custom hours):**
```bash
php artisan waf logs --hours=48
```

**Clear Rate Limits:**
```bash
php artisan waf clear-rate-limits
```

**Clear WAF Logs:**
```bash
php artisan waf clear-logs
```

### Bypassing WAF
To bypass WAF protection for testing or maintenance:

1. **Using Bypass Token:**
   Add header to requests:
   ```
   X-WAF-Bypass: your-secret-token
   ```
   Configure tokens in `.env`:
   ```bash
   WAF_BYPASS_TOKENS=token1,token2,token3
   ```

2. **IP Whitelisting:**
   Add IP addresses to `.env`:
   ```bash
   WAF_IP_WHITELIST=192.168.1.100,10.0.0.1
   ```

### Monitoring
WAF logs are stored in `storage/logs/waf.log` and include:
- Timestamp
- IP address
- Request method and URI
- Violation type
- Details of the violation

## Security Rules

### SQL Injection Detection
Patterns that trigger SQL injection detection:
- `UNION SELECT` statements
- `SELECT FROM` patterns
- SQL comments (`--`, `/* */`)
- Database functions (`benchmark()`, `sleep()`)
- Common SQL injection patterns

### XSS Detection
Patterns that trigger XSS detection:
- `<script` tags
- `javascript:` protocol
- Event handlers (`onload=`, `onerror=`, `onclick=`)
- `alert()` calls
- `document.` and `window.` references

### Path Traversal Prevention
Patterns that trigger path traversal detection:
- Directory traversal (`../`, `..\`)
- Sensitive file references (`/etc/passwd`, `/etc/shadow`)
- Configuration files (`.env`, `.git`)

### File Upload Security
- Maximum file size: 10MB (configurable)
- Blocked extensions: `.php`, `.exe`, `.sh`, `.js`, etc.
- Allowed extensions: images, documents, PDFs

## Testing

### Test Mode
Set WAF to monitor mode to test rules without blocking:
```bash
WAF_MODE=monitor
```

### Testing Commands
Use curl to test WAF rules:

```bash
# Test SQL injection detection
curl -X POST http://localhost:8000/api/test \
  -H "Content-Type: application/json" \
  -d '{"query": "SELECT * FROM users WHERE 1=1"}'

# Test XSS detection
curl -X POST http://localhost:8000/api/test \
  -H "Content-Type: application/json" \
  -d '{"input": "<script>alert(1)</script>"}'

# Test rate limiting
for i in {1..11}; do
  curl -X POST http://localhost:8000/api/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@example.com","password":"test"}'
done
```

## Integration with Existing Authentication

The WAF integrates with and enhances the existing authentication system:

1. **Rate Limiting:** Additional rate limits on auth endpoints
2. **IP Protection:** IP-based restrictions for authentication
3. **Request Validation:** Additional validation on auth requests
4. **Logging:** Detailed logging of authentication attempts

## Performance Considerations

1. **Middleware Order:** WAF runs early in the middleware stack to block malicious requests before processing.
2. **Pattern Matching:** Regular expressions are optimized for performance.
3. **Logging:** Logging can be disabled or limited to reduce I/O overhead.
4. **Monitoring Mode:** Use monitor mode in development to test without performance impact.

## Troubleshooting

### Common Issues

1. **False Positives:**
   - Adjust pattern matching in `config/waf.php`
   - Use monitor mode to identify issues
   - Add legitimate patterns to bypass lists

2. **Performance Issues:**
   - Disable logging: `WAF_LOGGING_ENABLED=false`
   - Reduce rate limit checks
   - Use IP whitelisting for trusted sources

3. **Blocking Legitimate Requests:**
   - Check WAF logs for violation details
   - Adjust request size limits
   - Review file upload restrictions

### Log Analysis
WAF logs use the following format:
```
[YYYY-MM-DD HH:MM:SS] WAF.LEVEL: WAF Violation {"type":"violation_type","ip":"client_ip",...}
```

## Maintenance

### Regular Tasks
1. **Review Logs:** Regularly check `storage/logs/waf.log`
2. **Update Rules:** Keep pattern matching rules updated
3. **Monitor Performance:** Watch for performance impacts
4. **Update IP Lists:** Maintain current IP whitelist/blacklist

### Security Updates
1. **Pattern Updates:** Regularly update attack patterns
2. **Dependency Updates:** Keep Laravel and dependencies updated
3. **Configuration Review:** Periodically review WAF configuration

## References

- [OWASP Web Application Firewall](https://owasp.org/www-project-web-application-firewall/)
- [Laravel Security Documentation](https://laravel.com/docs/12.x/authentication)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Mozilla Security Guidelines](https://infosec.mozilla.org/guidelines/web_security)
```
