<?php
namespace Geovin;

class US_Pricing {


	private static $outside_region_adjust_by = 1.125;

	public function __construct() {

		// Add and save custom fields for US Pricing on products
        add_action( 'woocommerce_variation_options_pricing', array( $this, 'woo_add_custom_pricing_fields' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'woo_add_custom_pricing_fields_save' ), 10, 2 );
        add_filter( 'woocommerce_available_variation', array( $this, 'add_custom_pricing_field_variation_data'), 10, 1 );

        // set base price based on usd or cad
        add_filter( 'woocommerce_get_price_excluding_tax', array( $this, 'use_location_price' ), 10, 3 );

        // adjust price when calc totals
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'filter_cart_price_for_location' ), 9998 );
        add_filter( 'woocommerce_cart_totals_order_total_html', array( $this, 'add_formatting_to_cart_totals'), 9998, 1  ); 
        add_filter( 'woocommerce_cart_subtotal', array( $this, 'add_formatting_to_cart_subtotals'), 9998, 3  ); 

        //adjust label price based on location
        add_filter( 'woocommerce_get_price_html', array( $this, 'add_location_price_formatting'), 9998, 2 );

        //remove legacy from menu for us
        add_filter('wp_get_nav_menu_items', array( $this, 'remove_legacy' ), 10, 3 );

        add_action( 'personal_options', array( $this, 'display_country' ) );

        //add_filter( 'woocommerce_maxmind_geolocation_database_path', array( $this, 'change_geo_db_path' ), 10, 1 );
        
	}

	/**
	 * NOT CURRENTLY USED
	 * Change the path for the MaxMind GeoIP database.
	 * This is used to determine the user's location for pricing.
	 *
	 * @param string $path The current path to the GeoIP database.
	 * @return string The modified path.
	 */
	public function change_geo_db_path( $path ) {
		$path = str_replace('/code/wp-content/uploads/woocommerce_uploads', '/files/woocommerce_uploads', $path );
		return $path;
	}

	/**
	 * Display the user's geolocation country in the user profile.
	 * 
	 * @return void
	 */
	public function display_country() {
		$country = Geolocation::get_pricing_country();
		echo 'Geolocation Country is: ' . esc_html( $country );
		if ( WC()->session ) {
	        echo ' Outside 750km: ' . esc_html( WC()->session->get('outside_range') );
	    }
	}

	/**
	 * Remove the Legacy Collection menu item for non-Canadian users.
	 * 
	 * @param array $items The menu items.
	 * @param object $menu The menu object.
	 * @param object $args The menu arguments.
	 * 
	 * @return array Filtered menu items.
	 */
	public function remove_legacy( $items, $menu, $args ) {
		if ( Geolocation::get_pricing_country() !== 'CA' && ! is_admin() ) {
			$new_items = array();
			foreach( $items as $item ) {
				if ( $item->post_title !== 'Legacy Collection<small>(Soho, Hampton, ...)</small>' ) {
					$new_items[] = $item;
				}
			}
			$items = $new_items;
		}
		return $items;
	}

	/**
	 * Filter the cart price based on the user's location.
	 * 
	 * @param object $cart The WooCommerce cart object.
	 * 
	 * @return void
	 */
	public function filter_cart_price_for_location( $cart ) {

	    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	 
	    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) return;
	 
	    // LOOP THROUGH CART ITEMS 
	    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
	        $product = $cart_item['data'];

	        $price = $product->get_price();

	        $adjusted_price = $this->use_location_price( $price, $cart_item['quantity'], $cart_item['data'], 'cart' );

	        $cart_item['data']->set_price( $adjusted_price );

	    }
	 
	}

	/**
	 * Add country currency formatting to the cart totals.
	 * 
	 * @param string $value The cart total value.
	 * 
	 * @return string The formatted value with currency.
	 */
	public function add_formatting_to_cart_totals( $value ) {

		$country = Geolocation::get_pricing_country();
		if ( $country === 'CA' ) {
			$value = str_replace( '</bdi>', ' CAD</bdi>', $value);
		} elseif ( $country === 'US' ) {
			$value = str_replace( '</bdi>', ' USD</bdi>', $value);
		}
		return $value;
	}

	/**
	 * Add country currency formatting to the cart subtotals.
	 * 
	 * @param string $cart_subtotal The cart subtotal value.
	 * @param bool $compound Whether the subtotal is compound.
	 * @param object $cart The WooCommerce cart object.
	 * 
	 * @return string The formatted subtotal with currency.
	 */
	public function add_formatting_to_cart_subtotals( $cart_subtotal, $compound, $cart ) {
		$country = Geolocation::get_pricing_country();
		if ( $country === 'CA' ) {
			$cart_subtotal = str_replace( '</bdi>', ' CAD</bdi>', $cart_subtotal);
		} elseif ( $country === 'US' ) {
			$cart_subtotal = str_replace( '</bdi>', ' USD</bdi>', $cart_subtotal);
		}
		return $cart_subtotal;
	}

	/**
	 * Return the correct price based on the user's location.
	 * 
	 * @param float $return_price The original price.
	 * @param int $qty The quantity of the product.
	 * @param object $product The product object.
	 * @param string $context The context in which the price is being used (e.g, 'product', 'cart').
	 * @return float The adjusted price based on the user's location.
	 */
	public function use_location_price( $return_price, $qty, $product, $context = 'product' ) {

		if ( $context === 'product' && did_action( 'woocommerce_before_calculate_totals' ) > 0 ) {
			return $return_price;
		}

		
		/*if ( isset(WC()->session) && ! WC()->session->has_session() ) {
	        //WC()->session->set_customer_session_cookie( true );
	        //error_log('no session heree');
	    }
		if ( WC()->session->get('outside_region') === 'yes' ) {
			//error_log('filtering extra for freight');
			$raw_price = $product->get_price();
			$return_price = intval($raw_price) * self::$outside_region_adjust_by;
		}*/ // removed when disabling pricing for non-logged in users and removing MSRP 2 calculations
		
		$country = Geolocation::get_pricing_country();

		if ( $country === 'CA' ) {
			//in CA, add CAD
			
		} else {
			//show US price
			$us_price = get_post_meta( $product->get_id(), 'us_price', true );
			if ( $us_price ) {
				$return_price = $us_price;

			} 
		}

		return $return_price;
	}

	/**
	 * Add the location-based price formatting to the product price.
	 * 
	 * @param string $price The original price HTML.
	 * @param object $product The product object.
	 * @return string The formatted price with currency.
	 */
	public function add_location_price_formatting( $price, $product ) {
		
		$country = Geolocation::get_pricing_country();

		if ( $country === 'CA' ) {
			//in CA, add CAD
			
			$price = str_replace( '</bdi>', ' CAD</bdi>', $price );
		} else {
			//show US price
			$us_price = get_post_meta( $product->get_id(), 'us_price', true );

			$us_price = wc_price( $us_price );
			if ( $us_price ) {
				$price = str_replace( '</bdi>', ' USD</bdi>', $price );
			} else {
				$price = str_replace( '</bdi>', ' CAD</bdi>', $price );
			}
			
		}

		return $price;
	}

	/**
	 * Adds a US price field to the product variations.
	 * 
	 * @param int $loop The current loop index.
	 * @param array $variation_data The variation data.
	 * @param object $variation The variation object.
	 */
	public function woo_add_custom_pricing_fields( $loop, $variation_data, $variation ) {
		global $woocommerce, $post;

        woocommerce_wp_text_input(
            array(
                'id'    => 'us_price[' . $loop . ']',
                'name'  => 'us_price[' . $loop . ']',
				'class' => 'short wp_input_price',
				'label' => __( 'US Price ($)', 'woocommerce' ),
				'value' => get_post_meta( $variation->ID, 'us_price', true ),
				'data_type'     => 'price',
				'wrapper_class' => 'form-row form-row-first',
				'placeholder'   => __( 'US price (required)', 'woocommerce' ),
            )
        );

	}

	/**
	 * Save the US price field for product variations.
	 * 
	 * @param int $variation_id The variation ID.
	 * @param int $i The index of the variation in the loop.
	 * 
	 * @return void
	 */
	public function woo_add_custom_pricing_fields_save( $variation_id, $i ) {

		$us_price_field = $_POST['us_price'][$i];
   		if ( isset( $us_price_field ) ) {
   			update_post_meta( $variation_id, 'us_price', esc_attr( $us_price_field ) );
   		}
	}

	/**
	 * Add US price data to the variation data.
	 * 
	 * @param array $variations The variations data.
	 * 
	 * @return array The modified variations data with US price.
	 */
	public function add_custom_pricing_field_variation_data( $variations ) {
		$variations['us_price'] = '<div class="woocommerce_us_price_field">US Price: <span>' . get_post_meta( $variations[ 'variation_id' ], 'us_price', true ) . '</span></div>';
	   return $variations;
	}

}

$us_pricing = new US_Pricing();