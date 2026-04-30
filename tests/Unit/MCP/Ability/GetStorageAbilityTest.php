<?php
/**
 * Tests for SlyMetrics\MCP\Ability\GetStorageAbility
 *
 * DB and filesystem operations depend on a real WordPress + database environment.
 * These unit tests verify the ability definition and that execute() returns the
 * expected structure, gracefully returning zeros when infrastructure is absent.
 *
 * @package SlyMetrics\Tests\Unit\MCP\Ability
 */

declare( strict_types=1 );

namespace SlyMetrics\Tests\Unit\MCP\Ability;

use Brain\Monkey;
use SlyMetrics\MCP\Ability\GetStorageAbility;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\MCP\Ability\GetStorageAbility
 */
class GetStorageAbilityTest extends TestCase_Base {

    public function test_definition_has_correct_name(): void {
        $def = GetStorageAbility::definition();
        $this->assertSame( 'metrics/get-storage', $def['name'] );
    }

    public function test_definition_has_label(): void {
        $def = GetStorageAbility::definition();
        $this->assertArrayHasKey( 'label', $def );
        $this->assertNotEmpty( $def['label'] );
    }

    public function test_definition_has_category(): void {
        $def = GetStorageAbility::definition();
        $this->assertArrayHasKey( 'category', $def );
        $this->assertNotEmpty( $def['category'] );
    }

    public function test_definition_has_mcp_public_flag(): void {
        $def = GetStorageAbility::definition();
        $this->assertTrue( $def['meta']['mcp']['public'] );
    }

    public function test_definition_has_permission_callback(): void {
        $def = GetStorageAbility::definition();
        $this->assertIsCallable( $def['permission_callback'] );
    }

    public function test_definition_has_no_required_input_params(): void {
        $def = GetStorageAbility::definition();
        $this->assertSame( array(), $def['input_schema']['required'] );
    }

    public function test_definition_output_schema_has_expected_sections(): void {
        $def   = GetStorageAbility::definition();
        $props = $def['output_schema']['properties'];

        $this->assertArrayHasKey( 'autoload',    $props );
        $this->assertArrayHasKey( 'database',    $props );
        $this->assertArrayHasKey( 'directories', $props );
    }

    public function test_execute_returns_all_expected_top_level_keys(): void {
        // wp_upload_dir and get_theme_root are stubbed to return non-existent paths,
        // so all directory sizes fall back to 0.
        Monkey\Functions\stubs( array(
            'wp_upload_dir'  => array( 'basedir' => '/nonexistent/uploads' ),
            'get_theme_root' => '/nonexistent/themes',
        ) );

        $result = GetStorageAbility::execute();

        $this->assertArrayHasKey( 'autoload',    $result );
        $this->assertArrayHasKey( 'database',    $result );
        $this->assertArrayHasKey( 'directories', $result );
    }

    public function test_execute_autoload_has_expected_keys(): void {
        Monkey\Functions\stubs( array(
            'wp_upload_dir'  => array( 'basedir' => '/nonexistent' ),
            'get_theme_root' => '/nonexistent',
        ) );

        $result = GetStorageAbility::execute();

        $this->assertArrayHasKey( 'total',      $result['autoload'] );
        $this->assertArrayHasKey( 'size_bytes', $result['autoload'] );
        $this->assertArrayHasKey( 'transients', $result['autoload'] );
    }

    public function test_execute_database_has_size_bytes_key(): void {
        Monkey\Functions\stubs( array(
            'wp_upload_dir'  => array( 'basedir' => '/nonexistent' ),
            'get_theme_root' => '/nonexistent',
        ) );

        $result = GetStorageAbility::execute();

        $this->assertArrayHasKey( 'size_bytes', $result['database'] );
    }

    public function test_execute_directories_has_expected_keys(): void {
        Monkey\Functions\stubs( array(
            'wp_upload_dir'  => array( 'basedir' => '/nonexistent' ),
            'get_theme_root' => '/nonexistent',
        ) );

        $result = GetStorageAbility::execute();

        $this->assertArrayHasKey( 'uploads', $result['directories'] );
        $this->assertArrayHasKey( 'themes',  $result['directories'] );
        $this->assertArrayHasKey( 'plugins', $result['directories'] );
        $this->assertArrayHasKey( 'total',   $result['directories'] );
    }

    public function test_execute_all_values_are_integers_when_infra_absent(): void {
        Monkey\Functions\stubs( array(
            'wp_upload_dir'  => array( 'basedir' => '/nonexistent' ),
            'get_theme_root' => '/nonexistent',
        ) );

        $result = GetStorageAbility::execute();

        $this->assertIsInt( $result['autoload']['total'] );
        $this->assertIsInt( $result['autoload']['size_bytes'] );
        $this->assertIsInt( $result['autoload']['transients'] );
        $this->assertIsInt( $result['database']['size_bytes'] );
        $this->assertIsInt( $result['directories']['uploads'] );
        $this->assertIsInt( $result['directories']['themes'] );
        $this->assertIsInt( $result['directories']['plugins'] );
        $this->assertIsInt( $result['directories']['total'] );
    }

    public function test_execute_directories_total_is_sum_of_parts(): void {
        Monkey\Functions\stubs( array(
            'wp_upload_dir'  => array( 'basedir' => '/nonexistent' ),
            'get_theme_root' => '/nonexistent',
        ) );

        $result = GetStorageAbility::execute();
        $dirs   = $result['directories'];

        $this->assertSame( $dirs['uploads'] + $dirs['themes'] + $dirs['plugins'], $dirs['total'] );
    }
}
