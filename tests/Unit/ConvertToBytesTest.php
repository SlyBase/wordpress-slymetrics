<?php
/**
 * Tests for SlyMetrics_Plugin::convert_to_bytes()
 *
 * @package SlyMetrics\Tests\Unit
 */

declare(strict_types=1);

namespace SlyMetrics\Tests\Unit;

/**
 * @covers \SlyMetrics_Plugin
 */
class ConvertToBytesTest extends TestCase_Base {

    /** @dataProvider size_string_provider */
    public function test_convert_to_bytes( string $input, float $expected ): void {
        $result = self::call_private_method( 'convert_to_bytes', [$input] );
        $this->assertEqualsWithDelta( $expected, $result, 0.001 );
    }

    /** @return array<string, array{0: string, 1: float}> */
    public static function size_string_provider(): array {
        return [
            'kilobytes lowercase k' => ['512K', 512 * 1024],
            'megabytes uppercase M' => ['128M', 128 * 1024 * 1024],
            'gigabytes uppercase G' => ['2G',   2 * 1024 * 1024 * 1024],
            'plain bytes integer'   => ['8192', 8192.0],
            'plain bytes with unit' => ['256M', 256 * 1024 * 1024],
            '1 gigabyte'           => ['1G',   1 * 1024 * 1024 * 1024],
            'whitespace trimmed'   => [' 64M ', 64 * 1024 * 1024],
            'zero megabytes'       => ['0M',   0.0],
        ];
    }

    public function test_plain_numeric_string_returns_float(): void {
        $result = self::call_private_method( 'convert_to_bytes', ['1024'] );
        $this->assertIsFloat( $result );
        $this->assertEqualsWithDelta( 1024.0, $result, 0.001 );
    }
}
