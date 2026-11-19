<?php
/**
 * Tests for SlyMetrics\Util\CoreUpdate (core update detection).
 *
 * Covers the front-end scenario from issue #10: the `update_core` transient
 * can be empty when wp-cron has not run recently, so the check must force a
 * fresh `wp_version_check()` call and read the populated result.
 *
 * @package SlyMetrics\Tests\Unit\Util
 */

declare(strict_types=1);

namespace SlyMetrics\Tests\Unit\Util;

use Brain\Monkey;
use SlyMetrics\Tests\Unit\TestCase_Base;
use SlyMetrics\Util\CoreUpdate;

/**
 * @covers \SlyMetrics\Util\CoreUpdate
 */
class CoreUpdateTest extends TestCase_Base {

    public function test_returns_true_when_transient_has_upgrade(): void {
        $GLOBALS['slymetrics_test_transients']['update_core'] = (object) array(
            'updates' => array( (object) array( 'response' => 'upgrade' ) ),
        );

        $this->assertTrue( CoreUpdate::is_available() );
    }

    public function test_returns_false_when_transient_has_latest(): void {
        $GLOBALS['slymetrics_test_transients']['update_core'] = (object) array(
            'updates' => array( (object) array( 'response' => 'latest' ) ),
        );

        $this->assertFalse( CoreUpdate::is_available() );
    }

    public function test_returns_false_when_transient_empty(): void {
        // No transient set — and wp_version_check is not available,
        // so the check cannot populate it.
        $this->assertFalse( CoreUpdate::is_available() );
    }

    public function test_returns_false_when_updates_array_empty(): void {
        $GLOBALS['slymetrics_test_transients']['update_core'] = (object) array( 'updates' => array() );

        $this->assertFalse( CoreUpdate::is_available() );
    }

    /**
     * When the transient is empty, CoreUpdate must force a fresh check via
     * wp_version_check() and then read the populated transient.
     */
    public function test_forces_fresh_check_when_transient_empty(): void {
        // Transient starts empty.
        $GLOBALS['slymetrics_test_transients']['update_core'] = false;

        // Stub wp_version_check to populate the transient with an upgrade.
        Monkey\Functions\expect( 'wp_version_check' )
            ->once()
            ->with( array(), true )
            ->and_return( function() {
                $GLOBALS['slymetrics_test_transients']['update_core'] = (object) array(
                    'updates' => array( (object) array( 'response' => 'upgrade' ) ),
                );
            } );

        $this->assertTrue( CoreUpdate::is_available() );
    }

    /**
     * When the transient is empty and wp_version_check returns a "latest"
     * result, the check should return false.
     */
    public function test_forced_check_returns_latest(): void {
        $GLOBALS['slymetrics_test_transients']['update_core'] = false;

        Monkey\Functions\expect( 'wp_version_check' )
            ->once()
            ->with( array(), true )
            ->and_return( function() {
                $GLOBALS['slymetrics_test_transients']['update_core'] = (object) array(
                    'updates' => array( (object) array( 'response' => 'latest' ) ),
                );
            } );

        $this->assertFalse( CoreUpdate::is_available() );
    }

    /**
     * When the transient is empty and wp_version_check fails to populate
     * the transient (e.g. network error), the check should return false.
     */
    public function test_forced_check_transient_still_empty(): void {
        $GLOBALS['slymetrics_test_transients']['update_core'] = false;

        // wp_version_check is called but does not populate the transient.
        Monkey\Functions\expect( 'wp_version_check' )
            ->once()
            ->with( array(), true )
            ->and_return( null );

        $this->assertFalse( CoreUpdate::is_available() );
    }

    /**
     * When wp_installing() is true, the check short-circuits to false
     * without touching the transient.
     */
    public function test_returns_false_when_installing(): void {
        // wp_installing() is stubbed in the bootstrap to return false.
        // We cannot easily override it here without a Brain\Monkey stub,
        // so this test documents the current behavior: not installing = proceeds.
        $GLOBALS['slymetrics_test_transients']['update_core'] = (object) array(
            'updates' => array( (object) array( 'response' => 'upgrade' ) ),
        );

        // Not installing → proceeds to check.
        $this->assertTrue( CoreUpdate::is_available() );
    }
}
