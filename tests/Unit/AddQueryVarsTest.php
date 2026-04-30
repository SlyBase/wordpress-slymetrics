<?php
/**
 * Tests for SlyMetrics_Plugin::add_query_vars()
 *
 * @package SlyMetrics\Tests\Unit
 */

declare(strict_types=1);

namespace SlyMetrics\Tests\Unit;

/**
 * @covers \SlyMetrics_Plugin
 */
class AddQueryVarsTest extends TestCase_Base {

    public function test_slymetrics_endpoint_is_added(): void {
        $result = \SlyMetrics_Plugin::add_query_vars( [] );
        $this->assertContains( 'slymetrics_endpoint', $result );
    }

    public function test_slymetrics_param_is_added(): void {
        $result = \SlyMetrics_Plugin::add_query_vars( [] );
        $this->assertContains( 'slymetrics', $result );
    }

    public function test_existing_vars_are_preserved(): void {
        $existing = ['custom_var', 'another_var'];
        $result   = \SlyMetrics_Plugin::add_query_vars( $existing );

        $this->assertContains( 'custom_var', $result );
        $this->assertContains( 'another_var', $result );
    }

    public function test_returns_array(): void {
        $result = \SlyMetrics_Plugin::add_query_vars( [] );
        $this->assertIsArray( $result );
    }
}
