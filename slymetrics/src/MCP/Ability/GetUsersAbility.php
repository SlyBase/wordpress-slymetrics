<?php

namespace SlyMetrics\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Ability: metrics/get-users
 *
 * Returns WordPress user counts broken down by role, plus the total count.
 *
 * No parameters required. Requires manage_options capability.
 *
 * @package SlyMetrics\MCP\Ability
 */
class GetUsersAbility {

    /**
     * Returns the ability definition array for wp_register_ability().
     *
     * @return array<string, mixed>
     */
    public static function definition(): array {
        return array(
            'name'               => 'metrics/get-users',
            'label'              => 'User Metrics',
            'category'           => 'user',
            'description'        => 'Returns WordPress user counts by role and the total count. No parameters required.',
            'input_schema'       => array( 'type' => 'object', 'properties' => array(), 'required' => array() ),
            'output_schema'      => array(
                'type'       => 'object',
                'properties' => array(
                    'total'    => array( 'type' => 'integer', 'description' => 'Total number of users across all roles' ),
                    'by_role'  => array( 'type' => 'object',  'description' => 'Map of role slug to user count' ),
                ),
            ),
            'permission_callback' => static fn() => current_user_can( 'manage_options' ),
            'execute_callback'    => array( self::class, 'execute' ),
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        );
    }

    /**
     * Builds and returns user metrics.
     *
     * @return array<string, mixed>
     */
    public static function execute(): array {
        $data = count_users();

        return array(
            'total'   => (int) ( $data['total_users'] ?? 0 ),
            'by_role' => array_map( 'intval', $data['avail_roles'] ?? array() ),
        );
    }
}
