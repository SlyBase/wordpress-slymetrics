<?php
/**
 * Tests for SlyMetrics_Plugin::add_plugin_action_links()
 *
 * @package SlyMetrics\Tests\Unit
 */

declare(strict_types=1);

namespace SlyMetrics\Tests\Unit;

/**
 * @covers \SlyMetrics_Plugin
 */
class AddPluginActionLinksTest extends TestCase_Base {

    protected function setUp(): void {
        parent::setUp();
        // admin_url and __ are pre-stubbed in bootstrap.php
    }

    public function test_settings_link_is_prepended(): void {
        $links  = ['<a href="#">Deactivate</a>'];
        $result = \SlyMetrics_Plugin::add_plugin_action_links( $links );

        // Settings link must be the first element
        $this->assertStringContainsString( 'Settings', $result[0] );
    }

    public function test_original_links_are_preserved(): void {
        $links  = ['<a href="#">Deactivate</a>'];
        $result = \SlyMetrics_Plugin::add_plugin_action_links( $links );

        $this->assertContains( '<a href="#">Deactivate</a>', $result );
    }

    public function test_settings_link_points_to_correct_page(): void {
        $result = \SlyMetrics_Plugin::add_plugin_action_links( [] );

        $this->assertStringContainsString( 'page=slymetrics', $result[0] );
    }

    public function test_returns_array(): void {
        $result = \SlyMetrics_Plugin::add_plugin_action_links( [] );
        $this->assertIsArray( $result );
    }
}
