<?php
/**
 * Tests for SlyMetrics_Plugin::escape_label_value()
 *
 * @package SlyMetrics\Tests\Unit
 */

declare(strict_types=1);

namespace SlyMetrics\Tests\Unit;

/**
 * @covers \SlyMetrics_Plugin
 */
class EscapeLabelValueTest extends TestCase_Base {

    // ------------------------------------------------------------------
    // Basic pass-through
    // ------------------------------------------------------------------

    public function test_plain_ascii_string_unchanged(): void {
        $result = self::call_private_method( 'escape_label_value', ['hello world'] );
        $this->assertSame( 'hello world', $result );
    }

    public function test_empty_string_returns_empty(): void {
        $result = self::call_private_method( 'escape_label_value', [''] );
        $this->assertSame( '', $result );
    }

    // ------------------------------------------------------------------
    // Special-character escaping required by Prometheus format
    // ------------------------------------------------------------------

    public function test_backslash_is_escaped(): void {
        $result = self::call_private_method( 'escape_label_value', ['path\\to\\file'] );
        $this->assertSame( 'path\\\\to\\\\file', $result );
    }

    public function test_double_quote_is_escaped(): void {
        $result = self::call_private_method( 'escape_label_value', ['say "hello"'] );
        $this->assertSame( 'say \\"hello\\"', $result );
    }

    public function test_newline_is_escaped(): void {
        $result = self::call_private_method( 'escape_label_value', ["line1\nline2"] );
        $this->assertSame( 'line1\\nline2', $result );
    }

    public function test_carriage_return_is_escaped(): void {
        $result = self::call_private_method( 'escape_label_value', ["line1\rline2"] );
        $this->assertSame( 'line1\\rline2', $result );
    }

    public function test_tab_is_escaped(): void {
        $result = self::call_private_method( 'escape_label_value', ["col1\tcol2"] );
        $this->assertSame( 'col1\\tcol2', $result );
    }

    // ------------------------------------------------------------------
    // HTML entity decoding
    // ------------------------------------------------------------------

    public function test_html_entities_are_decoded(): void {
        $result = self::call_private_method( 'escape_label_value', ['Timon &amp; S&#246;hne'] );
        // & is decoded, ö (ö) is decoded – no Prometheus-special chars, so no further escaping
        $this->assertSame( 'Timon & Söhne', $result );
    }

    public function test_html_entity_double_quote_decoded_then_escaped(): void {
        // &quot; → " → \"
        $result = self::call_private_method( 'escape_label_value', ['&quot;quoted&quot;'] );
        $this->assertSame( '\\"quoted\\"', $result );
    }

    // ------------------------------------------------------------------
    // UTF-8 / international characters must survive
    // ------------------------------------------------------------------

    public function test_utf8_umlauts_preserved(): void {
        $result = self::call_private_method( 'escape_label_value', ['Förster'] );
        $this->assertSame( 'Förster', $result );
    }

    public function test_utf8_emoji_preserved(): void {
        $result = self::call_private_method( 'escape_label_value', ['Site 🚀'] );
        $this->assertSame( 'Site 🚀', $result );
    }

    // ------------------------------------------------------------------
    // Control-character removal
    // ------------------------------------------------------------------

    public function test_null_byte_is_removed(): void {
        $result = self::call_private_method( 'escape_label_value', ["be\x00fore"] );
        $this->assertSame( 'before', $result );
    }

    public function test_bell_character_is_removed(): void {
        $result = self::call_private_method( 'escape_label_value', ["ring\x07bell"] );
        $this->assertSame( 'ringbell', $result );
    }

    // ------------------------------------------------------------------
    // Length truncation (DoS guard)
    // ------------------------------------------------------------------

    public function test_value_longer_than_1000_chars_is_truncated(): void {
        $long = str_repeat( 'a', 1100 );
        $result = self::call_private_method( 'escape_label_value', [$long] );

        // Result must end with '...' and the base must be 1000 chars
        $this->assertStringEndsWith( '...', $result );
        $this->assertLessThanOrEqual( 1003, strlen( $result ) );
    }

    public function test_value_exactly_1000_chars_is_not_truncated(): void {
        $exact = str_repeat( 'b', 1000 );
        $result = self::call_private_method( 'escape_label_value', [$exact] );
        $this->assertSame( $exact, $result );
    }
}
