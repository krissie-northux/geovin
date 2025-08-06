<?php
namespace Geovin;

class GF_Filters {
	public function __construct() {
		add_filter( 'gform_notification_1', array( $this, 'geovin_product_details_email' ), 10, 3 );
		add_filter( 'gform_countries', array( $this, 'use_only_specific_countries' ), 10, 1 );

	}

	/**
	 * Use only specific countries in the Gravity Forms country field.
	 * 
	 * @param array $countries The list of countries.
	 * 
	 * @return array Filtered list of countries.
	 */
	public function use_only_specific_countries( $countries ){
	    return array( 'CA' => 'Canada', 'US' => 'United States' );
	}

	/**
	 * Modify the email notification for the product details form.
	 * 
	 * @param array $notification The notification settings.
	 * @param array $form The form object.
	 * @param array $entry The entry object.
	 * 
	 * @return array Modified notification.
	 */
	public function geovin_product_details_email( $notification, $form, $entry ) {
		$GLOBALS['geovin_prod_email_sending'] = true;

		$product = wc_get_product( rgar( $entry, '1' ) );
		$price = $product->get_price_html();

		$link = $product->get_permalink() . '&active_tab=' . rgar($entry,'3');
		$attribute_selections = json_decode( rgar( $entry, '6') );

		$link = $link . '&niceatts=' . urlencode( base64_encode( rgar( $entry, '6') ) );
		$attribute_text = '';
		foreach( $attribute_selections as $key => $selection ) {
			$attribute_text = $attribute_text . '<b>' . $key . ':</b> ' . $selection . '<br/>';
		}
			$image = rgar( $entry, '4' );
			if ( strpos($image, 'base64') !== false ) {
				//upload the image and use the url
				$image = self::base64_to_png( $image );
				$link = $link . '&image=' . $image;
				$resized_image = explode('.png',$image)[0] . '-600x600.png';
				$image = self::resize_image( $resized_image, 600, 600, false );
			}
			if ( strpos( $price, '--trade-only') != false ) {
				$notification['message'] = str_replace('MSRP:', '', $notification['message'] );
				$notification['message'] = str_replace('{{Price}}', '', $notification['message'] );
				$notification['message'] = $notification['message'] . $price;
			} else {
				$price_shown = \WC()->session->get('price_shown');
				$notification['message'] = str_replace('MSRP', $price_shown, $notification['message'] );
				$notification['message'] = str_replace('{{Price}}', $price, $notification['message'] );
			}

		$notification['message'] = str_replace('{{Product Link}}', $link, $notification['message'] );
		$notification['message'] = str_replace('{Product Image:4}', '<img src="' . esc_attr( $image ) . '" width="600" style="display: block; max-width: 100%; min-width: 100px; width: 100%;"/>', $notification['message'] );
		$notification['message'] = str_replace('{Selected Attributes:6}', $attribute_text, $notification['message']);

	 	$GLOBALS['geovin_prod_email_sending'] = false;
	    return $notification;
	}

	/**
	 * Resize an image to the specified width and height.
	 * Used to make custom product images smaller for emails.
	 * 
	 * @param string $file The path to the image file.
	 * @param int $w The desired width.
	 * @param int $h The desired height.
	 * @param bool $crop Whether to crop the image.
	 * 
	 * @return string The path to the resized image.
	 */
	public static function resize_image($file, $w, $h, $crop=FALSE) {
		list($width, $height) = getimagesize($file);
		$r = $width / $height;
		if ($crop) {
			if ($width > $height) {
				$width = ceil($width-($width*abs($r-$w/$h)));
			} else {
				$height = ceil($height-($height*abs($r-$w/$h)));
			}
			$newwidth = $w;
			$newheight = $h;
		} else {
			if ($w/$h > $r) {
				$newwidth = $h*$r;
				$newheight = $h;
			} else {
				$newheight = $w/$r;
				$newwidth = $w;
			}
		}
		
		$src = imagecreatefromjpeg($file);
		return $file;
	}

	/**
	 * Take the base64 data from Shapediver that was submitted in a hidden
	 * GF field and save it as a PNG file
	 * in the uploads directory.
	 * 
	 * @param string $base64string The base64 encoded string.
	 * 
	 * @return string The URL of the saved PNG file.
	 * 
	 */
	public static function base64_to_png( $base64string ) {
		$base64_string = $base64string;
		$upload_dir = wp_upload_dir();
		$timestamp = current_time('timestamp');
		if ( wp_mkdir_p( $upload_dir['path'] . '/product-screenshots/' ) ) {
			$output_file = $upload_dir['path'] . '/product-screenshots/' . 'product-3d-screenshot_' . $timestamp . '.png';
			$sized_output = $upload_dir['path'] . '/product-screenshots/' . 'product-3d-screenshot_' . $timestamp . '-600x600.png';
		} else {
			$output_file = $upload_dir['basedir'] . '/product-screenshots/' . 'product-3d-screenshot_' . $timestamp . '.png';
			$sized_output = $upload_dir['basedir'] . '/product-screenshots/' . 'product-3d-screenshot_' . $timestamp . '-600x600.png';
		}
	    // open the output file for writing
	    $ifp = fopen( $output_file, 'wb' );
	    $ifps = fopen( $sized_output, 'wb' );

	    // split the string on commas
	    // $data[ 0 ] == "data:image/png;base64"
	    // $data[ 1 ] == <actual base64 string>
	    $data = explode( ',', $base64_string );

	    // we could add validation here with ensuring count( $data ) > 1
	    fwrite( $ifp, base64_decode( $data[ 1 ] ) );
	    fwrite( $ifps, base64_decode( $data[ 1 ] ) );

	    // clean up the file resource
	    fclose( $ifp ); 
	    fclose( $ifps );

	    $path_array = explode('/',$output_file);
	    $count = count($path_array);
	    $count--;
	    $output_file = get_home_url() . '/wp-content/uploads/' . $path_array[$count - 3] . '/' . $path_array[$count - 2] . '/' . $path_array[$count - 1] . '/' . $path_array[$count];


	    return $output_file;

	}
}

new GF_Filters();