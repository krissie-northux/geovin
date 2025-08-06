<?php

namespace Geovin;

class Shapediver {
    public function __construct() {

        // Add and save custom fields for Shapediver ticket data on products
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'woo_add_custom_general_fields' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'woo_add_custom_general_fields_save' ) );

        //Load Scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        //Add html to product page for use with shapediver
        add_action( 'woocommerce_single_product_summary', array( $this, 'add_shapediver_container' ), 90 );
    }

    /**
     * Add custom fields to the product general options area in WooCommerce.
     * 
     * @return void
     */
    public function woo_add_custom_general_fields() {

        global $woocommerce, $post;

        echo '<div class="options_group">';

        woocommerce_wp_text_input(
            array(
                'id'          => '_sd_ticket',
                'label'       => __( 'Shape Diver Ticket', 'woocommerce' ),
                'placeholder' => '',
                'desc_tip'    => 'true',
                'description' => __( 'Enter the custom value here.', 'woocommerce' )
            )
        );
        woocommerce_wp_text_input(
            array(
                'id'          => '_base_price',
                'label'       => __( 'Base Product Price', 'woocommerce' ),
                'placeholder' => '',
                'desc_tip'    => 'true',
                'description' => __( 'Enter the base price for this product.', 'woocommerce' )
            )
        );

       echo '</div>';

    }

    /**
     * Save custom fields for Shapediver ticket data on products.
     * 
     * @param int $post_id The product ID.
     * 
     * @return void
     */
    public function woo_add_custom_general_fields_save( $post_id ) {
        // ShapeDiver Ticket ID
        if ( isset( $_POST['_sd_ticket'] ) ) {
            update_post_meta( $post_id, '_sd_ticket', esc_attr( $_POST['_sd_ticket'] ) );
        }
        if ( isset( $_POST['_base_price'] ) ) {
            update_post_meta( $post_id, '_base_price', esc_attr( $_POST['_base_price'] ) );
        }
    }

    /**
     * Enqueue scripts for Shapediver functionality on product pages.
     * 
     * @return void
     */
    public function enqueue_scripts() {
        if ( is_product() ) {
            wp_enqueue_script( 'shapediver-base', 'https://viewer.shapediver.com/v3/2.12.1/bundle.js', array(), '2.12.1', true );
            wp_enqueue_script( 'shapediver', get_plugin_url() . 'sd-v3/build/index.js', array('shapediver-base'), '1.6', true );
            wp_enqueue_script( 'shapediver-adapter', get_plugin_url() . 'assets/js/shapediver-3.js', array('shapediver-base','shapediver','jquery'), '1.4', true );
        }
    }

    /**
     * Add the Shapediver container to the product page.
     * 
     * @return void
     */
    public function add_shapediver_container() {
        global $product;
        $mattress_toggle = '';
        $drawers_toggle = '';
        $storage_toggle = '';
        if ( has_term( 'beds', 'product_cat', $product->get_id() ) ) {
            $mattress_toggle = '<div class="mattress toggle show-mattress" id="show-mattress"><label for="mattress">Show Mattress<input type="checkbox" id="mattress" name="mattress" checked /><span class="slider"></span></label></div>';
        }
        if ( strpos( $product->get_name(), 'G10' ) !== false ) {
            $drawers_toggle = '<div class="drawers toggle open-drawers" id="open-drawers"><label for="drawers">Open Drawers<input type="checkbox" id="drawers" name="drawers" /><span class="slider"></span></label></div>';
        }
        if ( strpos( $product->get_name(), 'G11' ) !== false ) {
            $storage_toggle = '<div class="storage toggle show-storage" id="show-storage"><label for="storage">Show Storage<input type="checkbox" id="storage" name="storage" /><span class="slider"></span></label></div>';
        }
        $qty = '<div class="quantity pseudo_quantity__wrapper">
                    <label class="" for="pseudo_quantity">Qty</label>
                    <input class="qty__btn minus" type="button" value="-">
                    <input type="number" step="1" min="1" name="pseudo_quantity" value="1" title="Qty" class="input-text qty text pseudo-qty" id="pseudo_quantity" size="4">
                    <input class="qty__btn plus" type="button" value="+">
                </div>';

        echo '<div id="sd-wrapper" class="modal-container sd-wrapper"><div class="sd-curtain">Please be patient your model is loading. This should only take a few seconds.</br> <span></span><br/><div class="loading-bar"><div class="loading-bar__inner">5%</div></div></div><div class="product-meta"><div class="product__action"><span class="product-meta--price"></span><div class="product__btns">' . $qty . '<a class="button button--pseudo-addtocart js-trigger-addtocart">Add<span class="waiting">Please Wait</span></a><a class="icon--btn icon--btn--email js-modal-trigger"><svg class="icon-svg--email"><use xlink:href="#icon-email"></use></svg><span class="screen-reader-text">Email Product Details</span></a></div></div></div><div class="icon icon--zoom"><img src="/wp-content/themes/geovin/images/icons/icon-zoom-mobile.png" alt="this can be zoomed in/out" class="desktop-hide" /><img src="/wp-content/themes/geovin/images/icons/icon-zoom-desktop.png" alt="this can be zoomed in/out" class="mobile-hide" /></div><div class="icon icon--rotate"><img src="/wp-content/themes/geovin/images/icons/icon-rotate.png" alt="this can be rotated" /></div>' . $mattress_toggle . $drawers_toggle . $storage_toggle . '<span class="product-meta--sku"></span><div class="sd-wrapper__inner"><a class="close-btn sd-close">X<span class="waiting">Please Wait</a></a><div class="product__wrapper"><div id="sdv-container"><canvas id="sdv-canvas"></canvas></div><div class="sd-options-panel"></div><div class="sd-controls"><h3 class="title">Customize</h3><div class="sd-controls__wrapper">' . $this->shapediver_controls_html() . '</div></div></div></div></div>';
        ?>
        <?php
    }

    /**
     * Generate the HTML for the Shapediver controls.
     * 
     * @return string The HTML markup for the controls.
     */
    public function shapediver_controls_html() {
        global $product;
        $attributes = $product->get_attributes();
        $markup = '';
        $label = 'Width';
        if ( has_term( 'beds', 'product_cat', $product->get_id() ) ) {
            $label = 'Size';
        }
        if ( array_key_exists( 'pa_dimensions', $attributes ) && $attributes['pa_dimensions']->get_visible() ) {
            if ( $label === 'Size' ) {
                $units_toggle = '';
            } else {
                $units_toggle = '<div class="units toggle toggle-units" id="toggle-units"><label for="units">inches <input type="checkbox" id="units" name="units" /><span class="slider"></span> cm</label></div>';
            }
            $markup .= '<div class="sd-control sd-control--dimensions" data-control="dimensions"><div class="sd-control__title">'.$label.'' . $units_toggle . '</div><div class="sd-control__selected-value"></div><div class="sd-control__choices"></div></div>';
        }

        if ( array_key_exists( 'pa_wood-type', $attributes ) && $attributes['pa_wood-type']->get_visible() &&  array_key_exists( 'pa_finish', $attributes ) && $attributes['pa_finish']->get_visible() ) {
            $markup .= '<div class="sd-control sd-control--finish" data-control="finish"><div class="sd-control__title">Finishes</div><div class="sd-control__selected-value"></div><span class="open-options">more options</span><div class="sd-control__choices"></div></div>';
        }

        if ( array_key_exists( 'pa_hardware-shape', $attributes ) && $attributes['pa_hardware-shape']->get_visible() &&  array_key_exists( 'pa_hardware-finish', $attributes ) && $attributes['pa_hardware-finish']->get_visible() ) {
            $markup .= '<div class="sd-control sd-control--hardware-finish" data-control="hardware-finish"><div class="sd-control__title">Hardware</div><div class="sd-control__selected-value"></div><span class="open-options">more options</span><div class="sd-control__choices"></div></div>';
        }
        if ( array_key_exists( 'pa_base-finish', $attributes ) && $attributes['pa_base-finish']->get_visible() ) {
            $markup .= '<div class="sd-control sd-control--base-finish" data-control="base-finish"><div class="sd-control__title">Base Finish</div><div class="sd-control__selected-value"></div><div class="sd-control__choices"></div></div>';
        }
        if ( array_key_exists( 'pa_doors', $attributes ) && $attributes['pa_doors']->get_visible() ) {
            $markup .= '<div class="sd-control sd-control--doors" data-control="doors"><div class="sd-control__title">Doors</div><div class="sd-control__selected-value"></div><span class="open-options">more options</span><div class="sd-control__choices"></div></div>';
        }
        if ( array_key_exists( 'pa_headboard-panel', $attributes ) && $attributes['pa_headboard-panel']->get_visible() ) {
            $markup .= '<div class="sd-control sd-control--headboard-panel" data-control="headboard-panel"><div class="sd-control__title">Headboard Panel</div><div class="sd-control__selected-value"></div><div class="sd-control__choices"></div></div>';
        }
        if ( array_key_exists( 'pa_fabric', $attributes ) && $attributes['pa_fabric']->get_visible() ) {
            $markup .= '<div class="sd-control sd-control--fabric" data-control="fabric"><div class="sd-control__title">Fabric</div><div class="sd-control__selected-value"></div><span class="open-options">more options</span><div class="sd-control__choices"></div></div>';
        }

        // need to add for bed and cabinet attributes

        return $markup;
    }

    /**
     * Get the ShapeDiver ticket data for a product.
     * 
     * @param int $post_id The product ID.
     * 
     * @return string The ShapeDiver ticket ID.
     */
    public static function get_ticket( $post_id ) {
        $sd_ticket = get_post_meta( $post_id, '_sd_ticket', true );
        return $sd_ticket;
    }
}


$shapediver = new Shapediver();