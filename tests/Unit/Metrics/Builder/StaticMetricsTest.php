<?php
/**
 * Tests for SlyMetrics\Metrics\Builder\StaticMetrics (update_available label).
 *
 * get_core_updates() is intentionally never defined: on front-end requests
 * (the metrics endpoint) it does not exist. See issue #10.
 *
 * @package SlyMetrics\Tests\Unit\Metrics\Builder
 */

declare( strict_types=1 );

namespace SlyMetrics\Tests\Unit\Metrics\Builder;

use SlyMetrics\Metrics\Builder\StaticMetrics;
use SlyMetrics\Tests\Unit\TestCase_Base;

/**
 * @covers \SlyMetrics\Metrics\Builder\StaticMetrics
 * @covers \SlyMetrics\Util\CoreUpdate
 */
class StaticMetricsTest extends TestCase_Base {

    private function set_update_core( string $response ): void {
        $GLOBALS['slymetrics_test_transients']['update_core'] = (object) array(
            'updates' => array( (object) array( 'response' => $response ) ),
        );
    }

    public function test_front_end_has_no_get_core_updates(): void {
        $this->assertFalse( function_exists( 'get_core_updates' ) );
    }

    public function test_update_available_is_one_when_upgrade_offered(): void {
        $this->set_update_core( 'upgrade' );

        $this->assertStringContainsString( 'update_available="1"', StaticMetrics::build( 'WP SITE' ) );
    }

    public function test_update_available_is_zero_when_latest(): void {
        $this->set_update_core( 'latest' );

        $this->assertStringContainsString( 'update_available="0"', StaticMetrics::build( 'WP SITE' ) );
    }

    public function test_update_available_is_zero_without_transient(): void {
        $this->assertStringContainsString( 'update_available="0"', StaticMetrics::build( 'WP SITE' ) );
    }

    public function test_update_available_is_zero_when_updates_empty(): void {
        $GLOBALS['slymetrics_test_transients']['update_core'] = (object) array( 'updates' => array() );

        $this->assertStringContainsString( 'update_available="0"', StaticMetrics::build( 'WP SITE' ) );
    }
}
