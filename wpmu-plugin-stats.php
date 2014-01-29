<?php
/*
Plugin Name: WPMU Plugin Stats
Plugin URI: http://wordpress.org/plugins/wpmu-plugin-stats/
Description: WordPress plugin for letting site admins easily see what plugins are actively used on which sites
Version: 2.0.1
Author: Kevin Graeme, Deanna Schneider & Jason Lemahieu
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wpmu-plugin-stats
Domain Path: /languages
Network: true

	WPMU Plugin Stats

	Copyright (C) 2009 - 2013 Board of Regents of the University of Wisconsin System
	Cooperative Extension Technology Services
	University of Wisconsin-Extension

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
/**
 * @license		http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 * @package		WP-Repository\WPMU_Plugin_Stats
 * @version		2.1-beta
 */

//avoid direct calls to this file
if ( !defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

/**
 * Main class to run the plugin
 *
 * @since	1.0.0
 */
class WPMU_Plugin_Stats {
	
	/**
	 * Holds a copy of the object for easy reference.
	 *
	 * @since	1.0.0
	 * @static
	 * @access	private
	 * @var		object	$instance
	 */
	private static $instance;
	
	/**
	 * Current version of the plugin.
	 *
	 * @since	1.0.0
	 * @access	public
	 * @var		string	$version
	 */
	public $version = '2.0.1';
	
	/**
	 * Constructor. Hooks all interactions to initialize the class.
	 *
	 * @since	1.0.0
	 * @access	public
	 *
	 * @see	add_action()
	 * @see	add_filter()
	 *
	 * @return	void
	 */
	public function __construct() {
		
		add_action( 'network_admin_menu', array( $this, 'network_admin_menu'));
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'load-plugins_page_wpmu-plugin-stats', array( $this, 'load_admin_assets' ) );
		
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		
	} // END __construct()

	/**
	 * Getter method for retrieving the object instance.
	 *
	 * @since	1.0.0
	 * @static
	 * @access	public
	 *
	 * @return	object	WPMU_Plugin_Stats::$instance
	 */
	public static function get_instance() {

		return self::$instance;

	} // END get_instance()
	
	/**
	 * Fetch sites and the active plugins for every single site
	 *
	 * @since	1.0.0
	 * @access	private
	 *
	 * @see		get_plugins()
	 * @see		switch_to_blog()
	 * @see		trailingslashit()
	 * @see		get_bloginfo()
	 * @see		get_option()
	 * @see		restore_current_blog()
	 * @see		update_site_option()
	 *
	 * @global	object	$wpdb
	 * @global	array	$current_site
	 * @return	void
	 */
	private function generate_plugin_blog_list() {
		global $wpdb, $current_site;

		$blogs  = $wpdb->get_results( "SELECT blog_id, domain, path FROM " . $wpdb->blogs . " WHERE site_id = {$current_site->id} ORDER BY domain ASC" );
//		$blogplugins = array();
//		$processedplugins = array();
		$plugins = get_plugins();

		if ( $blogs ) {
			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog->blog_id );

				if ( constant( 'VHOST' ) == 'yes' ) {
					$blogurl = $blog->domain;			
				} else {
					$blogurl =  trailingslashit( $blog->domain . $blog->path );
				}

				$blog_info = array(
					'name' => get_bloginfo( 'name' ),
					'url' => $blogurl,
				);
				$active_plugins = get_option( 'active_plugins' );

				if ( sizeOf( $active_plugins ) > 0) {
					foreach ( $active_plugins as $plugin ) {

						//jason adding check for plugin existing on system
						if ( isset( $plugins[ $plugin ] ) ) {
							$this_plugin = $plugins[ $plugin ];
							
							if ( isset( $this_plugin['blogs'] ) && is_array( $this_plugin['blogs'] ) ) {
								array_push( $this_plugin['blogs'], $blog_info );
							} else {
								$this_plugin['blogs'] = array();
								array_push( $this_plugin['blogs'], $blog_info );
							}
							unset( $plugins[ $plugin] );
							$plugins[ $plugin ] = $this_plugin;
						} else {
							//this 'active' plugin is no longer on the system, so do nothing here?  (or could theoretically deactivate across all sites)
							//unset($plugins[$plugin]);
						}
					}
				} // foreach ($active_plugin as $plugin)

				restore_current_blog();
				
			}
		}

		// Set the site option to hold all this
		/*
		$old_stats = get_site_option('cets_plugin_stats_data', 'not-yet-set');
		if ($old_stats == 'not-yet-set') {
				add_site_option('cets_plugin_stats_data', $plugins);
		} else {  */
		update_site_option( 'cets_plugin_stats_data', $plugins );
		//}

		update_site_option( 'cets_plugin_stats_data_freshness', time() );

	} // END generate_plugin_blog_list()
	
	/**
	 * Add the menu item
	 *
	 * @since	1.0.0
	 * @access	public
	 *
	 * @see		add_submenu_page()
	 * @action	network_admin_menu
	 * @hook	filter	wpmu_plugin_stats_cap	Defaults 'manage_network'
	 *
	 * @return	void
	 */
	public function network_admin_menu() {
		
		add_submenu_page(
			'plugins.php',
			__( 'Plugin Statistics', 'wpmu-plugin-stats' ),
			__( 'Statistics', 'wpmu-plugin-stats' ),
			apply_filters( 'wpmu_plugin_stats_cap', 'manage_network' ),
			'wpmu-plugin-stats',
			array( $this, 'plugin_stats_page' )
		);

	} // END network_admin_menu()
	
	/**
	 * Create a function to actually display stuff on plugin usage
	 *
	 * @since	1.0.0
	 * @access	public
	 *
	 * @see		get_site_option()
	 * @see		maybe_unserialize()
	 * @uses	generate_plugin_blog_list()
	 *
	 * @return	void
	 */
	public function plugin_stats_page() {
		
		// Get the time when the plugin list was last generated
		$gen_time = get_site_option( 'cets_plugin_stats_data_freshness' );

		if ( ( time() - $gen_time ) > 3600 || ( isset( $_POST['action'] ) && $_POST['action'] == 'update' ) )  {
			// if older than an hour, regenerate, just to be safe
			$this->generate_plugin_blog_list();
		}
		
		$list = get_site_option( 'cets_plugin_stats_data' );
		ksort( $list );

		// this is the built-in sitewide activation
		$active_sitewide_plugins = maybe_unserialize( get_site_option( 'active_sitewide_plugins' ) );

		if ( time() - $gen_time > 60 ) {
			$lastregen = ( round( ( time() - $gen_time ) / 60, 0 ) ) . ' ' . __( 'minutes', 'wpmu-plugin-stats' );
		} else {
			$lastregen = __( 'less than 1 minute', 'wpmu-plugin-stats' );
		}
		?>
		<style type="text/css">
			table#wpmu-active-plugins {
				margin-top: 6px;
			}
			.bloglist {
				display:none;
			}
			span.plugin-not-found {
				color: red;
			}
			.plugins .active td.plugin-title {
				border-left: 4px solid #2EA2CC;
				font-weight: 700;
			}
		</style>
		<div class="wrap">
			<h2><?php _e( 'Plugin Statistics', 'wpmu-plugin-stats' ); ?></h2>
			<table class="wp-list-table widefat plugins" id="wpmu-active-plugins">
				<thead>
					<tr>
						<th class="nocase">
							<?php _e( 'Plugin', 'wpmu-plugin-stats' ); ?>
						</th>
						<th class="case" style="text-align: center !important">
							<?php _e( 'Activated Sitewide', 'wpmu-plugin-stats' ); ?>
						</th>
						<th class="num">
							<?php _e( 'Total Blogs', 'wpmu-plugin-stats' ); ?>
						</th>
						<th width="200px">
							<?php _e( 'Blog Titles', 'wpmu-plugin-stats' ); ?>
						</th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th class="nocase">
							<?php _e( 'Plugin', 'wpmu-plugin-stats' ); ?>
						</th>
						<th class="case" style="text-align: center !important">
							<?php _e( 'Activated Sitewide', 'wpmu-plugin-stats' ); ?>
						</th>
						<th class="num">
							<?php _e( 'Total Blogs', 'wpmu-plugin-stats' ); ?>
						</th>
						<th width="200px">
							<?php _e( 'Blog Titles', 'wpmu-plugin-stats' ); ?>
						</th>
					</tr>
				</tfoot>
				<tbody id="plugins">
					<?php
					$counter = 0;
					foreach ( $list as $file => $info ) {
						$counter = $counter + 1;
						$is_activated_sitewide = ( is_array( $active_sitewide_plugins ) && array_key_exists( $file, $active_sitewide_plugins ) ) ? true : false;
						
						// checking for non-existant plugins
						if ( isset( $info['Name'] ) ) {
							if ( strlen( $info['Name'] ) ) {
								$thisName = $info['Name'];
							} else {
								$thisName = $file;
							}
						} else {
							$thisName = $file . ' <span class="plugin-not-found">(' . __( 'Plugin File Not Found!', 'wpmu-plugin-stats' ) . ')</span>';
						}
						?>
						<tr valign="top" class="<?php echo $is_activated_sitewide ? 'active' : 'inactive'; ?>">
							<td class="plugin-title">
								<?php echo $thisName; ?>
							</td>
							<td align="center">
								<?php
								if ( $is_activated_sitewide ) {
									_e( 'Yes' );
								} else {
									_e( 'No' );
								}

								if ( isset( $info['blogs'] ) ) {
									$numBlogs = sizeOf( $info['blogs'] );
								} else {
									$numBlogs = 0;
								}
								?>
							</td>
							<td align="center">
								<?php echo $numBlogs; ?>
							</td>
							<td>
								<a href="javascript:void(0)" onClick="jQuery('#bloglist_<?php echo $counter; ?>').toggle(400);">
									<?php _e( 'Show/Hide Blogs', 'wpmu-plugin-stats' ); ?>
								</a>
								<ul class="bloglist" id="bloglist_<?php echo $counter; ?>">
									<?php
									if ( isset( $info['blogs'] ) && is_array( $info['blogs'] ) ) {
										foreach( $info['blogs'] as $blog ) {
											$link_title = empty( $blog['name'] ) ? $blog['url'] : $blog['name'];
											echo '<li><a href="http://' . $blog['url'] . '" target="new">' . $link_title . '</a></li>';
										}
									} else {
										echo '<li>' . __( 'N/A', 'wpmu-plugin-stats' ) . '</li>';
									}
									?>
								</ul>
							</td>
					<?php } ?>
				</tbody>
			</table>
				
			<?php // @TODO nonce? ?>
			<div class="tablenav bottom">
				<div class="alignleft actions bulkactions">
					<form name="plugininfoform" action="" method="post">
						<input type="submit" class="button-primary" value="<?php _e( 'Regenerate', 'wpmu-plugin-stats' ); ?>">
						<input type="hidden" name="action" value="update" />
					</form>
				</div>
			</div>
			<p>
				<?php printf( __( 'This data is not updated as blog users update their plugins. It was last generated %s ago.', 'wpmu-plugin-stats' ), $lastregen ); ?>
			</p>
		</div><!-- .wrap -->
		
	<?php
	} // END plugin_stats_page()
	
	/**
	 * Load assets on the page
	 *
	 * @since	1.0.0
	 * @access	public
	 *
	 * @see		wp_enqueue_script()
	 * @see		plugins_url()
	 * @action	load-plugins_page_wpmu-plugin-stats
	 * @hook	filter	wpmu_plugin_stats_debug	Defaults {@see WP_DEBUG}
	 *
	 * @return	void
	 */
	public function load_admin_assets() {
		
		$dev = apply_filters( 'wpmu_plugin_stats_debug', WP_DEBUG ) ? '' : '.min';

		wp_enqueue_script( 'tablesort', plugins_url( 'js/tablesort' . $dev . '.js', __FILE__ ), array(), '2.4', true );

	} // END load_admin_assets()
	
	/**
	 * Load the plugin's textdomain hooked to 'plugins_loaded'.
	 *
	 * @since	1.0.0
	 * @access	public
	 *
	 * @see		load_plugin_textdomain()
	 * @see		plugin_basename()
	 * @action	plugins_loaded
	 *
	 * @return	void
	 */
	public function load_plugin_textdomain() {
		
		load_plugin_textdomain(
			'wpmu-plugin-stats',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
		
	} // END load_plugin_textdomain()
	
	/**
	 * Add link to the GitHub repo to the plugin listing
	 *
	 * @since	1.0.0
	 * @access	public
	 *
	 * @see		plugin_basename()
	 *
	 * @param	array	$links
	 * @param	string	$file
	 * @return	array	$links
	 */
	public function plugin_row_meta( $links, $file ) {

		if ( $file == plugin_basename( __FILE__ ) ) {
			return array_merge(
				$links,
				array( '<a href="https://github.com/wp-repository/wpmu-plugin-stats" target="_blank">GitHub</a>' )
			);
		}

		return $links;
		
	} // END plugin_row_meta()

} // END class WPMU_Plugin_Stats

/**
 * Instantiate the main class
 *
 * @since	1.0.0
 * @access	public
 *
 * @var	object	$wpmu_plugin_stats holds the instantiated class {@uses WPMU_Plugin_Stats}
 */
$wpmu_plugin_stats = new WPMU_Plugin_Stats;
