<?php

namespace Geovin;


/**
 * Display the post content in the product description area.
 * This ensures that the block editor content is displayed on the woo template where we want it.
 */
add_action( 'woocommerce_after_single_product_summary', __NAMESPACE__ . '\add_description_content', 10 );
function add_description_content() {
    ?>
    <div class="entry-description">
        <?php the_content(); ?>
    </div>
    <?php
}

/**
 * Adds a collection carousel to the product page.
 */
add_action( 'woocommerce_after_single_product_summary', __NAMESPACE__ . '\add_collection_carousel', 10 );
function add_collection_carousel() {
    ?>
    <?php locate_template( 'template-parts/blocks/carousel.php', TRUE, TRUE ); ?>
    <?php
}

/**
 * Remove the default WooCommerce product tabs.
 * This is used to remove the tabs that are not needed for Geovin products.
 */
add_filter( 'woocommerce_product_tabs', __NAMESPACE__ . '\remove_tabs', 10 );
function remove_tabs() {
    return array();
}

/*
 * Remove product tabs and woo policies
 * Add Geovin privacy policy text
 */
add_action('plugins_loaded', __NAMESPACE__ . '\template_changes' );
function template_changes() {
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
    remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
    add_action( 'woocommerce_checkout_terms_and_conditions', __NAMESPACE__ . '\geovin_privacy_policy_text', 20 );
}

/**
 * Geovin privacy policy text
 */
function geovin_privacy_policy_text() {
    echo '<div class="woocommerce-privacy-policy-text"><small>';
    wc_privacy_policy_text( 'checkout' );
    echo '</div></small>';
}

/*
 * Filter My Account Sections in Woo
 */
add_filter( 'woocommerce_account_menu_items', __NAMESPACE__ . '\remove_my_account_links' );
function remove_my_account_links( $menu_links ){
    /* This has been removed from the my account page and is not in use
     * Please edit the My Account Menu in the WordPress Dashboard to update
     * My Account Sections that are available on the My Account Page
     */
    unset( $menu_links[ 'dashboard' ] ); // Remove Dashboard
    unset( $menu_links[ 'payment-methods' ] ); // Remove Payment Methods
    unset( $menu_links[ 'downloads' ] ); // Disable Downloads
    unset( $menu_links[ 'customer-logout' ] ); // Remove Logout link
    unset( $menu_links[ 'orders' ] ); // Remove Logout link

    $user = wp_get_current_user();
    if ( in_array( 'dealer_manager', $user->roles ) ) {
        $menu_links = array_slice( $menu_links, 0, 5, true ) 
        + array( 'manage-staff' => 'Manage Staff' )
        + array_slice( $menu_links, 5, NULL, true );
    } 
    
    return $menu_links;
    
}

/*
 * Add endpoints for My Account pages to allow for
 * adding, editing, and removing staff members.
 */
add_action( 'init', __NAMESPACE__ . '\add_endpoint' );
function add_endpoint() {

    add_rewrite_endpoint( 'manage-staff', EP_PAGES );
    add_rewrite_endpoint( 'add-staff', EP_PAGES );
    add_rewrite_endpoint( 'edit-staff', EP_PAGES );
    add_rewrite_endpoint( 'remove-staff', EP_PAGES );

}

/*
 * Add content for the new Manage Staff page in My Account.
 */
// content for the new page in My Account, woocommerce_account_{ENDPOINT NAME}_endpoint
add_action( 'woocommerce_account_manage-staff_endpoint', __NAMESPACE__ . '\my_account_manage_staff_content' );
function my_account_manage_staff_content() {

    $user = wp_get_current_user();
    if ( in_array( 'dealer_manager', $user->roles ) ) {
        // get this person's dealer
        $dealer = get_field( 'related_dealer', 'user_' . $user->ID );

        // get staff for this dealer
        $staff = Geovin_Dealers::get_dealer_users($dealer->ID);

        // list staff with edit links, and delete option
        ?>
        <table class="responsive-table">
            <thead>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Edit</th>
                <th>Remove</th>
            </thead>
            <tbody>
        <?php 
        foreach( $staff as $staff_person ) {
            if ( $staff_person == get_current_user_id() ) {
                $staff_user = get_user_by( 'ID', $staff_person );
                $staff_role = $staff_user->roles[0] === 'dealer_manager' ? 'Manager' : 'Staff';
                echo '<tr><td data-title="Name">' . $staff_user->first_name . ' ' . $staff_user->last_name . '</td><td data-title="Email">'.$staff_user->user_email.'</td><td data-title="Role">' . $staff_role . '</td><td data-title="Edit"><a href="/my-account/edit-staff/?id='.$staff_user->ID.'">Edit</a></td><td data-title="Remove">&nbsp;</td></tr>';
               
            } else {
                $staff_user = get_user_by( 'ID', $staff_person );

                if ( $staff_user ) {
                    $staff_role = $staff_user->roles[0] === 'dealer_manager' ? 'Manager' : 'Staff';
                echo '<tr><td data-title="Name">' . $staff_user->first_name . ' ' . $staff_user->last_name . '</td><td data-title="Email">'.$staff_user->user_email.'</td><td data-title="Role">' . $staff_role . '</td><td data-title="Edit"><a href="/my-account/edit-staff/?id='.$staff_user->ID.'">Edit</a></td><td data-title="Remove"><a href="/my-account/remove-staff/?id='.$staff_user->ID.'">Remove</a></td></tr>';
                 }
            }
            
        }
        ?>
        </tbody>
    </table>

        <?php

        // allow them to invite staff
        echo '<a href="/my-account/add-staff" class="button button--primary btn--right">Add Staff</a>';


    } else {
        echo 'You do not have permission to access this area of the site.';
    }

}

/*
 * Add content for the new Add Staff page in My Account.
 */
add_action( 'woocommerce_account_add-staff_endpoint', __NAMESPACE__ . '\my_account_add_staff_content' );
function my_account_add_staff_content() {

    wp_enqueue_script('add-staff', get_plugin_url() . 'assets/js/add-staff.js', array('jquery'), '3', true);
    new \GW_Disable_Submit( 5 );
    
    $user = wp_get_current_user();
    if ( in_array( 'dealer_manager', $user->roles ) ) {
        echo 'Users will be sent an activation email to complete setup of their account.<br/>';
        echo do_shortcode('[gravityforms id="5"]');
    } else {
        echo 'You do not have permission to access this area of the site.';
    }
}


/*
 * Add content for the new Edit Staff page in My Account.
 */
add_action( 'woocommerce_account_edit-staff_endpoint', __NAMESPACE__ . '\my_account_edit_staff_content' );
function my_account_edit_staff_content() {
    
    new \GW_Disable_Submit( 6 );
    
    $user = wp_get_current_user();
    if ( in_array( 'dealer_manager', $user->roles ) ) {
        echo do_shortcode('[gravityforms id="6"]');
    } else {
        echo 'You do not have permission to access this area of the site.';
    }
}

/*
 * Add content for the new Remove Staff page in My Account.
 */
add_action( 'woocommerce_account_remove-staff_endpoint', __NAMESPACE__ . '\my_account_remove_staff_content' );
function my_account_remove_staff_content() {
    
    $user = wp_get_current_user();
    if ( in_array( 'dealer_manager', $user->roles ) ) {
        echo do_shortcode('[gravityforms id="7"]');
    } else {
        echo 'You do not have permission to access this area of the site.';
    }
}

/*
 * Add Gutenburg to WooCommerce Products Post Type
 */
function activate_gutenberg_product( $can_edit, $post_type ) {
    if ( $post_type == 'product' ) {
        $can_edit = true;
    }
    return $can_edit;
}
add_filter( 'use_block_editor_for_post_type', __NAMESPACE__ . '\activate_gutenberg_product', 20, 2 );

/*
 * Add product taxonomies to REST for use with Gutenburg Editor
 */
function enable_taxonomy_rest( $args ) {
    $args['show_in_rest'] = true;
    return $args;
}
add_filter( 'woocommerce_taxonomy_args_product_cat', __NAMESPACE__ . '\enable_taxonomy_rest' );
add_filter( 'woocommerce_taxonomy_args_product_tag', __NAMESPACE__ . '\enable_taxonomy_rest' );

/*
 * Add Catalog Visibility meta box for use with Gutenburg Editor
 */
function register_catalog_meta_boxes() {
    global $current_screen;
    // Make sure gutenberg is loaded before adding the metabox
    if ( method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor() ) {
        add_meta_box( 'catalog-visibility', __( 'Catalog visibility', 'geovin' ), __NAMESPACE__ . '\product_data_visibility', 'product', 'side' );
    }
}
add_action( 'add_meta_boxes', __NAMESPACE__ . '\register_catalog_meta_boxes' );


/*
 * Template for Catalog Visibility meta box
 */
function product_data_visibility( $post ) {

    $thepostid          = $post->ID;
    $product_object     = $thepostid ? wc_get_product( $thepostid ) : new WC_Product();
    $current_visibility = $product_object->get_catalog_visibility();
    $current_featured   = wc_bool_to_string( $product_object->get_featured() );
    $visibility_options = wc_get_product_visibility_options();
    ?>
    <div class="misc-pub-section" id="catalog-visibility">
        <?php esc_html_e( 'Catalog visibility:', 'woocommerce' ); ?>
        <strong id="catalog-visibility-display">
            <?php

            echo isset( $visibility_options[ $current_visibility ] ) ? esc_html( $visibility_options[ $current_visibility ] ) : esc_html( $current_visibility );

            if ( 'yes' === $current_featured ) {
                echo ', ' . esc_html__( 'Featured', 'woocommerce' );
            }
            ?>
        </strong>

        <a href="#catalog-visibility"
           class="edit-catalog-visibility hide-if-no-js"><?php esc_html_e( 'Edit', 'woocommerce' ); ?></a>

        <div id="catalog-visibility-select" class="hide-if-js">

            <input type="hidden" name="current_visibility" id="current_visibility"
                   value="<?php echo esc_attr( $current_visibility ); ?>" />
            <input type="hidden" name="current_featured" id="current_featured"
                   value="<?php echo esc_attr( $current_featured ); ?>" />

            <?php
            echo '<p>' . esc_html__( 'This setting determines which shop pages products will be listed on.', 'woocommerce' ) . '</p>';

            foreach ( $visibility_options as $name => $label ) {
                echo '<input type="radio" name="_visibility" id="_visibility_' . esc_attr( $name ) . '" value="' . esc_attr( $name ) . '" ' . checked( $current_visibility, $name, false ) . ' data-label="' . esc_attr( $label ) . '" /> <label for="_visibility_' . esc_attr( $name ) . '" class="selectit">' . esc_html( $label ) . '</label><br />';
            }

            echo '<br /><input type="checkbox" name="_featured" id="_featured" ' . checked( $current_featured, 'yes', false ) . ' /> <label for="_featured">' . esc_html__( 'This is a featured product', 'woocommerce' ) . '</label><br />';
            ?>
            <p>
                <a href="#catalog-visibility"
                   class="save-post-visibility hide-if-no-js button"><?php esc_html_e( 'OK', 'woocommerce' ); ?></a>
                <a href="#catalog-visibility"
                   class="cancel-post-visibility hide-if-no-js"><?php esc_html_e( 'Cancel', 'woocommerce' ); ?></a>
            </p>
        </div>
    </div>
<?php
}

/*
 * Remove the default WooCommerce styles.
 */
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

/*
 * Change the add to cart text on single product pages.
 */
add_filter( 'woocommerce_product_single_add_to_cart_text', __NAMESPACE__ . '\woocommerce_custom_single_add_to_cart_text' ); 
function woocommerce_custom_single_add_to_cart_text() {
    return __( 'Add', 'woocommerce' ); 
}

/*
 * Remove the default WooCommerce price display location
 */
add_action('init', __NAMESPACE__ . '\move_price');
function move_price(){
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
}

/*
 * Remove the default WooCommerce related products section.
 */
add_action('init', __NAMESPACE__ . '\remove_related');
function remove_related(){
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
}

/*
 * Remove price column on edit products screens
 * as it causes a memory limit error when calcing 
 * thousands of variations for downsview
 */
add_filter( 'manage_edit-product_columns', __NAMESPACE__ . '\change_columns_filter',10, 1 );
function change_columns_filter( $columns ) {
    unset($columns['price']);

    return $columns;
}

/*
 * Change the order meta that is displayed in the order details.
 */
add_filter( 'woocommerce_display_item_meta', __NAMESPACE__ . '\adjust_meta_for_order_details', 10, 3 );
function adjust_meta_for_order_details( $html, $item, $args ) {
    $strings = array();
    $new_html    = '';
    $args    = wp_parse_args(
        $args,
        array(
            'before'       => '<ul class="wc-item-meta"><li>',
            'after'        => '</li></ul>',
            'separator'    => '</li><li>',
            'echo'         => true,
            'autop'        => false,
            'label_before' => '<strong class="wc-item-meta-label">',
            'label_after'  => ':</strong> ',
        )
    );

    foreach ( $item->get_formatted_meta_data() as $meta_id => $meta ) {
        if ( strpos( $meta->value, 'x' ) === false ) {
            $value     = $args['autop'] ? wp_kses_post( $meta->display_value ) : wp_kses_post( make_clickable( trim( $meta->display_value ) ) );
            $strings[] = $args['label_before'] . wp_kses_post( $meta->display_key ) . $args['label_after'] . $value;
        }
    }

    if ( $strings ) {
        $new_html = $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
    }
    if ( $new_html !== '' ) {
        $html = $new_html;
    }

    return $html;
}
