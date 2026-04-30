<?php

namespace SlyMetrics\MCP\Ability;

defined( 'ABSPATH' ) || exit;

use SlyMetrics\Health\Checker;

/**
 * Ability: metrics/get-health-checks
 *
 * Returns WordPress site health check results: an aggregated summary
 * by status and category, plus the individual test results.
 * Mirrors HeavyMetrics health Prometheus data via the Checker class.
 *
 * No parameters required. Requires manage_options capability.
 *
 * @package SlyMetrics\MCP\Ability
 */
class GetHealthChecksAbility {

    /**
     * Returns the ability definition array for wp_register_ability().
     *
     * @return array<string, mixed>
     */
    public static function definition(): array {
        return array(
            'name'                => 'metrics/get-health-checks',
            'label'               => 'Health Check Results',
            'category'            => 'site',
            'description'         => 'Returns WordPress site health check results: a summary with counts by status (good, recommended, critical) and category (security, performance), plus individual test results each with test name, status, category, and description. No parameters required.',
            'input_schema'        => array( 'type' => array( 'object', 'null' ), 'properties' => (object) array(), 'required' => array() ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'summary' => array(
                        'type'        => 'object',
                        'description' => 'Aggregated counts: good, recommended, critical, security, performance, total_failed',
                    ),
                    'checks'  => array(
                        'type'        => 'array',
                        'description' => 'Individual check results, each with: test, status, category, description',
                    ),
                ),
            ),
            'permission_callback' => static fn() => current_user_can( 'manage_options' ),
            'execute_callback'    => array( self::class, 'execute' ),
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        );
    }

    /**
     * Builds and returns health check results.
     *
     * @return array<string, mixed>
     */
    public static function execute(): array {
        return array(
            'summary' => Checker::get_summary(),
            'checks'  => Checker::get_details(),
        );
    }
}
