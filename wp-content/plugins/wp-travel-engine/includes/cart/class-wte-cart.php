<?php
/**
 * WP Travel Emgine Cart.
 *
 * @package WP Travel Engine
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Travel Engine Cart Shortcode Class.
 */
class WTE_Cart {

	/**
	 * Cart id/ name.
	 *
	 * @var string
	 */
	private $cart_id;

	/**
	 * Limit of item in cart.
	 *
	 * @var integer
	 */
	private $item_limit = 0;

	/**
	 * Limit of quantity per item.
	 *
	 * @var integer
	 */
	private $quantity_limit = 99;

	/**
	 * Cart items.
	 *
	 * @var array
	 */
	private $items = array();

	/**
	 * Cart Discounts.
	 *
	 * @var array
	 */
	private $discounts = array();

	/**
	 * Cart item attributes.
	 *
	 * @var array
	 */
	private $attributes = array();

	/**
	 * Cart errors.
	 *
	 * @var array
	 */
	private $errors = array();

	/**
	 * Initialize shopping cart.
	 *
	 * @return void
	 */
	function __construct() {

		$this->cart_id = 'wpte_trip_cart';

		// Read cart data on load.
		add_action( 'plugins_loaded', array( $this, 'read_cart_onload' ), 1 );
	}

	/**
	 * Output of cart shotcode.
	 *
	 * @since 2.2.3
	 */
	public static function output() {
        $wte = new Wp_Travel_Engine_Functions();
		$wte->get_template( 'content-cart.php' );
	}

	// @since 1.3.2
	/**
	 * Add an item to cart.
	 *
	 * @param int   $id    An unique ID for the item.
	 * @param int   $price Price of item.
	 * @param int   $qty   Quantity of item.
	 * @param array $attrs Item attributes.
	 * @return boolean
	 */
	public function add( $trip_id, $trip_price = 0, $trip_price_partial = 0, $pax, $price_key = '', $attrs = array() ) {

		$cart_item_id = $this->get_cart_item_id( $trip_id, $price_key = '', $attrs['trip_date'] );

		// For additional cart item attrs.
		if ( is_array( $attrs ) && count( $attrs ) > 0 ) {
			foreach ( $attrs as $key => $attr ) {
				$this->items[ $cart_item_id ][ $key ] = $attr;
			}
		}

		if ( is_array( $pax ) ) :

			$this->items[ $cart_item_id ]['trip_id']            = $trip_id;
			$this->items[ $cart_item_id ]['trip_price']         = wp_travel_engine_get_formated_price( $trip_price );
			$this->items[ $cart_item_id ]['trip_price_partial'] = wp_travel_engine_get_formated_price( $trip_price_partial );

		endif;

		$this->write();

		return true;
	}

	/**
	 * Write changes to cart session.
	 */
	private function write() {

		$cart_attributes_session_name = $this->cart_id . '_attributes';
		$items                        = array();

		foreach ( $this->items as $id => $item ) :

			if ( ! $id )
				continue;

			$items[ $id ] = $item;

		endforeach;

		$cart['cart_items'] = $items;
		$cart['discounts']  = $this->discounts;
		$cart['attributes'] = $this->attributes;

		$cart_items  = WTE()->session->set( $this->cart_id, $cart );

		// Cookie data to enable data info in js.
		ob_start();
			setcookie( 'wpte_trip_cart', wp_json_encode( $cart ), time() + 604800, '/' );
		ob_end_flush();

	}

	/**
	 * Read items from cart session.
	 *
	 * @return void
	 */
	private function read() {

		$cart            = WTE()->session->get( $this->cart_id );
		$cart_items      = $cart['cart_items'];
		$this->discounts = isset( $cart['discounts'] ) ? $cart['discounts'] : array();
		$this->attributes = isset( $cart['attributes'] ) ? $cart['attributes'] : array();

		if ( ! empty( $cart_items ) ) :

			foreach ( $cart_items as $id => $item ) :

				if ( empty( $item ) )
					continue;

				$this->items[ $id ] = $item;

			endforeach;

		endif;
	}

	/**
	 * Update item quantity.
	 *
	 * @param  int   $cart_item_id  ID of targed item.
	 * @param  int   $qty          Quantity.
	 * @param  array $attr         Attributes of item.
	 * @return boolean
	 */
	public function update( $cart_item_id, $pax, $trip_extras = false, $attr = array() ) {

		if ( is_array( $pax ) ) {

			if ( empty( $pax ) ) {

				return $this->remove( $cart_item_id );

			}
		}

		if ( isset( $this->items[ $cart_item_id ] ) ) {

			if ( is_array( $pax ) ) {

				$trip_id       = $this->items[ $cart_item_id ]['trip_id'];
				$trip_price    = $this->items[ $cart_item_id ]['trip_price'];
				$cart_trip     = $this->items[ $cart_item_id ]['trip'];

				$trip_price         = 0;
				$trip_price_partial = 0;

				$this->items[ $cart_item_id ]['trip_price']         = $trip_price;
				$this->items[ $cart_item_id ]['trip_price_partial'] = $trip_price_partial;

			}

			$this->write();

			return true;
		}
		return false;
	}

	/**
	 * Add Discount Values
	 */
	public function add_discount_values( $discount_name, $discount_type, $discount_value ) {

		$discount_id = rand();

		$this->discounts[ $discount_id ]['name']  = $discount_name;
		$this->discounts[ $discount_id ]['type']  = $discount_type;
		$this->discounts[ $discount_id ]['value'] = $discount_value;

		$this->write();

		return true;

	}
	/**
	 * Get discounts
	 */
	public function get_discounts() {

		return $this->discounts;
	}

	/**
	 * Get list of items in cart.
	 *
	 * @return array An array of items in the cart.
	 */
	public function getItems() {
		return $this->items;
	}

	public function cart_empty_message() {
		$url = get_post_type_archive_link( 'trip' );
		echo ( __( sprintf( 'Your cart is empty please <a href="%s"> click here </a> to add trips.', $url ), 'wp-travel-engine' ) );
	}
	/**
	 * Clear all items in the cart.
	 */
	public function clear() {

		$this->items      = array();
		$this->attributes = array();
		$this->discounts  = array();

		$this->write();
	}

	/**
	 * Get all attributes.
	 *
	 * @access public
	 * @since 3.0.5
	 *
	 * @return mixed Attributes
	 */
	public function get_attributes() {
		return $this->attributes;
	}

	/**
	 * Set all attributes.
	 *
	 * @since 3.0.5
	 * @access public
	 * @param mixed $attributes Atributes
	 * @return void
	 */
	public function set_attributes( $attributes ) {
		$this->attributes = $attributes;
		$this->write();
	}

	/**
	 * Get a single attribute value.
	 *
	 * @param string $key Attribute key.
	 * @return mixed|string Attribute value.
	 */
	public function get_attribute( $key ) {
		if ( ! isset( $this->attributes[ $key ] ) ) {
			return false;
		}

		return $this->attributes[ $key ];
	}

	/**
	 * Set a single attribute value.
	 *
	 * @param string $key  Attribute key.
	 * @param mixed $value Attribute value.
	 * @return void
	 */
	public function set_attribute( $key, $value ) {
		$this->attributes[ $key ] = $value;
		$this->write();
	}


	/**
	 * Read cart items on load.
	 *
	 * @return void
	 */
	function read_cart_onload() {

		$this->read();

	}

	/**
	 * Remove item from cart.
	 *
	 * @param integer $id ID of targeted item.
	 */
	public function remove( $id ) {

		unset( $this->items[ $id ] );

		unset( $this->attributes[ $id ] );

		$this->write();
	}

	/**
	 * Get cart totals.
	 *
	 * @param boolean $with_discount - calculate with discounts.
	 * @return void
	 */
	function get_total( $with_discount = true ) {

		$trips     = $this->items;
		$discounts = $this->discounts;

		$cart_total      = 0;
		$tax_amount      = 0;
		$discount_amount = 0;
		$trip_extras_total = 0;

		$cart_total_partial      = 0;
		$tax_amount_partial      = 0;
		$discount_amount_partial = 0;

		// Total amount without tax.
		if ( is_array( $trips ) && count( $trips ) > 0 ) {
			foreach ( $trips as $cart_id => $trip ) :

				$trip_price         = $trip['trip_price'];
				$trip_price_partial = isset( $trip['trip_price_partial'] ) ? $trip['trip_price_partial'] : $trip_price;

				$cart_total         += $trip_price;
				$cart_total_partial += $trip_price_partial;

				$trip_extras_total         = 0;
				$trip_extras_total_partial = 0;

				if ( is_array( $trip['trip_extras'] ) && ! empty( $trip[ 'trip_extras' ] ) ) :

					foreach( $trip['trip_extras'] as $key => $extra ) :

						$trip_extras_total += wp_travel_engine_get_formated_price( $extra['price'] * $extra['qty'] );

					endforeach;

				endif;

				$cart_total         += $trip_extras_total;
                $cart_total_partial += $trip_extras_total_partial;

			endforeach;
		}

		$cart_total = apply_filters( 'wp_travel_engine_cart_sub_total', wp_travel_engine_get_formated_price( $cart_total ) );

		// Discounts Calculation.
		if ( ! empty( $discounts ) && $with_discount ) { // $with_discount will help to get actual total while calculating discount.

			foreach( $discounts as $key => $discount ) :

				$d_typ = $discount['type'];
				$d_val = $discount['value'];

				if ( 'fixed' === $d_typ ) {

					$discount_amount += $discount_amount_partial = $d_val;

					if ( $discount_amount_partial >= $cart_total_partial ) {
						$discount_amount_partial += 0;
					}

				} elseif ( 'percentage' === $d_typ ) {

					$discount_amount         += ( $cart_total * $d_val ) / 100;
					$discount_amount_partial += ( $cart_total_partial * $d_val ) / 100;

				}

			endforeach;

		}

		// Totals after discount.
		$total_trip_price_after_dis         = $cart_total - $discount_amount;
		$total_trip_price_partial_after_dis = $cart_total_partial - $discount_amount_partial;

		$total_trip_price         = $total_trip_price_after_dis;
		$total_trip_price_partial = $total_trip_price_partial_after_dis;

		$cart_added_trips = $this->get_cart_trip_ids();

		if ( ! empty( $cart_added_trips ) && isset( $cart_added_trips[0] ) ) :

			$partial_payment_data = wp_travel_engine_get_trip_partial_payment_data( $cart_added_trips[0] );

			if ( ! empty( $partial_payment_data ) ) :

				if( 'amount' === $partial_payment_data['type'] ) :

					$total_trip_price_partial = $partial_payment_data['value'];

				elseif( 'percentage' === $partial_payment_data['type'] ) :

					$partial            = 100 - $partial_payment_data['value'];
					$total_trip_price_partial = ( $total_trip_price ) - ( $partial / 100 ) * $total_trip_price;

				endif;

			endif;

		endif;


		$get_total = array(
			'cart_total'         => wp_travel_engine_get_formated_price( $cart_total ), // Effective for multiple cart items[cart_total].
			'discount'           => wp_travel_engine_get_formated_price( $discount_amount ),
			'sub_total'          => wp_travel_engine_get_formated_price( $total_trip_price_after_dis ),
			'total'              => wp_travel_engine_get_formated_price( $total_trip_price ),
			'trip_extras_total'  => wp_travel_engine_get_formated_price( $trip_extras_total ),

			// Partial payments.
			'cart_total_partial' => wp_travel_engine_get_formated_price( $cart_total_partial ),
			'discount_partial'   => wp_travel_engine_get_formated_price( $discount_amount_partial ),
			'sub_total_partial'  => wp_travel_engine_get_formated_price( $total_trip_price_partial_after_dis ),
			'total_partial'      => wp_travel_engine_get_formated_price( $total_trip_price_partial ),
		);

		$get_total = apply_filters( 'wp_travel_engine_cart_get_total_fields', $get_total );
		return $get_total;
	}
	/**
	 * Return cart item id as per $trip_id.
	 *
	 * @param   $trip_id    post id of trip.
	 * @param   $price_key  String  Pricing Key [ unused ]
	 *
	 * @return  String  cart item id.
	 *
	 * @since   2.2.6
	 */
	public function get_cart_item_id( $trip_id, $price_key = '', $start_date = '' ) {

        $cart_item_id = $trip_id;

		return apply_filters( 'wp_travel_engine_filter_cart_item_id', $cart_item_id, $trip_id );
	}

	/**
	 * Return cart trip id.
	 *
	 * @return  String  trip id.
	 *
	 * @since   2.2.6
	 */
	public function get_cart_trip_ids() {
		return array_column( $this->items, 'trip_id');
	}
}

new WTE_Cart();

// Set cart global variable.
$GLOBALS['wte_cart'] = new WTE_Cart();
