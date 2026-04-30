<?php
/**
 * Tests for SlyMetrics\MCP\Ability\GetContentAbility
 *
 * @package SlyMetrics\Tests\Unit\MCP\Ability
 */

declare( strict_types=1 );

namespace SlyMetrics\Tests\Unit\MCP\Ability;

use Brain\Monkey;
use SlyMetrics\MCP\Ability\GetContentAbility;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\MCP\Ability\GetContentAbility
 */
class GetContentAbilityTest extends TestCase_Base {

    public function test_definition_has_correct_name(): void {
        $def = GetContentAbility::definition();
        $this->assertSame( 'metrics/get-content', $def['name'] );
    }

    public function test_definition_has_label(): void {
        $def = GetContentAbility::definition();
        $this->assertArrayHasKey( 'label', $def );
        $this->assertNotEmpty( $def['label'] );
    }

    public function test_definition_has_category(): void {
        $def = GetContentAbility::definition();
        $this->assertArrayHasKey( 'category', $def );
        $this->assertNotEmpty( $def['category'] );
    }

    public function test_definition_has_mcp_public_flag(): void {
        $def = GetContentAbility::definition();
        $this->assertTrue( $def['meta']['mcp']['public'] );
    }

    public function test_definition_has_permission_callback(): void {
        $def = GetContentAbility::definition();
        $this->assertIsCallable( $def['permission_callback'] );
    }

    public function test_definition_has_no_required_input_params(): void {
        $def = GetContentAbility::definition();
        $this->assertSame( array(), $def['input_schema']['required'] );
    }

    public function test_definition_output_schema_has_expected_keys(): void {
        $def   = GetContentAbility::definition();
        $props = $def['output_schema']['properties'];

        $this->assertArrayHasKey( 'comments',   $props );
        $this->assertArrayHasKey( 'categories', $props );
        $this->assertArrayHasKey( 'media',      $props );
        $this->assertArrayHasKey( 'tags',       $props );
    }

    public function test_execute_returns_all_expected_keys(): void {
        $comment_obj           = new \stdClass();
        $comment_obj->approved = 10;
        $comment_obj->spam     = 2;

        Monkey\Functions\stubs( array(
            'wp_count_comments' => $comment_obj,
            'wp_count_terms'    => 5,
            'wp_count_posts'    => (object) array( 'inherit' => 3 ),
        ) );

        $result = GetContentAbility::execute();

        $this->assertArrayHasKey( 'comments',   $result );
        $this->assertArrayHasKey( 'categories', $result );
        $this->assertArrayHasKey( 'media',      $result );
        $this->assertArrayHasKey( 'tags',       $result );
    }

    public function test_execute_comments_values_are_integers(): void {
        $comment_obj           = new \stdClass();
        $comment_obj->approved = '7';
        $comment_obj->spam     = '1';
        $comment_obj->trash    = '0';

        Monkey\Functions\stubs( array(
            'wp_count_comments' => $comment_obj,
            'wp_count_terms'    => 0,
            'wp_count_posts'    => (object) array(),
        ) );

        $result = GetContentAbility::execute();

        foreach ( $result['comments'] as $count ) {
            $this->assertIsInt( $count );
        }
    }

    public function test_execute_normalizes_awaiting_moderation_label(): void {
        $comment_obj                        = new \stdClass();
        $comment_obj->awaiting_moderation   = 3;

        Monkey\Functions\stubs( array(
            'wp_count_comments' => $comment_obj,
            'wp_count_terms'    => 0,
            'wp_count_posts'    => (object) array(),
        ) );

        $result = GetContentAbility::execute();

        $this->assertArrayHasKey( 'moderated', $result['comments'] );
        $this->assertSame( 3, $result['comments']['moderated'] );
    }

    public function test_execute_categories_and_tags_are_integers(): void {
        Monkey\Functions\stubs( array(
            'wp_count_comments' => (object) array(),
            'wp_count_terms'    => 12,
            'wp_count_posts'    => (object) array(),
        ) );

        $result = GetContentAbility::execute();

        $this->assertIsInt( $result['categories'] );
        $this->assertIsInt( $result['tags'] );
    }

    public function test_execute_media_sums_all_attachment_statuses(): void {
        $attachments = (object) array( 'inherit' => 5, 'private' => 2 );

        Monkey\Functions\stubs( array(
            'wp_count_comments' => (object) array(),
            'wp_count_terms'    => 0,
            'wp_count_posts'    => $attachments,
        ) );

        $result = GetContentAbility::execute();

        $this->assertSame( 7, $result['media'] );
    }
}
