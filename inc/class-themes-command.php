<?php
/**
 * Themes_Command class for WP-CLI.
 *
 * This class adds a custom command to WP-CLI to list all themes in a multisite network
 * and display how many sites they are active on, with sorting and filtering options.
 *
 * @package NetworkPluginAuditor\WPCLI
 */

namespace NetworkPluginAuditor\WPCLI;

use NetworkPluginAuditor\NetworkPluginAuditor;
use WP_CLI;

/**
 * A themes command class
 */
class Themes_Command extends Base_Command {
	/**
	 * List all themes in the network and how many sites they are active on, with sorting and filtering options.
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
	 * : Only display themes active on at least this number of sites.
	 *
	 * [--max-active-sites=<number>]
	 * : Only display themes active on no more than this number of sites.
	 *
	 * [--site-id=<number>]
	 * : Only display themes active on a particular site.
	 * ## EXAMPLES
	 *
	 *     wp network-plugin-auditor themes
	 *     wp network-plugin-auditor themes --order-by=active-sites
	 *     wp network-plugin-auditor themes --min-active-sites=2
	 *     wp network-plugin-auditor themes --max-active-sites=5 --site-id=1
	 *     wp network-plugin-auditor themes --min-active-sites=2 --max-active-sites=10 --order-by=active-sites
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function run( array $args, array $assoc_args ) : void {
		parent::run( $args, $assoc_args );

		$all_themes = array_keys( wp_get_themes( [ 'errors' => null ] ) );

		$column_name = 'Theme';
		$table = $this->get_table( $all_themes, $assoc_args, $column_name );

		if ( empty( $table ) ) {
			WP_CLI::log( 'No themes found matching the criteria.' );

			return;
		}

		WP_CLI\Utils\format_items( 'table', $table, [ $column_name, 'Active Sites', 'Site IDs' ] );
	}

	/**
	 * Retrieves data related to the active theme of the provided site.
	 *
	 * @param mixed $site The site object or identifier containing the necessary blog ID information.
	 *
	 * @return array An array with the active theme details of the provided site.
	 */
	protected function before_count( mixed $site ) : array {
		$auditor = NetworkPluginAuditor::get_instance();
		$theme = $auditor->get_active_theme( $site->blog_id );

		return [ $theme ];
	}
}
