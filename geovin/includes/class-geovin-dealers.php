<?php
/**
 * Geovin Dealers
 */
namespace Geovin;

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

class Geovin_Dealers {

    private static $price_shown;
    private static $excluded_price_toggle_paths;
    private static $excluded_price_toggle_pages;

    /**
     * Build the instance
     */
    public function __construct() {

        self::$excluded_price_toggle_paths = array(
            '/my-account/',
            '/my-account/orders/',
            '/cart/',
            '/checkout/',
        );
        self::$excluded_price_toggle_pages = array(
            'my-account',
            'my-account/orders',
            'cart',
            'checkout',
        );


        add_action('woocommerce_init', array( $this, 'check_price_shown' ) );

        //register post type to create Dealer Organizations
        add_action( 'init', array( $this, 'create_dealer_cpt' ) );

        //Establish Relationship between users and org
        //This has been done via ACF

        //Add Pricing Tier Taxonomy
        add_action( 'init', array( $this, 'create_pricing_teir_taxonomy' ) );
        //Set tax to use radio

        add_filter( 'woocommerce_get_price_excluding_tax', array( $this, 'maybe_adjust_price_for_dealers' ), 10, 3 ); // this may be redundant
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'maybe_filter_cart_price_for_dealers' ), 9999 );

        add_filter( 'woocommerce_cart_totals_order_total_html', array( $this, 'add_formatting_to_cart_totals'), 9999, 1  ); 
        add_filter( 'woocommerce_cart_subtotal', array( $this, 'add_formatting_to_cart_subtotals'), 9999, 3  ); 

        //Pre-populate address location options for dealer team members
        add_filter('acf/load_field/name=select_primary_address', array( $this, 'acf_load_address_choices' ), 10, 1 );

        //Set dynamic add user link to easily associate them with currently selected dealer
        add_filter('acf/load_field/name=user', array( $this, 'acf_link_new_user_for_dealer' ), 10, 1 );

        //populate dealer when adding new user from dealer cpt edit page
        add_filter( 'members_default_user_roles', array( $this, 'filter_default_role_when_adding_dealer_user' ), 10, 1 );
        add_filter( 'editable_roles', array( $this, 'filter_available_roles' ), 10, 1 ); 
        add_action( 'admin_head-user-new.php', array($this,'hide_website_field_css') );
        add_action( 'init', array( $this, 'load_new_user_with_dealer_data' ) );

        //Keep the Delear/User Relationships in sync
        add_filter('acf/update_value/name=managers_and_staff', array( $this, 'bidirectional_acf_relationship' ), 10, 3);
        add_filter('acf/update_value/name=related_dealer', array( $this, 'bidirectional_acf_relationship' ), 10, 3);
        // Neccessary to reference ACF date pre updating fields, especially when using a repeater
        add_filter('wp_insert_post_data', array($this, 'filter_save'), 10, 2);

        //Set extra normalized address data on address save
        add_filter('acf/update_value/name=address_location', array( $this, 'save_normalized_address_data' ), 10, 3);


        //Set permissions so that Dealer Admins can manage users in their org, add/remove

        //add toggle for price shown
        add_action( 'price_shown_toggle', array( $this, 'maybe_render_pricing_toggle' ) );

        //Add Ajax to toggle price shown
        add_action( 'wp_ajax_set_price_shown', array( $this, 'set_price_shown' ) );
        add_action( 'wp_ajax_nopriv_set_price_shown', array( $this, 'set_price_shown' ) );
    }

    /**
     * Find the country abbreviation for a given country name
     * 
     * @param string $country The full country name
     * 
     * @return string|false The country abbreviation or false if not found
     */
    public static function find_country_abbr( $country ) {
        $countries = WC()->countries->get_countries();

        foreach( $countries as $key => $value ) {
            if ( $value === $country ) {
                return $key;
            }
        }
        return false;
    }

    /**
     * Find the state abbreviation for a given state name and country code
     * 
     * @param string $state The full state name
     * @param string $country The country code
     * 
     * @return string|false The state abbreviation or false if not found
     */
    public static function find_state_abbr( $state, $country ) {
        $states = WC()->countries->get_states( $country );

        foreach( $states as $key => $value ) {
            if ( $value === $state ) {
                return $key;
            }
        }
        return false;
    }

    /**
     * Save normalized address data to the address field
     * 
     * @param array $value The address field value
     * @param int $post_id The post ID
     * @param array $field The field array
     * 
     * @return array The modified address field value
     */
    public function save_normalized_address_data( $value, $post_id, $field ) {

        $revert_to_main_address = false;

        //get street number if it is not empty
        if ( ! empty($value['street_number'] ) ) {
            //get street name if not empty
            if ( ! empty( $value['street_name'] ) ) {
                $value['normalized_address_line_1'] = $value['street_number'] . ' ' . $value['street_name'];
            } elseif ( ! empty($value['street_name_short'] ) ) {
                //if empty get street short name
                $value['normalized_address_line_1'] = $value['street_number'] . ' ' . $value['street_name_short'];
            } else {
                //if both empty use main address field
                $revert_to_main_address = true;
            }
        } else {
            //if street number is empty use main address field
            $revert_to_main_address = true;
        }

        //get subpremise if exists
        if ( ! empty( $value['subpremise'] ) && ! $revert_to_main_address ) {
            //check if it's a number
            if ( is_numeric( $value['subpremise'] ) ) {
              // add # to beginning if number
                $new_subpremise = '# ' . strval( $value['subpremise'] );
            } else {
              // else make sure first letter is capitalized
                $new_subpremise = ucfirst( $value['subpremise'] );
            }


            //check the length of subpremise and address line 1 and append if short enough
            if (((strlen( $value['normalized_address_line_1'] ) + strlen( $new_subpremise )) < 50 ) && (strlen( $new_subpremise ) < 10 )) {
                $value['normalized_address_line_1'] = $value['normalized_address_line_1'] . ', ' . $new_subpremise;
            } else {
                //else set to address line 2
                $value['normalized_address_line_2'] = $new_subpremise;
            }   
        }

        //get city if exists
        if ( ! empty( $value['city'] ) && ! $revert_to_main_address ) {
            $value['normalized_city'] = ucfirst( $value['city'] );
        }

        //get country name if not empty
        if ( ! empty( $value['country_short'] ) && ! $revert_to_main_address ) {
            $value['normalized_country'] = $value['country_short'];
        } elseif ( ! empty( $value['country'] ) && ! $revert_to_main_address ) {
            $value['normalized_country'] = self::find_country_abbr( $value['country'] );
        } else {
            //we have no country get from main
            $revert_to_main_address = true;
        }

        //get state short if not empty
        if ( ! empty( $value['state_short'] ) ) {
            $value['normalized_state'] = $value['state_short'];
        } elseif ( ! empty($value['state'] ) ) {
            //if empty get state name
            $value['normalized_state'] = self::find_state_abbr( $value['state'], $value['normalized_country'] );
        } else {
            //if both empty use main address field
            $revert_to_main_address = true;
        }

        //get postcode if not empty
        if ( ! empty( $value['post_code'] ) ) {
            $value['normalized_postcode'] = $value['post_code'];
        } else {
            $revert_to_main_address = true;
        }

        $value = self::reassign_normalized_values( $value );

        return $value;
    }

    /**
     * Reassign normalized values to main address fields
     * 
     * @param array $value The address field value
     * 
     * @return array The modified address field value
     */
    public static function reassign_normalized_values( $value ) {
        if ( ! empty( $value['normalized_address_line_2'] ) ) {
            $value['subpremise'] = $value['normalized_address_line_2'];
        } else {
            $value['subpremise'] = '';
        }

        if ( ! empty( $value['normalized_city'] ) ) {
            $value['city'] = $value['normalized_city'];
        }

        if ( ! empty( $value['normalized_state'] ) ) {
            $value['state_short'] = $value['normalized_state'];
        }

        if ( ! empty( $value['normalized_state'] ) ) {
            $value['state_short'] = $value['normalized_state'];
        }

        if ( ! empty( $value['normalized_country'] ) ) {
            $value['country_short'] = $value['normalized_country'];
        }

        if ( ! empty( $value['normalized_postcode'] ) ) {
            $value['post_code'] = $value['normalized_postcode'];
        }

        return $value;
    }

    /**
     * Get the primary address name assigned to a user
     * 
     * @param int $user_id The user ID
     * 
     * @return string|false The address name or false if not found
     */
    public static function get_user_assigned_address( $user_id ) {
        $address_name = false;
        $dealer = self::get_dealer( $user_id );
        $field_count = count( get_field('managers_and_staff', $dealer->ID, false) );
        $meta = get_post_meta( $dealer->ID );

        for ( $i = 0; $i < $field_count; $i++ ) {
            $this_user = get_field('managers_and_staff_' . $i . '_user', $dealer->ID, false);
            if ( $this_user == $user_id ) {
                $address_name = get_field( 'managers_and_staff_' . $i . '_select_primary_address', $dealer->ID, false);
            }
        }

        return $address_name;

    }

    /**
     * Get dealer managers for a given user
     * 
     * @param int $user_id The user ID
     * 
     * @return array The dealer manager users
     */
    public static function get_dealer_managers( $user_id = null ) {
        if ( ! $user_id ) {
            //assume we want the logged in user
            $user_id = get_current_user_id();
        }

        $dealer = get_field( 'related_dealer', 'user_' . $user_id );

        //Get users that have a role of manager and a related_dealer value matching the dealer ID for this user
        $args = array(
            'role' => 'dealer_manager',
            'meta_query'=> array(
                array(
                    array(
                        'key' => 'related_dealer',
                        'value' => $dealer->ID,
                        'compare' => "=",
                    ),
                )
            )
        );
        $users = get_users( $args );

        return $users;

    }

    /**
     * Get the dealer for a given user
     * 
     * @param int $user_id The user ID
     * 
     * @return WP_Post|false The dealer post object or false if not found
     */
    public static function get_dealer( $user_id = null ) {
        if ( ! $user_id ) {
            //assume we want the logged in user
            $user_id = get_current_user_id();
        }

        $dealer = get_field( 'related_dealer', 'user_' . $user_id );
        return $dealer;
    }

    /**
     * Get the addresses for a given dealer
     * 
     * @param int $dealer_id The dealer post ID
     * 
     * @return array|false The addresses or false if not found
     */
    public static function get_dealer_addresses( $dealer_id ) {
        $addresses = array();
        // if has rows
        if( have_rows('addresses', $dealer_id) ) {      
            // while has rows
            while( have_rows('addresses', $dealer_id) ) {        
                // instantiate row
                the_row();    
                // vars
                $value = get_sub_field('address_location');
                $name = get_sub_field('address_name');
                if ( ! empty( $value ) )  {
                    $addresses[] = array( 'location_data' => $value, 'name' => $name );
                }
            }
        }
        if ( ! empty( $addresses ) ) {
            return $addresses;
        } else {
            return false;
        }
    }

    /**
     * Get the dealer rate adjustment multiplier
     * 
     * @return float The rate adjustment multiplier
     */
    public static function get_dealers_rate_adjustment() {
        $multiplier = 1;
        if ( user_can_build_order() ) {
            $user = wp_get_current_user();
            $dealer = get_field( 'related_dealer', 'user_' . $user->ID );
            if ( $dealer ) {
                $tiers = get_terms(
                    array(
                        'taxonomy' => 'pricing-tier',
                        'object_ids' => $dealer->ID,
                    )
                );
                $primary_tier = $tiers[0];
                $term_id_prefixed = 'pricing-tier_'. $primary_tier->term_id;
                $discount = get_field( 'price_adjustment', $term_id_prefixed );
                $multiplier = ( 100 - $discount ) / 100;
            }
        }
        return $multiplier;
    }

    /**
     * Set the price shown (MSRP/Cost) in session via ajax
     * 
     * @return void
     */
    public function set_price_shown() {
        // Early initialize customer session
        if ( isset(WC()->session) && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
        $price_type = $_POST['price_type'];
        \WC()->session->set('price_shown',$price_type);
        echo 'complete';
        wp_die();
    }

    /**
     * Render the price shown toggle if user is a dealer and not on excluded pages
     * 
     * @return void
     */
    public function maybe_render_pricing_toggle() {
        if ( user_can_build_order() && ! is_page( self::$excluded_price_toggle_pages ) ) {
            wp_enqueue_script('geovin-pricing-toggle', get_plugin_url() . 'assets/js/pricing-toggle.js', array('jquery'), '1', true );
            wp_localize_script('geovin-pricing-toggle','ajax_price_shown',
                array( 
                    'ajax_url' => admin_url( 'admin-ajax.php' ), 
                    'price_shown' => json_encode(self::$price_shown),
                )
            );
            ?>
            <div class="price-shown toggle" id="price-shown_wrapper">&nbsp;<label for="price-shown">DEALER COST<input type="checkbox" id="price-shown" name="price-shown" <?php echo self::$price_shown === 'MSRP' ? 'checked' : ''; ?> /><span class="slider"></span>MSRP</label></div>
            <?php
        }
        
    }


    /**
     * Filter the cart prices for the dealers rate adjustment
     * 
     * @param WC_Cart $cart The WooCommerce cart object
     * 
     * @return void
     */
    public function maybe_filter_cart_price_for_dealers( $cart ) {

        if ( self::$price_shown === 'MSRP' ) {
            return $cart;
        }
 
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        } 
     
        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
            return;
        }
     
        // LOOP THROUGH CART ITEMS & APPLY DISCOUNT
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];
            $price = $product->get_price();
            $adjusted_price = $price * self::get_dealers_rate_adjustment();
            $cart_item['data']->set_price( $adjusted_price );
        }
     
    }

    /**
     * Add formatting to cart totals to indicate price type
     * 
     * @param string $value The cart total HTML
     * 
     * @return string The modified cart total HTML
     */
    public function add_formatting_to_cart_totals( $value ) {
        return $value . ' (' . self::$price_shown . ')';
    }

    /**
     * Add formatting to cart subtotal to indicate price type
     * 
     * @param string $cart_subtotal The cart subtotal HTML
     * @param bool $compound Whether the subtotal is compound
     * @param WC_Cart $cart The WooCommerce cart object
     * 
     * @return string The modified cart subtotal HTML
     */
    public function add_formatting_to_cart_subtotals( $cart_subtotal, $compound, $cart ) {
        $price_label = self::$price_shown === 'COST' ? 'Dealer Cost' : self::$price_shown;
        return '(' . $price_label . ') ' . $cart_subtotal;
    }

    /**
     * Check the referer URL for excluded paths
     * 
     * @param string $referer The referer URL
     * 
     * @return bool True if the referer contains an excluded path, false otherwise
     */
    private static function check_referer_for_exclusion( $referer ) {
        foreach( self::$excluded_price_toggle_paths as $page ) {
            if ( strpos($referer, $page) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check and set the price shown (MSRP/Cost) in session
     * 
     * @return string The price shown
     */
    public function check_price_shown() {
        $price_shown = false;

        $request = explode( '?', $_SERVER['REQUEST_URI'] )[0];
        if ( in_array( $request, self::$excluded_price_toggle_paths ) ) {
            $price_shown = 'COST';

        //check if we are running ajax from page that should only reference COST
        } elseif( isset($_SERVER['QUERY_STRING']) && strpos( $_SERVER['QUERY_STRING'], 'wc-ajax' ) !== false && self::check_referer_for_exclusion( $_SERVER['HTTP_REFERER'] ) ) {
            $price_shown = 'COST';

        // if not let's go with the save session var
        } else {
            if ( isset( \WC()->session) && ! \WC()->session->has_session() ) {
                \WC()->session->set_customer_session_cookie( true );
            }
            if ( \WC()->session ) {
                $price_shown = \WC()->session->get('price_shown');
                if ( empty( $price_shown ) ) {
                    \WC()->session->set('price_shown','MSRP');
                    $price_shown = 'MSRP';
                }
            }
        }
        
        self::$price_shown = $price_shown;
        return $price_shown;
        
    }

    /**
     * Maybe adjust the price for dealers based on the dealers rate adjustment
     * AND what price is being shown (MSRP/Cost)
     * 
     * @param float $return_price The original price
     * @param int $qty The quantity
     * @param WC_Product $product The product object
     * @param string $context The context (default: 'product')
     * 
     * @return float The adjusted price
     */
    public function maybe_adjust_price_for_dealers( $return_price, $qty, $product, $context = 'product' ) {
        if ( self::$price_shown === 'MSRP' ) {
            return $return_price;
        }
        if ( $context === 'product' && did_action( 'woocommerce_before_calculate_totals' ) > 0 ) {
            return $return_price;
        }

        $return_price = $return_price * self::get_dealers_rate_adjustment();
        return $return_price;
    }

    /**
     * Create the dealer custom post type
     * 
     * @return void
     */
    public function create_dealer_cpt() {
        $labels = array(
            'name'               => _x( 'Dealers', 'post type general name', 'geovin' ),
            'singular_name'      => _x( 'Dealer', 'post type singular name', 'geovin' ),
            'menu_name'          => _x( 'Dealers', 'admin menu', 'geovin' ),
            'name_admin_bar'     => _x( 'Dealer', 'add new on admin bar', 'geovin' ),
            'add_new'            => _x( 'Add New', 'book', 'geovin' ),
            'add_new_item'       => __( 'Add New Dealer', 'geovin' ),
            'new_item'           => __( 'New Dealer', 'geovin' ),
            'edit_item'          => __( 'Edit Dealer', 'geovin' ),
            'view_item'          => __( 'View Dealer', 'geovin' ),
            'all_items'          => __( 'All Dealers', 'geovin' ),
            'search_items'       => __( 'Search Dealers', 'geovin' ),
            'parent_item_colon'  => __( 'Parent Dealer:', 'geovin' ),
            'not_found'          => __( 'No Dealers found.', 'geovin' ),
            'not_found_in_trash' => __( 'No Dealers found in Trash.', 'geovin' ),
        );

        $args = array(
            'labels'              => $labels,
            'description'         => __( 'Description.', 'geovin' ),
            'menu_icon'           => 'dashicons-store',
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array( 'slug' => 'dealer' ),
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'exclude_from_search' => true,
            'menu_position'       => null,
            'supports'            => array( 'title' ),
        );

        register_post_type( 'geovin_dealer', $args );
    }

    /**
     * Create the pricing tier taxonomy
     * 
     * @return void
     */
    public function create_pricing_teir_taxonomy() {
        $labels = array(
            'name'          => _x( 'Pricing Tiers', 'taxonomy general name', 'geovin' ),
            'singular_name' => _x( 'Pricing Tier', 'taxonomy singular name', 'geovin' ),
            'search_items'  => __( 'Search Pricing Tiers', 'geovin' ),
            'all_items'     => __( 'All Pricing Tiers', 'geovin' ),
            'edit_item'     => __( 'Edit Pricing Tier', 'geovin' ),
            'update_item'   => __( 'Update Pricing Tier', 'geovin' ),
            'add_new_item'  => __( 'Add New Pricing Tier', 'geovin' ),
            'new_item_name' => __( 'New Pricing Tier name', 'geovin' ),
            'menu_name'     => __( 'Pricing Tier', 'geovin' ),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'pricing-tier' ),
            'meta_box_cb'       => array( $this, 'pricing_tier_meta_box' ),
        );

        register_taxonomy( 'pricing-tier', 'geovin_dealer', $args );
    }

    /**
     * Custom meta box to use radio buttons for pricing tier selection
     * 
     * @param WP_Post $post The post object
     * @param array $meta_box_properties The meta box properties
     * 
     * @return void
     */
    public function pricing_tier_meta_box($post, $meta_box_properties){
          $taxonomy = $meta_box_properties['args']['taxonomy'];
          $tax = get_taxonomy($taxonomy);
          $terms = get_terms($taxonomy, array('hide_empty' => 0));
          $name = 'tax_input[' . $taxonomy . ']';
          $postterms = get_the_terms( $post->ID, $taxonomy );
          $current = ($postterms ? array_pop($postterms) : false);
          $current = ($current ? $current->term_id : 0);
        ?>
        <div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
          <ul id="<?php echo $taxonomy; ?>-tabs" class="category-tabs">
            <li class="tabs"><a href="#<?php echo $taxonomy; ?>-all"><?php echo $tax->labels->all_items; ?></a></li>
          </ul>

          <div id="<?php echo $taxonomy; ?>-all" class="tabs-panel">
            <input name="tax_input[<?php echo $taxonomy; ?>][]" value="0" type="hidden">            
            <ul id="<?php echo $taxonomy; ?>checklist" data-wp-lists="list:symbol" class="categorychecklist form-no-clear">
        <?php   foreach($terms as $term){
              $id = $taxonomy.'-'.$term->term_id;?>
              <li id="<?php echo $id?>"><label class="selectit"><input value="<?php echo $term->term_id; ?>" name="tax_input[<?php echo $taxonomy; ?>][]" id="in-<?php echo $id; ?>"<?php if( $current === (int)$term->term_id ){?> checked="checked"<?php } ?> type="radio"> <?php echo $term->name; ?></label></li>
        <?php   }?>
            </ul>
          </div>
        </div>
        <?php
    }

    /**
     * Get all pricing tiers
     * 
     * @return array The pricing tiers
     */
    public static function get_pricing_tiers() {
        $tiers = get_terms([
            'taxonomy' => 'pricing-tier',
            'hide_empty' => false,
        ]);

        return $tiers;
    }

    /**
     * Get all dealers
     * 
     * @return array The dealer posts
     */
    public static function get_dealers() {
        $dealers = get_posts([
          'post_type' => 'geovin_dealer',
          'post_status' => 'publish',
          'numberposts' => -1,
          'order'          => 'ASC',
          'orderby'        => 'title',
        ]);

        return $dealers;
    }

    /**
     * Get the available address choices for the dealer
     * and load into the ACF select field
     * 
     * @param array $field The ACF field array
     * @return array The modified ACF field array
     */
    public function acf_load_address_choices( $field ) {
        // reset choices
        $field['choices'] = array();


        // if has rows
        if( have_rows('addresses') ) {      
            // while has rows
            while( have_rows('addresses') ) {        
                // instantiate row
                the_row();    
                // vars
                $value = get_sub_field('address_name');
                // append to choices
                $field['choices'][ $value ] = $value;   
            }
        }

        return $field;
    }

    /**
     * Show the Dealer's name in the user relationship field
     * 
     * @param array $field The value to be saved
     * @return array The modified value
     */
    public function acf_link_new_user_for_dealer( $field ) {
        global $post;
        if ( $post && strpos( $field['instructions'], '{{post_id}}' ) !== false ) {
            $field['instructions'] = str_replace( '{{post_id}}', $post->ID, $field['instructions'] );
        }
        return $field;
    }

    /**
     * Add the Dealer's ID to the new user creation form
     * 
     * @return void
     */
    public function load_new_user_with_dealer_data() {
        if ( is_admin() && isset( $_GET['dealer'] ) ) {
            $_POST['createuser'] = true;
        }
    }

    /**
     * Set the default role to dealer_staff when 
     * adding a user from the dealer edit screen
     * 
     * @param array $roles The available roles
     * @return array The modified roles
     */
    public function filter_default_role_when_adding_dealer_user( $roles ) {
        
        if ( isset( $_GET['dealer'] ) ) {
            $roles[0] = 'dealer_staff';
        }

        return $roles;
    }

    /**
     * Limit the available roles when adding a user from the dealer edit screen
     * 
     * @param array $all_roles The available roles
     * @return array The modified roles
     */
    public function filter_available_roles( $all_roles ) {
        if ( is_admin() && isset( $_GET['dealer'] ) ) {
            unset( $all_roles['administrator'] );
            unset( $all_roles['author'] );
            unset( $all_roles['contributor'] );
            unset( $all_roles['customer'] );
            unset( $all_roles['editor'] );
            unset( $all_roles['shop_manager'] );
            unset( $all_roles['subscriber'] );
        }

        return $all_roles;
    }

    /**
     * Adds CSS to hide the website field when creating a new user
     * Used with the 'admin_head-user-new.php' filter
     * 
     * @return void
     */
    public function hide_website_field_css() {
        if ( is_admin() && isset( $_GET['dealer'] ) ) {
            echo '<style>tr.form-field label[for="url"],tr.form-field input#url { display: none; }</style>';
        }
    }

    /**
     * When editing a Dealer CPT add the old value to $GLOBALS so we can reference it in a function that fires later
     * ACF update_value filter does not acurately track a repeaters old values, this is the work around
     * 
     * @param array $data The post data
     * @param array $postarr The original post data
     * @return array The modified post data
     */
    public function filter_save( $data, $postarr ) {
        if ( $postarr['post_type'] === 'geovin_dealer' ) {
            $old_val = get_field('managers_and_staff', $postarr['ID'], false);
            $old_users = array();
            if ( $old_val ) {
                foreach( $old_val as $val ) {
                    foreach( $val as $subval ) {
                        if ( is_numeric( $subval ) ) {
                            $old_users[] = $subval;
                        }
                    }
                }
                $GLOBALS['team_old_' . $postarr['ID']] = $old_users;
            }
        }

        return $data;
    }

    /**
     * Saves address to dealer
     * 
     * @param int $dealer_id The dealer post ID
     * @param string $address_1 The first line of the address
     * @param string $address_2 The second line of the address
     * @param string $city The city
     * @param string $province The province/state
     * @param string $post_code The postal/zip code
     * @param string $country The country
     * @param string $address_name The name of the address (default: 'Primary')
     * 
     * @return void
     */
    public static function add_primary_address_to_dealer( $dealer_id, $address_1, $address_2, $city, $province, $post_code, $country, $address_name = 'Primary' ) {
        $rows = array(
            array(
                'address_name' => $address_name,
                'address_location' => array(
                    'address' => $address_1 . ' ' . $address_2 . ', ' . $city . ', ' . $province . ', ' . $post_code . ', ' . $country,
                    'street_name' => $address_1 . ' ' . $address_2,
                    'city' => $city,
                    'state' => $province,
                    'post_code' => $post_code,
                    'country_short' => $country,
                ),
            ),
        );

        update_field('addresses', $rows, $dealer_id );
    }

    /**
     * Saves dealer to user
     * 
     * @param int $user_id The user ID
     * @param int $dealer_id The dealer post ID
     * 
     * @return void
     */
    public static function add_dealer_to_user( $user_id, $dealer_id ) {
        $dealer = get_post( $dealer_id );
        update_field( 'related_dealer', $dealer, 'user_' . $user_id );
    }

    /**
     * Saves user to dealer
     * 
     * @param int $user_id The user ID
     * @param int $dealer_id The dealer post ID
     * @param string|null $address_to_assign The address name to assign to the user (optional)
     * 
     * @return void
     */
    public static function add_user_to_dealer( $user_id, $dealer_id, $address_to_assign = null ) {
        $row = array(
            'user' => $user_id,
        );
        if ( $address_to_assign ) {
            $row['select_primary_address'] = $address_to_assign;
        }
        add_row( 'managers_and_staff', $row, $dealer_id );
    }

    /**
     * Remove user from dealer
     * 
     * @param int $user_id The user ID
     * @param int $dealer_id The dealer post ID
     * 
     * @return void
     */
    public static function remove_user_from_dealer( $user_id, $dealer_id ) {

        if ( have_rows( 'managers_and_staff', $dealer_id ) ) :
            while ( have_rows( 'managers_and_staff', $dealer_id) ) : the_row();
                $user = get_sub_field('user');
                if ( $user && $user_id == $user['ID'] ) {
                    $index_to_remove = get_row_index();
                }
            endwhile;
        endif;
        if ( isset($index_to_remove) ) {
            delete_row('managers_and_staff', $index_to_remove, $dealer_id);
        }
    }

    /**
     * Remove dealer from user
     * 
     * @param int $user The user ID
     * 
     * @return void
     */
    public static function remove_dealer_from_user( $user ) {
        update_field( 'related_dealer', '', 'user_' . $user );
    }

    /*
     * If user has this dealer
     * 
     * TODO: Not implemented
     */

    /*
     * If dealer has this user
     * 
     * TODO: Not implemented
     */

    /**
     * Get dealer's users
     * 
     * @param int $dealer_id The dealer post ID
     * @param int|bool $field_count The number of rows in the managers_and_staff repeater (optional)
     * 
     * @return array The user IDs
     */
    public static function get_dealer_users( $dealer_id, $field_count = false ) {
        $field_count = ! $field_count ? count( get_field('managers_and_staff', $dealer_id, false) ) : $field_count;
        $users = array();
        for ( $i = 0; $i < $field_count; $i++ ) {
            $users[] = get_field('managers_and_staff_' . $i . '_user', $dealer_id, false);
        }

        return $users;
    }

    /**
     * Update user address from dealer address if user has none specified
     * 
     * @param int $dealer_id The dealer post ID
     * @param int $user_id The user ID
     * 
     * @return void
     */
    public static function add_dealer_country_to_user( $dealer_id, $user_id ) {
        $address_count = get_field('addresses', $dealer_id);
        if ( $address_count > 0 ) {
            $addresses = get_field('addresses_0_address_location', $dealer_id);
            $country = $addresses['country_short'];

            //check if user has this set
            $billing_country = get_user_meta( $user_id, 'billing_country', true );
            
            if ( ! $billing_country ) {
                update_user_meta($user_id,'billing_country',$country);
            }
        }
    }

    /**
     * Keeps the user/dealer relationship data in sync when either field is changed
     * Important that both fields use the same function for the purpose of referencing locks and
     * preventing loops
     * 
     * @param mixed $value The value to be saved
     * @param int|string $post_id The post ID or 'user_{id}' for users
     * @param array $field The ACF field array
     * 
     * @return mixed The value to be saved
     */
    function bidirectional_acf_relationship( $value, $post_id, $field  ) {
        // vars refer to reciprocal field names
        $field_to_update = $field['name'] === 'related_dealer' ? 'managers_and_staff' : 'related_dealer';
        $field_to_check = $field['name'] === 'related_dealer' ? 'related_dealer' : 'managers_and_staff';
        $global_name = 'is_updating_' . $field_to_update;
        $global_check = 'is_updating_' . $field_to_check;

        // bail early if this filter was triggered from the update_field() function called within the loop below
        // - this prevents an inifinte loop
        if( isset( $GLOBALS[ $global_check ] ) && $GLOBALS[ $global_check ] == 1 ) {
            return $value;
        } 

        // set global variable to avoid inifite loop
        $GLOBALS[ $global_name ] = 1;

        // We are working with data when a dealer was saved
        if ( strpos( $field['name'], 'managers_and_staff') !== false ) {
            // This tells us how many rows are in the repeater
            // Indicating how many users exist in the new data
            $field_count = $value;
            //$value_to_update = $post_id;

            //Get the dealers previous users from before this new update
            //We want to remove users no longer present
            $old_users = isset( $GLOBALS['team_old_' . $post_id] ) ? $GLOBALS['team_old_' . $post_id] : false;

            //Get the new users that were in the latest save
            $new_users = self::get_dealer_users( $post_id, $field_count );

            if ( $old_users ) {
                //remove users no longer present
                foreach ( $old_users as $key => $old_user ) {
                    // check if the old users is in the new users array
                    if ( ! in_array( $old_user, $new_users ) ) {
                        //if not in the new users array, then we need to remove them from the dealer
                        self::remove_dealer_from_user( $old_user );
                    } else {
                        // we should remove this user from the new users array so we dont re-add them
                        unset( $new_users[$key] );
                    }
                }
            }

            if ( $new_users && ! empty( $new_users ) ) {
                foreach( $new_users as $key => $new_user ) {
                    self::add_dealer_to_user( $new_user, $post_id );
                }
            }
            //remove the lock so it will work next time
            unset( $GLOBALS['team_old_' . $post_id] );

        //We are working with data when a user was saved
        } elseif ( $field['name'] === 'related_dealer' ) {
            $user_id = str_replace('user_', '', $post_id);
            $old_dealer = get_field('related_dealer', $post_id, true);
            $old_dealer_id = $old_dealer ? $old_dealer->ID : null;
            $new_dealer_id = $value;
            if ( $old_dealer_id && $old_dealer_id !== $new_dealer_id ) {
                //if we have an old dealer and it is not the same as the new dealer
                self::remove_user_from_dealer( $user_id, $old_dealer_id );
            }
            if ( $new_dealer_id && $old_dealer_id !== $new_dealer_id ) {
                //if we have a new dealer and it is not the same as the old dealer
                self::add_user_to_dealer( $user_id, $new_dealer_id );
            }
            
        }
        
        // reset global varibale to allow this filter to function as per normal
        $GLOBALS[ $global_name ] = 0;
        
        // return
        return $value;
        
    }

}

new Geovin_Dealers();