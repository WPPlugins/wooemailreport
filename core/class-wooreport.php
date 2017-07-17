<?php
/**
 * class-wooreport.php
 *
 * Copyright (c) Antonio Blanco http://www.blancoleon.com
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
 * @author Antonio Blanco (eggemplo)
 * @package wooreport
 * @since wooreport 1.0.0
 */

/**
 * Wooreport class
 */
class Wooreport {

	static $frecuency;
	static $type;
	static $emails;

	public static function init() {

	}

	public static function addCron ($frecuency, $report, $emails) {
		
		self::delete_all_crons('wooreport');
		
		// CRON
		wp_schedule_event( time(), $frecuency , 'wooreport' , array("frecuency"=>$frecuency, "report"=>$report, "emails"=>$emails));
	
	}

	public static function wooreport_cron_hook ($frecuency, $type, $emails) {
			
		switch ($type) {
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

		$to = $emails;
		$subject = "WooEmailReport - " . $type;
		$message = $output;

		add_filter( 'wp_mail_content_type', array("Wooreport_Util", 'set_html_content_type') );

		wp_mail( $to, $subject, $message, $headers );

		// Reset content-type to avoid conflicts -- http://core.trac.wordpress.org/ticket/23578
		remove_filter( 'wp_mail_content_type', array("Wooreport_Util", 'set_html_content_type') );

	}


	public static function delete_all_crons ( $hook = "wooreport" ) {
		$crons = _get_cron_array();
		
		if ( empty( $crons ) ) {
			return;
		}
		foreach( $crons as $timestamp => $cron ) {
			if ( ! empty( $cron[$hook] ) )  {
				unset( $crons[$timestamp][$hook] );
			}
		}
		_set_cron_array( $crons );
		
		
	}

	
}
Wooreport::init();
