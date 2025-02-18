<?php

/**
 * Plugins_Command class for WP-CLI.
 *
 * This class adds a custom command to WP-CLI to list all plugins in a multisite network
 * and display how many sites they are active on, with sorting and filtering options.
 *
 * @package NetworkPluginAuditor\WPCLI
 */

namespace NetworkPluginAuditor\WPCLI;

use WP_CLI;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * A test command for WP-CLI.
	 */
	class Plugins_Command {
		/**
		 * List all plugins in the network and how many sites they are active on, with sorting and filtering options.
		 *
		 * ## OPTIONS
		 *
		 * [--order-by=<field>]
		 * : Order the results by 'name' (default) or 'active-sites'.
		 * ---
		 * default: name
		 * options:
		 *   - name
		 *   - active-sites
		 * ---
		 *
		 * [--min-active-sites=<number>]
		 * : Only display plugins active on at least this number of sites.
		 *
		 * [--max-active-sites=<number>]
		 * : Only display plugins active on no more than this number of sites.
		 *
		 * [--site-id=<number>]
		 * : Only display plugins active on a particular site.
		 * ## EXAMPLES
		 *
		 *     wp network-plugin-auditor plugins
		 *     wp network-plugin-auditor plugins --order-by=active-sites
		 *     wp network-plugin-auditor plugins --min-active-sites=2
		 *     wp network-plugin-auditor plugins --max-active-sites=5 --site-id=1
		 *     wp network-plugin-auditor plugins --min-active-sites=2 --max-active-sites=10 --order-by=active-sites
		 *
		 * @param array $args Positional arguments.
		 * @param array $assoc_args Associative arguments.
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

			$all_plugins = array_keys( get_plugins() );
			$plugin_counts = array_fill_keys( $all_plugins, 0 );
			$plugin_sites = array_fill_keys( $all_plugins, [] );

			// Count active plugins per site and store site IDs
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );

				$active_plugins = get_option( 'active_plugins', [] );

				foreach ( $active_plugins as $plugin ) {
					if ( isset( $plugin_counts[ $plugin ] ) ) {
						$plugin_counts[ $plugin ] ++;
					} else {
						$plugin_counts[ $plugin ] = 1;
					}

					if ( isset( $plugin_sites[ $plugin ] ) ) {
						$plugin_sites[ $plugin ][] = $site->blog_id;
					} else {
						$plugin_sites[ $plugin ] = [ $site->blog_id ];
					}
				}

				restore_current_blog();
			}

			// Apply filters
			$order_by = $assoc_args['order-by'] ?? 'name';
			$min_sites_active = isset( $assoc_args['min-active-sites'] ) ? (int) $assoc_args['min-active-sites'] : null;
			$max_sites_active = isset( $assoc_args['max-active-sites'] ) ? (int) $assoc_args['max-active-sites'] : null;
			$filter_site_id = isset( $assoc_args['site-id'] ) ? (int) $assoc_args['site-id'] : null;

			$table = [];
			foreach ( $plugin_counts as $plugin => $count ) {
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
					( empty( $plugin_sites[ $plugin ] ) || ! in_array( $filter_site_id, $plugin_sites[ $plugin ], false ) )
				) {
					continue;
				}

				$table[] = [
					'Plugin' => $plugin,
					'Active Sites' => $count,
					'Site IDs' => empty( $plugin_sites[ $plugin ] ) ? 'None' : implode( ', ', $plugin_sites[ $plugin ] ),
				];
			}

			// Sort the table
			if ( $order_by === 'active-sites' ) {
				usort( $table, static function ( $a, $b ) {
					return $b['Active Sites'] <=> $a['Active Sites'];
				} );
			} else {
				usort( $table, static function ( $a, $b ) {
					return strcasecmp( $a['Plugin'], $b['Plugin'] );
				} );
			}

			if ( empty( $table ) ) {
				WP_CLI::log( 'No plugins found matching the criteria.' );

				return;
			}

			WP_CLI\Utils\format_items( 'table', $table, [ 'Plugin', 'Active Sites', 'Site IDs' ] );
		}
	}
}
