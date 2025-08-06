<?php
namespace Geovin;

use ipinfo\ipinfo\IPinfo;

class Geolocation {
	private static $api_key = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXX'; // Replace with your actual API key
	private static $maps_endpoint = 'https://maps.google.com/maps/api/js?libraries=geometry';
	private static $session_ip = '';
	private static $access_token = 'XXXXXXXXXXXXXXX'; // Replace with your actual access token

	private static $needs_session_region = true;
	private static $needs_session_geo_country = true;
	private static $needs_session_user_country = true;

	private static $log = '';

	private static $radius_threshold = '750';

	public function __construct() {

        //Add Ajax
        add_action( 'wp_ajax_set_distance', array( $this, 'set_distance' ) );
        add_action( 'wp_ajax_nopriv_set_distance', array( $this, 'set_distance' ) );

        //initialize
        add_action('woocommerce_init', array( $this, 'init' ) );
	}

	/**
	 * Initialize geolocation functions
	 * 
	 * @return void
	 */
	public function init() {
		self::$log = self::$log . ' || ' . 'initializing';
		// Early initialize customer session
	    if ( isset(WC()->session) && ! WC()->session->has_session() ) {
	        WC()->session->set_customer_session_cookie( true );
	    }
		self::check_force();
		self::set_ip();
		self::check_region();
		self::maybe_set_country();

		//Load Scripts
		$this->maybe_load_scripts();
	}

	/**
	 * Check for forceLocation or clearSession in URL
	 * this was added to assist with client demonstration and
	 * testing of the geolocation functionality
	 * 
	 * @return void
	 */
	private static function check_force() {
		if ( isset( $_GET['clearSession'] ) && $_GET['clearSession'] === 'true' ) {
			self::$needs_session_region = true;
			self::$needs_session_user_country = true;
			self::$needs_session_geo_country = true;
			WC()->session->__unset('geo_country');
			WC()->session->__unset('geovin_user_country');
			WC()->session->__unset('outside_region');
		} elseif ( isset( $_GET['forceLocation'] ) ) {
			self::$log = self::$log . ' || ' . 'we have force on, set to ' . $_GET['forceLocation'];
			self::$needs_session_region = false;
			self::$needs_session_user_country = false;
			self::$needs_session_geo_country = false;
			
			if ( $_GET['forceLocation'] == 'US' ) {
				WC()->session->set('geo_country','US');
				WC()->session->set('geovin_user_country','US');
				WC()->session->set('outside_region','no');
			} elseif ( $_GET['forceLocation'] == 'CA1' ) {
				WC()->session->set('geo_country','CA');
				WC()->session->set('geovin_user_country','CA');
				WC()->session->set('outside_region','no');
			} elseif ( $_GET['forceLocation'] == 'CA2' ) {
				WC()->session->set('geo_country','CA');
				WC()->session->set('geovin_user_country','CA');
				WC()->session->set('outside_region','yes');
			}
		}
	}

	/**
	 * Load scripts if we need to get region data
	 * 
	 * @return void
	 */
	private function maybe_load_scripts() {
		if ( self::$needs_session_region /*|| isset($_GET['forceLocation'] )*/ ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_alt_scripts' ) );
		}
	}

	/**
	 * Check to see if we already know the region
	 * Set it for the class if so
	 *
	 * @return void
	 */
	public static function check_region() {
		//if ( isset($_GET['forceLocation'] ) ) {
			//self::$needs_session_region = true;
			//WC()->session->set('outside_region','');
			//return;
		//}
		//check if we have session data (outside_region)
		if ( WC()->session ) {

			$outside_region = WC()->session->get('outside_region');

			//we should let the other functions know we don't need to calc this
			if ( $outside_region ) {
				self::$needs_session_region = false;
			}
		}
		
	}

	/**
	 * Check to see if we already know the country
	 * Set it for the class if so
	 *
	 * @return void
	 */
	public static function check_country() {
		//check if we have session data (country)
		//if ( isset($_GET['forceLocation'] ) ) {
			//return;
		//}
		if ( WC()->session ) {
			$geo_country = WC()->session->get('geo_country');
			$user_country = WC()->session->get('geovin_user_country');

			//we should let other functions know we don't need to calc these
			if ( $geo_country ) {
				self::$log = self::$log . ' || ' . 'we are using a preset geo country session var';
				self::$needs_session_geo_country = false;
			}
			if ( $user_country ) {
				self::$log = self::$log . ' || ' . 'we are using a preset user country session var';
				self::$needs_session_user_country = false;
			}
		}
	}

	/**
	 * Set the country session variables if we need to
	 * 
	 * @return void
	 */
	public static function maybe_set_country() {
		/*
		if (isset($_GET['forceLocation'] )) {
			if ( $_GET['forceLocation'] === 'US' ) {
				WC()->session->set('geo_country','US');
				WC()->session->set('geovin_user_country','US');
				self::$needs_session_geo_country = false;
			} elseif ( $_GET['forceLocation'] === 'CA1' || $_GET['forceLocation'] === 'CA2' ) {
				WC()->session->set('geo_country','CA');
				WC()->session->set('geovin_user_country','CA');
				self::$needs_session_user_country = false;
			}
			return;
		}*/
		if ( self::$needs_session_geo_country ) {
			self::$log = self::$log . ' || ' . 'we need a geo country';
			$country = self::get_new_geo_country();
			if ( $country ) {
				$country = self::normalize_country($country);
				self::$log = self::$log . ' || ' . 'preparing to set country as ' . $country;
				if ( WC()->session ) {
					self::$log = self::$log . ' || ' . 'we are setting the geo country session var to ' . $country;
					WC()->session->set('geo_country',$country);
					self::$needs_session_geo_country = false;
				}
				
			}
		}

		if ( self::$needs_session_user_country ) {
			$user_country = self::get_user_country();
			if ( $user_country ) {
				$user_country = self::normalize_country($user_country);
				if ( WC()->session ) {
					self::$log = self::$log . ' || ' . 'we are setting the user country session var to ' . $user_country;
					WC()->session->set('geovin_user_country',$user_country);
					self::$needs_session_user_country = false;
				}
			}
		}
	}

	/**
	 * Normalize country values to US or CA
	 * 
	 * @param string $country The country value to normalize
	 * @return string Normalized country value
	 */
	private static function normalize_country( $country ) {
		if ( $country === 'US' || $country === 'CA' ) {
			return $country;
		}

		if ( $country === 'United States' || $country === 'united states' ) {
			return 'US';
		} 

		if ( $country === 'Canada' || $country === 'canada' ) {
			return 'CA';
		}
		return $country;
	}

	/**
	 * Enqueue scripts to get user location and distance from Geovin
	 * 
	 * @return void
	 */
	public function enqueue_scripts() {
        wp_enqueue_script('maps-base', self::$maps_endpoint . '&key=' . self::$api_key, array(), false, true );
		wp_enqueue_script('maps-geovin', get_plugin_url() . 'assets/js/geo-transit-price.js', array('maps-base','jquery'), '4', true );
		
		$user_geo = self::get_geo_for_ip();
		$geovin_geo = self::get_geovin_geo();
		$session_data = self::get_session_data();
		$raw_data = self::get_raw_data();
		wp_localize_script('maps-geovin','ajax_object',
            array( 
            	'ajax_url' => admin_url( 'admin-ajax.php' ), 
            	'geo' => json_encode($user_geo),
            	'geovinGeo' => json_encode($geovin_geo),
            	'sessionData' => json_encode($session_data),
            	'rawData' => json_encode($raw_data),
            	'log' => self::$log
            )
        );
    }

	/**
	 * Enqueue alternative scripts if we don't need to get region data
	 * 
	 * @return void
	 */
    public function enqueue_alt_scripts() {
		wp_enqueue_script('maps-geovin', get_plugin_url() . 'assets/js/geo-transit-price.js', array('jquery'), '2', true );
		
		$user_geo = self::get_geo_for_ip();
		$geovin_geo = self::get_geovin_geo();
		$session_data = self::get_session_data();
		$raw_data = self::get_raw_data();
		wp_localize_script('maps-geovin','ajax_object',
            array( 
            	'ajax_url' => admin_url( 'admin-ajax.php' ), 
            	'geo' => json_encode($user_geo),
            	'geovinGeo' => json_encode($geovin_geo),
            	'sessionData' => json_encode($session_data),
            	'rawData' => json_encode($raw_data),
            	'log' => self::$log
            )
        );
    }

	/**
	 * Get session data for localization
	 * 
	 * @return array Session data
	 */
    public function get_session_data() {
    	$session_data = array(
    		'geoCountry' => WC()->session->get('geo_country'),
    		'userCountry' => WC()->session->get('geovin_user_country'),
    		'outsideRegion' => WC()->session->get('outside_region'),
    		'needsRegion' => self::$needs_session_region,
    	);
    	return $session_data;

    }

	/**
	 * Get raw data for localization
	 * @return array Raw data
	 */
    public function get_raw_data() {
    	$session_data = array(
    		'geoCountry' => self::get_geo_country(),
    		'userCountry' => self::get_user_country(),
    		'pricingCountry' => self::get_pricing_country(),
    	);
    	return $session_data;

    }

	/**
	 * Get Geovin location
	 * @return array Geovin location data
	 */
    public function get_geovin_geo() {
    	$geovin_geo = array(
    		'latitude' => '43.7682931',
    		'longitude' => '-79.5681833',
    	);

    	return $geovin_geo;
    }


    /*
     * AJAX function to set distance 
	 * 
	 * @return void
     */
    public function set_distance() {
    	// Early initialize customer session
	    if ( isset(WC()->session) && ! WC()->session->has_session() ) {
	        WC()->session->set_customer_session_cookie( true );
	    }

    	$distance = $_POST['distance'];

    	//convert meters to km
    	$km_distance = intval( $distance ) / 1000;

    	if ( $km_distance > self::$radius_threshold ) {
    		WC()->session->set('outside_region', 'yes');
    		echo '{"outsideRegion":true}';
    	} else {
    		WC()->session->set('outside_region', 'no');
    		echo '{"outsideRegion":false}';
    	}
    	wp_die();
    }

    /**
     * Set the users IP address
	 * 
	 * @return void
     */
	public function set_ip() {
		self::$log = self::$log . ' || ' . 'getting ip';
		self::$session_ip = \WC_Geolocation::get_ip_address();
		self::$log = self::$log . ' || ' . 'getting ip ' . self::$session_ip;
	}


	/**
     * Get lat/long for users IP
     * Use transient to reduce calls needed
	 * 
	 * @return array Geolocation data
     */
	private static function get_geo_for_ip() {
		$geo_transient = get_transient( 'geo_' . self::$session_ip );

		if ( $geo_transient ) {
			$geo = maybe_unserialize( $geo_transient );
			return $geo;
		}

		$client = new IPinfo(self::$access_token);
		$geo = $client->getDetails(self::$session_ip);
		if ( $geo ) {
			set_transient( 'geo_' . self::$session_ip, maybe_serialize($geo), WEEK_IN_SECONDS );
		}
		return $geo;
	}

	/**
	 * Get the country to use for pricing
	 * 
	 * @return string Country code
	 */
	public static function get_pricing_country() {

		if ( isset(WC()->session) && ! WC()->session->has_session() ) {
	        WC()->session->set_customer_session_cookie( true );
	    }
	    /*
		if ( isset($_GET['forceLocation']) && $_GET['forceLocation'] === 'US' ) {
			return 'US';
		} elseif( isset($_GET['forceLocation']) && $_GET['forceLocation'] === 'CA1' ) {
			return 'CA';
		} elseif( isset($_GET['forceLocation']) && $_GET['forceLocation'] === 'CA2' ) {
			return 'CA';
		}*/

		//check if we have a dealer user and use the dealers set currency/country
		if ( user_can_build_order() ) {
			//get dealer pricing tier country
			$dealer = Geovin_Dealers::get_dealer();
			if ( $dealer ) {
                $tiers = get_terms(
                    array(
                        'taxonomy' => 'pricing-tier',
                        'object_ids' => $dealer->ID,
                    )
                );
                $primary_tier = $tiers[0];
                $term_id_prefixed = 'pricing-tier_'. $primary_tier->term_id;
                $currency = get_field( 'currency', $term_id_prefixed );
                if ( $currency['value'] === 'cad' ) {
                	$country = 'CA';
                } elseif ( $currency['value'] === 'usd' ) {
					$country = 'US';
                }
            }
		}
		if ( empty( $country ) ) {
			self::$log = self::$log . ' || ' . 'getting user country for price';
			$country = self::get_user_country();
		}

		if ( empty( $country ) ) {
			self::$log = self::$log . ' || ' . 'getting geo country for price';
			$country = self::get_geo_country();
		}

		if ( empty( $country ) ) {
			self::$log = self::$log . ' || ' . 'we are having to set a default price country';
			$country = 'Canada';
		}

		if ( $country ) {
			$country = self::normalize_country( $country );
		}
		return $country;
	}

	/**
	 * Get the country from session or IP geolocation
	 * 
	 * @return string Country code
	 */
	public static function get_geo_country() {

		if ( isset(WC()->session) && ! WC()->session->has_session() ) {
	        WC()->session->set_customer_session_cookie( true );
	    }

	    if ( isset( WC()->session ) ) {
	    	
			$geo_country = WC()->session->get('geo_country');
			self::$log = self::$log . ' || ' . 'using session on get geo as ' . $geo_country;
		}

		if ( isset( $geo_country ) && $geo_country ) {
			return $geo_country;
		}

		$geo = self::get_geo_for_ip();

		if ( isset($geo->country) && $geo->country ) {
			return $geo->country;
		} elseif( isset($geo->country_name) && $geo->country_name ) {
			return $geo->country_name;
		} else {
			return false;
		}
	}

	/**
	 * Get the country from IP geolocation only
	 * 
	 * @return string Country code
	 */
	public static function get_new_geo_country() {

		$geo = self::get_geo_for_ip();

		if ( isset($geo->country) && $geo->country ) {
			return $geo->country;
		} elseif( isset($geo->country_name) && $geo->country_name ) {
			return $geo->country_name;
		} else {
			return false;
		}
	}

	/**
	 * Get the country from session or the users billing country
	 * 
	 * @return string Country code
	 */
	private static function get_user_country() {

		if ( isset(WC()->session) && ! WC()->session->has_session() ) {
	        WC()->session->set_customer_session_cookie( true );
	    }

	    if ( isset( WC()->session ) ) {
			$user_country = WC()->session->get('geovin_user_country');
		}

		if ( isset($user_country) && $user_country ) {
			return $user_country;
		}

		$country = false;
		if ( WC()->customer ) {
			$country = WC()->customer->get_billing_country();
		}

		return $country;
	}

}
new Geolocation();