<?php 

/**
 * Plugin Name: Geovin
 * Plugin URI: https://northuxdesign.com
 * Description: Functions specific to the Geovin.com website
 * Version: 1.0.0
 * Author: North UX
 * Author URI: https://northuxdesign.com
 * Text Domain: geovin
 *
 * @package Geovin
 */

namespace Geovin;

defined( 'ABSPATH' ) || exit;

function get_plugin_dir() {
  return plugin_dir_path( __FILE__ );
}

function get_plugin_url() {
  return plugin_dir_url( __FILE__ );
}


require_once get_plugin_dir() . '/vendor/autoload.php';

require get_plugin_dir() . 'includes/class-geolocation.php';
require get_plugin_dir() . 'includes/register-taxonomies.php';
require get_plugin_dir() . 'includes/woocommerce-filters.php';
require get_plugin_dir() . 'includes/class-gw-disable-submit.php';
require get_plugin_dir() . 'includes/class-us-pricing.php';
require get_plugin_dir() . 'includes/class-shapediver.php';
require get_plugin_dir() . 'includes/class-geovin-product-page.php';
require get_plugin_dir() . 'includes/class-gf-filters.php';
require get_plugin_dir() . 'includes/class-geovin-importer.php';
require get_plugin_dir() . 'includes/class-add-product-type.php';
require get_plugin_dir() . 'includes/class-cli.php';
require get_plugin_dir() . 'includes/class-invite-users.php';
require get_plugin_dir() . 'includes/class-geovin-dealers.php';
require get_plugin_dir() . 'includes/class-cart.php';



/**
 * Checks if a user is able to build an order.
 * 
 * @return bool True if the user can build an order, false otherwise.
 */
function user_can_build_order() {
  $has_access = false;
  if ( is_user_logged_in() ) {
    
    $user = wp_get_current_user();
    if ( in_array( 'dealer_staff', $user->roles ) || in_array( 'dealer_manager', $user->roles ) || in_array( 'administrator', $user->roles ) ) {
      $has_access = true;
    }
  }
  return $has_access;
}

/**
 * Checks if a user is able to place an order.
 * 
 * @return bool True if the user can place an order, false otherwise.
 */
function user_can_place_order() {
  $has_access = false;
  $user = wp_get_current_user();
  if ( in_array( 'dealer_manager', $user->roles ) || in_array( 'administrator', $user->roles ) ) {
    $has_access = true;
  }
  return $has_access;
}

/**
 * Geovin mini cart markup
 * 
 * @return void
 */
function geovin_mini_cart( $color = 'white' ) {
  $cartcount = WC()->cart->get_cart_contents_count(); ?>
    <?php if( $cartcount > 0 ) {
      $icon_url = $color === 'white' ? '/wp-content/themes/geovin/images/icons/icon-cart.svg' : '/wp-content/themes/geovin/images/icons/icon-cart-black.svg';
      $product_ids = array();
      foreach( WC()->cart->get_cart() as $cart_item ){
          $product_ids[] = $cart_item['variation_id'];
      }
      ?>
        <a class="geovin_mini_cart geovin_mini_cart--<?php echo $color; ?>" href="<?php echo wc_get_cart_url(); ?>" data-cart-ids="<?php echo json_encode( $product_ids ); ?>">
            <div class="cart__count">
                <img src="<?php echo $icon_url; ?>" class="cart__icon" alt="">
                <span class="count"><?php echo $cartcount; ?></span>
            </div>
        </a>
    <?php } else {

      $icon_url = $color === 'white' ? '/wp-content/themes/geovin/images/icons/icon-cart.svg' : '/wp-content/themes/geovin/images/icons/icon-cart-black.svg';
      ?>
        <a class="geovin_mini_cart geovin_mini_cart--hide geovin_mini_cart--<?php echo $color; ?>" href="<?php echo wc_get_cart_url(); ?>" data-cart-ids="[]">
            <div class="cart__count">
                <img src="<?php echo $icon_url; ?>" class="cart__icon" alt="">
                <span class="count"><?php echo $cartcount; ?></span>
            </div>
        </a>

    <?php }
}
