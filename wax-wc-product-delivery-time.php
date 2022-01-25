<?php
/**
 * Plugin Name:       Wax WooCommerce Product Delivery Time
 * Plugin URI:        https://www.webaxones.com
 * Description:       Add a field to WooCommerce product to set an estimated delivery time in days
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author:            Loïc Antignac
 * Author URI:        https://www.webaxones.com
 * License:           Unprotected
 * Text Domain:       wax-product-delivery-time
 */

defined( 'ABSPATH' ) || exit;


/**
 * Deactivate plugin if WooCommerce is not activated.
 *
 * @return void
 */
function smm_wax_wc_product_delivery_time_activation() {
	if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 
			__( 'Sorry! To use Wax WooCommerce Product Delivery Time, you must install and activate the WooCommerce extension.', 'wax-product-delivery-time' ),
			__( 'Extension Wax WooCommerce Product Delivery Time', 'wax-product-delivery-time' ),
			array( 'back_link' => true )
		);
	}
}
register_activation_hook( __FILE__, 'smm_wax_wc_product_delivery_time_activation' );

/**
 * Adds a field to the product to define an estimated number of delivery days when backorder is allowed (out-of-stock).
 *
 * @return void
 */
function wax_add_estimated_delivery_time_in_days_to_product_stock_settings() {

	$value           = get_post_meta( get_the_ID(), '_wax_product_nb_delivery_days', true );
	$default_nb_days = get_option( 'wax_delivery_time_default_nb_days' ) ?: 0;

	if ( ! $value ) {
        $value = (int) $default_nb_days;
    }

	$args = array(
		'id'                => 'wax_product_nb_delivery_days',
        'name'              => 'wax_product_nb_delivery_days',
        'wrapper_class'     => '',
        'label'             => __( 'Estimated number of days', 'wax-product-delivery-time' ),
		'desc_tip'          => true,
		'description'       => __( 'A multiple of 7 will be displayed in number of weeks. Example: 14 days will be displayed "2 weeks", but 15 days will be displayed "15 days"', 'wax-product-delivery-time' ),
        'value'             => $value,
		'type'              => 'number',
		'custom_attributes' => array(
			'step' => '1',
			'min'  => '0',
		),
	);

	woocommerce_wp_text_input( $args );

}
add_action( 'woocommerce_product_options_stock_fields', 'wax_add_estimated_delivery_time_in_days_to_product_stock_settings', 15, 0 );


/**
 * Saves the estimated number of delivery days when backorder is allowed (out-of-stock).
 *
 * @param  mixed $post_id product id.
 * @return void
 */
function wax_save_estimated_delivery_time_in_days_to_product_stock_settings( $post_id ) {

	$wax_product_nb_delivery_days = $_POST['wax_product_nb_delivery_days'] ?? 0;
	$backorders                   = $_POST['_backorders'] ?? 'no';

	if ( 'no' === $backorders || 0 === $wax_product_nb_delivery_days ) {
		return;
	}

	$product = wc_get_product( $post_id );
	$product->update_meta_data( '_wax_product_nb_delivery_days', $wax_product_nb_delivery_days );
	$product->save();

}
add_action( 'woocommerce_process_product_meta', 'wax_save_estimated_delivery_time_in_days_to_product_stock_settings' );


/**
 * Adds a field to the variation to define an estimated number of delivery days when backorder is allowed (out-of-stock).
 *
 * @param  mixed $loop loop.
 * @param  mixed $variation_data variation_data.
 * @param  mixed $variation variation.
 * @return void
 */
function wax_add_estimated_delivery_time_in_days_to_product_variations_settings( $loop, $variation_data, $variation ) {
	$value           = $variation_data['_wax_variation_nb_delivery_days'][ $loop ];
	$default_nb_days = get_option( 'wax_delivery_time_default_nb_days' ) ?: 0;

	if ( ! $value ) {
        $value = (int) $default_nb_days;
    }

	$args = array(
		'id'                => 'wax_variation_nb_delivery_days',
        'name'              => 'wax_variation_nb_delivery_days',
        'wrapper_class'     => '',
        'label'             => __( 'Estimated number of days', 'wax-product-delivery-time' ),
        'value'             => $value,
		'type'              => 'number',
		'style'             => 'padding:5px;width:100%;',
		'custom_attributes' => array(
			'step' => '1',
			'min'  => '0',
		),
	);

	woocommerce_wp_text_input( $args );

}
add_action( 'woocommerce_variation_options_inventory', 'wax_add_estimated_delivery_time_in_days_to_product_variations_settings', 10, 3 );


/**
 * Saves the estimated number of delivery days for the variation when backorder is allowed (out-of-stock).
 *
 * @param  mixed $variation_id variation id.
 * @param  mixed $loop loop number of variation.
 * @return void
 */
function wax_save_estimated_delivery_time_in_days_to_variations_settings( $variation_id, $loop ) {

	$wax_product_nb_delivery_days = $_POST['wax_variation_nb_delivery_days'] ?? 0;
	$backorders                   = $_POST['variable_backorders'][ $loop ] ?? 'no';

	if ( 'no' === $backorders || 0 === $wax_product_nb_delivery_days ) {
		return;
	}

    update_post_meta( $variation_id, '_wax_variation_nb_delivery_days', $wax_product_nb_delivery_days );

}
add_action( 'woocommerce_save_product_variation', 'wax_save_estimated_delivery_time_in_days_to_variations_settings', 10, 2 );


/**
 * Adds JS script to show or hide estimated delivery times custom field depending on backorder choice.
 * Manages the case of simple products and variable products.
 *
 * @return void
 */
function wax_show_hide_estimated_delivery_time_field_depending_on_backorder_choice() {
    ?>
    <script type="text/javascript">
	document.addEventListener( 'DOMContentLoaded', ( event ) => {
        let productSelectBackorders = document.querySelector( 'select#_backorders' )
        let waxProductDeliveryDays  = document.querySelector( '.wax_product_nb_delivery_days_field' )

		if ( null === productSelectBackorders || null === waxProductDeliveryDays ) {
			return
		}

        if ( 'no' === productSelectBackorders.options[productSelectBackorders.selectedIndex].value ) {
			waxProductDeliveryDays.style.display = 'none'
		}

		productSelectBackorders.addEventListener( 'change', ( e ) => {
			if ( 'no' === productSelectBackorders.options[productSelectBackorders.selectedIndex].value ) {
				waxProductDeliveryDays.style.display = 'none'
			} else {
				waxProductDeliveryDays.style.display = 'block'
			}
		} )

		jQuery( document ).on( 'woocommerce_variations_loaded', function( event ) {

			let variationSelectBackorders = document.querySelectorAll( 'select[id^="variable_backorders"]' )

			variationSelectBackorders.forEach( ( selectBackorders ) => {

				let waxVariationDeliveryDays = selectBackorders.closest( '.show_if_variation_manage_stock' ).querySelector( '.wax_variation_nb_delivery_days_field' )

				if ( null !== selectBackorders && null !== waxVariationDeliveryDays ) {
					waxVariationDeliveryDays.style.display = ( 'no' === selectBackorders.options[selectBackorders.selectedIndex].value ) ? 'none' : 'block'
				}

				selectBackorders.addEventListener( 'change', ( e ) => {
					if ( 'no' === selectBackorders.options[selectBackorders.selectedIndex].value ) {
						waxVariationDeliveryDays.style.display = 'none'
					} else {
						waxVariationDeliveryDays.style.display = 'block'
					}
				} )

			} )
		} )
    } );
    </script>
    <?php
}
add_action( 'woocommerce_product_data_panels', 'wax_show_hide_estimated_delivery_time_field_depending_on_backorder_choice' );


/**
 * Add Delivery times for backorders section to WooCommerce Shipping Section in settings.
 *
 * @param  mixed $sections sections.
 * @return mixed $sections sections
 */
function wax_add_delivery_times_for_backorders_section( $sections ) {

	$sections['wax_delivery_times'] = __( 'Backorders', 'wax-product-delivery-time' );
	return $sections;

}
add_filter( 'woocommerce_get_sections_shipping', 'wax_add_delivery_times_for_backorders_section' );


function wax_delivery_get_settings( $settings, $current_section ) {

	if ( 'wax_delivery_times' === $current_section ) {

		$settings_delivery = [];

		$settings_delivery[] = [
			'name' => __( 'Delivery times for backorders', 'wax-product-delivery-time' ),
			'type' => 'title',
			'desc' => __( 'The following options are used to configure default delivery times for backorders', 'wax-product-delivery-time' ),
			'id'   => 'wax_delivery_time',
		];

		$settings_delivery[] = [
			'name'     => __( 'Default number of days', 'wax-product-delivery-time' ),
			'desc_tip' => __( 'The number of days that will be set by default if a product is allowed to be backordered', 'wax-product-delivery-time' ),
			'id'       => 'wax_delivery_time_default_nb_days',
			'type'     => 'number',
			'desc'     => __( 'Multiples of 7 will be displayed in number of weeks. Example: 14 days will be displayed in 2 weeks.', 'wax-product-delivery-time' ),
		];

		$settings_delivery[] = [
			'name'     => __( 'Delivery times text', 'wax-product-delivery-time' ),
			'desc_tip' => __( 'This will override the "Available for back order" text on product pages. Important: use %s into this text and it will be replaced with the number of days. Example: "Available for backorders within %s" will display "Available for backorders within 4 days" if previous field contains 4.', 'wax-product-delivery-time' ),
			'id'       => 'wax_delivery_time_text',
			'type'     => 'text',
			'desc'     => __( 'Don’t forget the code "%s" if you want to display the number of days!', 'wax-product-delivery-time' ),
		];

		$settings_delivery[] = [
			'type' => 'sectionend',
			'id'   => 'wax_delivery_times',
		];

		return $settings_delivery;
	}
	return $settings;
}
add_filter( 'woocommerce_get_settings_shipping', 'wax_delivery_get_settings', 10, 2 );

/**
 * Generate backorder notification text depending on delivery times settings.
 *
 * @param  object $product product.
 * @return string $text overrided backorder notification text
 */
function wax_get_backorder_text_depending_on_delivery_settings( $product ) {
	if ( 'simple' === $product->get_type() ) {
		$delivery_times_nb_days = get_post_meta( $product->get_id(), '_wax_product_nb_delivery_days', true );
	}

	if ( 'variation' === $product->get_type() ) {
		$delivery_times_nb_days = get_post_meta( $product->get_id(), '_wax_variation_nb_delivery_days', true );
	}

	$delivery_times_unit    = __( 'days', 'wax-product-delivery-time' );
	$delivery_times_delay   = (int) $delivery_times_nb_days;

	if ( 0 === (int) $delivery_times_nb_days % 7 ) {
		$delivery_times_delay = (int) $delivery_times_nb_days / 7;
		$delivery_times_unit  = __( 'weeks', 'wax-product-delivery-time' );
	}
	$delivery_times = $delivery_times_delay . ' ' . $delivery_times_unit;

	$text = get_option( 'wax_delivery_time_text' );
	$text = str_replace( '%s', $delivery_times, $text );
	return $text;
}

/**
 * Change backorder text notification on single product page.
 *
 * @param  string $text text.
 * @param  object $product product.
 * @return string $text text
 */
function wax_get_backorder_text_overrided( $text, $product ) {
    if ( $product->managing_stock() && $product->is_on_backorder() ) {
		$text = wax_get_backorder_text_depending_on_delivery_settings( $product );
    }
    return $text;
}
add_filter( 'woocommerce_get_availability_text', 'wax_get_backorder_text_overrided', 10, 2 );

/**
 * Filter woocommerce_cart_item_product_id to return real product ID in case of variable product instead of parent ID.
 *
 * @param  int $cart_item_product_id cart item product id.
 * @param  mixed $cart_item cart item.
 * @param  mixed $cart_item_key cart item key.
 * @return int $cart_item_product_id cart item product id
 */
function wax_return_woocommerce_cart_item_variation_id_instead_of_product_id( $cart_item_product_id, $cart_item, $cart_item_key ) {
    $cart_item_product_id = $cart_item['variation_id'] > 0 ? $cart_item['variation_id'] : $cart_item['product_id'];

    return $cart_item_product_id;
}
add_filter( 'woocommerce_cart_item_product_id', 'wax_return_woocommerce_cart_item_variation_id_instead_of_product_id', 10, 3 );

/**
 * Change backorder text notification on cart page.
 *
 * @param  string $html html notification.
 * @param  mixed  $product_id product id.
 * @return string $html html notification
 */
function wax_change_backorder_text_on_cart( $html, $product_id ) {
	$product = wc_get_product( $product_id );
	if ( $product->managing_stock() && $product->is_on_backorder() ) {
		$text = wax_get_backorder_text_depending_on_delivery_settings( $product );
		$html = '<p class="backorder_notification">' . $text . '</p>';
    }
	return $html;
}
add_filter( 'woocommerce_cart_item_backorder_notification', 'wax_change_backorder_text_on_cart', 10, 2 );

/**
 * Change backorder text on order item meta.
 *
 * @param  mixed $html item meta text.
 * @param  mixed $item order item.
 * @param  mixed $args args.
 * @return mixed $html item meta text
 */
function wax_change_backorder_text_on_order_item_meta( $html, $item, $args ) {
	$product_id = $item->get_product_id();
	$product    = wc_get_product( $product_id );
	if ( $product->managing_stock() && $product->is_on_backorder() && false !== strpos( $html, 'approvisionnement' ) ) {
		$text = wax_get_backorder_text_depending_on_delivery_settings( $product );
		$html = '<p class="backorder_notification">' . $text . '</p>';
    }
    return $html;
};
add_filter( 'woocommerce_display_item_meta', 'wax_change_backorder_text_on_order_item_meta', 10, 3 );
