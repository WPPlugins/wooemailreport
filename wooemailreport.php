<?php
/**
 * wooreport.php
 *
 * Copyright (c) 2011,2012 Antonio Blanco http://www.blancoleon.com
 *
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This header and all notices must be kept intact.
 *
 * @author Antonio Blanco
 * @package wooreport
 * @since wooreport 1.0.0
 *
 * Plugin Name: Woocommerce Email Report
 * Plugin URI: http://www.eggemplo.com/plugins/wooemailreport
 * Description: Send email with WooCommerce reports periodically.
 * Version: 2.1
 * Author: eggemplo
 * Author URI: http://www.eggemplo.com
 * Text Domain: wooemailreport
 * Domain Path: /languages
 * License: GPLv3
 */

define( 'WOOREPORT_FILE', __FILE__ );
define( 'WOOREPORT_URL', WP_PLUGIN_URL );

define( 'WOOREPORT_CORE_DIR', WP_PLUGIN_DIR . '/wooemailreport/core/' );

define( 'WOOREPORT_TEMPLATES_DIR', WP_PLUGIN_DIR . '/wooemailreport/templates/' );

include_once WOOREPORT_CORE_DIR . 'class-wooreport.php';

include_once WOOREPORT_CORE_DIR . 'class-wooreport-reports.php';

include_once WOOREPORT_CORE_DIR . 'class-util.php';

include_once WOOREPORT_TEMPLATES_DIR . 'class-wooreport-template.php';


class Wooreport_Plugin {
	
	private static $notices = array();
	
	public static function init() {
			
		load_plugin_textdomain( 'wooemailreport', null, 'wooemailreport/languages' );
		
		register_activation_hook( WOOREPORT_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( WOOREPORT_FILE, array( __CLASS__, 'deactivate' ) );
		
		register_uninstall_hook( WOOREPORT_FILE, array( __CLASS__, 'uninstall' ) );
		
		add_action( 'init', array( __CLASS__, 'wp_init' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
	}
	
	public static function wp_init() {
		$active_plugins = get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );
			$active_sitewide_plugins = array_keys( $active_sitewide_plugins );
			$active_plugins = array_merge( $active_plugins, $active_sitewide_plugins );
		}
		$woocommerce_is_active = in_array( 'woocommerce/woocommerce.php', $active_plugins );
		if ( !$woocommerce_is_active )  {
			self::$notices[] = "<div class='error'>" . __( '<strong>WooReport</strong> plugin requires Woocommerce.', 'wooemailreport' ) . "</div>";
		} else {

			add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 40 );
			add_action( 'admin_init', array( __CLASS__, 'register_wooreport_settings' ) );
			add_action('wooreport', array ("Wooreport", 'wooreport_cron_hook'), 10, 3);
		}
	}

	/**
	 * Register settings
	 */
	public static function register_wooreport_settings() {

	}

	public static function admin_notices() { 
		if ( !empty( self::$notices ) ) {
			foreach ( self::$notices as $notice ) {
				echo $notice;
			}
		}
	}

	/**
	 * Adds the admin section.
	 */
	public static function admin_menu() {
		$admin_page = add_submenu_page(
				'woocommerce',
				__( 'WooEmailReport' ),
				__( 'WooEmailReport' ),
				'manage_options',
				'wooreport',
				array( __CLASS__, 'wooreport_page' )
		);
		
	}

	public static function wooreport_page () {
		
		echo '<div class="wrap">';
		echo '<h2>' . __( 'WooEmailReport ', 'wooemailreport' ) . '</h2>';
		
		if ( isset( $_POST['test'] ) ) { // send a test email	

			switch ($_POST['tipo']) {
				case "totals":
					$datas = Wooreport_Reports::woocommerce_sales_overview();
					
					$output = apply_filters( 'wooreport_totals_before', "" );
					$output .= Wooreport_Template::woocommerce_sales_overview($datas);
					$output = apply_filters( 'wooreport_totals_after', $output );
					break;
				case "daily":
					$datas = Wooreport_Reports::woocommerce_daily_sales();
						
					$output = apply_filters( 'wooreport_daily_before', "" );
					$output .= Wooreport_Template::woocommerce_daily_sales($datas);
					$output = apply_filters( 'wooreport_daily_after', $output );
					break;
					
			}
			$headers[] = 'From: ' . wp_specialchars_decode( get_bloginfo( 'blogname' ), ENT_QUOTES ) . " <" . get_option('admin_email') . ">";

			$to = $_POST['emails'];
			$subject = "WooReport - Test";
			$message = $output;

			add_filter( 'wp_mail_content_type', array("Wooreport_Util", 'set_html_content_type') );

			wp_mail( $to, $subject, $message, $headers );

			// Reset content-type to avoid conflicts -- http://core.trac.wordpress.org/ticket/23578
			remove_filter( 'wp_mail_content_type', array("Wooreport_Util", 'set_html_content_type') );

		} elseif ( isset( $_POST['add'] ) ) { // add new cron
			Wooreport::addCron ( $_POST['frecuency'], $_POST['report'], $_POST['emails'] );
		}

		echo '<div style="border:1px solid #ccc; padding: 10px;">';
		echo '<form method="post" action="">
				<table class="form-table">
					<tr valign="top">
						<th scope="row">' . __( 'Frecuency:', 'wooemailreport' ) . '</th>
						<td> 
							<select name="frecuency">';

		$sche = wp_get_schedules();
		foreach ($sche as $key=>$value) {
			echo '<option value="' . $key . '">' . $value['display'] . '</option>';
		}

		$crons = _get_cron_array();

		$button_text = "Save";

		echo '				</select>
	        			</td>
	        		</tr>
					<tr valign="top">
	        			<th scope="row">' . __( 'Type report:', 'wooemailreport' ) . '</th>
		        		<td> 
	        				<select name="report">
								<option value="totals">Totals</option>
								<option value="daily">Daily</option>
	        				</select>
	        			</td>
	        		</tr>
	        		<tr valign="top">
	        			<th scope="row">' . __( 'Emails (separated by comma)', 'wooemailreport' ) . '</th>
	        			<td>
	        				<input type="text" name="emails" value=""/>		
	        			</td>
	        		</tr>
	        		<tr valign="top">
						<th scope="row">';
							submit_button($button_text, "primary", "add");
		echo 			'</th>
						<td></td>
	        		</tr>
	        	</table>
	          </form>';	
		
			if ( empty( $crons ) ) {
				echo "<p>" . __("No cron created", 'wooemailreport') . "</p>";
			}
			foreach( $crons as $timestamp => $cron ) {
				if ( ! empty( $cron["wooreport"] ) )  {
					foreach ($cron as $events) {
						foreach ( (array) $events as $event ) { 
							echo '<table class="widefat fixed" cellspacing="0">
    <thead>
    <tr>
        <tr>
            <th id="columnname" class="manage-column column-columnname" scope="col">Frecuency</th> 
            <th id="columnname" class="manage-column column-columnname" scope="col">Report type</th>
            <th id="columnname" class="manage-column column-columnname " scope="col">Emails</th> 
        </tr>
    </tr>
    </thead>

    <tbody>
        <tr class="alternate">
            <th class="column-columnname">' . $event['args']['frecuency'] . '</th>
            <td class="column-columnname">' . $event['args']['report'] . '</td>
            <td class="column-columnname">' . $event['args']['emails'] . '</td>
        </tr>
   </tbody>
   </table>';
						}
					}
				}
			}
		
		echo '</div>';

		echo '<hr>';

		echo '<div style="border:1px solid #ccc; padding: 10px;">';

		echo '<form method="post" action="">
				<table class="form-table">
					<tr valign="top">
					<td>
		';

		echo '<select name="tipo">
		<option value="totals">Totals</option>
		<option value="daily">Daily</option>
		</select>';

		echo '</td><td>';
		echo '<span>Email:</span><input type="text" name="emails" value=""/>';		
		echo '</td></tr></table>';

		submit_button("Send Test email", "primary", "test");
		settings_fields( 'wooreport-settings' );

		echo '</form>';
		echo '</div>';

		echo '</div>';

	}

	/**
	 * Plugin activation work.
	 * 
	 */
	public static function activate() {

	}

	/**
	 * Plugin deactivation.
	 *
	 */
	public static function deactivate() {
		Wooreport::delete_all_crons();
	}

	/**
	 * Plugin uninstall. 
	 *
	 */
	public static function uninstall() {
		Wooreport::delete_all_crons();	
	}

}
Wooreport_Plugin::init();
