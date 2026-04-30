<?php
/**
 * Tests for SlyMetrics_Plugin::get_client_ip()
 *
 * @package SlyMetrics\Tests\Unit
 */

declare(strict_types=1);

namespace SlyMetrics\Tests\Unit;

/**
 * @covers \SlyMetrics_Plugin
 */
class GetClientIpTest extends TestCase_Base {

    protected function setUp(): void {
        parent::setUp();
        // sanitize_text_field and wp_unslash are pre-stubbed in bootstrap.php
    }

    protected function tearDown(): void {
        // Restore $_SERVER superglobal
        foreach ( ['REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP',
                   'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED',
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_CLIENT_IP'] as $key ) {
            unset( $_SERVER[ $key ] );
        }
        parent::tearDown();
    }

    public function test_returns_remote_addr_when_no_proxy_headers(): void {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.10';

        $ip = self::call_private_method( 'get_client_ip' );
        $this->assertSame( '192.168.1.10', $ip );
    }

    public function test_cloudflare_header_takes_priority_over_remote_addr(): void {
        $_SERVER['REMOTE_ADDR']            = '10.0.0.1';
        $_SERVER['HTTP_CF_CONNECTING_IP']  = '1.2.3.4';

        $ip = self::call_private_method( 'get_client_ip' );
        $this->assertSame( '1.2.3.4', $ip );
    }

    public function test_x_forwarded_for_first_ip_is_used(): void {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10, 10.0.0.1, 172.16.0.1';

        $ip = self::call_private_method( 'get_client_ip' );
        $this->assertSame( '203.0.113.10', $ip );
    }

    public function test_invalid_ip_in_header_falls_through_to_next(): void {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = 'not-an-ip';
        $_SERVER['REMOTE_ADDR']           = '10.0.0.5';

        $ip = self::call_private_method( 'get_client_ip' );
        // CF header is invalid, so should fall back to REMOTE_ADDR
        $this->assertSame( '10.0.0.5', $ip );
    }

    public function test_returns_unknown_when_no_valid_ip_found(): void {
        $_SERVER['REMOTE_ADDR'] = 'not-valid';

        $ip = self::call_private_method( 'get_client_ip' );
        $this->assertSame( 'unknown', $ip );
    }

    public function test_ipv6_address_is_accepted(): void {
        $_SERVER['REMOTE_ADDR'] = '::1';

        $ip = self::call_private_method( 'get_client_ip' );
        $this->assertSame( '::1', $ip );
    }

    public function test_x_real_ip_is_used_when_cf_missing(): void {
        $_SERVER['HTTP_X_REAL_IP'] = '198.51.100.5';
        $_SERVER['REMOTE_ADDR']    = '10.0.0.1';

        $ip = self::call_private_method( 'get_client_ip' );
        $this->assertSame( '198.51.100.5', $ip );
    }
}
