<?php
/**
 * Base Command class for WP-CLI functionality in a WordPress multisite network.
 *
 * This abstract class provides a foundation for creating WP-CLI commands
 * that operate on multisite installations. It includes methods to fetch site data,
 * process plugins or themes, and generate tabular output with various filters.
 *
 * @package NetworkPluginAuditor\WPCLI
 */

namespace NetworkPluginAuditor\WPCLI;

use WP_CLI;
use WP_Site;

/**
 * A base command for WP-CLI.
 */
abstract class Base_Command {
	protected array $sites;

	/**
	 * Executes the command to retrieve and store all sites in a multisite installation.
	 *
	 * @param array $args Positional arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 *
	 * @return void
	 */
	public function run( array $args, array $assoc_args ) : void {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'This command can only be run on a multisite installation.' );

			return;
		}

		$sites = get_sites( [ 'number' => 0 ] ); // Get all sites
		if ( empty( $sites ) ) {
			WP_CLI::error( 'No sites found in the network.' );

			return;
		}

		$this->sites = $sites;
	}

	/**
	 * Generates a filtered and sorted table based on active plugins or themes across a multisite installation.
	 *
	 * @param array  $list A list of plugins or themes to evaluate for active status.
	 * @param array  $assoc_args An associative array of filters and sorting criteria, including:
	 *                          - 'order-by': Sort the table by 'name' or 'active-sites'.
	 *                          - 'min-active-sites': Minimum number of active sites required for inclusion.
	 *                          - 'max-active-sites': Maximum number of active sites allowed for inclusion.
	 *                          - 'site-id': Filter by a specific site ID.
	 * @param string $column_name The name of the primary column to display in the table.
	 *
	 * @return array A filtered and sorted array of data, where each entry contains:
	 *               - The primary column key with the plugin/theme name.
	 *               - 'Active Sites': The count of sites where the plugin/theme is active.
	 *               - 'Site IDs': A comma-separated list of site IDs where the plugin/theme is active.
	 */
	public function get_table( array $list, array $assoc_args, string $column_name ) : array {
		$active_counts = array_fill_keys( $list, 0 );
		$active_sites = array_fill_keys( $list, [] );

		// Count active themes per site and store site IDs
		foreach ( $this->sites as $site ) {
			$plugins_or_themes = $this->before_count( $site );

			foreach ( $plugins_or_themes as $plugin_or_theme ) {
				if ( isset( $active_counts[ $plugin_or_theme ] ) ) {
					$active_counts[ $plugin_or_theme ] ++;
				} else {
					$active_counts[ $plugin_or_theme ] = 1;
				}

				if ( isset( $active_sites[ $plugin_or_theme ] ) ) {
					$active_sites[ $plugin_or_theme ][] = $site->blog_id;
				} else {
					$active_sites[ $plugin_or_theme ] = [ $site->blog_id ];
				}
			}

			$this->after_count();
		}

		// Apply filters
		$order_by = $assoc_args['order-by'] ?? 'name';
		$min_sites_active = isset( $assoc_args['min-active-sites'] ) ? (int) $assoc_args['min-active-sites'] : null;
		$max_sites_active = isset( $assoc_args['max-active-sites'] ) ? (int) $assoc_args['max-active-sites'] : null;
		$filter_site_id = isset( $assoc_args['site-id'] ) ? (int) $assoc_args['site-id'] : null;

		$table = [];
		foreach ( $active_counts as $plugin_or_theme => $count ) {
			// Apply min/max filters
			if (
				( $min_sites_active !== null && $count < $min_sites_active ) ||
				( $max_sites_active !== null && $count > $max_sites_active )
			) {
				continue;
			}

			// Apply site ID filter, if provided
			if (
				$filter_site_id !== null &&
				( empty( $active_sites[ $plugin_or_theme ] ) || ! in_array( $filter_site_id, $active_sites[ $plugin_or_theme ], false ) )
			) {
				continue;
			}

			$table[] = [
				$column_name => $plugin_or_theme,
				'Active Sites' => $count,
				'Site IDs' => empty( $active_sites[ $plugin_or_theme ] ) ? 'None' : implode( ', ', $active_sites[ $plugin_or_theme ] ),
			];
		}

		// Sort the table
		if ( $order_by === 'active-sites' ) {
			usort( $table, static function ( $a, $b ) {
				return $b['Active Sites'] <=> $a['Active Sites'];
			} );
		} else {
			usort( $table, static function ( $a, $b ) use ( $column_name ) {
				return strcasecmp( $a[ $column_name ], $b[ $column_name ] );
			} );
		}

		return $table;
	}

	/**
	 * Executes operations to be performed before a count is initiated.
	 *
	 * @param WP_Site $site The site object for which the count will be prepared.
	 *
	 * @return array An array of data to support the count operation.
	 */
	abstract protected function before_count( WP_Site $site ) : array;

	/**
	 * Executes optional operations to be performed after a count is completed.
	 *
	 * @return void
	 */
	protected function after_count() : void {
		// Optional
	}
}
