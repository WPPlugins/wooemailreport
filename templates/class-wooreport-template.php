<?php
/**
 * class-wooreport-template.php
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

class Wooreport_Template {

	public static function woocommerce_sales_overview ( $datas ) {
		$css = 'table { width: 100%;}
				table thead { background-color: #ccc;}
				table .numeric { text-align: right;}';
		$css = apply_filters('wooemailreport-totals-css' ,$css);
		
		$output = "<html><head><style type=\"text/css\">" . $css . "</style></head><body>";
		
		$output .= apply_filters('wooemailreport-totals-before-email' ,"");
		
		$output = "<h2>" . __("Total sales", 'wooemailreport') . "</h2>";
		$output .= "<table><tbody>";
		$output .= "<tr><td>" . __("Total sales:", 'wooemailreport') . "</td><td>" . $datas['total_sales'] . "</td></tr>";
		$output .= "<tr><td>" . __("Total orders:", 'wooemailreport') . "</td><td>" . $datas['total_orders'] . "</td></tr>";
		$output .= "<tr><td>" . __("Total items:", 'wooemailreport') . "</td><td>" . $datas['total_items'] . "</td></tr>";
		$output .= "<tr><td>" . __("Average order total:", 'wooemailreport') . "</td><td>" . $datas['average_total'] . "</td></tr>";
		$output .= "<tr><td>" . __("Average order items:", 'wooemailreport') . "</td><td>" . $datas['average_items'] . "</td></tr>";
		$output .= "<tr><td>" . __("Discounts used:", 'wooemailreport') . "</td><td>" . $datas['discounts'] . "</td></tr>";
		$output .= "<tr><td>" . __("Total shipping costs:", 'wooemailreport') . "</td><td>" . $datas['shippings'] . "</td></tr>";
		$output .= "</tbody></table>";

		$output .= apply_filters('wooemailreport-totals-after-email' ,"");
		
		$output .= "</body></html>";
		
		return $output;
	}
	
	public static function woocommerce_daily_sales ( $datas ) {
		$css = 'table { width: 100%;}
				table thead { background-color: #ccc;}
				table .numeric { text-align: right;}';
		$css = apply_filters('wooemailreport-daily-css' ,$css);
		
		$output = "<html><head><style type=\"text/css\">" . $css . "</style></head><body>";
		
		$output .= apply_filters('wooemailreport-daily-before-email' ,"");
		
		$output .= "<h2>" . __("Daily sales", 'wooemailreport') . "</h2>";
		$output .= "<h3>" . __("Resume", 'wooemailreport') . "</h3>";
		$output .= "<table><tbody>";
		$output .= "<tr><td>" . __("Total sales:", 'wooemailreport') . "</td><td>" . $datas['total_sales'] . "</td></tr>";
		$output .= "<tr><td>" . __("Total orders:", 'wooemailreport') . "</td><td>" . $datas['total_orders'] . "</td></tr>";
		$output .= "<tr><td>" . __("Total items:", 'wooemailreport') . "</td><td>" . $datas['total_items'] . "</td></tr>";
		$output .= "<tr><td>" . __("Average order total:", 'wooemailreport') . "</td><td>" . $datas['average_total'] . "</td></tr>";
		$output .= "<tr><td>" . __("Average order items:", 'wooemailreport') . "</td><td>" . $datas['average_items'] . "</td></tr>";
		$output .= "</tbody></table>";
		
		$output .= "<hr>";
		
		if ( count ($datas['data']['items']) > 0 ) {
			$output .= "<h3>" . __("Details", 'wooemailreport') . "</h3>";
			$output .= "<table><thead>";
			$output .= '<th>Date</th><th class="numeric">Num. items</th><th class="numeric">Amount</th>';
			$output .= "</thead><tbody>";
			$cnt = 0;
			foreach ($datas['data']['items'] as $sale) {
				$output .= "<tr><td>" . $sale[0] . '</td><td class="numeric">' . $sale[1] . '</td><td class="numeric">' . $sale[2] . "</td></tr>";
				$cnt ++;
			}
			$output .= "</tbody></table>";
			
		}
		
		$output .= apply_filters('wooemailreport-daily-after-email' ,"");
		
		$output .= "</body></html>";
		return $output;
	}
	
}

?>