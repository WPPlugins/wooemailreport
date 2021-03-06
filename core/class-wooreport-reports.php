<?php
/**
 * class-wooreport-reports.php
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

class Wooreport_Reports {
	
	/**
	 * Output the sales overview chart.
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_sales_overview() {
		global $woocommerce, $wpdb, $wp_locale;
	
		$total_sales = $total_orders = $order_items = $discount_total = $shipping_total = 0;
	
		$order_totals = apply_filters( 'woocommerce_reports_sales_overview_order_totals', $wpdb->get_row( "
				SELECT SUM(meta.meta_value) AS total_sales, COUNT(posts.ID) AS total_orders FROM {$wpdb->posts} AS posts
	
				LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
	
				WHERE 	meta.meta_key 		= '_order_total'
				AND 	posts.post_type 	= 'shop_order'
				AND 	posts.post_status 	= 'wc-completed'
		" ) );
	
		$total_sales 	= $order_totals->total_sales;
		$total_orders 	= absint( $order_totals->total_orders );
	
		$discount_total = apply_filters( 'woocommerce_reports_sales_overview_discount_total', $wpdb->get_var( "
		SELECT SUM(meta.meta_value) AS total_sales FROM {$wpdb->posts} AS posts
	
		LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
		LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
	
		WHERE 	meta.meta_key 		IN ('_order_discount', '_cart_discount')
		AND 	posts.post_type 	= 'shop_order'
		AND 	posts.post_status 	= 'wc-completed'
	" ) );
	
		$shipping_total = apply_filters( 'woocommerce_reports_sales_overview_shipping_total', $wpdb->get_var( "
				SELECT SUM(meta.meta_value) AS total_sales FROM {$wpdb->posts} AS posts
	
				LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
	
				WHERE 	meta.meta_key 		= '_order_shipping'
				AND 	posts.post_type 	= 'shop_order'
				AND 	posts.post_status 	= 'wc-completed'
		" ) );
	
		$order_items = apply_filters( 'woocommerce_reports_sales_overview_order_items', absint( $wpdb->get_var( "
				SELECT SUM( order_item_meta.meta_value )
				FROM {$wpdb->prefix}woocommerce_order_items as order_items
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
				LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
					LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
		WHERE 	posts.post_status 	= 'wc-completed'
		AND 	order_items.order_item_type = 'line_item'
			AND 	order_item_meta.meta_key = '_qty'
		" ) ) );
		
		$output['total_sales'] = wc_price($total_sales);
		$output['total_orders'] = $total_orders;
		$output['total_items'] = $order_items;
		if ( $total_orders > 0 ) {
			$output['average_total'] = wc_price($total_sales/$total_orders);
			$output['average_items'] = number_format($order_items/$total_orders, 2);
		} else {
			$output['average_total'] = wc_price( 0 );
			$output['average_items'] = 0;
		}
		$output['discounts'] = wc_price($discount_total);
		$output['shippings'] = wc_price($shipping_total);
			
		return $output;
	}
	
	
	/**
	 * Output the daily sales chart.
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_daily_sales() {
	
		global $start_date, $end_date, $woocommerce, $wpdb, $wp_locale;
	
		$start_date = isset( $_POST['start_date'] ) ? $_POST['start_date'] : '';
		$end_date	= isset( $_POST['end_date'] ) ? $_POST['end_date'] : '';
	
		if ( ! $start_date)
			$start_date = date( 'Ymd', strtotime( date('Ym', current_time( 'timestamp' ) ) . '01' ) );
		if ( ! $end_date)
			$end_date = date( 'Ymd', current_time( 'timestamp' ) );
	
		$start_date = strtotime( $start_date );
		$end_date = strtotime( $end_date );
	
		$total_sales = $total_orders = $order_items = 0;
	
		// Blank date ranges to begin
		$order_counts = $order_amounts = array();
	
		$count = 0;
	
		$days = ( $end_date - $start_date ) / ( 60 * 60 * 24 );
	
		if ( $days == 0 )
			$days = 1;
	
		while ( $count < $days ) {
			$time = strtotime( date( 'Ymd', strtotime( '+ ' . $count . ' DAY', $start_date ) ) ) . '000';
	
			$order_counts[ $time ] = $order_amounts[ $time ] = 0;
	
			$times[$time] = date( 'Y-m-d', strtotime( '+ ' . $count . ' DAY', $start_date ) );
			
			$count++;
		}
	
		// Get order ids and dates in range
		$orders = apply_filters( 'woocommerce_reports_daily_sales_orders', $wpdb->get_results( "
			SELECT posts.ID, posts.post_date, meta.meta_value AS total_sales FROM {$wpdb->posts} AS posts
	
			LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
	
			WHERE 	meta.meta_key 		= '_order_total'
			AND 	posts.post_type 	= 'shop_order'
			AND 	posts.post_status 	= 'wc-completed'
			AND 	post_date > '" . date('Y-m-d', $start_date ) . "'
			AND 	post_date < '" . date('Y-m-d', strtotime('+1 day', $end_date ) ) . "'
	
			GROUP BY posts.ID
			ORDER BY post_date ASC
		" ), $start_date, $end_date );
	
		if ( $orders ) {
	
			$total_orders = sizeof( $orders );
			$order_items = 0;
			
			foreach ( $orders as $order ) {
	
				// get order timestamp
				$time = strtotime( date( 'Ymd', strtotime( $order->post_date ) ) ) . '000';
	
				// Add order total
				$total_sales += $order->total_sales;
	
				// Get items
				$order_items += apply_filters( 'woocommerce_reports_daily_sales_order_items', absint( $wpdb->get_var( $wpdb->prepare( "
					SELECT SUM( order_item_meta.meta_value )
					FROM {$wpdb->prefix}woocommerce_order_items as order_items
					LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
					WHERE	order_id = %d
					AND 	order_items.order_item_type = 'line_item'
					AND 	order_item_meta.meta_key = '_qty'
				", $order->ID ) ) ), $order->ID );
	
				// Set times
				if ( isset( $order_counts[ $time ] ) )
					$order_counts[ $time ]++;
				else
					$order_counts[ $time ] = 1;
	
				if ( isset( $order_amounts[ $time ] ) )
					$order_amounts[ $time ] = $order_amounts[ $time ] + $order->total_sales;
				else
					$order_amounts[ $time ] = floatval( $order->total_sales );
		
				$times[$time] = ( date( 'Y-m-d',strtotime($order->post_date)));
			}
			
		}
		
		
		$data['total_sales'] = wc_price( $total_sales );
		$data['total_orders'] = $total_orders;
		$data['total_items'] = $order_items;
		if ( $total_orders > 0 ) {
			$data['average_total'] = wc_price( $total_sales / $total_orders );
			$data['average_items'] = number_format( $order_items / $total_orders, 2 );
		} else {
			$data['average_total'] = wc_price ( 0 );
			$data['average_items'] = 0;
		}
		
		$data['data']['items'] = array();
		
		
		//$order_counts_array = $order_amounts_array = array();
	
		foreach ( $order_counts as $key => $count )
			$data['data']['items'][] = array( esc_js( $times[$key] ), esc_js( $count ), esc_js( $order_amounts[$key] ) );
	
		//foreach ( $order_amounts as $key => $amount )
		//	$data['data']['order_amounts'][] = array( esc_js( $key ), esc_js( $amount ) );
	
		//$order_data = array( 'order_counts' => $order_counts_array, 'order_amounts' => $order_amounts_array );
	
		//$data['data'] = $order_data;
		
		return $data;
	}
	
	
	/**
	 * Output the monthly sales chart.
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_monthly_sales() {
	
		global $start_date, $end_date, $woocommerce, $wpdb, $wp_locale;
	
		$first_year = $wpdb->get_var( "SELECT post_date FROM $wpdb->posts WHERE post_date != 0 ORDER BY post_date ASC LIMIT 1;" );
	
		$first_year = $first_year ? date( 'Y', strtotime( $first_year ) ) : date('Y');
	
		$current_year 	= isset( $_POST['show_year'] ) ? $_POST['show_year'] : date( 'Y', current_time( 'timestamp' ) );
		$start_date 	= strtotime( $current_year . '0101' );
	
		$total_sales = $total_orders = $order_items = 0;
		$order_counts = $order_amounts = array();
	
		for ( $count = 0; $count < 12; $count++ ) {
			$time = strtotime( date('Ym', strtotime( '+ ' . $count . ' MONTH', $start_date ) ) . '01' ) . '000';
	
			if ( $time > current_time( 'timestamp' ) . '000' )
				continue;
	
			$month = date( 'Ym', strtotime(date('Ym', strtotime('+ '.$count.' MONTH', $start_date)).'01') );
	
			$months_orders = apply_filters( 'woocommerce_reports_monthly_sales_orders', $wpdb->get_row( $wpdb->prepare( "
				SELECT SUM(meta.meta_value) AS total_sales, COUNT(posts.ID) AS total_orders FROM {$wpdb->posts} AS posts
	
				LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
	
				WHERE 	meta.meta_key 		= '_order_total'
				AND 	posts.post_type 	= 'shop_order'
				AND 	posts.post_status 	= 'wc-completed'
				AND		%s 					= date_format(posts.post_date,'%%Y%%m')
			", $month ) ), $month );
	
	
	
			$order_counts[ $time ] 	= (int) $months_orders->total_orders;
			$order_amounts[ $time ] = (float) $months_orders->total_sales;
	
			$total_orders			+= (int) $months_orders->total_orders;
			$total_sales			+= (float) $months_orders->total_sales;
	
			// Count order items
			$order_items += apply_filters( 'woocommerce_reports_monthly_sales_order_items', absint( $wpdb->get_var( $wpdb->prepare( "
				SELECT SUM( order_item_meta.meta_value )
				FROM {$wpdb->prefix}woocommerce_order_items as order_items
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
				LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
				WHERE 	posts.post_status 	= 'wc-completed'
				AND		%s		 			= date_format( posts.post_date, '%%Y%%m' )
				AND 	order_items.order_item_type = 'line_item'
				AND 	order_item_meta.meta_key = '_qty'
			", $month ) ) ), $month );
		}
		?>
		<form method="post" action="">
			<p><label for="show_year"><?php _e( 'Year:', 'woocommerce' ); ?></label>
			<select name="show_year" id="show_year">
				<?php
					for ( $i = $first_year; $i <= date( 'Y' ); $i++ )
						printf('<option value="%s" %s>%s</option>', $i, selected( $current_year, $i, false ), $i );
				?>
			</select> <input type="submit" class="button" value="<?php _e( 'Show', 'woocommerce' ); ?>" /></p>
		</form>
		<div id="poststuff" class="woocommerce-reports-wrap">
			<div class="woocommerce-reports-sidebar">
				<div class="postbox">
					<h3><span><?php _e( 'Total sales for year', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ($total_sales>0) echo wc_price($total_sales); else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
				<div class="postbox">
					<h3><span><?php _e( 'Total orders for year', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ( $total_orders > 0 ) echo $total_orders . ' (' . $order_items . ' ' . __( 'items', 'woocommerce' ) . ')'; else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
				<div class="postbox">
					<h3><span><?php _e( 'Average order total for year', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ($total_orders>0) echo wc_price($total_sales/$total_orders); else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
				<div class="postbox">
					<h3><span><?php _e( 'Average order items for year', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ($total_orders>0) echo number_format($order_items/$total_orders, 2); else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
			</div>
			<div class="woocommerce-reports-main">
				<div class="postbox">
					<h3><span><?php _e( 'Monthly sales for year', 'woocommerce' ); ?></span></h3>
					<div class="inside chart">
						<div id="placeholder" style="width:100%; overflow:hidden; height:568px; position:relative;"></div>
						<div id="cart_legend"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	
		$order_counts_array = $order_amounts_array = array();
	
		foreach ( $order_counts as $key => $count )
			$order_counts_array[] = array( esc_js( $key ), esc_js( $count ) );
	
		foreach ( $order_amounts as $key => $amount )
			$order_amounts_array[] = array( esc_js( $key ), esc_js( $amount ) );
	
		$order_data = array( 'order_counts' => $order_counts_array, 'order_amounts' => $order_amounts_array );
	
		return $order_data;
		
	}
	
	
	/**
	 * Output the top sellers chart.
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_top_sellers() {
	
		global $start_date, $end_date, $woocommerce, $wpdb;
	
		$start_date = isset( $_POST['start_date'] ) ? $_POST['start_date'] : '';
		$end_date	= isset( $_POST['end_date'] ) ? $_POST['end_date'] : '';
	
		if ( ! $start_date )
			$start_date = date( 'Ymd', strtotime( date( 'Ym', current_time( 'timestamp' ) ) . '01' ) );
		if ( ! $end_date )
			 $end_date = date( 'Ymd', current_time( 'timestamp' ) );
	
		$start_date = strtotime( $start_date );
		$end_date = strtotime( $end_date );
	
		// Get order ids and dates in range
		$order_items = apply_filters( 'woocommerce_reports_top_sellers_order_items', $wpdb->get_results( "
			SELECT order_item_meta_2.meta_value as product_id, SUM( order_item_meta.meta_value ) as item_quantity FROM {$wpdb->prefix}woocommerce_order_items as order_items
	
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_2 ON order_items.order_item_id = order_item_meta_2.order_item_id
			LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
	
			WHERE 	posts.post_type 	= 'shop_order'
			AND 	posts.post_status 	= 'wc-completed'
			AND 	post_date > '" . date('Y-m-d', $start_date ) . "'
			AND 	post_date < '" . date('Y-m-d', strtotime('+1 day', $end_date ) ) . "'
			AND 	order_items.order_item_type = 'line_item'
			AND 	order_item_meta.meta_key = '_qty'
			AND 	order_item_meta_2.meta_key = '_product_id'
			GROUP BY order_item_meta_2.meta_value
		" ), $start_date, $end_date );
	
		$found_products = array();
	
		if ( $order_items ) {
			foreach ( $order_items as $order_item ) {
				$found_products[ $order_item->product_id ] = $order_item->item_quantity;
			}
		}
	
		asort( $found_products );
		$found_products = array_reverse( $found_products, true );
		$found_products = array_slice( $found_products, 0, 25, true );
		reset( $found_products );
		?>
		<form method="post" action="">
			<p><label for="from"><?php _e( 'From:', 'woocommerce' ); ?></label> <input type="text" name="start_date" id="from" readonly="readonly" value="<?php echo esc_attr( date('Y-m-d', $start_date) ); ?>" /> <label for="to"><?php _e( 'To:', 'woocommerce' ); ?></label> <input type="text" name="end_date" id="to" readonly="readonly" value="<?php echo esc_attr( date('Y-m-d', $end_date) ); ?>" /> <input type="submit" class="button" value="<?php _e( 'Show', 'woocommerce' ); ?>" /></p>
		</form>
		<table class="bar_chart">
			<thead>
				<tr>
					<th><?php _e( 'Product', 'woocommerce' ); ?></th>
					<th><?php _e( 'Sales', 'woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
					$max_sales = current( $found_products );
					foreach ( $found_products as $product_id => $sales ) {
						$width = $sales > 0 ? ( $sales / $max_sales ) * 100 : 0;
						$product_title = get_the_title( $product_id );
	
						if ( $product_title ) {
							$product_name = '<a href="' . get_permalink( $product_id ) . '">'. __( $product_title ) .'</a>';
							$orders_link = admin_url( 'edit.php?s&post_status=all&post_type=shop_order&action=-1&s=' . urlencode( $product_title ) . '&shop_order_status=' . implode( ",", apply_filters( 'woocommerce_reports_order_statuses', array( 'completed', 'processing', 'on-hold' ) ) ) );
						} else {
							$product_name = __( 'Product does not exist', 'woocommerce' );
							$orders_link = admin_url( 'edit.php?s&post_status=all&post_type=shop_order&action=-1&s=&shop_order_status=' . implode( ",", apply_filters( 'woocommerce_reports_order_statuses', array( 'completed', 'processing', 'on-hold' ) ) ) );
						}
	
						$orders_link = apply_filters( 'woocommerce_reports_order_link', $orders_link, $product_id, $product_title );
	
						echo '<tr><th>' . $product_name . '</th><td width="1%"><span>' . esc_html( $sales ) . '</span></td><td class="bars"><a href="' . esc_url( $orders_link ) . '" style="width:' . esc_attr( $width ) . '%">&nbsp;</a></td></tr>';
					}
				?>
			</tbody>
		</table>
		<script type="text/javascript">
			jQuery(function(){
				<?php woocommerce_datepicker_js(); ?>
			});
		</script>
		<?php
	}
	
	
	/**
	 * Output the top earners chart.
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_top_earners() {
	
		global $start_date, $end_date, $woocommerce, $wpdb;
	
		$start_date = isset( $_POST['start_date'] ) ? $_POST['start_date'] : '';
		$end_date	= isset( $_POST['end_date'] ) ? $_POST['end_date'] : '';
	
		if ( ! $start_date )
			$start_date = date( 'Ymd', strtotime( date('Ym', current_time( 'timestamp' ) ) . '01' ) );
		if ( ! $end_date )
			$end_date = date( 'Ymd', current_time( 'timestamp' ) );
	
		$start_date = strtotime( $start_date );
		$end_date = strtotime( $end_date );
	
		// Get order ids and dates in range
		$order_items = apply_filters( 'woocommerce_reports_top_earners_order_items', $wpdb->get_results( "
			SELECT order_item_meta_2.meta_value as product_id, SUM( order_item_meta.meta_value ) as line_total FROM {$wpdb->prefix}woocommerce_order_items as order_items
	
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_2 ON order_items.order_item_id = order_item_meta_2.order_item_id
			LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
	
			WHERE 	posts.post_type 	= 'shop_order'
			AND 	posts.post_status 	= 'wc-completed'
			AND 	post_date > '" . date('Y-m-d', $start_date ) . "'
			AND 	post_date < '" . date('Y-m-d', strtotime('+1 day', $end_date ) ) . "'
			AND 	order_items.order_item_type = 'line_item'
			AND 	order_item_meta.meta_key = '_line_total'
			AND 	order_item_meta_2.meta_key = '_product_id'
			GROUP BY order_item_meta_2.meta_value
		" ), $start_date, $end_date );
	
		$found_products = array();
	
		if ( $order_items ) {
			foreach ( $order_items as $order_item ) {
				$found_products[ $order_item->product_id ] = $order_item->line_total;
			}
		}
	
		asort( $found_products );
		$found_products = array_reverse( $found_products, true );
		$found_products = array_slice( $found_products, 0, 25, true );
		reset( $found_products );
		?>
		<form method="post" action="">
			<p><label for="from"><?php _e( 'From:', 'woocommerce' ); ?></label> <input type="text" name="start_date" id="from" readonly="readonly" value="<?php echo esc_attr( date('Y-m-d', $start_date) ); ?>" /> <label for="to"><?php _e( 'To:', 'woocommerce' ); ?></label> <input type="text" name="end_date" id="to" readonly="readonly" value="<?php echo esc_attr( date('Y-m-d', $end_date) ); ?>" /> <input type="submit" class="button" value="<?php _e( 'Show', 'woocommerce' ); ?>" /></p>
		</form>
		<table class="bar_chart">
			<thead>
				<tr>
					<th><?php _e( 'Product', 'woocommerce' ); ?></th>
					<th colspan="2"><?php _e( 'Sales', 'woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
					$max_sales = current( $found_products );
					foreach ( $found_products as $product_id => $sales ) {
						$width = $sales > 0 ? ( round( $sales ) / round( $max_sales ) ) * 100 : 0;
	
						$product_title = get_the_title( $product_id );
	
						if ( $product_title ) {
							$product_name = '<a href="'.get_permalink( $product_id ).'">'. __( $product_title ) .'</a>';
							$orders_link = admin_url( 'edit.php?s&post_status=all&post_type=shop_order&action=-1&s=' . urlencode( $product_title ) . '&shop_order_status=' . implode( ",", apply_filters( 'woocommerce_reports_order_statuses', array( 'completed', 'processing', 'on-hold' ) ) ) );
						} else {
							$product_name = __( 'Product no longer exists', 'woocommerce' );
							$orders_link = admin_url( 'edit.php?s&post_status=all&post_type=shop_order&action=-1&s=&shop_order_status=' . implode( ",", apply_filters( 'woocommerce_reports_order_statuses', array( 'completed', 'processing', 'on-hold' ) ) ) );
						}
	
						$orders_link = apply_filters( 'woocommerce_reports_order_link', $orders_link, $product_id, $product_title );
	
						echo '<tr><th>' . $product_name . '</th><td width="1%"><span>' . wc_price( $sales ) . '</span></td><td class="bars"><a href="' . esc_url( $orders_link ) . '" style="width:' . esc_attr( $width ) . '%">&nbsp;</a></td></tr>';
					}
				?>
			</tbody>
		</table>
		<script type="text/javascript">
			jQuery(function(){
				<?php woocommerce_datepicker_js(); ?>
			});
		</script>
		<?php
	}
	
	
	/**
	 * Output the product sales chart for single products.
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_product_sales() {
	
		global $wpdb, $woocommerce;
	
		$chosen_product_ids = ( isset( $_POST['product_ids'] ) ) ? array_map( 'absint', (array) $_POST['product_ids'] ) : '';
	
		if ( $chosen_product_ids && is_array( $chosen_product_ids ) ) {
	
			$start_date = date( 'Ym', strtotime( '-12 MONTHS', current_time('timestamp') ) ) . '01';
			$end_date 	= date( 'Ymd', current_time( 'timestamp' ) );
	
			$max_sales = $max_totals = 0;
			$product_sales = $product_totals = array();
	
			// Get titles and ID's related to product
			$chosen_product_titles = array();
			$children_ids = array();
	
			foreach ( $chosen_product_ids as $product_id ) {
				$children = (array) get_posts( 'post_parent=' . $product_id . '&fields=ids&post_status=any&numberposts=-1' );
				$children_ids = $children_ids + $children;
				$chosen_product_titles[] = get_the_title( $product_id );
			}
	
			// Get order items
			$order_items = apply_filters( 'woocommerce_reports_product_sales_order_items', $wpdb->get_results( "
				SELECT order_item_meta_2.meta_value as product_id, posts.post_date, SUM( order_item_meta.meta_value ) as item_quantity, SUM( order_item_meta_3.meta_value ) as line_total
				FROM {$wpdb->prefix}woocommerce_order_items as order_items
	
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_2 ON order_items.order_item_id = order_item_meta_2.order_item_id
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_3 ON order_items.order_item_id = order_item_meta_3.order_item_id
				LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
	
				WHERE 	posts.post_type 	= 'shop_order'
				AND 	order_item_meta_2.meta_value IN ('" . implode( "','", array_merge( $chosen_product_ids, $children_ids ) ) . "')
				AND 	posts.post_status 	= 'wc-completed'
				AND 	order_items.order_item_type = 'line_item'
				AND 	order_item_meta.meta_key = '_qty'
				AND 	order_item_meta_2.meta_key = '_product_id'
				AND 	order_item_meta_3.meta_key = '_line_total'
				GROUP BY order_items.order_id
				ORDER BY posts.post_date ASC
			" ), array_merge( $chosen_product_ids, $children_ids ) );
	
			$found_products = array();
	
			if ( $order_items ) {
				foreach ( $order_items as $order_item ) {
	
					if ( $order_item->line_total == 0 && $order_item->item_quantity == 0 )
						continue;
	
					// Get date
					$date 	= date( 'Ym', strtotime( $order_item->post_date ) );
	
					// Set values
					$product_sales[ $date ] 	= isset( $product_sales[ $date ] ) ? $product_sales[ $date ] + $order_item->item_quantity : $order_item->item_quantity;
					$product_totals[ $date ] 	= isset( $product_totals[ $date ] ) ? $product_totals[ $date ] + $order_item->line_total : $order_item->line_total;
	
					if ( $product_sales[ $date ] > $max_sales )
						$max_sales = $product_sales[ $date ];
	
					if ( $product_totals[ $date ] > $max_totals )
						$max_totals = $product_totals[ $date ];
				}
			}
			?>
			<h4><?php printf( __( 'Sales for %s:', 'woocommerce' ), implode( ', ', $chosen_product_titles ) ); ?></h4>
			<table class="bar_chart">
				<thead>
					<tr>
						<th><?php _e( 'Month', 'woocommerce' ); ?></th>
						<th colspan="2"><?php _e( 'Sales', 'woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
						if ( sizeof( $product_sales ) > 0 ) {
							foreach ( $product_sales as $date => $sales ) {
								$width = ($sales>0) ? (round($sales) / round($max_sales)) * 100 : 0;
								$width2 = ($product_totals[$date]>0) ? (round($product_totals[$date]) / round($max_totals)) * 100 : 0;
	
								$orders_link = admin_url( 'edit.php?s&post_status=all&post_type=shop_order&action=-1&s=' . urlencode( implode( ' ', $chosen_product_titles ) ) . '&m=' . date( 'Ym', strtotime( $date . '01' ) ) . '&shop_order_status=' . implode( ",", apply_filters( 'woocommerce_reports_order_statuses', array( 'completed', 'processing', 'on-hold' ) ) ) );
								$orders_link = apply_filters( 'woocommerce_reports_order_link', $orders_link, $chosen_product_ids, $chosen_product_titles );
	
								echo '<tr><th><a href="' . esc_url( $orders_link ) . '">' . date_i18n( 'F', strtotime( $date . '01' ) ) . '</a></th>
								<td width="1%"><span>' . esc_html( $sales ) . '</span><span class="alt">' . wc_price( $product_totals[ $date ] ) . '</span></td>
								<td class="bars">
									<span style="width:' . esc_attr( $width ) . '%">&nbsp;</span>
									<span class="alt" style="width:' . esc_attr( $width2 ) . '%">&nbsp;</span>
								</td></tr>';
							}
						} else {
							echo '<tr><td colspan="3">' . __( 'No sales :(', 'woocommerce' ) . '</td></tr>';
						}
					?>
				</tbody>
			</table>
			<?php
	
		} else {
			?>
			<form method="post" action="">
				<p><select id="product_ids" name="product_ids[]" class="ajax_chosen_select_products" multiple="multiple" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ); ?>" style="width: 400px;"></select> <input type="submit" style="vertical-align: top;" class="button" value="<?php _e( 'Show', 'woocommerce' ); ?>" /></p>
				<script type="text/javascript">
					jQuery(function(){
	
						// Ajax Chosen Product Selectors
						jQuery("select.ajax_chosen_select_products").ajaxChosen({
						    method: 	'GET',
						    url: 		'<?php echo admin_url('admin-ajax.php'); ?>',
						    dataType: 	'json',
						    afterTypeDelay: 100,
						    data:		{
						    	action: 		'woocommerce_json_search_products',
								security: 		'<?php echo wp_create_nonce("search-products"); ?>'
						    }
						}, function (data) {
	
							var terms = {};
	
						    jQuery.each(data, function (i, val) {
						        terms[i] = val;
						    });
	
						    return terms;
						});
	
					});
				</script>
			</form>
			<?php
		}
	}
	
	
	/**
	 * Output the coupons overview stats.
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_coupons_overview() {
		global $start_date, $end_date, $woocommerce, $wpdb;
	
		$start_date = isset( $_POST['start_date'] ) ? $_POST['start_date'] : '';
		$end_date	= isset( $_POST['end_date'] ) ? $_POST['end_date'] : '';
	
		if ( ! $start_date )
			$start_date = date( 'Ymd', strtotime( date('Ym', current_time( 'timestamp' ) ) . '01' ) );
		if ( ! $end_date )
			$end_date = date( 'Ymd', current_time( 'timestamp' ) );
	
		$start_date = strtotime( $start_date );
		$end_date = strtotime( $end_date );
	
		$total_order_count = apply_filters( 'woocommerce_reports_coupons_overview_total_order_count', absint( $wpdb->get_var( "
			SELECT COUNT( DISTINCT posts.ID ) as order_count
			FROM {$wpdb->posts} AS posts
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
			WHERE 	posts.post_status 	= 'wc-completed'
			AND 	post_date > '" . date('Y-m-d', $start_date ) . "'
			AND 	post_date < '" . date('Y-m-d', strtotime('+1 day', $end_date ) ) . "'
		" ) ) );
	
		$coupon_totals = apply_filters( 'woocommerce_reports_coupons_overview_totals', $wpdb->get_row( "
			SELECT COUNT( DISTINCT posts.ID ) as order_count, SUM( order_item_meta.meta_value ) as total_discount
			FROM {$wpdb->prefix}woocommerce_order_items as order_items
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
			LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
			WHERE 	posts.post_status 	= 'wc-completed'
			AND 	order_items.order_item_type = 'coupon'
			AND 	order_item_meta.meta_key = 'discount_amount'
			AND 	post_date > '" . date('Y-m-d', $start_date ) . "'
			AND 	post_date < '" . date('Y-m-d', strtotime('+1 day', $end_date ) ) . "'
		" ) );
	
		$coupons_by_count = apply_filters( 'woocommerce_reports_coupons_overview_coupons_by_count', $wpdb->get_results( "
			SELECT COUNT( order_items.order_id ) as count, order_items.*
			FROM {$wpdb->prefix}woocommerce_order_items as order_items
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
			LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
			WHERE 	posts.post_status 	= 'wc-completed'
			AND 	order_items.order_item_type = 'coupon'
			AND 	order_item_meta.meta_key = 'discount_amount'
			AND 	order_items.order_item_name != ''
			AND 	post_date > '" . date('Y-m-d', $start_date ) . "'
			AND 	post_date < '" . date('Y-m-d', strtotime('+1 day', $end_date ) ) . "'
			GROUP BY order_items.order_item_name
			ORDER BY count DESC
			LIMIT 15
		" ) );
	
		$coupons_by_amount = apply_filters( 'woocommerce_reports_coupons_overview_coupons_by_count', $wpdb->get_results( "
			SELECT SUM( order_item_meta.meta_value ) as amount, order_items.*
			FROM {$wpdb->prefix}woocommerce_order_items as order_items
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
			LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
			WHERE 	posts.post_status 	= 'wc-completed'
			AND 	order_items.order_item_type = 'coupon'
			AND 	order_item_meta.meta_key = 'discount_amount'
			AND 	order_items.order_item_name != ''
			AND 	post_date > '" . date('Y-m-d', $start_date ) . "'
			AND 	post_date < '" . date('Y-m-d', strtotime('+1 day', $end_date ) ) . "'
			GROUP BY order_items.order_item_name
			ORDER BY amount DESC
			LIMIT 15
		" ) );
	
		?>
		<form method="post" action="">
			<p><label for="from"><?php _e( 'From:', 'woocommerce' ); ?></label> <input type="text" name="start_date" id="from" readonly="readonly" value="<?php echo esc_attr( date('Y-m-d', $start_date) ); ?>" /> <label for="to"><?php _e( 'To:', 'woocommerce' ); ?></label> <input type="text" name="end_date" id="to" readonly="readonly" value="<?php echo esc_attr( date('Y-m-d', $end_date) ); ?>" /> <input type="submit" class="button" value="<?php _e( 'Show', 'woocommerce' ); ?>" /></p>
		</form>
	
		<div id="poststuff" class="woocommerce-reports-wrap">
			<div class="woocommerce-reports-sidebar">
				<div class="postbox">
					<h3><span><?php _e( 'Total orders containing coupons', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ( $coupon_totals->order_count > 0 ) echo absint( $coupon_totals->order_count ); else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
				<div class="postbox">
					<h3><span><?php _e( 'Percent of orders containing coupons', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ( $coupon_totals->order_count > 0 ) echo round( $coupon_totals->order_count / $total_order_count * 100, 2 ) . '%'; else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
				<div class="postbox">
					<h3><span><?php _e( 'Total coupon discount', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ( $coupon_totals->total_discount > 0 ) echo wc_price( $coupon_totals->total_discount ); else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
			</div>
			<div class="woocommerce-reports-main">
				<div class="woocommerce-reports-left">
					<div class="postbox">
						<h3><span><?php _e( 'Most popular coupons', 'woocommerce' ); ?></span></h3>
						<div class="inside">
							<ul class="wc_coupon_list wc_coupon_list_block">
								<?php
									if ( $coupons_by_count ) {
										foreach ( $coupons_by_count as $coupon ) {
											$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'shop_coupon' AND post_status = 'publish' LIMIT 1;", $coupon->order_item_name ) );
	
											$link = $post_id ? admin_url( 'post.php?post=' . $post_id . '&action=edit' ) : admin_url( 'edit.php?s=' . esc_url( $coupon->order_item_name ) . '&post_status=all&post_type=shop_coupon' );
	
											echo '<li><a href="' . $link . '" class="code"><span><span>' . esc_html( $coupon->order_item_name ). '</span></span></a> - ' . sprintf( _n( 'Used 1 time', 'Used %d times', $coupon->count, 'woocommerce' ), absint( $coupon->count ) ) . '</li>';
										}
									} else {
										echo '<li>' . __( 'No coupons found', 'woocommerce' ) . '</li>';
									}
								?>
							</ul>
						</div>
					</div>
				</div>
				<div class="woocommerce-reports-right">
					<div class="postbox">
						<h3><span><?php _e( 'Greatest discount amount', 'woocommerce' ); ?></span></h3>
						<div class="inside">
							<ul class="wc_coupon_list wc_coupon_list_block">
								<?php
									if ( $coupons_by_amount ) {
										foreach ( $coupons_by_amount as $coupon ) {
											$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'shop_coupon' AND post_status = 'publish' LIMIT 1;", $coupon->order_item_name ) );
	
											$link = $post_id ? admin_url( 'post.php?post=' . $post_id . '&action=edit' ) : admin_url( 'edit.php?s=' . esc_url( $coupon->order_item_name ) . '&post_status=all&post_type=shop_coupon' );
	
											echo '<li><a href="' . $link . '" class="code"><span><span>' . esc_html( $coupon->order_item_name ). '</span></span></a> - ' . sprintf( __( 'Discounted %s', 'woocommerce' ), wc_price( $coupon->amount ) ) . '</li>';
										}
									} else {
										echo '<li>' . __( 'No coupons found', 'woocommerce' ) . '</li>';
									}
								?>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
		<script type="text/javascript">
			jQuery(function(){
				<?php woocommerce_datepicker_js(); ?>
			});
		</script>
		<?php
	}
	
	
	/**
	 * woocommerce_coupon_discounts function.
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_coupon_discounts() {
		global $start_date, $end_date, $woocommerce, $wpdb, $wp_locale;
	
		$first_year = $wpdb->get_var( "SELECT post_date FROM $wpdb->posts WHERE post_date != 0 AND post_type='shop_order' ORDER BY post_date ASC LIMIT 1;" );
		$first_year = ( $first_year ) ? date( 'Y', strtotime( $first_year ) ) : date( 'Y' );
	
		$current_year 	= isset( $_POST['show_year'] ) 	? absint( $_POST['show_year'] ) : date( 'Y', current_time( 'timestamp' ) );
		$start_date 	= strtotime( $current_year . '0101' );
	
		$order_statuses = implode( "','", apply_filters( 'woocommerce_reports_order_statuses', array( 'completed', 'processing', 'on-hold' ) ) );
	
		$used_coupons = apply_filters( 'woocommerce_reports_coupons_sales_used_coupons', $wpdb->get_col( "
			SELECT order_items.order_item_name
			FROM {$wpdb->prefix}woocommerce_order_items as order_items
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
			LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
			WHERE 	posts.post_status 	= 'wc-completed'
			AND 	order_items.order_item_type = 'coupon'
			AND 	order_item_meta.meta_key = 'discount_amount'
			AND 	order_items.order_item_name != ''
			GROUP BY order_items.order_item_name
			ORDER BY order_items.order_item_name ASC
		" ) );
		?>
	
		<form method="post" action="" class="report_filters">
			<p>
				<label for="show_year"><?php _e( 'Show:', 'woocommerce' ); ?></label>
				<select name="show_year" id="show_year">
					<?php
						for ( $i = $first_year; $i <= date( 'Y' ); $i++ )
							printf( '<option value="%s" %s>%s</option>', $i, selected( $current_year, $i, false ), $i );
					?>
				</select>
	
				<select multiple="multiple" class="chosen_select" id="show_coupons" name="show_coupons[]" style="width: 300px;">
					<?php
						foreach ( $used_coupons as $coupon )
							echo '<option value="' . $coupon . '" ' . selected( ! empty( $_POST['show_coupons'] ) && in_array( $coupon, $_POST['show_coupons'] ), true ) . '>' . $coupon . '</option>';
					?>
				</select>
	
				<input type="submit" class="button" value="<?php _e( 'Show', 'woocommerce' ); ?>" />
			</p>
		</form>
	
		<?php
	
		if ( ! empty( $_POST['show_coupons'] ) && count( $_POST['show_coupons'] ) > 0 ) {
	
			$coupons = $_POST['show_coupons'];
	
			$coupon_sales = $monthly_totals = array();
	
			foreach( $coupons as $coupon ) {
	
				$coupon_amounts = apply_filters( 'woocommerce_reports_coupon_sales_order_totals', $wpdb->get_results( $wpdb->prepare( "
					SELECT order_items.order_item_name, date_format(posts.post_date, '%%Y%%m') as month, SUM( order_item_meta.meta_value ) as discount_total
					FROM {$wpdb->prefix}woocommerce_order_items as order_items
					LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
					LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
					LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
					WHERE 	posts.post_status 	= 'wc-completed'
					AND 	order_items.order_item_type = 'coupon'
					AND 	order_item_meta.meta_key = 'discount_amount'
					AND		'{$current_year}'	= date_format(posts.post_date,'%%Y')
					AND 	order_items.order_item_name = %s
					GROUP BY month
				", $coupon ) ), $order_statuses, $current_year, $coupon );
	
				foreach( $coupon_amounts as $sales ) {
					$month = $sales->month;
					$coupon_sales[ $coupon ][ $month ] = $sales->discount_total;
				}
			}
			?>
			<div class="woocommerce-wide-reports-wrap">
				<table class="widefat">
					<thead>
						<tr>
							<th><?php _e( 'Coupon', 'woocommerce' ); ?></th>
							<?php
								$column_count = 0;
								for ( $count = 0; $count < 12; $count++ ) :
									if ( $count >= date ( 'm' ) && $current_year == date( 'Y' ) )
										continue;
									$month = date( 'Ym', strtotime( date( 'Ym', strtotime( '+ '. $count . ' MONTH', $start_date ) ) . '01' ) );
	
									// set elements before += them below
									$monthly_totals[$month] = 0;
	
									$column_count++;
									?>
									<th><?php echo date( 'F', strtotime( '2012-' . ( $count + 1 ) . '-01' ) ); ?></th>
							<?php endfor; ?>
							<th><strong><?php _e( 'Total', 'woocommerce' ); ?></strong></th>
						</tr>
					</thead>
	
					<tbody><?php
	
						// save data for chart while outputting
						$chart_data = $coupon_totals = array();
	
						foreach( $coupon_sales as $coupon_code => $sales ) {
	
							echo '<tr><th>' . esc_html( $coupon_code ) . '</th>';
	
							for ( $count = 0; $count < 12; $count ++ ) {
	
								if ( $count >= date ( 'm' ) && $current_year == date( 'Y' ) )
										continue;
	
								$month = date( 'Ym', strtotime( date( 'Ym', strtotime( '+ '. $count . ' MONTH', $start_date ) ) . '01' ) );
	
								$amount = isset( $sales[$month] ) ? $sales[$month] : 0;
								echo '<td>' . wc_price( $amount ) . '</td>';
	
								$monthly_totals[$month] += $amount;
	
								$chart_data[$coupon_code][] = array( strtotime( date( 'Ymd', strtotime( $month . '01' ) ) ) . '000', $amount );
	
							}
	
							echo '<td><strong>' . wc_price( array_sum( $sales ) ) . '</strong></td>';
	
							// total sales across all months
							$coupon_totals[$coupon_code] = array_sum( $sales );
	
							echo '</tr>';
	
						}
	
						if ( $coupon_totals ) {
	
							$top_coupon_name = current( array_keys( $coupon_totals, max( $coupon_totals ) ) );
							$top_coupon_sales = $coupon_totals[$top_coupon_name];
	
							$worst_coupon_name = current( array_keys( $coupon_totals, min( $coupon_totals ) ) );
							$worst_coupon_sales = $coupon_totals[$worst_coupon_name];
	
							$median_coupon_sales = array_values( $coupon_totals );
							sort($median_coupon_sales);
	
						} else {
							$top_coupon_name = $top_coupon_sales = $worst_coupon_name = $worst_coupon_sales = $median_coupon_sales = '';
						}
	
						echo '<tr><th><strong>' . __( 'Total', 'woocommerce' ) . '</strong></th>';
	
						foreach( $monthly_totals as $month => $totals )
							echo '<td><strong>' . wc_price( $totals ) . '</strong></td>';
	
						echo '<td><strong>' .  wc_price( array_sum( $monthly_totals ) ) . '</strong></td></tr>';
	
					?></tbody>
				</table>
			</div>
	
			<?php if ( sizeof( $coupon_totals ) > 1 ) : ?>
			<div id="poststuff" class="woocommerce-reports-wrap">
				<div class="woocommerce-reports-sidebar">
					<div class="postbox">
						<h3><span><?php _e( 'Top coupon', 'woocommerce' ); ?></span></h3>
						<div class="inside">
							<p class="stat"><?php
								echo $top_coupon_name . ' (' . wc_price( $top_coupon_sales ) . ')';
							?></p>
						</div>
					</div>
					<div class="postbox">
						<h3><span><?php _e( 'Worst coupon', 'woocommerce' ); ?></span></h3>
						<div class="inside">
							<p class="stat"><?php
								echo $worst_coupon_name . ' (' . wc_price( $worst_coupon_sales ) . ')';
							?></p>
						</div>
					</div>
					<div class="postbox">
						<h3><span><?php _e( 'Discount average', 'woocommerce' ); ?></span></h3>
						<div class="inside">
							<p class="stat"><?php
									echo wc_price( array_sum( $coupon_totals ) / count( $coupon_totals ) );
							?></p>
						</div>
					</div>
					<div class="postbox">
						<h3><span><?php _e( 'Discount median', 'woocommerce' ); ?></span></h3>
						<div class="inside">
							<p class="stat"><?php
								if ( count( $median_coupon_sales ) == 2 )
									echo __( 'N/A', 'woocommerce' );
								elseif ( count( $median_coupon_sales ) % 2 )
									echo wc_price(
										(
											$median_coupon_sales[ floor( count( $median_coupon_sales ) / 2 ) ] + $median_coupon_sales[ ceil( count( $median_coupon_sales ) / 2 ) ]
										) / 2
									);
								else
	
									echo wc_price( $median_coupon_sales[ count( $median_coupon_sales ) / 2 ] );
							?></p>
						</div>
					</div>
				</div>
				<div class="woocommerce-reports-main">
					<div class="postbox">
						<h3><span><?php _e( 'Monthly discounts by coupon', 'woocommerce' ); ?></span></h3>
						<div class="inside chart">
							<div id="placeholder" style="width:100%; overflow:hidden; height:568px; position:relative;"></div>
							<div id="cart_legend"></div>
						</div>
					</div>
				</div>
			</div>
			<script type="text/javascript">
				jQuery(function(){
	
					<?php
						// Variables
						foreach ( $chart_data as $name => $data ) {
							$varname = 'coupon_' . str_replace( '-', '_', sanitize_title( $name ) ) . '_data';
							echo 'var ' . $varname . ' = jQuery.parseJSON( \'' . json_encode( $data ) . '\' );';
						}
					?>
	
					var placeholder = jQuery("#placeholder");
	
					var plot = jQuery.plot(placeholder, [
						<?php
						$labels = array();
	
						foreach ( $chart_data as $name => $data ) {
							$labels[] = '{ label: "' . esc_js( $name ) . '", data: ' . 'coupon_' . str_replace( '-', '_', sanitize_title( $name ) ) . '_data }';
						}
	
						echo implode( ',', $labels );
						?>
					], {
						legend: {
							container: jQuery('#cart_legend'),
							noColumns: 2
						},
						series: {
							lines: { show: true, fill: true },
							points: { show: true, align: "left" }
						},
						grid: {
							show: true,
							aboveData: false,
							color: '#aaa',
							backgroundColor: '#fff',
							borderWidth: 2,
							borderColor: '#aaa',
							clickable: false,
							hoverable: true
						},
						xaxis: {
							mode: "time",
							timeformat: "%b %y",
							monthNames: <?php echo json_encode( array_values( $wp_locale->month_abbrev ) ) ?>,
							tickLength: 1,
							minTickSize: [1, "month"]
						},
						yaxes: [ { min: 0, tickDecimals: 2 } ]
				 	});
	
				 	placeholder.resize();
	
					<?php woocommerce_tooltip_js(); ?>
				});
			</script>
			<?php endif; ?>
			<?php
		} // end POST check
		?>
		<script type="text/javascript">
			jQuery(function(){
				jQuery("select.chosen_select").chosen();
			});
		</script>
		<?php
	}
	
	
	/**
	 * Output the customer overview stats.
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_customer_overview() {
	
		global $start_date, $end_date, $woocommerce, $wpdb, $wp_locale;
	
		$total_customers = 0;
		$total_customer_sales = 0;
		$total_guest_sales = 0;
		$total_customer_orders = 0;
		$total_guest_orders = 0;
	
		$users_query = new WP_User_Query( array(
			'fields' => array('user_registered'),
			'role' => 'customer'
			) );
		$customers = $users_query->get_results();
		$total_customers = (int) sizeof($customers);
	
		$customer_orders = apply_filters( 'woocommerce_reports_customer_overview_customer_orders', $wpdb->get_row( "
			SELECT SUM(meta.meta_value) AS total_sales, COUNT(posts.ID) AS total_orders FROM {$wpdb->posts} AS posts
	
			LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
	
			WHERE 	meta.meta_key 		= '_order_total'
			AND 	posts.post_type 	= 'shop_order'
			AND 	posts.post_status 	= 'wc-completed'
			AND		posts.ID			IN (
				SELECT post_id FROM {$wpdb->postmeta}
				WHERE 	meta_key 		= '_customer_user'
				AND		meta_value		> 0
			)
		" ) );
	
		$total_customer_sales	= $customer_orders->total_sales;
		$total_customer_orders	= absint( $customer_orders->total_orders );
	
		$guest_orders = apply_filters( 'woocommerce_reports_customer_overview_guest_orders', $wpdb->get_row( "
			SELECT SUM(meta.meta_value) AS total_sales, COUNT(posts.ID) AS total_orders FROM {$wpdb->posts} AS posts
	
			LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
	
			WHERE 	meta.meta_key 		= '_order_total'
			AND 	posts.post_type 	= 'shop_order'
			AND 	posts.post_status 	= 'wc-completed'
			AND		posts.ID			IN (
				SELECT post_id FROM {$wpdb->postmeta}
				WHERE 	meta_key 		= '_customer_user'
				AND		meta_value		= 0
			)
		" ) );
	
		$total_guest_sales	= $guest_orders->total_sales;
		$total_guest_orders	= absint( $guest_orders->total_orders );
		?>
		<div id="poststuff" class="woocommerce-reports-wrap">
			<div class="woocommerce-reports-sidebar">
				<div class="postbox">
					<h3><span><?php _e( 'Total customers', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ($total_customers>0) echo $total_customers; else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
				<div class="postbox">
					<h3><span><?php _e( 'Total customer sales', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ($total_customer_sales>0) echo wc_price($total_customer_sales); else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
				<div class="postbox">
					<h3><span><?php _e( 'Total guest sales', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ($total_guest_sales>0) echo wc_price($total_guest_sales); else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
				<div class="postbox">
					<h3><span><?php _e( 'Total customer orders', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ($total_customer_orders>0) echo $total_customer_orders; else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
				<div class="postbox">
					<h3><span><?php _e( 'Total guest orders', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ($total_guest_orders>0) echo $total_guest_orders; else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
				<div class="postbox">
					<h3><span><?php _e( 'Average orders per customer', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php if ($total_customer_orders>0 && $total_customers>0) echo number_format($total_customer_orders/$total_customers, 2); else _e( 'n/a', 'woocommerce' ); ?></p>
					</div>
				</div>
			</div>
			<div class="woocommerce-reports-main">
				<div class="postbox">
					<h3><span><?php _e( 'Signups per day', 'woocommerce' ); ?></span></h3>
					<div class="inside chart">
						<div id="placeholder" style="width:100%; overflow:hidden; height:568px; position:relative;"></div>
						<div id="cart_legend"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	
		$start_date = strtotime('-30 days', current_time('timestamp'));
		$end_date = current_time('timestamp');
		$signups = array();
	
		// Blank date ranges to begin
		$count = 0;
		$days = ($end_date - $start_date) / (60 * 60 * 24);
		if ($days==0) $days = 1;
	
		while ($count < $days) :
			$time = strtotime(date('Ymd', strtotime('+ '.$count.' DAY', $start_date))).'000';
	
			$signups[ $time ] = 0;
	
			$count++;
		endwhile;
	
		foreach ($customers as $customer) :
			if (strtotime($customer->user_registered) > $start_date) :
				$time = strtotime(date('Ymd', strtotime($customer->user_registered))).'000';
	
				if (isset($signups[ $time ])) :
					$signups[ $time ]++;
				else :
					$signups[ $time ] = 1;
				endif;
			endif;
		endforeach;
	
		$signups_array = array();
		foreach ($signups as $key => $count) :
			$signups_array[] = array( esc_js( $key ), esc_js( $count ) );
		endforeach;
	
		return $signups_array;
	}
	
	
	/**
	 * Output the stock overview stats.
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_stock_overview() {
	
		global $start_date, $end_date, $woocommerce, $wpdb;
	
		// Low/No stock lists
		$lowstockamount = get_option('woocommerce_notify_low_stock_amount');
		if (!is_numeric($lowstockamount)) $lowstockamount = 1;
	
		$nostockamount = get_option('woocommerce_notify_no_stock_amount');
		if (!is_numeric($nostockamount)) $nostockamount = 0;
	
		// Get low in stock simple/downloadable/virtual products. Grouped don't have stock. Variations need a separate query.
		$args = array(
			'post_type'			=> 'product',
			'post_status' 		=> 'publish',
			'posts_per_page' 	=> -1,
			'meta_query' => array(
				array(
					'key' 		=> '_manage_stock',
					'value' 	=> 'yes'
				),
				array(
					'key' 		=> '_stock',
					'value' 	=> $lowstockamount,
					'compare' 	=> '<=',
					'type' 		=> 'NUMERIC'
				)
			),
			'tax_query' => array(
				array(
					'taxonomy' 	=> 'product_type',
					'field' 	=> 'name',
					'terms' 	=> array('simple'),
					'operator' 	=> 'IN'
				)
			),
			'fields' => 'id=>parent'
		);
	
		$low_stock_products = (array) get_posts($args);
	
		// Get low stock product variations
		$args = array(
			'post_type'			=> 'product_variation',
			'post_status' 		=> 'publish',
			'posts_per_page' 	=> -1,
			'meta_query' => array(
				array(
					'key' 		=> '_stock',
					'value' 	=> $lowstockamount,
					'compare' 	=> '<=',
					'type' 		=> 'NUMERIC'
				),
				array(
					'key' 		=> '_stock',
					'value' 	=> array( '', false, null ),
					'compare' 	=> 'NOT IN'
				)
			),
			'fields' => 'id=>parent'
		);
	
		$low_stock_variations = (array) get_posts($args);
	
		// Get low stock variable products (where stock is set for the parent)
		$args = array(
			'post_type'			=> array('product'),
			'post_status' 		=> 'publish',
			'posts_per_page' 	=> -1,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' 		=> '_manage_stock',
					'value' 	=> 'yes'
				),
				array(
					'key' 		=> '_stock',
					'value' 	=> $lowstockamount,
					'compare' 	=> '<=',
					'type' 		=> 'NUMERIC'
				)
			),
			'tax_query' => array(
				array(
					'taxonomy' 	=> 'product_type',
					'field' 	=> 'name',
					'terms' 	=> array('variable'),
					'operator' 	=> 'IN'
				)
			),
			'fields' => 'id=>parent'
		);
	
		$low_stock_variable_products = (array) get_posts($args);
	
		// Get products marked out of stock
		$args = array(
			'post_type'			=> array( 'product' ),
			'post_status' 		=> 'publish',
			'posts_per_page' 	=> -1,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' 		=> '_stock_status',
					'value' 	=> 'outofstock'
				)
			),
			'fields' => 'id=>parent'
		);
	
		$out_of_stock_status_products = (array) get_posts($args);
	
		// Merge results
		$low_in_stock = apply_filters( 'woocommerce_reports_stock_overview_products', $low_stock_products + $low_stock_variations + $low_stock_variable_products + $out_of_stock_status_products );
		?>
		<div id="poststuff" class="woocommerce-reports-wrap halved">
			<div class="woocommerce-reports-left">
				<div class="postbox">
					<h3><span><?php _e( 'Low stock', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<?php
						if ( $low_in_stock ) {
							echo '<ul class="stock_list">';
							foreach ( $low_in_stock as $product_id => $parent ) {
	
								$stock 	= (int) get_post_meta( $product_id, '_stock', true );
								$sku	= get_post_meta( $product_id, '_sku', true );
	
								if ( $stock <= $nostockamount || in_array( $product_id, array_keys( $out_of_stock_status_products ) ) )
									continue;
	
								$title = esc_html__( get_the_title( $product_id ) );
	
								if ( $sku )
									$title .= ' (' . __( 'SKU', 'woocommerce' ) . ': ' . esc_html( $sku ) . ')';
	
								if ( get_post_type( $product_id ) == 'product' )
									$product_url = admin_url( 'post.php?post=' . $product_id . '&action=edit' );
								else
									$product_url = admin_url( 'post.php?post=' . $parent . '&action=edit' );
	
								printf( '<li><a href="%s"><small>' .  _n('%d in stock', '%d in stock', $stock, 'woocommerce') . '</small> %s</a></li>', $product_url, $stock, $title );
	
							}
							echo '</ul>';
						} else {
							echo '<p>'.__( 'No products are low in stock.', 'woocommerce' ).'</p>';
						}
						?>
					</div>
				</div>
			</div>
			<div class="woocommerce-reports-right">
				<div class="postbox">
					<h3><span><?php _e( 'Out of stock', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<?php
						if ( $low_in_stock ) {
							echo '<ul class="stock_list">';
							foreach ( $low_in_stock as $product_id => $parent ) {
	
								$stock 	= get_post_meta( $product_id, '_stock', true );
								$sku	= get_post_meta( $product_id, '_sku', true );
	
								if ( $stock > $nostockamount && ! in_array( $product_id, array_keys( $out_of_stock_status_products ) ) )
									continue;
	
								$title = esc_html__( get_the_title( $product_id ) );
	
								if ( $sku )
									$title .= ' (' . __( 'SKU', 'woocommerce' ) . ': ' . esc_html( $sku ) . ')';
	
								if ( get_post_type( $product_id ) == 'product' )
									$product_url = admin_url( 'post.php?post=' . $product_id . '&action=edit' );
								else
									$product_url = admin_url( 'post.php?post=' . $parent . '&action=edit' );
	
								if ( $stock == '' )
									printf( '<li><a href="%s"><small>' .  __('Marked out of stock', 'woocommerce') . '</small> %s</a></li>', $product_url, $title );
								else
									printf( '<li><a href="%s"><small>' .  _n('%d in stock', '%d in stock', $stock, 'woocommerce') . '</small> %s</a></li>', $product_url, $stock, $title );
	
							}
							echo '</ul>';
						} else {
							echo '<p>'.__( 'No products are out in stock.', 'woocommerce' ).'</p>';
						}
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
	
	
	/**
	 * Output the monthly tax stats.
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_monthly_taxes() {
		global $start_date, $end_date, $woocommerce, $wpdb;
	
		$first_year = $wpdb->get_var( "SELECT post_date FROM $wpdb->posts WHERE post_date != 0 ORDER BY post_date ASC LIMIT 1;" );
	
		if ( $first_year )
			$first_year = date( 'Y', strtotime( $first_year ) );
		else
			$first_year = date( 'Y' );
	
		$current_year 	= isset( $_POST['show_year'] ) 	? $_POST['show_year'] 	: date( 'Y', current_time( 'timestamp' ) );
		$start_date 	= strtotime( $current_year . '0101' );
	
		$total_tax = $total_sales_tax = $total_shipping_tax = $count = 0;
		$taxes = $tax_rows = $tax_row_labels = array();
	
		for ( $count = 0; $count < 12; $count++ ) {
	
			$time = strtotime( date('Ym', strtotime( '+ ' . $count . ' MONTH', $start_date ) ) . '01' );
	
			if ( $time > current_time( 'timestamp' ) )
				continue;
	
			$month = date( 'Ym', strtotime( date( 'Ym', strtotime( '+ ' . $count . ' MONTH', $start_date ) ) . '01' ) );
	
			$gross = $wpdb->get_var( $wpdb->prepare( "
				SELECT SUM( meta.meta_value ) AS order_tax
				FROM {$wpdb->posts} AS posts
				LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
				WHERE 	meta.meta_key 		= '_order_total'
				AND 	posts.post_type 	= 'shop_order'
				AND 	posts.post_status 	= 'wc-completed'
				AND		%s					= date_format(posts.post_date,'%%Y%%m')
			", $month ) );
	
			$shipping = $wpdb->get_var( $wpdb->prepare( "
				SELECT SUM( meta.meta_value ) AS order_tax
				FROM {$wpdb->posts} AS posts
				LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
				WHERE 	meta.meta_key 		= '_order_shipping'
				AND 	posts.post_type 	= 'shop_order'
				AND 	posts.post_status 	= 'wc-completed'
				AND		%s		 			= date_format(posts.post_date,'%%Y%%m')
			", $month ) );
	
			$order_tax = $wpdb->get_var( $wpdb->prepare( "
				SELECT SUM( meta.meta_value ) AS order_tax
				FROM {$wpdb->posts} AS posts
				LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
				WHERE 	meta.meta_key 		= '_order_tax'
				AND 	posts.post_type 	= 'shop_order'
				AND 	posts.post_status 	= 'wc-completed'
				AND		%s		 			= date_format(posts.post_date,'%%Y%%m')
			", $month ) );
	
			$shipping_tax = $wpdb->get_var( $wpdb->prepare( "
				SELECT SUM( meta.meta_value ) AS order_tax
				FROM {$wpdb->posts} AS posts
				LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
				LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
				WHERE 	meta.meta_key 		= '_order_shipping_tax'
				AND 	posts.post_type 	= 'shop_order'
				AND 	posts.post_status 	= 'wc-completed'
				AND		%s		 			= date_format(posts.post_date,'%%Y%%m')
			", $month ) );
	
			$tax_rows = $wpdb->get_results( $wpdb->prepare( "
				SELECT
					order_items.order_item_name as name,
					SUM( order_item_meta.meta_value ) as tax_amount,
					SUM( order_item_meta_2.meta_value ) as shipping_tax_amount,
					SUM( order_item_meta.meta_value + order_item_meta_2.meta_value ) as total_tax_amount
	
				FROM 		{$wpdb->prefix}woocommerce_order_items as order_items
	
				LEFT JOIN 	{$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
				LEFT JOIN 	{$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_2 ON order_items.order_item_id = order_item_meta_2.order_item_id
	
				LEFT JOIN 	{$wpdb->posts} AS posts ON order_items.order_id = posts.ID
				LEFT JOIN 	{$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
	
				WHERE 		order_items.order_item_type = 'tax'
				AND 		posts.post_type 	= 'shop_order'
				AND 		posts.post_status 	= 'wc-completed'
				AND			%s = date_format( posts.post_date,'%%Y%%m' )
				AND 		order_item_meta.meta_key = 'tax_amount'
				AND 		order_item_meta_2.meta_key = 'shipping_tax_amount'
	
				GROUP BY 	order_items.order_item_name
			", $month ) );
	
			if ( $tax_rows ) {
				foreach ( $tax_rows as $tax_row ) {
					if ( $tax_row->total_tax_amount > 0 )
						$tax_row_labels[] = $tax_row->name;
				}
			}
	
			$taxes[ date( 'M', strtotime( $month . '01' ) ) ] = array(
				'gross'			=> $gross,
				'shipping'		=> $shipping,
				'order_tax' 	=> $order_tax,
				'shipping_tax' 	=> $shipping_tax,
				'total_tax' 	=> $shipping_tax + $order_tax,
				'tax_rows'		=> $tax_rows
			);
	
			$total_sales_tax += $order_tax;
			$total_shipping_tax += $shipping_tax;
		}
		$total_tax = $total_sales_tax + $total_shipping_tax;
		?>
		<form method="post" action="">
			<p><label for="show_year"><?php _e( 'Year:', 'woocommerce' ); ?></label>
			<select name="show_year" id="show_year">
				<?php
					for ( $i = $first_year; $i <= date('Y'); $i++ )
						printf( '<option value="%s" %s>%s</option>', $i, selected( $current_year, $i, false ), $i );
				?>
			</select> <input type="submit" class="button" value="<?php _e( 'Show', 'woocommerce' ); ?>" /></p>
		</form>
		<div id="poststuff" class="woocommerce-reports-wrap">
			<div class="woocommerce-reports-sidebar">
				<div class="postbox">
					<h3><span><?php _e( 'Total taxes for year', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php
							if ( $total_tax > 0 )
								echo wc_price( $total_tax );
							else
								_e( 'n/a', 'woocommerce' );
						?></p>
					</div>
				</div>
				<div class="postbox">
					<h3><span><?php _e( 'Total product taxes for year', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php
							if ( $total_sales_tax > 0 )
								echo wc_price( $total_sales_tax );
							else
								_e( 'n/a', 'woocommerce' );
						?></p>
					</div>
				</div>
				<div class="postbox">
					<h3><span><?php _e( 'Total shipping tax for year', 'woocommerce' ); ?></span></h3>
					<div class="inside">
						<p class="stat"><?php
							if ( $total_shipping_tax > 0 )
								echo wc_price( $total_shipping_tax );
							else
								_e( 'n/a', 'woocommerce' );
						?></p>
					</div>
				</div>
			</div>
			<div class="woocommerce-reports-main">
				<table class="widefat">
					<thead>
						<tr>
							<th><?php _e( 'Month', 'woocommerce' ); ?></th>
							<th class="total_row"><?php _e( 'Total Sales', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("This is the sum of the 'Order Total' field within your orders.", 'woocommerce'); ?>" href="#">[?]</a></th>
							<th class="total_row"><?php _e( 'Total Shipping', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("This is the sum of the 'Shipping Total' field within your orders.", 'woocommerce'); ?>" href="#">[?]</a></th>
							<th class="total_row"><?php _e( 'Total Product Taxes', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("This is the sum of the 'Cart Tax' field within your orders.", 'woocommerce'); ?>" href="#">[?]</a></th>
							<th class="total_row"><?php _e( 'Total Shipping Taxes', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("This is the sum of the 'Shipping Tax' field within your orders.", 'woocommerce'); ?>" href="#">[?]</a></th>
							<th class="total_row"><?php _e( 'Total Taxes', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("This is the sum of the 'Cart Tax' and 'Shipping Tax' fields within your orders.", 'woocommerce'); ?>" href="#">[?]</a></th>
							<th class="total_row"><?php _e( 'Net profit', 'woocommerce' ); ?> <a class="tips" data-tip="<?php _e("Total sales minus shipping and tax.", 'woocommerce'); ?>" href="#">[?]</a></th>
							<?php
								$tax_row_labels = array_filter( array_unique( $tax_row_labels ) );
								foreach ( $tax_row_labels as $label )
									echo '<th class="tax_row">' . $label . '</th>';
							?>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<?php
								$total = array();
	
								foreach ( $taxes as $month => $tax ) {
									$total['gross'] = isset( $total['gross'] ) ? $total['gross'] + $tax['gross'] : $tax['gross'];
									$total['shipping'] = isset( $total['shipping'] ) ? $total['shipping'] + $tax['shipping'] : $tax['shipping'];
									$total['order_tax'] = isset( $total['order_tax'] ) ? $total['order_tax'] + $tax['order_tax'] : $tax['order_tax'];
									$total['shipping_tax'] = isset( $total['shipping_tax'] ) ? $total['shipping_tax'] + $tax['shipping_tax'] : $tax['shipping_tax'];
									$total['total_tax'] = isset( $total['total_tax'] ) ? $total['total_tax'] + $tax['total_tax'] : $tax['total_tax'];
	
									foreach ( $tax_row_labels as $label )
										foreach ( $tax['tax_rows'] as $tax_row )
											if ( $tax_row->name == $label ) {
												$total['tax_rows'][ $label ] = isset( $total['tax_rows'][ $label ] ) ? $total['tax_rows'][ $label ] + $tax_row->total_tax_amount : $tax_row->total_tax_amount;
											}
	
								}
	
								echo '
									<td>' . __( 'Total', 'woocommerce' ) . '</td>
									<td class="total_row">' . wc_price( $total['gross'] ) . '</td>
									<td class="total_row">' . wc_price( $total['shipping'] ) . '</td>
									<td class="total_row">' . wc_price( $total['order_tax'] ) . '</td>
									<td class="total_row">' . wc_price( $total['shipping_tax'] ) . '</td>
									<td class="total_row">' . wc_price( $total['total_tax'] ) . '</td>
									<td class="total_row">' . wc_price( $total['gross'] - $total['shipping'] - $total['total_tax'] ) . '</td>';
	
								foreach ( $tax_row_labels as $label )
									if ( isset( $total['tax_rows'][ $label ] ) )
										echo '<td class="tax_row">' . wc_price( $total['tax_rows'][ $label ] ) . '</td>';
									else
										echo '<td class="tax_row">' .  wc_price( 0 ) . '</td>';
							?>
						</tr>
						<tr>
							<th colspan="<?php echo 7 + sizeof( $tax_row_labels ); ?>"><button class="button toggle_tax_rows"><?php _e( 'Toggle tax rows', 'woocommerce' ); ?></button></th>
						</tr>
					</tfoot>
					<tbody>
						<?php
							foreach ( $taxes as $month => $tax ) {
								$alt = ( isset( $alt ) && $alt == 'alt' ) ? '' : 'alt';
								echo '<tr class="' . $alt . '">
									<td>' . $month . '</td>
									<td class="total_row">' . wc_price( $tax['gross'] ) . '</td>
									<td class="total_row">' . wc_price( $tax['shipping'] ) . '</td>
									<td class="total_row">' . wc_price( $tax['order_tax'] ) . '</td>
									<td class="total_row">' . wc_price( $tax['shipping_tax'] ) . '</td>
									<td class="total_row">' . wc_price( $tax['total_tax'] ) . '</td>
									<td class="total_row">' . wc_price( $tax['gross'] - $tax['shipping'] - $tax['total_tax'] ) . '</td>';
	
	
	
								foreach ( $tax_row_labels as $label ) {
	
									$row_total = 0;
	
									foreach ( $tax['tax_rows'] as $tax_row ) {
										if ( $tax_row->name == $label ) {
											$row_total = $tax_row->total_tax_amount;
										}
									}
	
									echo '<td class="tax_row">' . wc_price( $row_total ) . '</td>';
								}
	
								echo '</tr>';
							}
						?>
					</tbody>
				</table>
				<script type="text/javascript">
					jQuery('.toggle_tax_rows').click(function(){
						jQuery('.tax_row').toggle();
						jQuery('.total_row').toggle();
					});
					jQuery('.tax_row').hide();
				</script>
			</div>
		</div>
		<?php
	}
	
	
	/**
	 * woocommerce_category_sales function.
	 *
	 * @access public
	 * @return void
	 */
	public static function woocommerce_category_sales() {
		global $start_date, $end_date, $woocommerce, $wpdb, $wp_locale;
	
		$first_year = $wpdb->get_var( "SELECT post_date FROM $wpdb->posts WHERE post_date != 0 ORDER BY post_date ASC LIMIT 1;" );
		$first_year = ( $first_year ) ? date( 'Y', strtotime( $first_year ) ) : date( 'Y' );
	
		$current_year 	= isset( $_POST['show_year'] ) ? $_POST['show_year'] : date( 'Y', current_time( 'timestamp' ) );
	
		$categories = get_terms( 'product_cat', array( 'orderby' => 'name' ) );
		?>
		<form method="post" action="" class="report_filters">
			<p>
				<label for="show_year"><?php _e( 'Show:', 'woocommerce' ); ?></label>
				<select name="show_year" id="show_year">
					<?php
						for ( $i = $first_year; $i <= date( 'Y' ); $i++ )
							printf( '<option value="%s" %s>%s</option>', $i, selected( $current_year, $i, false ), $i );
					?>
				</select>
	
				<select multiple="multiple" class="chosen_select" id="show_categories" name="show_categories[]" style="width: 300px;">
					<?php
						$r = array();
						$r['pad_counts'] 	= 1;
						$r['hierarchal'] 	= 1;
						$r['hide_empty'] 	= 1;
						$r['value']			= 'id';
						$r['selected'] 		= isset( $_POST['show_categories'] ) ? $_POST['show_categories'] : '';
	
						include_once( $woocommerce->plugin_path() . '/classes/walkers/class-product-cat-dropdown-walker.php' );
	
						echo woocommerce_walk_category_dropdown_tree( $categories, 0, $r );
					?>
				</select>
	
				<input type="submit" class="button" value="<?php _e( 'Show', 'woocommerce' ); ?>" />
			</p>
		</form>
		<?php
	
		$item_sales = array();
	
		// Get order items
		$order_items = apply_filters( 'woocommerce_reports_category_sales_order_items', $wpdb->get_results( $wpdb->prepare( "
			SELECT order_item_meta_2.meta_value as product_id, posts.post_date, SUM( order_item_meta.meta_value ) as line_total
			FROM {$wpdb->prefix}woocommerce_order_items as order_items
	
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta_2 ON order_items.order_item_id = order_item_meta_2.order_item_id
			LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
			LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID
	
			WHERE 	posts.post_type 	= 'shop_order'
			AND 	posts.post_status 	= 'wc-completed'
			AND		date_format(posts.post_date,'%%Y') = %s
			AND 	order_items.order_item_type = 'line_item'
			AND 	order_item_meta.meta_key = '_line_total'
			AND 	order_item_meta_2.meta_key = '_product_id'
			GROUP BY order_items.order_item_id
			ORDER BY posts.post_date ASC
		", $current_year ) ) );
	
		if ( $order_items ) {
			foreach ( $order_items as $order_item ) {
	
				$month = date( 'm', strtotime( $order_item->post_date ) ) - 1;
	
				$item_sales[ $month ][ $order_item->product_id ] = isset( $item_sales[ $month ][ $order_item->product_id ] ) ? $item_sales[ $month ][ $order_item->product_id ] + $order_item->line_total : $order_item->line_total;
			}
		}
	
		if ( ! empty( $_POST['show_categories'] ) && sizeof( $_POST['show_categories'] ) > 0 ) {
	
			$show_categories = $include_categories = array_map( 'absint', $_POST['show_categories'] );
	
			foreach( $show_categories as $cat )
				$include_categories = array_merge( $include_categories, get_term_children( $cat, 'product_cat' ) );
	
			$categories = get_terms( 'product_cat', array( 'include' => array_unique( $include_categories ) ) );
			?>
			<div class="woocommerce-wide-reports-wrap">
				<table class="widefat">
					<thead>
						<tr>
							<th><?php _e( 'Category', 'woocommerce' ); ?></th>
							<?php
								$column_count = 0;
								for ( $count = 0; $count < 12; $count++ ) :
									if ( $count >= date ( 'm' ) && $current_year == date( 'Y' ) )
										continue;
									$column_count++;
									?>
									<th><?php echo date( 'F', strtotime( '2012-' . ( $count + 1 ) . '-01' ) ); ?></th>
							<?php endfor; ?>
							<th><strong><?php _e( 'Total', 'woocommerce' ); ?></strong></th>
						</tr>
					</thead>
					<tbody><?php
						// While outputting, lets store them for the chart
						$chart_data = $month_totals = $category_totals = array();
						$top_cat = $bottom_cat = $top_cat_name = $bottom_cat_name = '';
	
						for ( $count = 0; $count < 12; $count++ )
							if ( $count >= date( 'm' ) && $current_year == date( 'Y' ) )
								break;
							else
								$month_totals[ $count ] = 0;
	
						foreach ( $categories as $category ) {
	
							$cat_total = 0;
							$category_chart_data = $term_ids = array();
	
							$term_ids 		= get_term_children( $category->term_id, 'product_cat' );
							$term_ids[] 	= $category->term_id;
							$product_ids 	= get_objects_in_term( $term_ids, 'product_cat' );
	
							if ( $category->parent > 0 )
								$prepend = '&mdash; ';
							else
								$prepend = '';
	
							$category_sales_html = '<tr><th>' . $prepend . $category->name . '</th>';
	
							for ( $count = 0; $count < 12; $count++ ) {
	
								if ( $count >= date( 'm' ) && $current_year == date( 'Y' ) )
									continue;
	
								if ( ! empty( $item_sales[ $count ] ) ) {
									$matches = array_intersect_key( $item_sales[ $count ], array_flip( $product_ids ) );
									$total = array_sum( $matches );
									$cat_total += $total;
								} else {
									$total = 0;
								}
	
								if ( sizeof( array_intersect( $include_categories, get_ancestors( $category->term_id, 'product_cat' ) ) ) == 0 )
									$month_totals[ $count ] += $total;
	
								$category_sales_html .= '<td>' . wc_price( $total ) . '</td>';
	
								$category_chart_data[] = array( strtotime( date( 'Ymd', strtotime( '2012-' . ( $count + 1 ) . '-01' ) ) ) . '000', $total );
							}
	
							if ( $cat_total == 0 )
								continue;
	
							$category_totals[] = $cat_total;
	
							$category_sales_html .= '<td><strong>' . wc_price( $cat_total ) . '</strong></td>';
	
							$category_sales_html .= '</tr>';
	
							echo $category_sales_html;
	
							$chart_data[ $category->name ] = $category_chart_data;
	
							if ( $cat_total > $top_cat ) {
								$top_cat = $cat_total;
								$top_cat_name = $category->name;
							}
	
							if ( $cat_total < $bottom_cat || $bottom_cat === '' ) {
								$bottom_cat = $cat_total;
								$bottom_cat_name = $category->name;
							}
	
						}
	
						sort( $category_totals );
	
						echo '<tr><th><strong>' . __( 'Total', 'woocommerce' ) . '</strong></th>';
						for ( $count = 0; $count < 12; $count++ )
							if ( $count >= date( 'm' ) && $current_year == date( 'Y' ) )
								break;
							else
								echo '<td><strong>' . wc_price( $month_totals[ $count ] ) . '</strong></td>';
						echo '<td><strong>' .  wc_price( array_sum( $month_totals ) ) . '</strong></td></tr>';
	
					?></tbody>
				</table>
			</div>
	
			<div id="poststuff" class="woocommerce-reports-wrap">
				<div class="woocommerce-reports-sidebar">
					<div class="postbox">
						<h3><span><?php _e( 'Top category', 'woocommerce' ); ?></span></h3>
						<div class="inside">
							<p class="stat"><?php
								echo $top_cat_name . ' (' . wc_price( $top_cat ) . ')';
							?></p>
						</div>
					</div>
					<?php if ( sizeof( $category_totals ) > 1 ) : ?>
					<div class="postbox">
						<h3><span><?php _e( 'Worst category', 'woocommerce' ); ?></span></h3>
						<div class="inside">
							<p class="stat"><?php
								echo $bottom_cat_name . ' (' . wc_price( $bottom_cat ) . ')';
							?></p>
						</div>
					</div>
					<div class="postbox">
						<h3><span><?php _e( 'Category sales average', 'woocommerce' ); ?></span></h3>
						<div class="inside">
							<p class="stat"><?php
								if ( sizeof( $category_totals ) > 0 )
									echo wc_price( array_sum( $category_totals ) / sizeof( $category_totals ) );
								else
									echo __( 'N/A', 'woocommerce' );
							?></p>
						</div>
					</div>
					<div class="postbox">
						<h3><span><?php _e( 'Category sales median', 'woocommerce' ); ?></span></h3>
						<div class="inside">
							<p class="stat"><?php
								if ( sizeof( $category_totals ) == 0 )
									echo __( 'N/A', 'woocommerce' );
								elseif ( sizeof( $category_totals ) % 2 )
									echo wc_price(
										(
											$category_totals[ floor( sizeof( $category_totals ) / 2 ) ] + $category_totals[ ceil( sizeof( $category_totals ) / 2 ) ]
										) / 2
									);
								else
									echo wc_price( $category_totals[ sizeof( $category_totals ) / 2 ] );
							?></p>
						</div>
					</div>
					<?php endif; ?>
				</div>
				<div class="woocommerce-reports-main">
					<div class="postbox">
						<h3><span><?php _e( 'Monthly sales by category', 'woocommerce' ); ?></span></h3>
						<div class="inside chart">
							<div id="placeholder" style="width:100%; overflow:hidden; height:568px; position:relative;"></div>
							<div id="cart_legend"></div>
						</div>
					</div>
				</div>
			</div>
			<script type="text/javascript">
				jQuery(function(){
	
					<?php
						// Variables
						foreach ( $chart_data as $name => $data ) {
							$varname = 'cat_' . str_replace( '-', '_', sanitize_title( $name ) ) . '_data';
							echo 'var ' . $varname . ' = jQuery.parseJSON( \'' . json_encode( $data ) . '\' );';
						}
					?>
	
					var placeholder = jQuery("#placeholder");
	
					var plot = jQuery.plot(placeholder, [
						<?php
						$labels = array();
	
						foreach ( $chart_data as $name => $data ) {
							$labels[] = '{ label: "' . esc_js( $name ) . '", data: ' . 'cat_' . str_replace( '-', '_', sanitize_title( $name ) ) . '_data }';
						}
	
						echo implode( ',', $labels );
						?>
					], {
						legend: {
							container: jQuery('#cart_legend'),
							noColumns: 2
						},
						series: {
							lines: { show: true, fill: true },
							points: { show: true, align: "left" }
						},
						grid: {
							show: true,
							aboveData: false,
							color: '#aaa',
							backgroundColor: '#fff',
							borderWidth: 2,
							borderColor: '#aaa',
							clickable: false,
							hoverable: true
						},
						xaxis: {
							mode: "time",
							timeformat: "%b",
							monthNames: <?php echo json_encode( array_values( $wp_locale->month_abbrev ) ) ?>,
							tickLength: 1,
							minTickSize: [1, "month"]
						},
						yaxes: [ { min: 0, tickDecimals: 2 } ]
				 	});
	
				 	placeholder.resize();
	
					<?php woocommerce_tooltip_js(); ?>
				});
			</script>
			<?php
		}
		?>
		<script type="text/javascript">
			jQuery(function(){
				jQuery("select.chosen_select").chosen();
			});
		</script>
		<?php
	}
		
	
	
	
	
}
