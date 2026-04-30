<?php
/**
 * Tests for SlyMetrics\MCP\Ability\GetHealthChecksAbility
 *
 * Note: Checker::get_details() is called during execute(). The bootstrap
 * provides stubs for get_site_transient (returns false = no updates) and
 * is_ssl (returns false = no HTTPS). WP_DEBUG is defined as false.
 *
 * @package SlyMetrics\Tests\Unit\MCP\Ability
 */

declare( strict_types=1 );

namespace SlyMetrics\Tests\Unit\MCP\Ability;

use SlyMetrics\MCP\Ability\GetHealthChecksAbility;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\MCP\Ability\GetHealthChecksAbility
 */
class GetHealthChecksAbilityTest extends TestCase_Base {

    public function test_definition_has_correct_name(): void {
        $def = GetHealthChecksAbility::definition();
        $this->assertSame( 'metrics/get-health-checks', $def['name'] );
    }

    public function test_definition_has_label(): void {
        $def = GetHealthChecksAbility::definition();
        $this->assertArrayHasKey( 'label', $def );
        $this->assertNotEmpty( $def['label'] );
    }

    public function test_definition_has_category(): void {
        $def = GetHealthChecksAbility::definition();
        $this->assertArrayHasKey( 'category', $def );
        $this->assertNotEmpty( $def['category'] );
    }

    public function test_definition_has_mcp_public_flag(): void {
        $def = GetHealthChecksAbility::definition();
        $this->assertTrue( $def['meta']['mcp']['public'] );
    }

    public function test_definition_has_permission_callback(): void {
        $def = GetHealthChecksAbility::definition();
        $this->assertIsCallable( $def['permission_callback'] );
    }

    public function test_definition_has_no_required_input_params(): void {
        $def = GetHealthChecksAbility::definition();
        $this->assertSame( array(), $def['input_schema']['required'] );
    }

    public function test_definition_output_schema_has_summary_and_checks(): void {
        $def   = GetHealthChecksAbility::definition();
        $props = $def['output_schema']['properties'];

        $this->assertArrayHasKey( 'summary', $props );
        $this->assertArrayHasKey( 'checks',  $props );
    }

    public function test_execute_returns_summary_and_checks_keys(): void {
        $result = GetHealthChecksAbility::execute();

        $this->assertArrayHasKey( 'summary', $result );
        $this->assertArrayHasKey( 'checks',  $result );
    }

    public function test_execute_summary_has_expected_keys(): void {
        $result  = GetHealthChecksAbility::execute();
        $summary = $result['summary'];

        $this->assertArrayHasKey( 'good',         $summary );
        $this->assertArrayHasKey( 'recommended',  $summary );
        $this->assertArrayHasKey( 'critical',     $summary );
        $this->assertArrayHasKey( 'security',     $summary );
        $this->assertArrayHasKey( 'performance',  $summary );
        $this->assertArrayHasKey( 'total_failed', $summary );
    }

    public function test_execute_summary_values_are_integers(): void {
        $result = GetHealthChecksAbility::execute();

        foreach ( $result['summary'] as $value ) {
            $this->assertIsInt( $value );
        }
    }

    public function test_execute_checks_is_array(): void {
        $result = GetHealthChecksAbility::execute();

        $this->assertIsArray( $result['checks'] );
    }

    public function test_execute_checks_entries_have_required_keys(): void {
        $result = GetHealthChecksAbility::execute();

        foreach ( $result['checks'] as $check ) {
            $this->assertArrayHasKey( 'test',        $check );
            $this->assertArrayHasKey( 'status',      $check );
            $this->assertArrayHasKey( 'category',    $check );
            $this->assertArrayHasKey( 'description', $check );
        }
    }

    public function test_execute_summary_total_failed_equals_recommended_plus_critical(): void {
        $result  = GetHealthChecksAbility::execute();
        $summary = $result['summary'];

        $this->assertSame(
            $summary['recommended'] + $summary['critical'],
            $summary['total_failed']
        );
    }
}
