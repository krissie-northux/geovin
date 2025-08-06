<?php
/**
 * Geovin Variable Product Type
 */
namespace Geovin;

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

class Add_Product_Type {

    /**
     * Build the instance
     */
    public function __construct() {
        add_action( 'woocommerce_loaded', array( $this, 'load_product_type' ) );
        add_filter( 'product_type_selector', array( $this, 'add_type' ) );
        register_activation_hook( __FILE__, array( $this, 'install' ) );

        add_filter('woocommerce_product_class', array( $this, 'geovin_variable_product_type_class'), 10, 2);

        add_action('admin_footer', array($this,'geovin_variable_product_type_data_tabs'));


        add_filter('woocommerce_product_data_tabs', array($this,'geovin_variable_product_data_tabs_for_product'), 10, 1);

        add_filter('woocommerce_delete_variations_on_product_type_change', array($this,'do_not_remove_variations'), 10, 4);
        add_filter( 'woocommerce_data_stores', array($this,'geovin_variable_data_store'), 10, 1 );
        //add_action( 'woocommerce_geovin_add_to_cart', 'woocommerce_variable_add_to_cart' );

        add_action( 'wp_ajax_woocommerce_get_variation_from_sku', array( $this, 'get_variation_from_sku' ) );
        add_action( 'wp_ajax_nopriv_woocommerce_get_variation_from_sku', array( $this, 'get_variation_from_sku' ) );

        // WC AJAX can be used for frontend ajax requests.
        add_action( 'wc_ajax_get_variation_from_sku', array( $this, 'get_variation_from_sku' ) );

        add_filter( 'woocommerce_add_to_cart_handler', array( $this, 'filter_add_to_cart_type' ), 10, 2 );

        add_action( 'woocommerce_after_variations_table', array( $this, 'add_cart_image_input' ) );

        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_image_data' ), 10, 4 );

        add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'adjust_cart_thumb' ), 10, 3 );

    }

    /**
     * If we have a custom image from a shapediver rendering 
     * it is saved in the cart item data 'image_to_use', 
     * we want to use it instead of the product default image
     *
     * @param string $image The current cart item thumbnail HTML.
     * @param array $cart_item The cart item data.
     * @param string $cart_item_key The cart item key.
     * @return string Modified cart item thumbnail HTML.
     */
    public function adjust_cart_thumb( $image, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['image_to_use'] ) && $cart_item['image_to_use'] !== '' ) {
            $image = '<img width="300" height="300" src="' . esc_attr( $cart_item['image_to_use'] ) . '" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" alt="" loading="lazy" />';
        }
        return $image;
    }

    /**
     * Save custom data from the attributes selected and the shapediver rendering
     * to the cart item data so we can use it later in the cart and checkout
     *
     * @param array $cart_item_data The cart item data.
     * @param int $product_id The product ID.
     * @param int $variation_id The variation ID.
     * @param int $quantity The quantity being added to the cart.
     * @return array Modified cart item data.
     */
    public function add_cart_image_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
        $cart_item_data['image_to_use'] = filter_input( INPUT_POST, 'cart_image_to_use', FILTER_SANITIZE_URL );
        $cart_item_data['link_to_use'] = filter_input( INPUT_POST, 'cart_link_to_use', FILTER_SANITIZE_URL );
        $cart_item_data['dimensions_to_use'] = filter_input( INPUT_POST, 'cart_dimensions_to_use', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $cart_item_data['niceatts_to_use'] = filter_input( INPUT_POST, 'cart_niceatts_to_use', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        return $cart_item_data;
    }

    /**
     * Add hidden inputs for custom cart data.
     * 
     * @return void
     */
    public function add_cart_image_input() {
        echo '<input type="hidden" name="cart_image_to_use" id="cart_image_to_use" value="" />';
        echo '<input type="hidden" name="cart_link_to_use" id="cart_link_to_use" value="" />';
        echo '<input type="hidden" name="cart_dimensions_to_use" id="cart_dimensions_to_use" value="" />';
        echo '<input type="hidden" name="cart_niceatts_to_use" id="cart_niceatts_to_use" value="" />';
    }

    /**
     * Filter the add-to-cart handler for the custom product type to ensure
     * that our product type is seen as a variable product when added to cart
     *
     * @param string $type The current product type.
     * @param object $adding_to_cart The product being added to the cart.
     * @return string Modified product type.
     */
    public function filter_add_to_cart_type( $type, $adding_to_cart ) {      
        if ( $type === 'geovin' ) {
            $type = 'variable';
        }
        return $type;
    }

    /**
     * Defines Data Store Class for new custom product type
     * The new product type is based on variable products
     * and does not need a custom data store
     * 
     * @param array $stores
     * @return array Modified data stores.
     */
    function geovin_variable_data_store( $stores ) {
        $stores['product-geovin'] = 'WC_Product_Variable_Data_Store_CPT';
        return $stores;
    }

    /**
     * Prevent removal of variations when changing product type.
     * By default WooCommerce removes all variations when changing product type.
     * If the correct product type is not selected before adding variations, they will be
     * lost when changing product type. This filter prevents that from happening.
     *
     * @param bool $condition The current condition.
     * @param object $product The product object.
     * @param string $from The current product type.
     * @param string $to The new product type.
     * @return bool Modified condition.
     */
    public function do_not_remove_variations( $condition, $product, $from, $to ) {
        if ( $to === 'geovin' || $to === 'variable' ) {
            return false;
        } elseif ( $from === 'geovin' && $to === 'simple' ) {
            return true;
        } else {
            return $condition;
        } 
    }

     /**
     * This filter ensures that the correct product editor tabs 
     * are used for the Geovin product type.
     *
     * @param array $tabs The current product data tabs.
     * @return array Modified product data tabs.
     */
    public function geovin_variable_product_data_tabs_for_product( $tabs ) {
        array_push($tabs['attribute']['class'], 'show_if_variable show_if_geovin');
        array_push($tabs['variations']['class'], 'show_if_geovin');
        array_push($tabs['inventory']['class'], 'show_if_geovin');
        array_push($tabs['general']['class'], 'show_if_geovin');

        return $tabs;
    }

    /**
     * Add custom JavaScript so that the dynamic portions of the product editor
     * for the Geovin product type are shown correctly.
     * 
     * @return void
     */
    public function geovin_variable_product_type_data_tabs() {
        if('product' != get_post_type()) :
            return;
        endif;
        ?>
        <script type='text/javascript'>
            jQuery(document).ready(function () {
                
                jQuery('.enable_variation').addClass('show_if_geovin').show();
                            jQuery('.inventory_options').addClass('show_if_geovin').show();
                jQuery('#inventory_product_data ._manage_stock_field').addClass('show_if_geovin').show();
                jQuery('#inventory_product_data ._sold_individually_field').parent().addClass('show_if_geovin').show();
                jQuery('#inventory_product_data ._sold_individually_field').addClass('show_if_geovin').show();
            });
        </script>
        <?php

    }

    /**
     * Map the custom product type to its class for extending
     * the variable product class.
     *
     * @param string $classname The current class name.
     * @param string $product_type The product type.
     * @return string Modified class name.
     */
    public function geovin_variable_product_type_class( $classname, $product_type ) {
    if ( $product_type == 'geovin' ) {
        $classname = 'Geovin\Geovin_Variable_Product';
        }
        return $classname;
    }

   /**
     * Load the custom product type class file.
     * 
     * @return void
     */
    public function load_product_type() {
        require get_plugin_dir() . 'includes/class-geovin-variable-product.php';
    }

     /**
     * Add the custom product type to the product type selector.
     *
     * @param array $types The current product types.
     * @return array Modified product types.
     */
    public function add_type( $types ) {
        $types['geovin'] = __( 'Geovin Variable', 'geovin' );
       
        return $types;
    }

    /**
     * Install the custom product type on plugin activation.
     *
     * @return void
     */
    public function install() {
        // If there is no advanced product type taxonomy, add it.
        if ( ! get_term_by( 'slug', 'geovin', 'product_type' ) ) {
            wp_insert_term( 'geovin', 'product_type' );
        }
    }

    /**
     * Get a matching variation based on SKU.
     * 
     * @return void
     */
    public static function get_variation_from_sku() {
        ob_start();

        if ( empty( $_POST['sku'] ) ) {
            wp_die();
        }
        $variable_product_id = wc_get_product_id_by_sku( $_POST['sku'] );
        $variable_product = wc_get_product( absint( $_POST['product_id'] ) );

        if ( ! $variable_product ) {
            wp_die();
        }

        $variation    = $variable_product_id ? $variable_product->get_available_variation( $variable_product_id ) : false;
        wp_send_json( $variation );

    }
}

new Add_Product_Type();