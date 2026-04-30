<?php
/**
 * Tests for SlyMetrics_Plugin::format_metric()
 *
 * @package SlyMetrics\Tests\Unit
 */

declare(strict_types=1);

namespace SlyMetrics\Tests\Unit;

/**
 * @covers \SlyMetrics_Plugin
 */
class FormatMetricTest extends TestCase_Base {

    // ------------------------------------------------------------------
    // Happy path
    // ------------------------------------------------------------------

    public function test_basic_metric_line_structure(): void {
        $line = self::call_private_method( 'format_metric', [
            'wordpress_posts_total',
            'My Site',
            [],
            42,
        ] );

        $this->assertSame(
            'wordpress_posts_total{wordpress_site="My Site"} 42' . "\n",
            $line
        );
    }

    public function test_additional_labels_are_appended(): void {
        $line = self::call_private_method( 'format_metric', [
            'wordpress_posts_total',
            'My Site',
            ['status' => 'published'],
            10,
        ] );

        $this->assertSame(
            'wordpress_posts_total{wordpress_site="My Site",status="published"} 10' . "\n",
            $line
        );
    }

    public function test_multiple_labels_all_present(): void {
        $line = self::call_private_method( 'format_metric', [
            'wordpress_plugins_total',
            'My Site',
            ['status' => 'active', 'env' => 'prod'],
            5,
        ] );

        $this->assertStringContainsString( 'status="active"', $line );
        $this->assertStringContainsString( 'env="prod"', $line );
    }

    public function test_float_value_is_output(): void {
        $line = self::call_private_method( 'format_metric', [
            'wordpress_database_size_bytes',
            'Site',
            [],
            1024.5,
        ] );

        $this->assertStringEndsWith( " 1024.5\n", $line );
    }

    public function test_default_value_is_one(): void {
        $line = self::call_private_method( 'format_metric', [
            'some_metric',
            'Site',
        ] );

        $this->assertStringEndsWith( " 1\n", $line );
    }

    // ------------------------------------------------------------------
    // Metric-name sanitisation
    // ------------------------------------------------------------------

    public function test_invalid_chars_in_metric_name_are_replaced(): void {
        $line = self::call_private_method( 'format_metric', [
            'my-metric!name',
            'Site',
            [],
            1,
        ] );

        $this->assertStringStartsWith( 'my_metric_name{', $line );
    }

    public function test_empty_metric_name_returns_empty_string(): void {
        $line = self::call_private_method( 'format_metric', [
            '',
            'Site',
            [],
            1,
        ] );

        $this->assertSame( '', $line );
    }

    // ------------------------------------------------------------------
    // Non-numeric value fallback
    // ------------------------------------------------------------------

    public function test_non_numeric_value_is_replaced_with_zero(): void {
        $line = self::call_private_method( 'format_metric', [
            'my_metric',
            'Site',
            [],
            'not_a_number',
        ] );

        $this->assertStringEndsWith( " 0\n", $line );
    }

    // ------------------------------------------------------------------
    // Label-key sanitisation
    // ------------------------------------------------------------------

    public function test_invalid_label_key_chars_are_replaced(): void {
        $line = self::call_private_method( 'format_metric', [
            'my_metric',
            'Site',
            ['my-key!' => 'value'],
            1,
        ] );

        $this->assertStringContainsString( 'my_key_="value"', $line );
    }

    // ------------------------------------------------------------------
    // Site-name escaping
    // ------------------------------------------------------------------

    public function test_double_quote_in_site_name_is_escaped(): void {
        $line = self::call_private_method( 'format_metric', [
            'my_metric',
            'Say "Hello"',
            [],
            1,
        ] );

        $this->assertStringContainsString( 'wordpress_site="Say \\"Hello\\""', $line );
    }
}
