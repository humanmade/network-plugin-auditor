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

/**
 * A plugins command class for WP-CLI.
 */
class Plugins_Command extends Base_Command {
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
		parent::run( $args, $assoc_args );

		$all_plugins = array_keys( get_plugins() );

		$column_name = 'Plugin';
		$table = $this->get_table( $all_plugins, $assoc_args, $column_name );

		if ( empty( $table ) ) {
			WP_CLI::log( 'No plugins found matching the criteria.' );

			return;
		}

		WP_CLI\Utils\format_items( 'table', $table, [ $column_name, 'Active Sites', 'Site IDs' ] );
	}

	/**
	 * Retrieves the list of active plugins for a given site.
	 *
	 * This method switches to the specified site context, fetches its active plugins from the options table,
	 * and then returns the list of active plugins.
	 *
	 * @param mixed $site The site object or data containing the blog ID of the site.
	 *
	 * @return array List of active plugins for the specified site.
	 */
	protected function before_count( mixed $site ) : array {
		switch_to_blog( $site->blog_id );

		$active_plugins = get_option( 'active_plugins', [] );

		return $active_plugins;
	}

	/**
	 * Finalizes operations after performing a count action.
	 *
	 * @return void
	 */
	protected function after_count() : void {
		restore_current_blog();
	}
}
