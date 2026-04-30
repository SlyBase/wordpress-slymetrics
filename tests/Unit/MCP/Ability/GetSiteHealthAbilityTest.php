<?php
/**
 * Tests for SlyMetrics\MCP\Ability\GetSiteHealthAbility
 *
 * Note: Functions pre-defined in tests/bootstrap.php (get_bloginfo, wp_installing)
 * cannot be stubbed. Bootstrap defaults:
 *   get_bloginfo('version') → '6.5'
 *   wp_installing() → false
 *
 * @package SlyMetrics\Tests\Unit\MCP\Ability
 */

declare( strict_types=1 );

namespace SlyMetrics\Tests\Unit\MCP\Ability;

use Brain\Monkey;
use SlyMetrics\MCP\Ability\GetSiteHealthAbility;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\MCP\Ability\GetSiteHealthAbility
 */
class GetSiteHealthAbilityTest extends TestCase_Base {

    public function test_definition_has_correct_name(): void {
        $def = GetSiteHealthAbility::definition();
        $this->assertSame( 'metrics/get-site-health', $def['name'] );
    }

    public function test_definition_has_mcp_public_flag(): void {
        $def = GetSiteHealthAbility::definition();
        $this->assertTrue( $def['meta']['mcp']['public'] );
    }

    public function test_definition_has_no_required_params(): void {
        $def = GetSiteHealthAbility::definition();
        $this->assertSame( array(), $def['input_schema']['required'] );
    }

    public function test_definition_output_schema_has_all_sections(): void {
        $def   = GetSiteHealthAbility::definition();
        $props = $def['output_schema']['properties'];

        $this->assertArrayHasKey( 'wordpress', $props );
        $this->assertArrayHasKey( 'php', $props );
        $this->assertArrayHasKey( 'database', $props );
        $this->assertArrayHasKey( 'debug', $props );
    }

    public function test_execute_returns_all_expected_sections(): void {
        Monkey\Functions\stubs(
            array( 'get_core_updates' => array() )
        );

        $result = GetSiteHealthAbility::execute();

        $this->assertArrayHasKey( 'wordpress', $result );
        $this->assertArrayHasKey( 'php', $result );
        $this->assertArrayHasKey( 'database', $result );
        $this->assertArrayHasKey( 'debug', $result );
    }

    public function test_execute_wordpress_version_is_string(): void {
        // get_bloginfo('version') returns '6.5' from bootstrap
        Monkey\Functions\stubs(
            array( 'get_core_updates' => array() )
        );

        $result = GetSiteHealthAbility::execute();

        $this->assertIsString( $result['wordpress']['version'] );
        $this->assertNotEmpty( $result['wordpress']['version'] );
    }

    public function test_execute_php_version_matches_runtime(): void {
        Monkey\Functions\stubs(
            array( 'get_core_updates' => array() )
        );

        $result = GetSiteHealthAbility::execute();

        $this->assertSame( PHP_VERSION, $result['php']['version'] );
    }

    public function test_execute_debug_flags_are_booleans(): void {
        Monkey\Functions\stubs(
            array( 'get_core_updates' => array() )
        );

        $result = GetSiteHealthAbility::execute();

        $this->assertIsBool( $result['debug']['wp_debug'] );
        $this->assertIsBool( $result['debug']['wp_debug_log'] );
    }

    public function test_execute_update_available_false_when_no_updates(): void {
        // get_core_updates returns [] → no upgrade available
        Monkey\Functions\stubs(
            array( 'get_core_updates' => array() )
        );

        $result = GetSiteHealthAbility::execute();

        $this->assertFalse( $result['wordpress']['update_available'] );
    }

    public function test_execute_update_available_true_when_upgrade_response(): void {
        $upgrade           = new \stdClass();
        $upgrade->response = 'upgrade';

        Monkey\Functions\stubs(
            array( 'get_core_updates' => array( $upgrade ) )
        );

        $result = GetSiteHealthAbility::execute();

        $this->assertTrue( $result['wordpress']['update_available'] );
    }

    public function test_execute_php_section_has_expected_keys(): void {
        Monkey\Functions\stubs(
            array( 'get_core_updates' => array() )
        );

        $result = GetSiteHealthAbility::execute();

        $this->assertArrayHasKey( 'version', $result['php'] );
        $this->assertArrayHasKey( 'memory_limit', $result['php'] );
        $this->assertArrayHasKey( 'max_execution_time', $result['php'] );
        $this->assertArrayHasKey( 'upload_max_filesize', $result['php'] );
    }
}
