<?php
/**
 * Tests for SlyMetrics\MCP\Ability\GetPostsAbility
 *
 * Note: sanitize_key is pre-defined in tests/bootstrap.php and cannot be
 * stubbed via Brain\Monkey. Tests rely on the bootstrap implementation:
 *   sanitize_key($str) → lowercase alphanumeric/dash/underscore only.
 *
 * @package SlyMetrics\Tests\Unit\MCP\Ability
 */

declare( strict_types=1 );

namespace SlyMetrics\Tests\Unit\MCP\Ability;

use Brain\Monkey;
use SlyMetrics\MCP\Ability\GetPostsAbility;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\MCP\Ability\GetPostsAbility
 */
class GetPostsAbilityTest extends TestCase_Base {

    public function test_definition_has_correct_name(): void {
        $def = GetPostsAbility::definition();
        $this->assertSame( 'metrics/get-posts', $def['name'] );
    }

    public function test_definition_has_mcp_public_flag(): void {
        $def = GetPostsAbility::definition();
        $this->assertTrue( $def['meta']['mcp']['public'] );
    }

    public function test_definition_has_post_type_parameter(): void {
        $def = GetPostsAbility::definition();
        $this->assertArrayHasKey( 'post_type', $def['input_schema']['properties'] );
        $this->assertSame( 'post', $def['input_schema']['properties']['post_type']['default'] );
    }

    public function test_definition_post_type_is_not_required(): void {
        $def = GetPostsAbility::definition();
        $this->assertNotContains( 'post_type', $def['input_schema']['required'] );
    }

    public function test_definition_output_schema_has_expected_keys(): void {
        $def   = GetPostsAbility::definition();
        $props = $def['output_schema']['properties'];

        $this->assertArrayHasKey( 'post_type', $props );
        $this->assertArrayHasKey( 'counts', $props );
        $this->assertArrayHasKey( 'total', $props );
    }

    public function test_execute_defaults_to_post_type(): void {
        Monkey\Functions\expect( 'wp_count_posts' )
            ->once()
            ->with( 'post' )
            ->andReturn( (object) array( 'publish' => 5, 'draft' => 2 ) );

        $result = GetPostsAbility::execute();

        $this->assertSame( 'post', $result['post_type'] );
    }

    public function test_execute_with_page_post_type(): void {
        Monkey\Functions\expect( 'wp_count_posts' )
            ->once()
            ->with( 'page' )
            ->andReturn( (object) array( 'publish' => 8, 'draft' => 1, 'trash' => 0 ) );

        $result = GetPostsAbility::execute( array( 'post_type' => 'page' ) );

        $this->assertSame( 'page', $result['post_type'] );
        $this->assertSame( 9, $result['total'] );
    }

    public function test_execute_total_sums_all_statuses(): void {
        Monkey\Functions\stubs(
            array(
                'wp_count_posts' => (object) array( 'publish' => 10, 'draft' => 3, 'trash' => 2, 'private' => 1 ),
            )
        );

        $result = GetPostsAbility::execute();

        $this->assertSame( 16, $result['total'] );
    }

    public function test_execute_sanitizes_empty_post_type_to_post(): void {
        Monkey\Functions\expect( 'wp_count_posts' )
            ->once()
            ->with( 'post' )
            ->andReturn( (object) array( 'publish' => 1 ) );

        $result = GetPostsAbility::execute( array( 'post_type' => '' ) );

        $this->assertSame( 'post', $result['post_type'] );
    }

    public function test_execute_returns_counts_as_integers(): void {
        Monkey\Functions\stubs(
            array(
                'wp_count_posts' => (object) array( 'publish' => '7', 'draft' => '3' ),
            )
        );

        $result = GetPostsAbility::execute();

        foreach ( $result['counts'] as $count ) {
            $this->assertIsInt( $count );
        }
    }

    public function test_execute_post_type_is_sanitized(): void {
        // sanitize_key('MY-Post') → 'my-post'
        Monkey\Functions\expect( 'wp_count_posts' )
            ->once()
            ->with( 'my-post' )
            ->andReturn( (object) array() );

        $result = GetPostsAbility::execute( array( 'post_type' => 'MY-Post' ) );

        $this->assertSame( 'my-post', $result['post_type'] );
    }
}
