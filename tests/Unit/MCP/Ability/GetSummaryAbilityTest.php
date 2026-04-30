<?php
/**
 * Tests for SlyMetrics\MCP\Ability\GetSummaryAbility
 *
 * Note: Functions pre-defined in tests/bootstrap.php (get_bloginfo, get_option,
 * get_site_transient, wp_installing) cannot be stubbed via Brain\Monkey because
 * Patchwork requires loading files through its stream filter. Tests rely on the
 * bootstrap defaults for those functions.
 *
 * @package SlyMetrics\Tests\Unit\MCP\Ability
 */

declare( strict_types=1 );

namespace SlyMetrics\Tests\Unit\MCP\Ability;

use Brain\Monkey;
use SlyMetrics\MCP\Ability\GetSummaryAbility;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\MCP\Ability\GetSummaryAbility
 */
class GetSummaryAbilityTest extends TestCase_Base {

    public function test_definition_has_correct_name(): void {
        $def = GetSummaryAbility::definition();
        $this->assertSame( 'metrics/get-summary', $def['name'] );
    }

    public function test_definition_has_mcp_public_flag(): void {
        $def = GetSummaryAbility::definition();
        $this->assertTrue( $def['meta']['mcp']['public'] );
    }

    public function test_definition_has_permission_callback(): void {
        $def = GetSummaryAbility::definition();
        $this->assertIsCallable( $def['permission_callback'] );
    }

    public function test_definition_has_execute_callback(): void {
        $def = GetSummaryAbility::definition();
        $this->assertIsArray( $def['execute_callback'] );
        $this->assertSame( GetSummaryAbility::class, $def['execute_callback'][0] );
        $this->assertSame( 'execute', $def['execute_callback'][1] );
    }

    public function test_definition_input_schema_has_no_required_params(): void {
        $def = GetSummaryAbility::definition();
        $this->assertSame( array(), $def['input_schema']['required'] );
    }

    public function test_definition_output_schema_has_expected_properties(): void {
        $def   = GetSummaryAbility::definition();
        $props = $def['output_schema']['properties'];

        $this->assertArrayHasKey( 'site', $props );
        $this->assertArrayHasKey( 'users', $props );
        $this->assertArrayHasKey( 'posts', $props );
        $this->assertArrayHasKey( 'pages', $props );
        $this->assertArrayHasKey( 'plugins', $props );
        $this->assertArrayHasKey( 'wordpress_version', $props );
        $this->assertArrayHasKey( 'php_version', $props );
    }

    public function test_execute_returns_all_expected_keys(): void {
        Monkey\Functions\stubs(
            array(
                'count_users'    => array( 'total_users' => 5, 'avail_roles' => array( 'administrator' => 1 ) ),
                'wp_count_posts' => (object) array( 'publish' => 10, 'draft' => 2 ),
                'get_plugins'    => array(),
            )
        );

        $result = GetSummaryAbility::execute();

        $this->assertArrayHasKey( 'site', $result );
        $this->assertArrayHasKey( 'users', $result );
        $this->assertArrayHasKey( 'posts', $result );
        $this->assertArrayHasKey( 'pages', $result );
        $this->assertArrayHasKey( 'plugins', $result );
        $this->assertArrayHasKey( 'wordpress_version', $result );
        $this->assertArrayHasKey( 'php_version', $result );
    }

    public function test_execute_user_total_is_integer(): void {
        Monkey\Functions\stubs(
            array(
                'count_users'    => array( 'total_users' => 7, 'avail_roles' => array( 'administrator' => 2, 'editor' => 5 ) ),
                'wp_count_posts' => (object) array( 'publish' => 3, 'draft' => 0 ),
                'get_plugins'    => array(),
            )
        );

        $result = GetSummaryAbility::execute();

        $this->assertSame( 7, $result['users']['total'] );
        $this->assertIsInt( $result['users']['total'] );
    }

    public function test_execute_plugin_counts_sum_correctly(): void {
        // get_option('active_plugins', []) returns [] from bootstrap → active = 0
        $all_plugins = array( 'a/a.php' => array(), 'b/b.php' => array(), 'c/c.php' => array() );

        Monkey\Functions\stubs(
            array(
                'count_users'    => array( 'total_users' => 1, 'avail_roles' => array() ),
                'wp_count_posts' => (object) array( 'publish' => 0, 'draft' => 0 ),
                'get_plugins'    => $all_plugins,
            )
        );

        $result = GetSummaryAbility::execute();

        $this->assertSame( 3, $result['plugins']['total'] );
        $this->assertSame( 0, $result['plugins']['active'] );   // get_option returns []
        $this->assertSame( 3, $result['plugins']['inactive'] ); // 3 - 0 = 3
    }

    public function test_execute_php_version_matches_runtime(): void {
        Monkey\Functions\stubs(
            array(
                'count_users'    => array( 'total_users' => 0, 'avail_roles' => array() ),
                'wp_count_posts' => (object) array(),
                'get_plugins'    => array(),
            )
        );

        $result = GetSummaryAbility::execute();

        $this->assertSame( PHP_VERSION, $result['php_version'] );
    }

    public function test_execute_site_name_is_string(): void {
        Monkey\Functions\stubs(
            array(
                'count_users'    => array( 'total_users' => 0, 'avail_roles' => array() ),
                'wp_count_posts' => (object) array(),
                'get_plugins'    => array(),
            )
        );

        $result = GetSummaryAbility::execute();

        $this->assertIsString( $result['site'] );
    }
}
