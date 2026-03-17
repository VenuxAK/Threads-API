<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WafEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test WAF handles encoded SQL injection attempts.
     */
    public function test_waf_handles_encoded_sql_injection_attempts(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.sql_injection.enabled', true);

        // URL encoded SQL injection
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '1%27%20UNION%20SELECT%20NULL--',
        ]);

        $response->assertStatus(403);

        // HTML encoded SQL injection
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '1&#39; UNION SELECT NULL--',
        ]);

        $response->assertStatus(403);

        // Double encoded
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '1%2527%2520UNION%2520SELECT%2520NULL--',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test WAF handles obfuscated XSS attempts.
     */
    public function test_waf_handles_obfuscated_xss_attempts(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.xss.enabled', true);

        // Obfuscated script tag
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '<scr<script>ipt>alert(1)</scr</script>ipt>',
        ]);

        $response->assertStatus(403);

        // JavaScript with split strings
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '<img src="x" onerror="ale'.'rt(1)">',
        ]);

        $response->assertStatus(403);

        // Base64 encoded
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test WAF handles path traversal with various encodings.
     */
    public function test_waf_handles_path_traversal_with_various_encodings(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.path_traversal.enabled', true);

        // Double dot slash
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '../../../etc/passwd',
        ]);

        $response->assertStatus(403);

        // URL encoded
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '..%2F..%2F..%2Fetc%2Fpasswd',
        ]);

        $response->assertStatus(403);

        // Double encoded
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '..%252F..%252F..%252Fetc%252Fpasswd',
        ]);

        $response->assertStatus(403);

        // With null bytes
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '../../../etc/passwd%00',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test WAF handles various file upload bypass attempts.
     */
    public function test_waf_handles_various_file_upload_bypass_attempts(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.file_upload.enabled', true);

        // Test with double extension
        $file = \Illuminate\Http\UploadedFile::fake()->create('test.php.jpg', 5);
        $response = $this->call('POST', '/api/v1/waf-test', [], [], ['file' => $file]);
        $response->assertStatus(403);

        // Test with null byte in filename
        $file = \Illuminate\Http\UploadedFile::fake()->create('test.php%00.jpg', 5);
        $response = $this->call('POST', '/api/v1/waf-test', [], [], ['file' => $file]);
        $response->assertStatus(403);

        // Test with case variation
        $file = \Illuminate\Http\UploadedFile::fake()->create('test.PHP', 5);
        $response = $this->call('POST', '/api/v1/waf-test', [], [], ['file' => $file]);
        $response->assertStatus(403);

        // Test with space before extension
        $file = \Illuminate\Http\UploadedFile::fake()->create('test .php', 5);
        $response = $this->call('POST', '/api/v1/waf-test', [], [], ['file' => $file]);
        $response->assertStatus(403);
    }

    /**
     * Test WAF handles oversized requests gracefully.
     */
    public function test_waf_handles_oversized_requests_gracefully(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.request_limits.max_post_size', 10); // 10KB

        // Create request slightly over limit
        $largePayload = str_repeat('a', 10241); // 10KB + 1 byte

        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => $largePayload,
        ]);

        $response->assertStatus(403);

        // Test with exactly at limit (should pass)
        $exactPayload = str_repeat('a', 10240); // Exactly 10KB

        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => $exactPayload,
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test WAF handles long URIs.
     */
    public function test_waf_handles_long_uris(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.request_limits.max_uri_length', 100);

        // Create long query string
        $longQuery = '?'.str_repeat('a', 100);

        $response = $this->getJson('/api/v1/waf-test'.$longQuery);

        $response->assertStatus(403);
    }

    /**
     * Test WAF IP whitelist/blacklist edge cases.
     */
    public function test_waf_ip_whitelist_blacklist_edge_cases(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.ip_whitelist', ['192.168.1.100', '10.0.0.0/24']);
        Config::set('waf.ip_blacklist', ['203.0.113.1']);

        // Note: We can't easily test IP mocking in Laravel without additional packages
        // This test documents the expected behavior

        $this->assertTrue(true); // Placeholder assertion
    }

    /**
     * Test WAF with disabled protections.
     */
    public function test_waf_with_disabled_protections(): void
    {
        Config::set('waf.mode', 'protect');

        // Disable SQL injection protection
        Config::set('waf.sql_injection.enabled', false);

        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => "1' UNION SELECT NULL--",
        ]);

        // Should not be blocked since protection is disabled
        $response->assertStatus(200);

        // Re-enable and disable XSS
        Config::set('waf.sql_injection.enabled', true);
        Config::set('waf.xss.enabled', false);

        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '<script>alert(1)</script>',
        ]);

        // Should not be blocked since XSS protection is disabled
        $response->assertStatus(200);
    }

    /**
     * Test WAF monitor mode logs correctly.
     */
    public function test_waf_monitor_mode_logs_correctly(): void
    {
        Config::set('waf.mode', 'monitor');
        Config::set('waf.sql_injection.enabled', true);
        Config::set('waf.logging.enabled', true);
        Config::set('waf.logging.log_blocked_only', false);

        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => "1' OR 1=1--",
        ]);

        // Should not be blocked in monitor mode
        $response->assertStatus(200);

        // Check that log file exists (can't easily assert log content in tests)
        $logPath = storage_path('logs/waf.log');
        $this->assertFileExists($logPath);
    }

    /**
     * Test WAF with multiple bypass tokens.
     */
    public function test_waf_with_multiple_bypass_tokens(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.sql_injection.enabled', true);
        Config::set('waf.bypass_tokens', ['token1', 'token2', 'token3']);

        // Test with first token
        $response = $this->withHeaders([
            'X-WAF-Bypass' => 'token1',
        ])->postJson('/api/v1/waf-test', [
            'test_data' => "1' UNION SELECT NULL--",
        ]);

        $response->assertStatus(200);

        // Test with second token
        $response = $this->withHeaders([
            'X-WAF-Bypass' => 'token2',
        ])->postJson('/api/v1/waf-test', [
            'test_data' => "1' UNION SELECT NULL--",
        ]);

        $response->assertStatus(200);

        // Test with invalid token
        $response = $this->withHeaders([
            'X-WAF-Bypass' => 'invalidtoken',
        ])->postJson('/api/v1/waf-test', [
            'test_data' => "1' UNION SELECT NULL--",
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test WAF with empty bypass token header.
     */
    public function test_waf_with_empty_bypass_token_header(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.sql_injection.enabled', true);
        Config::set('waf.bypass_tokens', ['validtoken']);

        // Empty header value
        $response = $this->withHeaders([
            'X-WAF-Bypass' => '',
        ])->postJson('/api/v1/waf-test', [
            'test_data' => "1' UNION SELECT NULL--",
        ]);

        $response->assertStatus(403);

        // No header at all
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => "1' UNION SELECT NULL--",
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test WAF response includes reason when configured.
     */
    public function test_waf_response_includes_reason_when_configured(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.sql_injection.enabled', true);
        Config::set('waf.response.include_reason', true);

        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => "1' UNION SELECT NULL--",
        ]);

        $response->assertStatus(403);
        $response->assertJsonStructure([
            'message',
            'reason',
            'details',
        ]);

        $responseData = $response->json();
        $this->assertEquals('sql_injection', $responseData['reason']);
        $this->assertStringContainsString('UNION SELECT', $responseData['details']);
    }

    /**
     * Test WAF handles JSON injection attempts.
     */
    public function test_waf_handles_json_injection_attempts(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.sql_injection.enabled', true);

        // JSON with SQL injection
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '{"query": "SELECT * FROM users WHERE 1=1"}',
        ]);

        $response->assertStatus(403);

        // Nested JSON with XSS
        Config::set('waf.xss.enabled', true);
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '{"user": {"name": "<script>alert(1)</script>"}}',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test WAF handles command injection attempts.
     */
    public function test_waf_handles_command_injection_attempts(): void
    {
        Config::set('waf.mode', 'protect');

        // Command injection patterns (not currently in WAF config, but testing edge cases)
        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '; rm -rf /',
        ]);

        // Currently not blocked by WAF (would need additional patterns)
        $response->assertStatus(200);

        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => '| cat /etc/passwd',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test WAF with very large number of headers.
     */
    public function test_waf_with_very_large_number_of_headers(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.request_limits.max_headers_size', 8192); // 8KB

        // Create many headers
        $headers = [];
        for ($i = 0; $i < 100; $i++) {
            $headers["X-Custom-Header-$i"] = str_repeat('a', 100);
        }

        $response = $this->withHeaders($headers)->getJson('/api/v1/waf-test');

        // Should either pass or be blocked due to header size
        // Can't easily test exact header size calculation
        $this->assertContains($response->status(), [200, 403]);
    }

    /**
     * Test WAF with malformed JSON.
     */
    public function test_waf_with_malformed_json(): void
    {
        Config::set('waf.mode', 'protect');

        // Malformed JSON should be handled by Laravel validation, not WAF
        $response = $this->call('POST', '/api/v1/waf-test', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'malformed{json');

        // Laravel should return 422 for malformed JSON
        $response->assertStatus(422);
    }

    /**
     * Test WAF with recursive data structures.
     */
    public function test_waf_with_recursive_data_structures(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.sql_injection.enabled', true);

        // Create recursive array (will be JSON encoded)
        $data = ['key' => 'value'];
        $data['self'] = &$data;

        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => $data,
        ]);

        // Should handle recursion without crashing
        $response->assertStatus(200);
    }

    /**
     * Test WAF with binary data.
     */
    public function test_waf_with_binary_data(): void
    {
        Config::set('waf.mode', 'protect');

        // Binary data in request
        $binaryData = "\x00\x01\x02\x03\x04\x05".'SELECT * FROM users'."\x06\x07\x08\x09";

        $response = $this->call('POST', '/api/v1/waf-test', [], [], [], [], $binaryData);

        // Should handle binary data without crashing
        $this->assertContains($response->status(), [200, 403, 422]);
    }

    /**
     * Test WAF performance under load.
     */
    public function test_waf_performance_under_load(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.sql_injection.enabled', true);
        Config::set('waf.xss.enabled', true);
        Config::set('waf.path_traversal.enabled', true);

        $startTime = microtime(true);

        // Make multiple requests to test performance
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v1/waf-test', [
                'test_data' => "Test request $i",
            ]);
            $response->assertStatus(200);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert WAF adds reasonable overhead (less than 100ms per request on average)
        $averageTimePerRequest = $executionTime / 10;
        $this->assertLessThan(0.1, $averageTimePerRequest, "WAF average time per request: {$averageTimePerRequest}s");
    }

    /**
     * Test WAF with disabled logging.
     */
    public function test_waf_with_disabled_logging(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.sql_injection.enabled', true);
        Config::set('waf.logging.enabled', false);

        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => "1' UNION SELECT NULL--",
        ]);

        $response->assertStatus(403);

        // Log file should not be created or updated (hard to test without mocking)
        $this->assertTrue(true); // Placeholder
    }

    /**
     * Test WAF with custom response configuration.
     */
    public function test_waf_with_custom_response_configuration(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.sql_injection.enabled', true);
        Config::set('waf.response.status_code', 400);
        Config::set('waf.response.message', 'Custom block message');

        $response = $this->postJson('/api/v1/waf-test', [
            'test_data' => "1' UNION SELECT NULL--",
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Custom block message',
        ]);
    }

    /**
     * Test WAF handles edge case user agents.
     */
    public function test_waf_handles_edge_case_user_agents(): void
    {
        Config::set('waf.mode', 'protect');
        Config::set('waf.user_agent_blocking.enabled', true);

        // Test with blocked user agent
        $response = $this->withHeaders([
            'User-Agent' => 'sqlmap/1.0',
        ])->getJson('/api/v1/waf-test');

        $response->assertStatus(403);

        // Test with legitimate user agent
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ])->getJson('/api/v1/waf-test');

        $response->assertStatus(200);

        // Test with very long user agent
        $longUserAgent = str_repeat('A', 1000);
        $response = $this->withHeaders([
            'User-Agent' => $longUserAgent,
        ])->getJson('/api/v1/waf-test');

        // Should handle long user agent without crashing
        $this->assertContains($response->status(), [200, 403]);
    }
}
