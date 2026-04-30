<?php
/**
 * Tests for SlyMetrics\MCP\Ability\GetUsersAbility
 *
 * @package SlyMetrics\Tests\Unit\MCP\Ability
 */

declare( strict_types=1 );

namespace SlyMetrics\Tests\Unit\MCP\Ability;

use Brain\Monkey;
use SlyMetrics\MCP\Ability\GetUsersAbility;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\MCP\Ability\GetUsersAbility
 */
class GetUsersAbilityTest extends TestCase_Base {

    public function test_definition_has_correct_name(): void {
        $def = GetUsersAbility::definition();
        $this->assertSame( 'metrics/get-users', $def['name'] );
    }

    public function test_definition_has_label(): void {
        $def = GetUsersAbility::definition();
        $this->assertArrayHasKey( 'label', $def );
        $this->assertNotEmpty( $def['label'] );
    }

    public function test_definition_has_category(): void {
        $def = GetUsersAbility::definition();
        $this->assertArrayHasKey( 'category', $def );
        $this->assertNotEmpty( $def['category'] );
    }

    public function test_definition_has_mcp_public_flag(): void {
        $def = GetUsersAbility::definition();
        $this->assertTrue( $def['meta']['mcp']['public'] );
    }

    public function test_definition_has_permission_callback(): void {
        $def = GetUsersAbility::definition();
        $this->assertIsCallable( $def['permission_callback'] );
    }

    public function test_definition_has_no_required_input_params(): void {
        $def = GetUsersAbility::definition();
        $this->assertSame( array(), $def['input_schema']['required'] );
    }

    public function test_execute_returns_total_and_by_role(): void {
        Monkey\Functions\stubs(
            array(
                'count_users' => array(
                    'total_users' => 10,
                    'avail_roles' => array( 'administrator' => 1, 'editor' => 3, 'subscriber' => 6 ),
                ),
            )
        );

        $result = GetUsersAbility::execute();

        $this->assertArrayHasKey( 'total', $result );
        $this->assertArrayHasKey( 'by_role', $result );
        $this->assertSame( 10, $result['total'] );
    }

    public function test_execute_by_role_values_are_integers(): void {
        Monkey\Functions\stubs(
            array(
                'count_users' => array(
                    'total_users' => 5,
                    'avail_roles' => array( 'administrator' => '2', 'subscriber' => '3' ),
                ),
            )
        );

        $result = GetUsersAbility::execute();

        foreach ( $result['by_role'] as $count ) {
            $this->assertIsInt( $count );
        }
    }

    public function test_execute_handles_empty_roles(): void {
        Monkey\Functions\stubs(
            array(
                'count_users' => array( 'total_users' => 0, 'avail_roles' => array() ),
            )
        );

        $result = GetUsersAbility::execute();

        $this->assertSame( 0, $result['total'] );
        $this->assertSame( array(), $result['by_role'] );
    }
}
