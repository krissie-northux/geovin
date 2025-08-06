<?php
/**
 * Geovin Variable Product Type
 */
namespace Geovin;

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

class Invite_Users {
    private static $existing;
    private static $invite_count;
    private static $dealer_onboarding_form_id;
    private static $staff_invite_form_id;
    private static $staff_edit_form_id;
    private static $staff_remove_form_id;

    public function __construct() {

        self::$dealer_onboarding_form_id = \GFFormsModel::get_form_id( 'Dealer Onboarding' );
        self::$staff_invite_form_id = \GFFormsModel::get_form_id( 'Invite Staff' );
        self::$staff_edit_form_id = \GFFormsModel::get_form_id( 'Edit Staff' );
        self::$staff_remove_form_id = \GFFormsModel::get_form_id( 'Remove Staff' );

        add_action( 'init', array( $this, 'register_invite_menu_page' ) );
        add_shortcode( 'dealer_onboarding_page', array( $this, 'dealer_onboarding_page' ) );
        add_filter( 'gform_validation_' . self::$dealer_onboarding_form_id, array( $this, 'verify_self_onboarding' ) );

        add_action( 'gform_after_submission_' . self::$dealer_onboarding_form_id, array( $this, 'process_new_user' ), 10, 2 );

        add_filter( 'gform_field_value_dealer_id', array( $this, 'dealer_id_value' ) );
        add_filter( 'gform_field_value_staff_name', array( $this, 'staff_name_value' ) );
        add_filter( 'gform_field_value_staff_first_name', array( $this, 'staff_first_name_value' ) );
        add_filter( 'gform_field_value_staff_last_name', array( $this, 'staff_last_name_value' ) );
        add_filter( 'gform_field_value_staff_email', array( $this, 'staff_email_value' ) );
        add_filter( 'gform_field_value_staff_role', array( $this, 'staff_role_value' ) );

        //Process Manager Invites
        add_action( 'gform_after_submission_' . self::$staff_invite_form_id, array( $this, 'invite_staff' ), 10, 2 );
        add_action( 'gform_after_submission_' . self::$staff_edit_form_id, array( $this, 'edit_staff' ), 10, 2 );
        add_action( 'gform_after_submission_' . self::$staff_remove_form_id, array( $this, 'remove_staff' ), 10, 2 );

        add_filter( 'gform_validation_' . self::$staff_edit_form_id, array( $this, 'check_manager_role' ), 10, 1);

        //ajax to get dealer address options
        add_action( 'wp_ajax_load_dealer_addresses', array( $this, 'load_dealer_addresses') );

    }

    /**
     * Load dealer addresses for the select field in the invite staff form.
     * 
     * @return void
     */
    public function load_dealer_addresses() {
        $dealer_id = intval( $_POST['dealer_id'] );
        $dealer_addresses = Geovin_Dealers::get_dealer_addresses( $dealer_id );
        $json = json_encode( $dealer_addresses );
        echo $json;
        wp_die();
    }

    /**
     * get the staff member's full name from the user ID
     * 
     * @param string $value The current value of the field.
     * @return string The staff member's name.
     */
    public function staff_name_value( $value ) {
        if ( isset($_GET['id']) ) {
            $user = get_user_by('ID',$_GET['id']);
            $value = $user->first_name . ' ' . $user->last_name;
        }
        return $value;
    }

    /**
     * get the staff member's first name from the user ID
     * 
     * @param string $value The current value of the field.
     * @return string The staff member's name.
     */
    public function staff_first_name_value( $value ) {
        if ( isset($_GET['id']) ) {
            $user = get_user_by('ID',$_GET['id']);
            $value = $user->first_name;
        }
        return $value;
    }

    /**
     * get the staff member's last name from the user ID
     * 
     * @param string $value The current value of the field.
     * @return string The staff member's name.
     */
    public function staff_last_name_value( $value ) {
        if ( isset($_GET['id']) ) {
            $user = get_user_by('ID',$_GET['id']);
            $value = $user->last_name;
        }
        return $value;
    }

    /**
     * get the staff member's email from the user ID
     * 
     * @param string $value The current value of the field.
     * @return string The staff member's email.
     */
    public function staff_email_value( $value ) {
        if ( isset($_GET['id']) ) {
            $user = get_user_by('ID',$_GET['id']);
            $value = $user->user_email;
        }
        return $value;
    }

    /**
     * get the staff member's role from the user ID
     * 
     * @param string $value The current value of the field.
     * @return string The staff member's name.
     */
    public function staff_role_value( $value ) {
        if ( isset($_GET['id']) ) {
            $user = get_user_by('ID',$_GET['id']);
            $value = $user->roles[0];
        }
        return $value;
    }

    /**
     * get the currently logged in user's assigned dealer
     * 
     * @param string $value The current value of the field.
     * @return string The staff member's name.
     */
    public function dealer_id_value( $value ) {
        $user_id = get_current_user_id();
        $dealer = get_field('related_dealer', 'user_' . $user_id, true);
        $value = $dealer->ID;
        return $value;
    }

    /**
     * Send the invitation email to the staff selected in the gravity form.
     * 
     * @param array $entry The form entry.
     * @param array $form The form object.
     * 
     * @return void
     */
    public function invite_staff( $entry, $form ) {
        $staff = maybe_unserialize( rgar( $entry, 1 ) );
        $dealer_id = rgar($entry,2);
        $dealer = get_post( $dealer_id );
        $dealer_name = $dealer->post_title;

        $email_content = get_option('geovin_email_invite_copy');
        $email_subject = get_option('geovin_email_invite_subject');

        foreach ( $staff as $staff_person ) {
            $role = $staff_person['Staff Role'] === 'Manager' ? 'dealer_manager' : 'dealer_staff';
            $email = $staff_person['Email'];
            $assigned_dealer = $dealer->ID;
            $name = $staff_person['First Name'] . ' ' . $staff_person['Last Name'];
            self::send_invite_email( $email_content, $email_subject, $email, $role, $assigned_dealer, null, null, $name );
        }
  
    }

    /**
     * Update a staff member's information when the
     * edit staff form is submitted.
     * 
     * @param array $entry The form entry.
     * @param array $form The form object.
     * 
     * @return void
     */
    public function edit_staff( $entry, $form ) {
        $role = rgar($entry,4);
        $userdata = array(
            'ID' => rgar($entry,5),
            'user_login' => rgar($entry,3),
            'user_email' => rgar($entry,3),
            'display_name' => rgar($entry,1) . ' ' . rgar($entry,2),
            'nickname' => rgar($entry,1) . ' ' . rgar($entry,2),
            'first_name' => rgar($entry,1),
            'last_name' => rgar($entry,2),
            'role' => $role
        );
        $user_id = wp_insert_user( $userdata );
    }

    /**
     * Remove a staff member from the dealer.
     * 
     * @param array $entry The form entry.
     * @param array $form The form object.
     * 
     * @return void
     */
    public function remove_staff( $entry, $form ) {
        //remove from dealer
        $user_id = rgar( $entry, 1 );
        $dealer_id = rgar( $entry, 4 );
        Geovin_Dealers::remove_user_from_dealer( $user_id, $dealer_id );
        require_once(ABSPATH.'wp-admin/includes/user.php');
        \wp_delete_user( $user_id );
        
    }

    /**
     * Take necessary steps when the add user form is submitted.
     * 
     * @param array $entry The form entry.
     * @param array $form The form object.
     * 
     * @return void
     */
    public function process_new_user( $entry, $form ) {
        $user_email = rgar( $entry, 7 );
        $dealer_id = rgar( $entry, 11 );
        $role_to_assign = rgar( $entry, 10 );
        $tier_to_assign = rgar( $entry, 14);
        $address_to_assign = rgar( $entry, 17 );
        $company_name = rgar( $entry, 4 );

        //Get the new user registered by this submission
        $user = get_user_by( 'email', $user_email );

        //check if we should add a new dealer and there is a company name to use
        if ( $dealer_id === '' && $role_to_assign === 'dealer_manager' && $company_name !== '' ) {

            //create a dealer
            $post = array(
                'post_type' => 'geovin_dealer',
                'post_title' => $company_name,
                'post_content' => '',
                'post_status' => 'publish',
                'tax_input'    => array(
                    "pricing-tier" => array ( $tier_to_assign ) //assign saved tier
                ),
            );
            $dealer_id = wp_insert_post( $post );

            //add address to dealer
            Geovin_Dealers::add_primary_address_to_dealer( $dealer_id, rgar( $entry, '6.1' ), rgar( $entry, '6.2' ), rgar( $entry, '6.3' ), rgar( $entry, '6.4' ), rgar( $entry, '6.5' ), rgar( $entry, '6.6' ) );

            //add address to user
            Geovin_Dealers::add_dealer_country_to_user( $dealer_id, $user->ID );
        }

        //check if dealer there is a dealer id
        if ( $dealer_id ) {
            //assign user to dealer, this will auto trigger a recipicol dealer to user action
            Geovin_Dealers::add_user_to_dealer( $user->ID, $dealer_id, $address_to_assign );
        }
    }

    /**
     * Adds a submenu page under users.
     * 
     * @return void
     */
    public function register_invite_menu_page() {
        add_users_page(
            __( 'Invite Users', 'geovin' ),
            __( 'Invite Users', 'geovin' ),
            'manage_options',
            'invite-users',
            array( $this, 'invite_users_page_callback' )
        );
    }

    /**
     * Displays a success message after invites are sent.
     * 
     * @return void
     */
    public function invites_sent_notice() {
        $message = 'Remaining user invites have been sent.';
        if ( empty( self::$existing ) ) {
            $message = 'User invites have been sent.';
        }
        if ( ! empty( self::$invite_count ) ) {
            ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php
        }
    }

    /**
     * Confirms that the default email copy was updated
     * 
     * @return void
     */
    public function options_updated_notice() {
        $message = 'Your default email copy has been updated.';
        ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php
    }

    /**
     * Displays an error notice when no invites were sent.
     * 
     * @return void
     */
    public function no_invites_sent_notice() {
        $message = 'No emails were provided and no invites were sent.';
        ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php
    }

    /**
     * Displays an error if there is no dealer selected
     * 
     * @return void
     */
    public function must_assign_to_dealer_notice() {
        $message = 'When inviting users with a role of Dealer Staff or Manager, you must select a Dealer to assign them to.';
        ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php
    }

    /**
     * Displays an error if there is no tier selected
     * for a new Dealer Manager.
     * 
     * @return void
     */
    public function must_assign_to_tier_notice() {
        $message = 'When inviting users with a role of Dealer Manager and no existing Dealer is assigned, you must select a Pricing Tier to assign them to.';
        ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php
    }

    /**
     * Displays an error if no role was selected
     * when inviting users.
     * 
     * @return void
     */
    public function no_role_selected_notice() {
        $message = 'No role was selected, and no invites have been sent. Please select a role to assign and try again.';
        ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php
    }

    /**
     * Displays a notice if the user already exists.
     * 
     * @param string $email The email address of the existing user.
     * @param string|null $name The name of the existing user, if available.
     * 
     * @return void
     */
    public function user_exists_notice( $email, $name = null ) {
        self::$existing[] = $email;
        ?>
        <div class="notice notice-warning is-dismissible"><p><?php echo $name ? esc_html( $name ) . ' is already a user with ' . esc_html( $email ) . ', invite not sent.' : esc_html( $email ) . ' is already in use, invite not sent.' ; ?></p></div>
        <?php
    }

    /**
     * Sends an invitation email to a user.
     * 
     * @param string $email_content The content of the email.
     * @param string $email_subject The subject of the email.
     * @param string $email The email address to send the invitation to.
     * @param string $role The role to assign to the user.
     * @param int|null $assigned_dealer The ID of the dealer to assign, if applicable.
     * @param string|null $assigned_address The address to assign, if applicable.
     * @param string|null $assigned_tier The tier to assign, if applicable.
     * @param string|null $name The name of the user, if available.
     * 
     * @return void
     */
    public function send_invite_email( $email_content, $email_subject, $email, $role, $assigned_dealer = null, $assigned_address = null, $assigned_tier = null, $name = null ) { 

        $current_users_email_content = $email_content;
        $current_users_email_subject = $email_subject;

        $email_hash = wp_hash( $email );
        $hash_auth = wp_generate_password(8, false);
        set_transient( $email_hash, $hash_auth, WEEK_IN_SECONDS ); // invite will expire after one week.
        set_transient( 'role_for_' . $email_hash, $role , WEEK_IN_SECONDS );
        if ( $assigned_dealer && $assigned_dealer !== 'Do not assign' ) {
            set_transient( 'dealer_to_assign_' . $email_hash, $assigned_dealer, WEEK_IN_SECONDS);
        }
        if ( $assigned_dealer && $assigned_dealer === 'Do not assign' && $assigned_tier !== "Use selected Dealer's Tier") {
            set_transient( 'tier_to_assign_' . $email_hash, $assigned_tier, WEEK_IN_SECONDS);
        }
        if ( ! empty( $assigned_address ) && $assigned_address !== 'Do not assign to an address' ) {
            set_transient( 'address_to_assign_' . $email_hash, $assigned_address, WEEK_IN_SECONDS);
        }
        if ( $role === 'dealer_manager' ) {
            $link = site_url('/dealer-onboarding?email=' . $email . '&verify=' . $hash_auth );
        } else {
            $link = site_url('/dealer-staff-onboarding?email=' . $email . '&verify=' . $hash_auth );
        }

        //Replace merge tags, set default name if none exist
        if ( ! $name ) {
            $name = 'Geovin Dealer';
        }

        $style = "-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;outline-color:0;text-decoration:none;display:block;padding-top:0 !important;padding-bottom:0 !important;padding-right:12px !important;padding-left:12px !important;mso-padding-alt:0;background-color:#000000;border-left-width:8px;border-left-style:solid;border-left-color:#000000;border-right-width:8px;border-right-style:solid;border-right-color:#000000;mso-border-alt:8px solid #000000;color:white;font-family:'Helvetica Neue', Helvetica, Arial, sans-serif;font-size:14px;font-weight:500;line-height:38px;mso-line-height-rule:exactly;text-align:center;vertical-align:middle;white-space:nowrap;width:fit-content;";
        $current_users_email_content = str_replace( '{{name}}', $name, $current_users_email_content );
        $current_users_email_content = str_replace( '{{email}}', $email, $current_users_email_content );
        $current_users_email_content = str_replace( '{{invite_link}}', '<a href="' . esc_url( $link ) . '" style="'. $style .'">Accept Invitation</a>', $current_users_email_content );

        $current_users_email_subject = str_replace( '{{name}}', $name, $current_users_email_subject );
        $current_users_email_subject = str_replace( '{{email}}', $email, $current_users_email_subject );

        //build email
        ob_start();
        require_once( get_plugin_dir() . 'templates/header-default.php' );
        echo '<table><tr><td>';
        echo esc_html( preg_replace('/\r\n|[\r\n]/','<br/>', $current_users_email_content) );
        echo '</td></tr></table>';
        require_once( get_plugin_dir() . 'templates/footer-default.php' );
        $body = ob_get_clean();
        $subject = $current_users_email_subject;
        $to = $email . ' <' . esc_html( $name ) . '>';
        //send email
        wp_mail( $email, $subject, $body, 'Content-Type: text/html; charset="UTF-8"' );
    }

    /**
     * The invite users page in the Users Admin menu.
     * 
     * @return void
     */
    function invite_users_page_callback() {

        if ( ! current_user_can( 'create_users' ) ) {
            wp_die(
                '<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
                '<p>' . __( 'Sorry, you are not allowed to create users.' ) . '</p>',
                403
            );
        }
        if ( isset( $_REQUEST['action'] ) && 'inviteusers' === filter_input( INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) {
            check_admin_referer( 'invite-users', '_wpnonce_invite-users' );

            $email_content = $_POST['email_copy'];
            $email_subject = $_POST['email_subject'];

            //Save the Default Email Message if Checked
            if ( isset( $_POST['save_default'] ) && filter_input( INPUT_POST, 'save_default', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) === 'on' ) {
                update_option('geovin_email_invite_copy', $email_content );
                update_option('geovin_email_invite_subject', $email_subject );
                add_action( 'geovin_admin_notices', array( $this, 'options_updated_notice' ) );
            }

            if ( isset($_POST['email_list']) && ! empty( $_POST['email_list'] ) ) {

                if ( empty( $_POST['role'] ) ) {
                    add_action( 'geovin_admin_notices', array( $this, 'no_role_selected_notice' ) );
                } else {
                    //format the list into an array of email, name values
                    $emails = preg_split('/\r\n|[\r\n]/', $_POST['email_list']); 
                    
                    //loop through list and check if any emails are already users, add notice if so
                    foreach( $emails as $user ) {
                        if ( strpos( $user, ',' ) !== false ) {
                            $email = trim( explode(',', $user)[0] );
                            $name = trim( explode(',', $user)[1] );
                        } else {
                            $email = trim( $user );
                            $name = false;
                        }
                        if ( email_exists( $email ) ) {
                            add_action('geovin_admin_notices', function($arg1) use ($email,$name) { 
                                self::user_exists_notice( $email, $name );
                            }, 10, 2);
                        } else {
                            $role = isset( $_POST['role'] ) ? $_POST['role'] : null;
                            $assigned_dealer = isset( $_POST['assigned_dealer'] ) ? $_POST['assigned_dealer'] : null;
                            $assigned_tier = isset( $_POST['assigned_tier'] ) ? $_POST['assigned_tier'] : null;
                            $assigned_address = isset( $_POST['assigned_address'] ) ? $_POST['assigned_address'] : null;
                            self::send_invite_email( $email_content, $email_subject, $email, $role, $assigned_dealer, $assigned_address, $assigned_tier, $name );
                            self::$invite_count = self::$invite_count < 1 ? 1 : self::$invite_count + 1;
                        }
                        
                    }
                    add_action( 'geovin_admin_notices', array( $this, 'invites_sent_notice' ) );
                }
            } else {
                add_action( 'geovin_admin_notices', array( $this, 'no_invites_sent_notice' ) );
            }
        }
        ?>
        <div class="wrap" style="max-width:768px;">
            <style>
                label {
                    display: inline-block;
                    margin-bottom: .5rem;
                    font-weight: 700;
                }
                fieldset {
                    display: flex;
                    flex-direction: row;
                    flex-wrap: wrap;

                }
                fieldset label {
                    width: 25%;
                    margin-bottom: 1rem;
                }
            </style>
            <?php 
            do_action( 'geovin_admin_notices' );
            ?>
            <form method="post">
                <input name="action" type="hidden" value="inviteusers">
                <?php wp_nonce_field( 'invite-users', '_wpnonce_invite-users' ); ?>
                <h1><?php _e( 'Invite Users', 'geovin' ); ?></h1>
                <section><?php _e( 'You can send invites to email addresses and only these recipients will be able to use the user registration form to self-onboard. Dealer Manager user roles will also be able to onboard their own internal staff only.', 'geovin' ); ?></section>
                <section>
                    <h2>Emails</h2>
                    <p>Enter each email on a seperate line, you can add a name if desired seperated from the email address by a comma.</p>
                    <label for="email_list"><strong>Emails</strong></label>
                    <textarea id="email_list" name="email_list" style="width:100%;min-height:200px" placeholder="krissie@northuxdesign.com, Krissie VandeNoord&#10;dealer@furniture.com, Dealer"></textarea>
                </section>
                <section>
                    <h2>Role Type</h2>
                    <p>Select which role these users are being invited too. Note: You may not use this form to add site administrators.</p>
                    <?php
                    $roles = $this->get_roles();
                    if ( $roles ) {
                        $first = true;
                        $roles = array_reverse( $roles, true );
                        echo '<fieldset>';
                        foreach( $roles as $key => $role ) {
                            // create a radio input
                            ?>
                            <label for="<?php echo esc_attr( $key ); ?>" style="margin-right:30px;">
                                <input type="radio" id="<?php echo esc_attr( $key ); ?>" name="role" value="<?php echo esc_attr( $key ); ?>" <?php echo $first ? 'checked' : ''; ?> />
                                <?php echo esc_html( $role['name'] ); ?>
                            </label>
                            <?php 
                            $first = false;
                        }
                        echo '</fieldset>';
                    }
                    ?>
                </section>
                <section class="assigned-dealer">
                    <h2>Dealer to assign</h2>
                    <?php
                        $dealers = Geovin_Dealers::get_dealers();
                    ?>
                    <p>If you'd like to assign these users to an already established dealer in the system, please select that dealer from the list. If no dealer is selected, a dealer will be created when they complete registration.</p>
                    <label for="assigned_dealer">Dealer to Assign</label><br/>
                    <select id="assigned_dealer" name="assigned_dealer" style="width:100%;">
                        <option>Select a Dealer</option>
                        <?php 
                            foreach( $dealers as $dealer ) {
                                ?>
                                <option value="<?php echo esc_attr( $dealer->ID ); ?>"><?php echo esc_html( $dealer->post_title ); ?></option>
                                <?php
                            }
                        ?>
                    </select>
                    
                </section>
                
                <section>
                    <?php
                    $default_email_copy = get_option('geovin_email_invite_copy');
                    $default_email_subject = get_option('geovin_email_invite_subject');
                    ?>
                    <h2>Email Copy</h2>
                    <p>You can edit the default email copy below. Select the "save as default" checkbox to update the default copy to your revised version. You may use the following merge tags: {{email}}, {{name}}, {{invite_link}} Note: if no name is provided the {{name}} merge tag will default to "Geovin Dealer"</p>
                    <label for="email_subject">Email Subject</label>
                    <input type="text" id="email_subject" name="email_subject" style="width:100%;" value="<?php echo esc_attr( $default_email_subject ); ?>" />
                    <label for="email_copy" style="margin-top:1rem;">Email Content</label>
                    <textarea id="email_copy" name="email_copy" style="width:100%;min-height:200px"><?php echo esc_html( $default_email_copy ); ?></textarea>
                    <label for="save_default"><input id="save_default" type="checkbox" name="save_default" /> Save as default</label>
                </section>
                <section>
                    <input type="submit" class="button button-primary" value="Send Emails" />
                </section>
            </form>
        </div>
        <?php
    }

    /**
     * Get available roles for the invite users form.
     * 
     * @return array The available roles.
     */
    private function get_roles() {
        global $wp_roles;

        $all_roles = $wp_roles->roles;
        $editable_roles = apply_filters('editable_roles', $all_roles);

        //let's remove the admin role
        unset($editable_roles['administrator']);

        //also remove roles that would not be invitation type roles
        unset($editable_roles['subscriber']);
        unset($editable_roles['customer']);
        unset($editable_roles['editor']);
        unset($editable_roles['contributor']);
        unset($editable_roles['shop_manager']);
        unset($editable_roles['author']);

        $editable_roles = array_reverse($editable_roles);

        return $editable_roles;

    }

    /**
     * Shortcode to display the dealer onboarding page.
     * 
     * @param array $args The shortcode attributes.
     * 
     * @return string The HTML output of the onboarding page.
     */
    public function dealer_onboarding_page( $args ) {
        $form_id = $args['form'];
        $email = $_GET['email'];
        $auth = $_GET['verify'];
        $email_hash = wp_hash( $email );
        $transient = get_transient( $email_hash );

        $role = get_transient( 'role_for_' . $email_hash );
        $dealer_id = get_transient( 'dealer_to_assign_' . $email_hash );
        $tier_id = get_transient('tier_to_assign_' . $email_hash );
        $address_name = get_transient('address_to_assign_' . $email_hash );

        ob_start();
        if ( $transient && $transient === $auth && $role ) {
            $pre_message = '';
            $field_values = array(
                'nonce_value' => wp_create_nonce($auth),
                'role_to_assign' => $role,
                'assigned_dealer' => $dealer_id,
                'assigned_tier' => $tier_id,
                'assigned_address' => $address_name,
            );
            if ( $dealer_id ) {
                $dealer_name = get_the_title( $dealer_id );
                $pre_message = '<p>You are registering as staff of, <strong>' . esc_html( $dealer_name ) . '</strong>. If this is not correct please request an alternate invite link.</p>';
            }
            echo $pre_message;
            gravity_form( $form_id, false, false, false, $field_values );
        } else {
            echo '<div class="warning">We were unable to verify your invitation. Please try the link you were given again. Links expire after one week. You can contact us to request a new invite link.</div>';
        }
        return ob_get_clean();
    }

    public function check_manager_role( $validation_result ) {
        foreach( $validation_result['form']['fields'] as &$field ) {
            if ( $field->inputName === 'staff_role' ) {
                if ( $_POST['input_5'] == get_current_user_id() && $_POST['input_4'] === 'dealer_staff' ) {
                    $validation_result['is_valid'] = false;
                    $field->failed_validation = true;
                    $field->validation_message = 'You can not change your own role.';

                }
            }

        }
        return $validation_result;
    }

    /**
     * Verify the self-onboarding form submission.
     * 
     * @param array $validation_result The validation result.
     * 
     * @return array The updated validation result.
     */
    public function verify_self_onboarding( $validation_result ) {

        //find nonce field
        $fields = $validation_result['form']['fields'];
        $input_id = '';
        $auth = $_GET['verify'];
        $email = $_GET['email'];
        $email_hash = wp_hash( $email );

        foreach( $validation_result['form']['fields'] as &$field ) {
            if ( $field->inputName === 'assigned_dealer' ) {
                //verify that the role submitted matches the transient role saved
                $input_id = 'input_' . $field->id;
                if ( $input_id !== '' && isset( $_POST[$input_id] ) && $_POST[$input_id] !== '' ) {
                    $dealer_id = get_transient( 'dealer_to_assign_' . $email_hash );
                    if ( $dealer_id !== $_POST[$input_id] ) {
                        $validation_result['is_valid'] = false;
                        $field->failed_validation = true;
                        $field->validation_message = 'We were unable to link you to a supporting Dealer in our system, please try the link again or request a new invitation.';
                        add_filter( 'gform_validation_message', array( $this,'change_failed_message' ), 10, 2 );
                        break;
                    }
                }
            }
            if ( $field->inputName === 'assigned_tier' ) {
                //verify that the role submitted matches the transient role saved
                $input_id = 'input_' . $field->id;
                if ( $input_id !== '' && isset( $_POST[$input_id] ) && $_POST[$input_id] !== "Use Assigned Dealer's Tier" ) {
                    $tier_id = get_transient( 'tier_to_assign_' . $email_hash );
                    if ( $tier_id && $tier_id !== $_POST[$input_id] ) {
                        $validation_result['is_valid'] = false;
                        $field->failed_validation = true;
                        $field->validation_message = 'We were unable to assign you the appropriate Dealer pricing, please try the link again or request a new invitation.';
                        add_filter( 'gform_validation_message', array( $this,'change_failed_message' ), 10, 2 );
                        break;
                    }
                }
            }
            if ( $field->inputName === 'role_to_assign' ) {
                //verify that the role submitted matches the transient role saved
                $input_id = 'input_' . $field->id;
                if ( $input_id !== '' && isset( $_POST[$input_id] ) ) {
                    $role = get_transient( 'role_for_' . $email_hash );
                    if ( $role !== $_POST[$input_id] ) {
                        $validation_result['is_valid'] = false;
                        $field->failed_validation = true;
                        $field->validation_message = 'We were unable to verify your submission, please refresh the page and try again.';
                        add_filter( 'gform_validation_message', array( $this,'change_failed_message' ), 10, 2 );
                        break;
                    }
                }
            }
            if ( $field->inputName === 'nonce_value' ) {
                $input_id = 'input_' . $field->id;
                if ( $input_id !== '' && isset( $_POST[$input_id] ) ) {
                    $nonce = $_POST[$input_id];
                    $result = wp_verify_nonce( $nonce, $auth );
                    if ( ! $result ) {
                        // nonce is not valid
                        $validation_result['is_valid'] = false;
                        $field->failed_validation = true;
                        $field->validation_message = 'We were unable to verify your submission, please refresh the page and try again.';
                        add_filter( 'gform_validation_message', array( $this,'change_failed_message' ), 10, 2 );
                        break;
                    }
                }
            }
        }
        return $validation_result;
    }

    /**
     * Change the failed message for the self-onboarding form.
     * 
     * @param string $message The default message.
     * @param array $form The form object.
     * 
     * @return string The updated message.
     */
    function change_failed_message( $message, $form ) {
        return "<div class='validation_error'>There was an issue with your registration. We could not verify the authenticity of your registration link. Please check your link, and try again or contact sales@geovin.com to get a new link.</div>";
    }
}

new Invite_Users();