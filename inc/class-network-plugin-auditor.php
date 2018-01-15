<?php

namespace NetworkPluginAuditor;

/**
 * Class NetworkPluginAuditor
 */
class NetworkPluginAuditor {

	/**
	 * Flag for transient use.
	 */
	const USE_TRANSIENT = true;

	/**
	 * Instance of the class.
	 *
	 * @var NetworkPluginAuditor $instance Instance of the class.
	 */
	private static $instance;

	/**
	 * Singleton accessor.
	 *
	 * @return NetworkPluginAuditor
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Add our WordPress filter/action callbacks.
	 */
	public function init() {
		// On the network plugins page, show which blogs have this plugin active.
		add_filter( 'manage_plugins-network_columns', array( $this, 'add_plugins_column' ), 10, 1 );
		add_action( 'manage_plugins_custom_column', array( $this, 'manage_plugins_custom_column' ), 10, 3 );

		// On the network theme list page, show each blog next to its active theme.
		add_filter( 'manage_themes-network_columns', array( $this, 'add_themes_column' ), 10, 1 );
		add_action( 'manage_themes_custom_column', array( $this, 'manage_themes_custom_column' ), 10, 3 );

		// On the blog list page, show the plugins and theme active on each blog.
		add_filter( 'manage_sites-network_columns', array( $this, 'add_sites_column' ), 10, 1 );
		add_action( 'manage_sites_custom_column', array( $this, 'manage_sites_custom_column' ), 10, 3 );

		// Clear the transients when plugin or themes change.
		add_action( 'activated_plugin', array( $this, 'clear_plugin_transient' ), 10, 2 );
		add_action( 'deactivated_plugin', array( $this, 'clear_plugin_transient' ), 10, 2 );
		add_action( 'switch_theme', array( $this, 'clear_theme_transient' ), 10, 2 );
	}

	/**
	 * Adds a column to the plugins list table for the blogs where the plugin is active.
	 *
	 * @param array $column_details The column details.
	 *
	 * @return mixed
	 */
	public function add_plugins_column( $column_details ) {
		$column_details['active_blogs'] = __( 'Active Blogs', 'network-plugin-auditor' );

		return $column_details;
	}

	/**
	 * Add column to plugin list.
	 *
	 * @param string $column_name Name of the column.
	 * @param string $plugin_file Path to the plugin file.
	 */
	public function manage_plugins_custom_column( $column_name, $plugin_file ) {
		if ( $column_name !== 'active_blogs' ) {
			return;
		}

		// Is this plugin network activated.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active_for_network( $plugin_file ) ) {
			// We don't need to check any further for network active plugins.
			$output = '<strong>' . esc_html__( 'Network Activated', 'network-plugin-auditor' ) . '</strong>';

		} else {
			// Is this plugin Active on any blogs in this network?
			$active_on_blogs = $this->is_plugin_active_on_blogs( $plugin_file );
			if ( is_array( $active_on_blogs ) ) {

				$output = '<ul>';

				// Loop through the blog list, gather details and append them to the output string.
				foreach ( $active_on_blogs as $blog_id ) {
					$blog_id = trim( $blog_id );
					if ( empty( $blog_id ) ) {
						continue;
					}

					$blog_details = get_blog_details( $blog_id, true );

					if ( isset( $blog_details->siteurl, $blog_details->blogname ) ) {
						$blog_name  = $blog_details->blogname;
						$blog_state = '';
						$style      = '';

						if ( $blog_details->archived || $blog_details->deleted ) {

							$style = 'text-decoration: line-through;';

							$status_list = array(
								'archived' => array( 'site-archived', __( 'Archived' ) ),
								'spam'     => array( 'site-spammed', _x( 'Spam', 'site' ) ),
								'deleted'  => array( 'site-deleted', __( 'Deleted' ) ),
								'mature'   => array( 'site-mature', __( 'Mature' ) ),
							);
							$blog_states = array();
							foreach ( $status_list as $status => $col ) {
								if ( get_blog_status( $blog_details->blog_id, $status ) === 1 ) {
									$blog_states[] = $col[1];
								}
							}

							$state_count = count( $blog_states );
							$i           = 0;
							$blog_state .= ' - ';
							foreach ( $blog_states as $state ) {
								++ $i;
								( $i === $state_count ) ? $sep = '' : $sep = ', ';
								$blog_state .= '<span class="post-state">' . esc_html( $state . $sep ) . '</span>';
							}
						}

						$output .= '<li><nobr><a style="' . esc_attr( $style ) . '" title="' . esc_attr( sprintf( __( 'Manage plugins on %s', 'network-plugin-auditor' ), $blog_name ) ) . '" href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html( $blog_name ) . '</a>' . esc_html( $blog_state ) . '</nobr></li>';
					}

					unset( $blog_details );
				}
				$output .= '</ul>';
			}
		}
		echo $output;
	}

	/**
	 * Adds a column to the themes list table.
	 *
	 * @param array $column_details The column details.
	 *
	 * @return mixed
	 */
	public function add_themes_column( $column_details ) {
		$column_details['active_blogs'] = __( 'Active Blogs', 'network-plugin-auditor' );

		if ( function_exists( 'wp_get_theme' ) && function_exists( 'wp_get_themes' ) ) {
			$column_details['has_children'] = __( 'Children', 'network-plugin-auditor' );
		}

		return $column_details;
	}

	/**
	 * Add custom column to theme list table.
	 *
	 * @param string    $column_name Name of the column.
	 * @param string    $stylesheet Directory name of the theme.
	 * @param `WP_Theme $theme Current WP_Theme object.
	 */
	public function manage_themes_custom_column( $column_name, $stylesheet, $theme ) {
		if ( $column_name !== 'active_blogs' && $column_name !== 'has_children' ) {
			return;
		}

		$output = '';

		if ( $column_name === 'active_blogs' ) {
			// Is this theme Active on any blogs in this network?
			$active_on_blogs = $this->is_theme_active_on_blogs( $stylesheet );

			// Loop through the blog list, gather details and append them to the output string.
			if ( is_array( $active_on_blogs ) ) {
				$output .= '<ul>';

				foreach ( $active_on_blogs as $blog_id ) {
					$blog_id = trim( $blog_id );
					if ( empty( $blog_id ) ) {
						continue;
					}
					$output .= '<li>' . $this->get_theme_link( $blog_id ) . '</li>';
				}

				$output .= '</ul>';
			}
		}

		if ( $column_name === 'has_children' ) {
			// Find all the children of the current theme.
			$themes = wp_get_themes();

			// Filter down to possible children.
			$child_themes = array_reduce( $themes, function ( $carry, $item ) use ( $stylesheet ) {
				if ( ( $item->get_template() === $stylesheet ) && ( $item->get_template() !== $item->get_stylesheet() ) ) {
					$carry[] = $item;
				}

				return $carry;
			} );
			if ( null === $child_themes ) {
				$output .= '<ul><li>' . __( 'No child themes', 'network-plugin-auditor' ) . '</li></ul>';
			} else {
				$output .= '<ul>';
				foreach ( $child_themes as $childtheme ) {
					if ( $theme->get_stylesheet() !== $childtheme->get_stylesheet() ) {
						$output .= '<li>' . esc_html( $childtheme->get_stylesheet() ) . '</li>';
					}
				}
				$output .= '</ul>';
			}
		}

		echo $output; // xss ok.
	}

	/**
	 * Add a column to the sites list table.
	 *
	 * @param array $column_details The column details.
	 *
	 * @return mixed
	 */
	public function add_sites_column( $column_details ) {
		$column_details['active_plugins'] = __( 'Active Plugins', 'network-plugin-auditor' ) . ' <span title="' . esc_attr( __( 'Excludes network-active and must-use plugins', 'network-plugin-auditor' ) ) . '">[?]</span>';

		$column_details['active_theme'] = __( 'Active Theme', 'network-plugin-auditor' );

		return $column_details;
	}

	/**
	 * Manage site custom columns.
	 *
	 * @param string $column_name The column name.
	 * @param int    $blog_id The blog ID.
	 */
	public function manage_sites_custom_column( $column_name, $blog_id ) {
		if ( $column_name !== 'active_plugins' && $column_name !== 'active_theme' ) {
			return;
		}

		$output = '';

		if ( $column_name === 'active_plugins' ) {

			// Get the active plugins for this blog_id.
			$plugins_active_here = $this->get_active_plugins( $blog_id );
			$plugins_active_here = maybe_unserialize( $plugins_active_here );

			if ( is_array( $plugins_active_here ) && count( $plugins_active_here ) > 0 ) {
				$output .= '<ul>';
				foreach ( $plugins_active_here as $plugin ) {
					$plugin_path = WP_PLUGIN_DIR . '/' . $plugin;

					// Fetch the plugin's data from the file.
					if ( file_exists( $plugin_path ) && function_exists( 'get_plugin_data' ) ) {
						$plugin_data = get_plugin_data( $plugin_path );

						$plugin_name = $plugin_data['Name'] ?: '';
						$plugin_url  = $plugin_data['PluginURI'] ?: '';

						if ( null !== $plugin_url ) {
							$output .= '<li><a href="' . esc_url( $plugin_url ) . '" title="' . esc_attr( __( 'Visit the plugin URL', 'network-plugin-auditor' ) ) . '">' . esc_html( $plugin_name ) . '</a></li>';

						} else {
							$output .= '<li>' . esc_html( $plugin_name ) . '</li>';
						}
					} else {
						// Could not determine anything from this plugin's data block, just print the path.
						$output .= '<li>' . esc_html( $plugin ) . ' <span title="' . esc_attr( __( 'Plugin files were removed while the plugin was active on this blog', 'network-plugin-auditor' ) ) . '">[?]</span></li>';
					}
				}
				$output .= '</ul>';

			} else {
				$output .= '<ul><li>' . esc_html__( 'No Active Plugins', 'network-plugin-auditor' ) . '</li></ul>';
			}
		}

		if ( $column_name == 'active_theme' ) {
			// Get the active theme for this blog_id.
			$output .= '<ul><li>' . $this->get_theme_link( $blog_id, 'theme' ) . '</li></ul>';
		}

		echo $output;
	}

	/**
	 * Determine if the given plugin is active on a list of blogs.
	 *
	 * @param string $plugin_file The plugin slug.
	 *
	 * @return array|bool|mixed
	 */
	protected function is_plugin_active_on_blogs( $plugin_file ) {
		// Get the list of blogs.
		$blog_ids = get_sites(
			array(
				'fields'  => 'ids',
				'number'  => 100,
				'deleted' => 0,
			)
		);

		if ( ! empty( $blog_ids ) ) {
			// Fetch the list from the transient cache if available.
			$auditor_active_plugins = get_site_transient( 'auditor_active_plugins' );
			if ( ! is_array( $auditor_active_plugins ) ) {
				$auditor_active_plugins = array();
			}
			$transient_name = $this->get_transient_friendly_name( $plugin_file );

			if ( self::USE_TRANSIENT === true || ! array_key_exists( $transient_name, $auditor_active_plugins ) ) {
				// We're either not using or don't have the transient index.
				$active_on = array();

				// Gather the list of blogs this plugin is active on.
				foreach ( $blog_ids as $blog_id ) {
					// If the plugin is active here then add it to the list.
					if ( $this->is_plugin_active( $blog_id, $plugin_file ) ) {
						$active_on[] = $blog_id;
					}
				}

				// Store our list of blogs.
				$auditor_active_plugins[ $transient_name ] = $active_on;

				// Store for one hour.
				set_site_transient( 'auditor_active_plugins', $auditor_active_plugins, HOUR_IN_SECONDS );

				return $active_on;

			}
			// The transient index is available, return it.
			$active_on = $auditor_active_plugins[ $transient_name ];

			return $active_on;
		}

		return false;
	}

	/**
	 * Given a blog id and plugin path, determine if that plugin is active.
	 *
	 * @param int    $blog_id The blog ID.
	 * @param string $plugin_file The plugin file name.
	 *
	 * @return bool
	 */
	public function is_plugin_active( $blog_id, $plugin_file ) {
		// Get the active plugins for this blog_id.
		$plugins_active_here = $this->get_active_plugins( $blog_id );

		// Is this plugin listed in the active blogs?
		return is_array( $plugins_active_here ) && in_array( $plugin_file, $plugins_active_here, true );
	}

	/**
	 * Get the list of active plugins for a single blog.
	 *
	 * @param int $blog_id The blog ID.
	 *
	 * @return null|string
	 */
	protected function get_active_plugins( $blog_id ) {
		return get_blog_option( $blog_id, 'active_plugins' );
	}

	/**
	 * Determine if the given theme is active on a list of blogs.
	 *
	 * @param string $theme_key Theme slug.
	 *
	 * @return array|mixed
	 */
	public function is_theme_active_on_blogs( $theme_key ) {
		// Get the list of blogs.
		$blog_ids = get_sites(
			array(
				'fields'  => 'ids',
				'number'  => 100,
				'deleted' => 0,
			)
		);

		if ( ! empty( $blog_ids ) ) {
			// Fetch the list from the transient cache if available.
			$auditor_active_themes = get_site_transient( 'auditor_active_themes' );
			if ( ! is_array( $auditor_active_themes ) ) {
				$auditor_active_themes = array();
			}
			$transient_name = $this->get_transient_friendly_name( $theme_key );

			if ( self::USE_TRANSIENT !== true || ! array_key_exists( $transient_name, $auditor_active_themes ) ) {
				// We're either not using or don't have the transient index.
				$active_on = array();

				// Gather the list of blogs this theme is active on.
				foreach ( $blog_ids as $blog_id ) {
					// If the theme is active here then add it to the list.
					if ( $this->is_theme_active( $blog_id, $theme_key ) ) {
						$active_on[] = $blog_id;
					}
				}

				// Store our list of blogs.
				$auditor_active_themes[ $transient_name ] = $active_on;

				// Store for one hour.
				set_site_transient( 'auditor_active_themes', $auditor_active_themes, HOUR_IN_SECONDS );

				return $active_on;

			}

			// The transient index is available, return it.
			$active_on = $auditor_active_themes[ $transient_name ];

			return $active_on;
		}

		return false;
	}

	/**
	 * Given a blog id and theme object, determine if that theme is used on a this blog.
	 *
	 * @param int    $blog_id The blog ID.
	 * @param string $theme_key The theme key.
	 *
	 * @return bool
	 */
	public function is_theme_active( $blog_id, $theme_key ) {
		// Get the active theme for this blog_id.
		$active_theme = $this->get_active_theme( $blog_id );

		// Is this theme listed in the active blogs?
		return null !== $active_theme && ( $active_theme === $theme_key );
	}

	/**
	 * Get the active theme for a single blog.
	 *
	 * @param int $blog_id The blog ID.
	 *
	 * @return null|string
	 */
	public function get_active_theme( $blog_id ) {
		return get_blog_option( $blog_id, 'stylesheet' );
	}

	/**
	 * Get the active theme for a single blog.
	 *
	 * @param int $blog_id The blog ID.
	 *
	 * @return null|string
	 */
	public function get_active_theme_name( $blog_id ) {

		// Determine parent-child theme relationships when possible.
		if ( function_exists( 'wp_get_theme' ) ) {
			$template   = get_blog_option( $blog_id, 'template' );
			$stylesheet = get_blog_option( $blog_id, 'stylesheet' );

			if ( $template !== $stylesheet ) {
				// The active theme is a child theme.
				$template   = wp_get_theme( $template );
				$stylesheet = wp_get_theme( $stylesheet );

				$active_theme = $stylesheet['Name'] . ' (' . sprintf( __( 'child of %s', 'network-plugin-auditor' ), $template['Name'] ) . ')';

			} else {
				$active_theme = get_blog_option( $blog_id, 'current_theme' );
			}
		} else {
			$active_theme = get_blog_option( $blog_id, 'current_theme' );
		}


		return $active_theme;
	}

	/**
	 * Determines the blog URL to use as a link in the list table.
	 *
	 * @param int    $blog_id The blog ID.
	 * @param string $display Display type.
	 *
	 * @return string
	 */
	public function get_theme_link( $blog_id, $display = 'blog' ) {
		$output = '';

		$blog_details = get_blog_details( $blog_id );

		if ( isset( $blog_details->siteurl, $blog_details->blogname ) ) {
			$blog_url   = $blog_details->siteurl;
			$blog_name  = $blog_details->blogname;
			$blog_state = '';
			$style      = '';

			if ( $blog_details->archived || $blog_details->deleted ) {
				$style = 'style="text-decoration: line-through;" ';

				$status_list = array(
					'archived' => array( 'site-archived', __( 'Archived' ) ),
					'spam'     => array( 'site-spammed', __( 'Spam' ) ),
					'deleted'  => array( 'site-deleted', __( 'Deleted' ) ),
					'mature'   => array( 'site-mature', __( 'Mature' ) ),
				);

				$blog_states = array();
				foreach ( $status_list as $status => $col ) {
					if ( get_blog_status( $blog_details->blog_id, $status ) === 1 ) {
						$class         = $col[0];
						$blog_states[] = $col[1];
					}
				}

				$state_count = count( $blog_states );
				$i           = 0;
				$blog_state  .= ' - ';
				foreach ( $blog_states as $state ) {
					++ $i;
					( $i === $state_count ) ? $sep = '' : $sep = ', ';
					$blog_state .= '<span class="post-state">' . $state . $sep . '</span>';
				}
			}

			if ( $display === 'blog' ) {
				// Show the blog name.
				$output .= '<a ' . $style . ' title="' . esc_attr( sprintf( __( 'Manage themes on %s', 'network-plugin-auditor' ), $blog_name ) ) . '" href="' . esc_url( $blog_url ) . '/wp-admin/themes.php">' . esc_html( $blog_name ) . '</a>' . $blog_state;
			} else {
				// Show the theme name.
				$output .= '<a title="' . esc_attr( sprintf( __( 'Manage themes on %s', 'network-plugin-auditor' ), $blog_name ) ) . '" href="' . esc_url( $blog_url ) . '/wp-admin/themes.php">' . esc_html( $this->get_active_theme_name( $blog_id ) ) . '</a>';
			}
		}

		unset( $blog_details );

		return $output;
	}

	/**
	 * Generate a transient key.
	 *
	 * @param string $file_name The file name.
	 *
	 * @return array|string
	 */
	public function get_transient_friendly_name( $file_name ) {
		$transient_name = substr( $file_name, 0, strpos( $file_name, '/' ) );
		if ( empty( $transient_name ) ) {
			$transient_name = $file_name;
		}
		if ( strlen( $transient_name ) >= 45 ) {
			$transient_name = substr( $transient_name, 0, 44 );
		}

		return $transient_name;
	}

	/**
	 * Clean up.
	 */
	public function clear_plugin_transient() {
		delete_site_transient( 'auditor_active_plugins' );
	}
}
