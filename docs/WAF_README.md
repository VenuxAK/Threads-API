# Web Application Firewall (WAF) Implementation

## Overview

This Laravel application includes a comprehensive Web Application Firewall (WAF) that provides multiple layers of security protection for your API. The WAF is implemented as middleware and includes various security features to protect against common web attacks.

## Features

### 1. **Attack Detection & Prevention**
- SQL Injection protection
- Cross-Site Scripting (XSS) protection
- Path traversal prevention
- Malicious user agent blocking
- File upload validation

### 2. **Rate Limiting**
- Configurable rate limits for different endpoints
- Separate limits for authentication endpoints
- Registration endpoint protection

### 3. **IP Management**
- IP whitelisting
- IP blacklisting
- Automatic blocking of malicious IPs

### 4. **Security Headers**
- HSTS (HTTP Strict Transport Security)
- X-Frame-Options
- Content-Security-Policy
- X-Content-Type-Options
- Referrer-Policy
- Permissions-Policy

### 5. **Logging & Monitoring**
- Dedicated WAF logging channel
- Detailed violation logging
- Console commands for log management

## Configuration

### Environment Variables

Copy the settings from `config/waf.env.example` to your `.env` file:

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

### Configuration File

The main configuration is in `config/waf.php`. You can customize:
- Attack patterns
- Rate limits
- File upload restrictions
- Response behavior

## Usage

### Console Commands

```bash
# Show WAF status
php artisan waf status

# View WAF logs (last 24 hours)
php artisan waf logs

# View logs for specific time period
php artisan waf logs --hours=48

# Clear rate limits
php artisan waf clear-rate-limits

# Clear WAF logs
php artisan waf clear-logs
```

### Middleware

The WAF middleware is automatically applied to all API routes. You can also apply it manually:

```php
// Apply to specific routes
Route::middleware(['waf'])->group(function () {
    // Your routes
});

// Apply security headers
Route::middleware(['security.headers'])->group(function () {
    // Your routes
});
```

### Bypass Tokens

For development or emergency access, you can use bypass tokens:

```bash
# In your .env file
WAF_BYPASS_TOKENS=your-secret-token-here,another-token

# In your request headers
X-WAF-Bypass: your-secret-token-here
```

## Security Features Details

### 1. SQL Injection Protection
Detects common SQL injection patterns including:
- UNION SELECT statements
- DROP TABLE commands
- OR 1=1 conditions
- Comment-based attacks

### 2. XSS Protection
Blocks common XSS attack vectors:
- `<script>` tags
- JavaScript: URLs
- Event handlers (onload, onerror, onclick)
- Alert() and eval() functions

### 3. File Upload Security
- Validates file extensions
- Enforces size limits
- Blocks dangerous file types (PHP, EXE, etc.)

### 4. Rate Limiting
- API endpoints: 100 requests/minute
- Authentication: 10 requests/minute
- Registration: 3 requests/hour

## Monitoring & Logging

### Log Location
WAF logs are stored in: `storage/logs/waf.log`

### Log Format
```json
{
  "type": "sql_injection",
  "ip": "192.168.1.100",
  "method": "POST",
  "uri": "/api/login",
  "user_agent": "Mozilla/5.0...",
  "details": "/union.*select/i",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Monitoring Commands
```bash
# Tail WAF logs
tail -f storage/logs/waf.log

# Count violations by type
grep -c "type.*sql_injection" storage/logs/waf.log

# Show recent violations
tail -n 50 storage/logs/waf.log
```

## Testing

### Test Mode
Set `WAF_MODE=monitor` in development to log violations without blocking requests.

### Testing Commands
```bash
# Test SQL injection protection
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test","password":"\' OR 1=1--"}'

# Test rate limiting
for i in {1..11}; do
  curl -X POST http://localhost/api/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@test.com","password":"password"}'
done
```

## Performance Considerations

- The WAF adds minimal overhead (1-5ms per request)
- Rate limiting uses Laravel's built-in rate limiter
- Pattern matching is optimized for performance
- Logging can be disabled in high-traffic environments

## Troubleshooting

### Common Issues

1. **False Positives**
   - Adjust patterns in `config/waf.php`
   - Use bypass tokens for testing
   - Switch to monitor mode temporarily

2. **Performance Issues**
   - Disable logging: `WAF_LOGGING_ENABLED=false`
   - Increase rate limits
   - Review attack patterns

3. **Blocked Legitimate Requests**
   - Check WAF logs for details
   - Add IP to whitelist
   - Adjust request size limits

### Debugging

```bash
# Enable detailed logging
WAF_LOG_LEVEL=debug
WAF_LOG_BLOCKED_ONLY=false

# Check middleware order
php artisan route:list --middleware

# Verify configuration
php artisan config:show waf
```

## Security Best Practices

1. **Regular Updates**
   - Review and update attack patterns regularly
   - Monitor WAF logs for new attack vectors
   - Keep Laravel and dependencies updated

2. **Monitoring**
   - Set up alerts for high violation rates
   - Monitor rate limit hits
   - Review blocked requests weekly

3. **Configuration**
   - Use different bypass tokens in production
   - Regularly update IP blacklists
   - Adjust limits based on traffic patterns

## Integration with Existing Security

The WAF complements existing Laravel security features:
- Laravel Sanctum for authentication
- Laravel CORS for cross-origin protection
- Laravel Rate Limiting for additional controls
- Laravel Validation for input sanitization

## Support

For issues or questions:
1. Check WAF logs for error details
2. Review configuration settings
3. Test in monitor mode first
4. Consult Laravel security documentation

## License

This WAF implementation is part of the application and follows the same licensing terms.
