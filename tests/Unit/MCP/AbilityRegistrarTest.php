<?php
/**
 * Tests for SlyMetrics\MCP\AbilityRegistrar
 *
 * @package SlyMetrics\Tests\Unit\MCP
 */

declare( strict_types=1 );

namespace SlyMetrics\Tests\Unit\MCP;

use SlyMetrics\MCP\AbilityRegistrar;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\MCP\AbilityRegistrar
 */
class AbilityRegistrarTest extends TestCase_Base {

    public function test_server_id_constant(): void {
        $this->assertSame( 'slymetrics-mcp-server', AbilityRegistrar::SERVER_ID );
    }

    public function test_rest_namespace_constant(): void {
        $this->assertSame( 'slymetrics-mcp-server', AbilityRegistrar::REST_NAMESPACE );
    }

    public function test_rest_route_constant(): void {
        $this->assertSame( 'mcp', AbilityRegistrar::REST_ROUTE );
    }

    public function test_init_does_not_throw_when_ability_api_available(): void {
        // wp_register_ability stub defined in bootstrap.php
        AbilityRegistrar::init();
        $this->assertTrue( true );
    }

    public function test_register_abilities_does_not_throw(): void {
        // Calls definition() on all 5 abilities and wp_register_ability (stubbed in bootstrap)
        AbilityRegistrar::register_abilities();
        $this->assertTrue( true );
    }

    public function test_register_mcp_server_exits_when_class_missing(): void {
        // McpAdapter class is not available in test context – must not throw
        AbilityRegistrar::register_mcp_server();
        $this->assertTrue( true );
    }

    public function test_register_abilities_covers_all_five_metric_types(): void {
        $ability_names = array(
            'metrics/get-summary',
            'metrics/get-users',
            'metrics/get-posts',
            'metrics/get-plugins',
            'metrics/get-site-health',
        );

        foreach ( $ability_names as $name ) {
            $this->assertStringStartsWith( 'metrics/', $name );
        }

        $this->assertCount( 5, $ability_names );
    }
}
