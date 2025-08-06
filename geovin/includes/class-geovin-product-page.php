<?php
namespace Geovin;

class Geovin_Product_Page {

    public function __construct( $context = null ) {
        //$context allows us to use some functions in a CLI call without adding actions unneccesarily
        if ( ! $context ) {
            // Add the product title back to the product page template
            add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 4 );
            add_action( 'woocommerce_single_product_summary', array( $this, 'geovin_pretitle'), 6 );
            add_action( 'woocommerce_before_single_product_summary', array( $this, 'add_tabs_nav' ), 99);

            add_action( 'wp_head', array( $this, 'replace_variable_add_to_cart') );

            add_action( 'woocommerce_before_variations_form', array( $this, 'add_trending_tabs' ), 6);
            //add_action( 'woocommerce_before_variations_form', array( $this, 'add_geovin_product_code' ), 7); this was moved

            //Add Geovin code data to variation inputs
            add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array($this, 'add_geovin_product_code_data'), 10, 2);

            // Make sure to clear the transient when we update the attribute combinations
            add_action('acf/save_post', array( $this, 'clear_transient_on_combo_save'), 20); 

            //Create dynamic specs shortcode
            add_shortcode( 'dynamic_spec', array( $this, 'dynamic_spec' ) );
            add_action( 'wp_footer', array( $this, 'dynamic_specs_script' ), 99 );
            add_action( 'wp_footer', array( $this, 'email_form_overlay' ), 1 );

            add_filter( 'woocommerce_available_variation', array( $this, 'variationfilter'), 10, 3);
            add_filter( 'woocommerce_show_variation_price', array( $this,'returntrue'), 10, 1);

            add_filter( 'woocommerce_product_get_price', array( $this, 'adjust_price'), 10, 1 );

            add_filter( 'woocommerce_get_price_html', array( $this, 'add_price_msrp'), 99, 2 );
            add_filter( 'woocommerce_loop_add_to_cart_link', array($this,'button_text'), 10, 3);
        }
       
    }

    /**
     * Replaces the default button text for variable products with a custom text.
     * 
     * @param string $html The HTML for the button.
     * @param WC_Product $product The product object.
     * @param array $args The arguments for the button.
     * 
     * @return string The modified HTML for the button.
     */
    public function button_text( $html, $product, $args ) {
        $html = str_replace('class="button', 'class="button button--primary', $html);
        $html = str_replace('>Select options<','>View ' . $product->get_title() . '<', $html);
        $html = str_replace('>Read more<','>View ' . $product->get_title() . '<', $html);

        return $html;
    }

    /**
     * Adjust the woocommerce_single_product_summary action 
     * to replace the default add to cart button and variations form
     * with the Geovin button and form
     * 
     * @return void
     */
    public function replace_variable_add_to_cart() {

        remove_action('woocommerce_single_product_summary', array( WC()->structured_data, 'generate_product_data' ), 60);
        remove_filter( 'post_class', 'wc_product_post_class', 20, 3 );

        remove_action('woocommerce_single_product_summary','woocommerce_template_single_add_to_cart', 30);
        add_action( 'woocommerce_single_product_summary', array( $this, 'geovin_variable_add_to_cart' ), 30);
    }

    /**
     * Add freight and disclaimer information to the product price.
     * 
     * @return void
     */
    public function add_price_msrp( $price, $product ){

        if ( ! user_can_build_order() ) {

            if ( isset( $GLOBALS['geovin_prod_email_sending'] ) && $GLOBALS['geovin_prod_email_sending']  ) {
                $message = get_field( 'product_email_message', 'option' );
                $html = '<span class="woocommerce-Price-amount amount amount--trade-only"><span class="trade-only__explanation">' . $message . '</span>';
                return $html;
            } else {
                $message = get_field( 'product_page_message', 'option' );
                $button_1 = get_field( 'button_1', 'option' );
                $button_2 = get_field( 'button_2', 'option' );

                if ( $button_1 ) {
                    $button_1_html = '<a href="' . esc_url( $button_1['url'] ) . '" target="' . esc_attr( $button_1['target'] ) . '" class="button button--primary">' . esc_html( $button_1['title'] ) . '</a>';
                } else {
                    $button_1_html = '';
                }
                if ( $button_2 ) {
                    $button_2_html = '<a href="' . esc_url( $button_2['url'] ) . '" target="' . esc_attr( $button_2['target'] ) . '" class="button button--primary">' . esc_html( $button_2['title'] ) . '</a>';
                } else {
                    $button_2_html = '';
                }
                $html = '<div class="woocommerce-Price-amount amount amount--trade-only"><div class="trade-only__explanation">' . $message . '</div>' . $button_1_html . $button_2_html . '</div>';
            return $html;
            }
        } 

        /*if ( Geolocation::get_pricing_country() === 'CA' && isset(WC()->session) && WC()->session->get('outside_region') === 'yes' ) {
            $line_1 = get_field('over_750km_freight_info_line_1', 'options');
            $line_2 = get_field('over_750km_freight_info_line_2', 'options');
        } else*/ // removed when disabling pricing for non-logged in users and removing MSRP 2 calculations

        if ( Geolocation::get_pricing_country() === 'CA' ) {
            $line_1 = get_field('freight_info_line_1', 'options');
            $line_2 = get_field('freight_info_line_2', 'options');
        } else {
            $line_1 = get_field('us_freight_info_line_1', 'options');
            $line_2 = get_field('us_freight_info_line_2', 'options');
        }
        if ( isset( $GLOBALS['geovin_prod_email_sending'] ) && $GLOBALS['geovin_prod_email_sending']  ) {
            
            $freight_html = '';

            if ( $line_1 && $line_1 !== '' && $line_2 && $line_2 !== '' ) {
                $freight_html = '<span class="freight-info">' . $line_1  . '</span>';
            } elseif ( $line_1 && $line_1 !== '' ) {
                $freight_html = '<span class="freight-info">' . $line_1  . '</span>';
            }

            return $price . ' ' . $freight_html;
            
        } else {
            
            $freight_html = '';

            if ( $line_1 && $line_1 !== '' && $line_2 && $line_2 !== '' ) {
                $freight_html = '<span class="freight-info">' . $line_1  . '<span class="info-icon js-tooltip-trigger" data-text="' . $line_2  . '">i</span></span>';
            } elseif ( $line_1 && $line_1 !== '' ) {
                $freight_html = '<span class="freight-info">' . $line_1  . '</span>';
            } elseif ( $line_2 && $line_2 !== '' ) {
                $freight_html = '<span class="freight-info"><span class="info-icon js-tooltip-trigger" data-text="' . $line_2  . '">i</span></span>';
            }
            $price_shown = \WC()->session->get('price_shown');
            return $price_shown . ' ' . $price . '' . $freight_html;
        }
            
    }

    /**
     * Get the product attributes and related data
     * 
     * @param object $product The product object.
     * @return array The attribute combinations.
     */
    public function get_geovin_product_attributes( $product ) {
        $meta = $product->get_attributes();
        $attributes = array();
        foreach ( $meta as $attribute ) {
            $options = $attribute->get_options();
            foreach( $options as $option ) {
                $term = get_term_by('ID', $option, $attribute->get_name() );
                $attributes[$attribute->get_name()][] = $term->slug;
            }
            
        }

        return $attributes;
    }

    /**
     * Add the Geovin variable add to cart button.
     * 
     * @return void
     */
    public function geovin_variable_add_to_cart() {
        global $product;

        wc_get_template( 'single-product/add-to-cart/variation.php' );

        $params = array(
            'wc_ajax_url'                      => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
            'i18n_no_matching_variations_text' => esc_attr__( 'Sorry, no products matched your selection. Please choose a different combination.', 'woocommerce' ),
            'i18n_make_a_selection_text'       => esc_attr__( 'Please select some product options before adding this product to your cart.', 'woocommerce' ),
            'i18n_unavailable_text'            => esc_attr__( 'Sorry, this product is unavailable. Please choose a different combination.', 'woocommerce' ),
        );

        // Enqueue variation scripts.
        wp_enqueue_script( 'add-to-cart-geovin-variation' , get_plugin_url() . 'assets/js/add-to-cart-geovin-variation.js', array( 'jquery', 'wp-util', 'jquery-blockui', 'geovin-global' ), '3', true );
        $name = 'wc_add_to_cart_geovin_variation_params';
        wp_localize_script( 'add-to-cart-geovin-variation', $name, $params );
        
        $attributes = self::get_geovin_product_attributes( $product );

        // Load the template.
        wc_get_template(
            'single-product/add-to-cart/variable.php',
            array(
                'available_variations' => false,
                'attributes'           => $attributes,
                'selected_attributes'  => $product->get_default_attributes(),
            )
        );
        
    }

    /**
     * In some templates the Geovin product type returns an empty
     * string for the price (this is done to avoid costly db queries of 
     * all variations). Even if the price is not shown, we need to return an integer value
     * 
     * @param int $price The product price
     * @return int The modified price
     */
    public function adjust_price( $price ) {
        if ( ! $price ) {
            $price = 1;
        }
        return $price;
    }

    /**
     * Generic function that returns true
     * perferable for debugging vs __return_true
     * 
     * @param mixed $value The value to be returned.
     * 
     * @return boolean
     */
    public function returntrue( $value ) {
        return true;
    }

    /**
     * Since Geovin product variations don't have images, we need to
     * set these values to false so it doesn't break the product page
     * 
     * @param array $data The variation data.
     * @param WC_Product_Variable $instance The variable product.
     * @param WC_Product_Variation $variation The product variation object.
     * 
     * @return array The modified variation data.
     */
    public function variationfilter( $data, $instance, $variation ) {
        $parent_id = $variation->get_parent_id();
        $data['image'] = false;
        $data['image_id'] = false;

        return $data;
    }

    /**
     * Add the Geovin email form overlay to the product page.
     * 
     * @return void
     */
    public function email_form_overlay() {
        ?>
        
        <div class="modal" aria-describedby='modal-description' aria-hidden="true">
            <div class="modal-overlay js-modal-overlay"></div>
            <div class="modal-canvas">
                <?php gravity_form( 1, false, true, false, array(), true); ?>
                <div class="icon-close js-modal-close"></div>
                <div class='screen-reader-text' id='modal-description'>
                    This is a dialog window which overlays the main content of the page. The modal instructs you to enter your email address to recieve an email of the product as it is configured. Pressing the Close Modal button at the top of the modal will close the modal and bring you back to where you were on the page.
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Dynamically generates the specs script for the product page.
     * 
     * @return void
     */
    public function dynamic_specs_script() {
        ?>
        <script>
            var dynamic_specs = {};

            <?php if ( have_rows( 'specs' ) ) :
                
                $dynamic_specs = array(); ?>

                <?php while ( have_rows( 'specs' ) ) : the_row(); ?>
                    <?php
                        $dependencies = array();
                        $spec_name = sanitize_title( get_sub_field( 'spec_name' ) );
                        $spec_value = get_sub_field('spec_value');
                        $spec_units = get_sub_field('spec_unit');
                        
                        $variables = get_sub_field('variable'); 
                        if ( $variables ) {
                            foreach ( $variables as $variable ) {
                                $dependencies[$variable->taxonomy][$variable->slug] = array('value' => get_sub_field( 'spec_value' ),'unit' =>  get_sub_field( 'spec_unit' ) );

                                if ( isset( $dynamic_specs[$spec_name] ) ) {
                                    $dynamic_specs[$spec_name][$variable->slug] = array( 'value' => $spec_value, 'units' => $spec_units );
                                } else {
                                    $dynamic_specs[$spec_name] = array('dependencies' => true, 'dependson' => $variable->taxonomy);
                                    
                                    $dynamic_specs[$spec_name][$variable->slug] = array( 'value' => $spec_value, 'units' => $spec_units );
                                }
                            }
                            
                        } else {
                            if ( isset( $dynamic_specs[$spec_name] ) ) {
                                //in the array
                            } else {
                                $dynamic_specs[$spec_name] = array('dependencies' => false, 'value' => $spec_value, 'units' => $spec_units );
                            }
                        }
                    ?>

                <?php endwhile;
                $dynamic_specs_json = json_encode($dynamic_specs);
                ?>
                dynamic_specs = <?php echo $dynamic_specs_json; ?>;
            <?php else : ?>
                <?php // no rows found ?>
            <?php endif; ?>
        </script>
        <?php
    }

    /**
     * Generates the pretitle for the product page based on the product category.
     * 
     * @return void
     */
    public function geovin_pretitle() {
        global $product;
        if ( has_term( 'beds', 'product_cat', $product->get_id() ) ) {
            $taxonomy = 'product_cat'; // <== Here set your custom taxonomy
            $terms = wp_get_post_terms( $product->get_id(), $taxonomy );
            $primary_term = $terms[0];

            echo '<div class="pretitle">';
            echo 'See more <a href="/category/' . esc_url( $primary_term->slug ) . '">' . esc_html( $primary_term->name ) . '</a>';
            echo '</div>';
        } else {
            $taxonomy = 'collections'; // <== Here set your custom taxonomy
            $terms = wp_get_post_terms( $product->get_id(), $taxonomy );
            $primary_term = $terms[0];

            echo '<div class="pretitle">';
            echo 'See more in the <a href="/collection/' . esc_url( $primary_term->slug ) . '">' . esc_html( $primary_term->name ) . ' Collection</a>';
            echo '</div>';
        }
        
    }

    /**
     * Add the dynamic spec markup to the product page.
     * 
     * @return void
     */
    public function dynamic_spec( $atts ) {
        $spec = sanitize_title( $atts['spec'] );
        $convert = isset( $atts['convert'] ) ? $atts['convert'] : 'false';
        return '<span class="js-dynamic-spec js-dynamic-spec--' . $spec . '" data-convert="' . $convert . '"></span>';
    }

    /**
     * Add data attributes to the variation form select options
     * 
     * @param string $html The HTML for the variations form.
     * @param array $args The arguments for the variations form.
     * @return string The modified HTML for the variations form.
     */
    public function add_geovin_product_code_data($html, $args) {
        $new_html = $this->attribute_html( $args );
        return $new_html;
    }

    /**
     * This function is an adaptation of wc_dropdown_variation_attribute_options( $args )
     * located in woocommerce/includes/wc-template-functions.php
     * adaptation was needed to add a data attribute to the option value
     * data attribute is an ACF field added to the taxonomy term that contains the Geovin code
     * 
     * @param array $args The arguments for the variations form.
     * 
     * @return string The HTML for the variations form select options.
     */
    public function attribute_html( $args ) {
        $args = wp_parse_args(
            apply_filters( 'woocommerce_dropdown_variation_attribute_options_args', $args ),
            array(
                'options'          => false,
                'attribute'        => false,
                'product'          => false,
                'selected'         => false,
                'name'             => '',
                'id'               => '',
                'class'            => '',
                'show_option_none' => __( 'Choose an option', 'woocommerce' ),
            )
        );

        // Get selected value.
        if ( false === $args['selected'] && $args['attribute'] && $args['product'] instanceof WC_Product ) {
            $selected_key = 'attribute_' . sanitize_title( $args['attribute'] );
            $args['selected'] = isset( $_REQUEST[ $selected_key ] ) ? wc_clean( wp_unslash( $_REQUEST[ $selected_key ] ) ) : $args['product']->get_variation_default_attribute( $args['attribute'] );
        }

        $options               = $args['options'];
        $product               = $args['product'];
        $attribute             = $args['attribute'];
        $name                  = $args['name'] ? $args['name'] : 'attribute_' . sanitize_title( $attribute );
        $id                    = $args['id'] ? $args['id'] : sanitize_title( $attribute );
        $class                 = $args['class'];
        $show_option_none      = (bool) $args['show_option_none'];
        $show_option_none_text = $args['show_option_none'] ? $args['show_option_none'] : __( 'Choose an option', 'woocommerce' ); // We'll do our best to hide the placeholder, but we'll need to show something when resetting options.

        if ( empty( $options ) && ! empty( $product ) && ! empty( $attribute ) ) {
            $attributes = $product->get_variation_attributes();
            $options    = $attributes[ $attribute ];
        }

        $html  = '<select id="' . esc_attr( $id ) . '" class="' . esc_attr( $class ) . '" name="' . esc_attr( $name ) . '" data-attribute_name="attribute_' . esc_attr( sanitize_title( $attribute ) ) . '" data-show_option_none="' . ( $show_option_none ? 'yes' : 'no' ) . '">';
        $html .= '<option value="">' . esc_html( $show_option_none_text ) . '</option>';

        if ( ! empty( $options ) ) {
            if ( $product && taxonomy_exists( $attribute ) ) {
                // Get terms if this is a taxonomy - ordered. We need the names too.
                $terms = wc_get_product_terms(
                    $product->get_id(),
                    $attribute,
                    array(
                        'fields' => 'all',
                    )
                );

                foreach ( $terms as $term ) {
                    
                    if ( in_array( $term->slug, $options, true ) ) {
                        $geovin_code = $this->get_term_geovin_code( $term );
                        $sku_code[] = $geovin_code;
                        $html .= '<option value="' . esc_attr( $term->slug ) . '" ' . selected( sanitize_title( $args['selected'] ), $term->slug, false ) . ' data-geovin-code-value="' . $geovin_code . '">' . esc_html( apply_filters( 'woocommerce_variation_option_name', $term->name, $term, $attribute, $product ) ) . '</option>';
                    }
                }
            } else {
                foreach ( $options as $option ) {
                    // This handles < 2.4.0 bw compatibility where text attributes were not sanitized.
                    $selected = sanitize_title( $args['selected'] ) === $args['selected'] ? selected( $args['selected'], sanitize_title( $option ), false ) : selected( $args['selected'], $option, false );
                    $html    .= '<option value="' . esc_attr( $option ) . '" ' . $selected . '>' . esc_html( apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute, $product ) ) . '</option>';
                }
            }
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * Sort function to list finishes in the order
     * Geovin prefers for users.
     * 
     */
    public static function sort_finishes( $a, $b ) {
        $a_code_wood_value = explode( '-', $a )[0];
        $b_code_wood_value = explode( '-', $b )[0];
        $a_code_finish_value = explode( '-', $a )[1];
        $b_code_finish_value = explode( '-', $b )[1];
        if ( $a_code_wood_value === $b_code_wood_value ) {
            return ( $a_code_finish_value < $b_code_finish_value ) ? -1 : +1;
        } elseif ( $a_code_wood_value === 'W' ) {
            return -1;
        } elseif ( $b_code_wood_value === 'W' ) {
            return +1;
        } elseif ( $a_code_wood_value === 'A' ) {
            return -1;
        } elseif ( $b_code_wood_value === 'A' ) {
            return +1;
        } elseif ( $a_code_wood_value === 'M' ) {
            return -1;
        } elseif ( $b_code_wood_value === 'M' ) {
            return +1;
        } elseif ( $a_code_wood_value === 'P' ) {
            return -1;
        } elseif ( $b_code_wood_value === 'P' ) {
            return +1;
        } 
    }

    /**
     * Sort function to sort dimensions in the order
     * Geovin prefers for users.
     */
    public static function sort_dimensions( $a, $b ) {
        $a_dim = explode('/', $a[1]);
        $b_dim = explode('/', $b[1]);
        return ( $a_dim < $b_dim ) ? -1 : +1;
    }

    /**
     * Add trending tab tab navigation markup to the product page.
     * 
     * @return void
     */
    public function add_tabs_nav() {
        $trending = $this->get_trending_product_data();
        $tab_count = get_field('number_of_trending_items');
        ?>
        <div class="tabs__nav tabs__nav--count-<?php echo esc_attr( $tab_count ); ?>">
            <ul>
                <?php foreach( $trending as $key => $trending_item ) {
                    error_log(print_r($key,true));
                    if ( $key > $tab_count ) {
                        break;
                    }
                $data_string = $this->create_data_attr_for_trending( $trending_item );
                ?>
                    <li class="tabs__nav__item" data-tab="tab-<?php echo esc_attr( $key ); ?>" <?php echo esc_attr( $data_string ); ?>>Trending <?php echo esc_html( $key ); ?></li>  
                <?php } ?>
                
            </ul>
        </div>
        <?php
    }

    /**
     * Add trending tabs to the product page.
     * 
     * @return void
     */
    public function add_trending_tabs() { 
        global $product;
        $sku_code = array();
        
        ?>
        <div class="tabs" data-product-sku="<?php echo esc_attr( $product->get_sku() ); ?>">
            <?php
                $trending = $this->get_trending_product_data();
                $tab_count = count($trending);
                $shapediver_ticket = Shapediver::get_ticket( get_the_ID() );
                $available_attributes = $this->get_product_attribute_combos(); 

                //sort finish attributes
                uksort( $available_attributes['finish'], array($this,'sort_finishes') );

                if ( $shapediver_ticket ) {
                    ?>
                    <script>
                        var sd_ticket = '<?php echo esc_html( $shapediver_ticket ); ?>';
                        var available_attributes = JSON.parse( '<?php echo json_encode( $available_attributes ); ?>' );
                    </script>
                    <?php
                }
            ?>
            
            <div class="geovin-product-code" ></div>
            <div class="tabs__wrapper">
                <div class="tabs__header">
                    <?php if ( $shapediver_ticket ) { ?>
                        <div class="tabs__nav__item tabs__nav__item--customize trigger-sd button button--primary" data-tab="tab-sd">Change Options</div>
                    <?php } ?>
                    <h3 class="title">Options Shown</h3>
                </div>
                <?php foreach( $trending as $key => $trending_item ) { ?>
                    <div id="tab-<?php echo esc_attr( $key ); ?>" class="tabs__tab"><?php $this->tab_content( $trending_item, $available_attributes ); ?></div>
                <?php } ?>
                <?php if ( $shapediver_ticket ) { ?>
                    <div id="tab-sd" class="tabs__tab"><?php $this->customization_tab_content( $available_attributes ); ?></div>
                <?php } ?>
            </div>
        </div>
        <?php
    }

    /**
     * Gets the avaialbe attribute combinations for the product.
     * Shapediver needs this to render options
     * 
     * @return array The trending product data.
     */
    public function get_product_attribute_combos() {
        global $product;
        $attributes = self::get_geovin_product_attributes( $product );
        $attribute_combos = $this->get_all_attribute_combinations();

        $attributes_array = array();

        foreach ( $attributes as $key => $attribute ) {
            foreach( $attribute as $term_slug ) {
                $term = get_term_by( 'slug', $term_slug, $key );
                $geovin_code = $this->get_term_geovin_code( $term );
                $group_name = str_replace( 'pa_', '', $key );
                $attributes_array[$group_name][] = array(
                        'name' => $term->name,
                        'code' => $geovin_code,
                    );
            }
        }
        
        $available_codes = array(
            'dimensions' => array(),
            'finish' => array(),
            'hardware-finish' => array(),
            'base-finish' => array(),
            'doors' => array(),
            'headboard-panel' => array(),
            'fabric' => array(),
        );
        foreach ( $attributes_array['wood-type'] as $wood_value ) {
            foreach ( $attributes_array['finish'] as $finish_value ) {
                $code_combo = $wood_value['code'] . '-' . $finish_value['code'];
                if ( isset( $attribute_combos['finish'][$code_combo] ) ) {
                    $available_codes['finish'][$code_combo] = $attribute_combos['finish'][$code_combo];
                }
                
            }
        }

        foreach ( $attributes_array['hardware-shape'] as $shape_value ) {
            foreach ( $attributes_array['hardware-finish'] as $finish_value ) {
                $code_combo = $shape_value['code'] . '-' . $finish_value['code'];
                if ( isset( $attribute_combos['hardware-finish'][$code_combo]) ) {
                    $available_codes['hardware-finish'][$code_combo] = $attribute_combos['hardware-finish'][$code_combo];
                } 
            }
        }
        foreach ( $attributes_array['dimensions'] as $dimension ) {
            $available_codes['dimensions'][$dimension['code']] = array( 'code' => $dimension['code'], 'label' =>  $dimension['name']);
        }
        foreach ( $attributes_array['base-finish'] as $base_finish ) {
            $available_codes['base-finish'][$base_finish['code']] = array( 'code' => $base_finish['code'], 'label' =>  $base_finish['name']);
        }

        foreach ( $attributes_array['doors'] as $door_value ) {
                $code_combo = $door_value['code'];
                if ( isset( $attribute_combos['doors'][$code_combo]) ) {
                    $available_codes['doors'][$code_combo] = $attribute_combos['doors'][$code_combo];
                } 
        }
        foreach ( $attributes_array['headboard-panel'] as $headboard_panel_value ) {
            $code_combo = $headboard_panel_value['code'];
            $available_codes['headboard-panel'][$headboard_panel_value['code']] = array( 'code' => $headboard_panel_value['code'], 'label' =>  $headboard_panel_value['name']); 
        }
        foreach ( $attributes_array['fabric'] as $fabric_value ) {
            $code_combo = $fabric_value['code'];
            if ( isset( $attribute_combos['fabric'][$code_combo]) ) {
                $available_codes['fabric'][$code_combo] = $attribute_combos['fabric'][$code_combo];
            } 
        }

        return $available_codes;
        
    }

    /**
     * Add trending tabs tab content markup to the product page.
     * 
     * @return void
     */
    public function tab_content( $trending_item, $attribute_combos ) {

        foreach( $trending_item as $attribute_name => $attribute_value ) {
            $attribute_pretty_name = str_replace( '_', ' ', $attribute_name );
            if ( $attribute_pretty_name === 'hardware-finish' ) {
                $attribute_pretty_name = 'hardware';
            }
            if ( $attribute_pretty_name === 'base-finish' ) {
                $attribute_pretty_name = 'base finish';
            }
            if ( $attribute_pretty_name === 'headboard-panel' ) {
                $attribute_pretty_name = 'headboard panel';
            }
            if ( isset( $attribute_value['doors']['code'] ) && $attribute_value['doors']['code'] === 'D00' ) {
                //we have a D00 door
            } else {
            ?>
            <div class="quickview js-selected-text__parent">
                
                <?php if ( is_array( $attribute_value ) ) {
                    $first_code = null;
                    $second_code = null;
                    foreach( $attribute_value as $key => $sub_value ) {
                        //error_log($key);
                        if ( $key === 'wood-type' || $key === 'hardware-shape' ) {
                            $first_code = $sub_value['code'];
                        } else {
                            $second_code = $sub_value['code'];
                        }
                    }
                    $combo_code = isset( $first_code ) ? $first_code . '-' . $second_code : $second_code;

                    $label = $attribute_combos[$attribute_name][$combo_code]['label'];
                    $image = isset( $attribute_combos[$attribute_name][$combo_code]['image'] ) ? $attribute_combos[$attribute_name][$combo_code]['image'] : false;
                    ?>
                    <?php if ( $attribute_pretty_name === 'dimensions' && has_term('beds','product_cat') ) {
                                echo '<span class="bed-size"><strong>Bed Size:</strong> ' . esc_html( $label ) . '</span>';
                            } ?>
                    <strong><?php echo esc_html( ucwords( $attribute_pretty_name ) ); ?>:</strong><br/>
                    <div class="product-attribute-selected product-attribute-selected--thumb" data-thumb="<?php echo esc_attr( $image ); ?>">
                        <?php if( $attribute_pretty_name != 'dimensions' && $attribute_pretty_name != 'base finish' && $attribute_pretty_name != 'headboard panel'): ?>
                            <div class="quickview__icon">
                                <svg class="icon-svg--view"><use xlink:href="#icon-view"></use></svg>
                                <div class="quickview__details">
                                    <div class="label"><?php echo esc_html( $label ); ?> &#8212 <span class="small-caps"><?php echo esc_html( $combo_code ); ?></span></div>
                                    <img src="<?php echo esc_attr( $image ); ?>"/>
                                    
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ( $attribute_pretty_name === 'dimensions' ) {
                            
                            
                            echo do_shortcode('<span class="js-selected-text">[dynamic_spec spec="overall, w" convert="cm"]W x [dynamic_spec spec="overall, d" convert="cm"]D x [dynamic_spec spec="overall, h" convert="cm"]H cm / [dynamic_spec spec="overall, w" convert="in"]W x [dynamic_spec spec="overall, d" convert="in"]D x [dynamic_spec spec="overall, h" convert="in"]H inches</span>');
                        } else {
                            echo '<span class="js-selected-text">' . esc_html( $label ) . ' &#8212 <span class="small-caps">' . esc_html( $combo_code ) . '</span></span>';
                        } ?>
                        
                    </div>
                    
                    <?php
                } else {
                    ?>
                    <span class="product-attribute-selected js-selected-text"><?php echo esc_html( $attribute_value ); ?></span>
                    <?php
                } ?>
            </div>
        <?php }
            }
    }

    /**
     * Add the customization tab to the product page.
     * 
     * @param array $available_attributes The available attributes for the product.
     * 
     * @return void
     */
    public function customization_tab_content( $available_attributes ) {
        foreach( $available_attributes as $key => $value ) {
            $pretty_name = ucwords( str_replace( '_', ' ', $key ) );
            $pretty_name = str_replace( '-', ' ', $pretty_name );
            if ($pretty_name === 'Hardware finish') {
                $pretty_name = 'Hardware';
            }
            $hidden = '';
            if ( $pretty_name === 'Dimensions' ) {
                $hidden = 'hidden';
            }

            if ( empty($value) || count($value) <= 1 && isset($value['X']) || count($value) <= 1 && isset($value['XXX'] ) ) {
                //only one value is x
            } else {
            ?>
                <p class="<?php echo esc_attr( $key ); ?> js-selected-text__parent <?php echo ($pretty_name === 'Dimensions') ? 'quickview' : ''; ?>">

                    <strong><?php echo esc_html( $pretty_name ); ?>:</strong><br/>
                    <span class="<?php echo esc_attr( $hidden ); ?> product-attribute-selected product-attribute-selected--thumb product-attribute-selected--empty" data-thumb=""><span class="js-selected-text <?php echo esc_attr( $hidden ); ?>"><span class="name"></span> - <span class="small-caps"></span></span></span>
                    <?php if ( $pretty_name === 'Dimensions' ) {
                        echo do_shortcode('<span class="js-selected-text">[dynamic_spec spec="overall, w" convert="cm"]W x [dynamic_spec spec="overall, d" convert="cm"]D x [dynamic_spec spec="overall, h" convert="cm"]H cm / [dynamic_spec spec="overall, w" convert="in"]W x [dynamic_spec spec="overall, d" convert="in"]D x [dynamic_spec spec="overall, h" convert="in"]H inches</span>');
                    } ?>
                </p>
            <?php }
            } ?>
        
        <?php
    }

    /**
     * Create a data attribute string for the trending item.
     * 
     * @param array $trending_item The trending item data.
     * 
     * @return string The data attribute string.
     */
    public function create_data_attr_for_trending( $trending_item ) {
        $data_string = '';
        $data_json = json_encode($trending_item);
        $data_string = "data-attributes='" . $data_json . "' "; 
        return $data_string;
    }

    /**
     * Get the trending product data.
     * 
     * @param int|null $product_id The product ID.
     * 
     * @return array The trending product data.
     */
    public function get_trending_product_data( $product_id = null ) {

        if (! $product_id) {
            global $post;
            $product_id = $post->ID;
        }

        $trending_groups = array( 1 => 'trending_1', 2 => 'trending_2', 3 => 'trending_3', 4 => 'trending_4', 5 => 'trending_5', 6 => 'trending_6' ); // acf group names
        $trending = array();

        foreach ( $trending_groups as $key => $trending_group ) {
            if ( have_rows( $trending_group, $product_id ) ) : 
                $trending[$key] = array();
                while ( have_rows( $trending_group, $product_id ) ) : the_row(); 
                    if ( have_rows( 'attributes_to_include' ) ): 

                       $trending[$key] = $this->loop_attribute_rows( $trending[$key] ); 
                    else: 
                        // no layouts found 
                    endif; 
                endwhile; 
            endif; 
        }
        if ( current_user_can('administrator') ) {
            $this->set_default_variation( $trending[1] );
        }
        return $trending;
    }

    /**
     * Set the default variation for the product based on the trending item.
     * If Geovin changes the trending items, we want to automatically
     * update the default variation for the product.
     * 
     * @param array $trending The trending item data.
     * 
     * @return void
     */
    public function set_default_variation( $trending ) {
        global $product;

        $default_attributes = get_post_meta( $product->get_id(), '_default_attributes', true);
        if ( isset( $trending ) ) {
            $default_attributes['pa_dimensions'] = isset($trending['dimensions']['dimensions']['code']) ? sanitize_title( $trending['dimensions']['dimensions']['code'] ) : 'xxxx';
            $default_attributes['pa_wood-type'] = isset($trending['finish']['wood-type']['name']) ? sanitize_title( $trending['finish']['wood-type']['name'] ) : 'x';
            $default_attributes['pa_finish'] = isset($trending['finish']['finish']['name']) ? sanitize_title( $trending['finish']['finish']['name'] ) : 'xxx';
            $default_attributes['pa_hardware-shape'] = isset( $trending['hardware-finish']['hardware-shape']['name'] ) ? sanitize_title( $trending['hardware-finish']['hardware-shape']['name'] ) : 'xxx';
            $default_attributes['pa_hardware-finish'] = isset( $trending['hardware-finish']['hardware-finish']['name'] ) ? sanitize_title( $trending['hardware-finish']['hardware-finish']['name'] ) : 'x';
            $default_attributes['pa_base-finish'] = isset( $trending['base-finish']['base-finish']['name'] ) ? sanitize_title( $trending['base-finish']['base-finish']['name'] ) : 'x';

            //get_term_geovin_code
            $default_attributes['pa_doors'] = isset( $trending['doors']['doors']['code'] ) ? sanitize_title( $trending['doors']['doors']['code'] ) : 'xxx';
            $default_attributes['pa_headboard-panel'] = isset( $trending['headboard-panel']['headboard-panel']['code'] ) ? sanitize_title( $trending['headboard-panel']['headboard-panel']['code'] ) : 'xxx';
            $default_attributes['pa_fabric'] = isset( $trending['fabric']['fabric']['name'] ) ? sanitize_title( $trending['fabric']['fabric']['name'] ) : 'xxx';
        }
        update_post_meta( $product->get_id(), '_default_attributes', $default_attributes );

    }

    /**
     * used by get_trending_product_data() to collect the data for each trending item
     * 
     * @param array $trending_item The trending item data.
     * 
     * @return array The modified trending item data.
     */
    public function loop_attribute_rows( $trending_item ) {
        while ( have_rows( 'attributes_to_include' ) ) : the_row();
            if ( get_row_layout() == 'dimensions' ) :
                $dimension_term = get_sub_field('dimension_value');
                if ( ! is_object( $dimension_term ) ) {
                    $dimension_term = get_term( $dimension_term );
                }
                $geovin_code = $this->get_term_geovin_code($dimension_term);
                $trending_item['dimensions']['dimensions'] = array( 'name' => get_sub_field('dimension_value'), 'code' => $geovin_code );
            elseif ( get_row_layout() == 'finish' ) : 
                $wood_type_term = get_sub_field( 'wood_type' ); 
                if ( $wood_type_term ): 
                    if ( ! is_object( $wood_type_term ) ) {
                        $wood_type_term = get_term( $wood_type_term );
                    }
                    $geovin_code = $this->get_term_geovin_code( $wood_type_term );
                    $trending_item['finish']['wood-type'] = array('name' => $wood_type_term->name, 'code' => $geovin_code);
                endif; 
                $finish_term = get_sub_field( 'finish' );
                if ( $finish_term ): 
                    if ( ! is_object( $finish_term ) ) {
                        $finish_term = get_term( $finish_term );
                    }
                    $geovin_code = $this->get_term_geovin_code( $finish_term );
                    $trending_item['finish']['finish'] = array('name' => $finish_term->name, 'code' => $geovin_code);
                endif; 
            elseif ( get_row_layout() == 'hardware_finish' ) : 
                $hardware_shape_term = get_sub_field( 'hardware_shape' ); 
                if ( $hardware_shape_term ): 
                    if ( ! is_object( $hardware_shape_term ) ) {
                        $hardware_shape_term = get_term( $hardware_shape_term );
                    }
                    $geovin_code = $this->get_term_geovin_code( $hardware_shape_term );
                    $trending_item['hardware-finish']['hardware-shape'] = array('name' => $hardware_shape_term->name, 'code' => $geovin_code); 
                endif; 
                $hardware_finish_term = get_sub_field( 'hardware_finish' ); 
                if ( $hardware_finish_term ): 
                    if ( ! is_object( $hardware_finish_term ) ) {
                        $hardware_finish_term = get_term( $hardware_finish_term );
                    }
                    $geovin_code = $this->get_term_geovin_code( $hardware_finish_term );
                    $trending_item['hardware-finish']['hardware-finish'] = array('name' => $hardware_finish_term->name, 'code' => $geovin_code); 
                endif; 
            elseif ( get_row_layout() == 'base_finish' ) : 
                $base_finish_term = get_sub_field( 'base_finish' ); 
                if ( $base_finish_term ): 
                    if ( ! is_object( $base_finish_term ) ) {
                        $base_finish_term = get_term( $base_finish_term );
                    }
                    $geovin_code = $this->get_term_geovin_code( $base_finish_term );
                    $trending_item['base-finish']['base-finish'] = array('name' => $base_finish_term->name, 'code' => $geovin_code); 
                endif; 
            elseif ( get_row_layout() == 'doors' ) : 
                $door_term = get_sub_field( 'doors' ); 
                if ( $door_term ): 
                    if ( ! is_object( $door_term ) ) {
                        $door_term = get_term( $door_term );
                    }
                    $geovin_code = $this->get_term_geovin_code( $door_term );
                    $trending_item['doors']['doors'] = array('name' => $door_term->name, 'code' => $geovin_code); 
                endif; 
            elseif ( get_row_layout() == 'headboard_panel' ) : 
                $headboard_term = get_sub_field( 'headboard_panel' ); 
                if ( $headboard_term ): 
                    if ( ! is_object( $headboard_term ) ) {
                        $headboard_term = get_term( $headboard_term );
                    }
                    $geovin_code = $this->get_term_geovin_code( $headboard_term );
                    $trending_item['headboard-panel']['headboard-panel'] = array('name' => $headboard_term->name, 'code' => $geovin_code); 
                endif; 
            elseif ( get_row_layout() == 'fabric' ) : 
                $fabric_term = get_sub_field( 'fabric' ); 
                if ( $fabric_term ): 
                    if ( ! is_object( $fabric_term ) ) {
                        $fabric_term = get_term( $fabric_term );
                    }
                    $geovin_code = $this->get_term_geovin_code( $fabric_term );
                    $trending_item['fabric']['fabric'] = array('name' => $fabric_term->name, 'code' => $geovin_code); 
                endif; 
            endif;
        endwhile; 

        return $trending_item;
    }

    /**
     * Get all possible attribute combinations that Geovin offers
     * 
     * @return array The attribute combinations.
     */
    public function get_all_attribute_combinations() {
        $combinations = $this->compute_attribute_combinations();
        set_transient('geovin_attribute_combination', $combinations );

        return $combinations;
    }

    /**
     * Compute the attribute combinations based on the ACF field 'combinations'.
     * 
     * @return array|false The attribute combinations or false if no combinations found.
     */
    public function compute_attribute_combinations() {
        $combinations = false;
        if ( have_rows( 'combinations', 'option' ) ) :
            $combinations = array();
            while ( have_rows( 'combinations', 'option' ) ) : the_row();
                $combination_category = get_sub_field( 'combination_category' );
                $combination_label = get_sub_field( 'label' );
                $image = get_sub_field( 'image' ); //$image['url']
                $combination_taxonomies = get_sub_field('attributes');
                $first_code = '';
                $second_code = '';
                foreach ( $combination_taxonomies as $term ) {
                    if ( $term->taxonomy === 'pa_wood-type' || $term->taxonomy === 'pa_hardware-shape' ) {
                        $first_code = $this->get_term_geovin_code( $term );
                    } else {
                        $second_code = $this->get_term_geovin_code( $term );
                    }
                }
                $combination_code = $first_code !== '' ? $first_code . '-' . $second_code : $second_code;
                $combinations[$combination_category][$combination_code] = array(
                    'label' => $combination_label,
                    'image' => $image['url'],
                );                 
            endwhile;
        endif;
        return $combinations;
    }

    /**
     * Clear the attribute combination transient.
     * This is used to clear the transient when a new combination is saved.
     * 
     * @return void
     */
    public function clear_attribute_combination_transient() {
        delete_transient( 'geovin_attribute_combination' );
    }

    /**
     * Clear the transient when a new combination is saved.
     * This is hooked to the 'save_post' action.
     * 
     * @return void
     */
    public function clear_transient_on_combo_save() {
        $screen = get_current_screen();
        if ( $screen->id === "product_page_attribute-combinations" ) {
            $this->clear_attribute_combination_transient();
        }
        
    }

    /**
     * Get the term by Geovin code.
     * 
     * @param string $code The Geovin code.
     * @param int $position The position of the attribute in the taxonomy.
     * 
     * @return WP_Term|false The term object or false if not found.
     */
    public static function get_term_by_geovin_code( $code, $position ) {
        $taxonomies = array(
            3 => 'pa_dimensions',
            4 => 'pa_wood-type',
            5 => 'pa_finish',
            6 => 'pa_hardware-shape',
            7 => 'pa_hardware-finish',
            8 => 'pa_base-finish',
            9 => 'pa_doors',
            10 => 'pa_headboard-panel',
            11 => 'pa_fabric',
        );

        $terms = get_terms( array(
            'taxonomy' => $taxonomies[$position],
            'hide_empty' => false,
            'meta_query' => array(
                array(
                   'key'       => 'code',
                   'value'     => $code,
                   
                )
            ),
        ) );

        if ( isset($terms[0]) ) {
            return $terms[0];
        } else {
            return false;
        }
        
    }

    /**
     * Get the Geovin code for a term.
     * 
     * @param WP_Term $term The term object.
     * 
     * @return string The Geovin code for the term.
     */
    public function get_term_geovin_code( $term ) {

        $taxonomy_prefix = $term->taxonomy;
        $term_id = $term->term_id;
        $term_id_prefixed = $taxonomy_prefix .'_'. $term_id;
        $geovin_code = get_field( 'code', $term_id_prefixed );

        return $geovin_code;
    }
}

new Geovin_Product_Page();