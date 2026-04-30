<?php
/**
 * Tests for SlyMetrics\MCP\Ability\GetPluginsAbility
 *
 * Note: Functions pre-defined in tests/bootstrap.php (get_option, get_site_transient)
 * cannot be stubbed via Brain\Monkey. Tests rely on bootstrap defaults:
 *   get_option('active_plugins', []) → [] (0 active plugins)
 *   get_site_transient('update_plugins') → false (no updates)
 *
 * @package SlyMetrics\Tests\Unit\MCP\Ability
 */

declare( strict_types=1 );

namespace SlyMetrics\Tests\Unit\MCP\Ability;

use Brain\Monkey;
use SlyMetrics\MCP\Ability\GetPluginsAbility;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\MCP\Ability\GetPluginsAbility
 */
class GetPluginsAbilityTest extends TestCase_Base {

    public function test_definition_has_correct_name(): void {
        $def = GetPluginsAbility::definition();
        $this->assertSame( 'metrics/get-plugins', $def['name'] );
    }

    public function test_definition_has_label(): void {
        $def = GetPluginsAbility::definition();
        $this->assertArrayHasKey( 'label', $def );
        $this->assertNotEmpty( $def['label'] );
    }

    public function test_definition_has_category(): void {
        $def = GetPluginsAbility::definition();
        $this->assertArrayHasKey( 'category', $def );
        $this->assertNotEmpty( $def['category'] );
    }

    public function test_definition_has_mcp_public_flag(): void {
        $def = GetPluginsAbility::definition();
        $this->assertTrue( $def['meta']['mcp']['public'] );
    }

    public function test_definition_has_no_required_params(): void {
        $def = GetPluginsAbility::definition();
        $this->assertSame( array(), $def['input_schema']['required'] );
    }

    public function test_definition_output_schema_has_plugins_and_themes(): void {
        $def   = GetPluginsAbility::definition();
        $props = $def['output_schema']['properties'];

        $this->assertArrayHasKey( 'plugins', $props );
        $this->assertArrayHasKey( 'themes', $props );
    }

    public function test_execute_returns_plugins_and_themes_keys(): void {
        // get_option returns [] (active = 0), get_site_transient returns false (no updates)
        Monkey\Functions\stubs(
            array(
                'get_plugins'   => array(),
                'wp_get_themes' => array(),
            )
        );

        $result = GetPluginsAbility::execute();

        $this->assertArrayHasKey( 'plugins', $result );
        $this->assertArrayHasKey( 'themes', $result );
    }

    public function test_execute_plugin_total_matches_get_plugins_count(): void {
        $all_plugins = array( 'a/a.php' => array(), 'b/b.php' => array(), 'c/c.php' => array() );

        Monkey\Functions\stubs(
            array(
                'get_plugins'   => $all_plugins,
                'wp_get_themes' => array(),
            )
        );

        $result = GetPluginsAbility::execute();

        // get_option returns [] → active = 0, inactive = 3, total = 3
        $this->assertSame( 3, $result['plugins']['total'] );
        $this->assertSame( 0, $result['plugins']['active'] );
        $this->assertSame( 3, $result['plugins']['inactive'] );
    }

    public function test_execute_inactive_count_is_never_negative(): void {
        Monkey\Functions\stubs(
            array(
                'get_plugins'   => array(),
                'wp_get_themes' => array(),
            )
        );

        $result = GetPluginsAbility::execute();

        $this->assertGreaterThanOrEqual( 0, $result['plugins']['inactive'] );
    }

    public function test_execute_updates_available_is_zero_without_site_transient(): void {
        // get_site_transient returns false from bootstrap → no updates
        Monkey\Functions\stubs(
            array(
                'get_plugins'   => array( 'a/a.php' => array(), 'b/b.php' => array() ),
                'wp_get_themes' => array(),
            )
        );

        $result = GetPluginsAbility::execute();

        $this->assertSame( 0, $result['plugins']['updates_available'] );
    }

    public function test_execute_themes_section_has_expected_keys(): void {
        Monkey\Functions\stubs(
            array(
                'get_plugins'   => array(),
                'wp_get_themes' => array(),
            )
        );

        $result = GetPluginsAbility::execute();

        $this->assertArrayHasKey( 'total', $result['themes'] );
        $this->assertArrayHasKey( 'parent', $result['themes'] );
        $this->assertArrayHasKey( 'child', $result['themes'] );
    }
}
