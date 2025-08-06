<?php
namespace Geovin;

use WP_CLI_Command;
use WP_CLI;
use Geovin\Geovin_Importer;

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	return;
}

/**
 * CLI access for Geovin commands.
 *
 * ## EXAMPLES
 *
 *     # Play a round of ping pong.
 *     $ wp andare ping
 *     pong
 */
class CLI extends WP_CLI_Command {

	public function __construct() {
		WP_CLI::add_command( 'ping', array( $this, 'ping' ) );
		WP_CLI::add_command( 'import_pricing', array( $this, 'import_pricing' ) );
		WP_CLI::add_command( 'import_us_pricing', array( $this, 'import_us_pricing' ) );
		WP_CLI::add_command( 'import_specs', array( $this, 'import_specs' ) );
		WP_CLI::add_command( 'export_trending_products', array( $this, 'export_trending_products' ) );
	}

	/**
	 * Example method to test that commands are registered.
	 *
	 * ## EXAMPLES
	 * 	wp ping
	 */
	public function ping() {
		WP_CLI::line( 'pong' );
	}

	/**
	 * Writes a CSV file of Trending Product SKUs and Links
	 * 
	 * ## EXAMPLES
	 * 	wp export_trending_products
	 *
	 */
	public function export_trending_products( $args, $assoc_args ) {
		$args = array(
			'type' => 'geovin', 
			'limit' => -1,
			'status' => 'publish'
		);
		$products = wc_get_products( $args );
		$rows = array();
		foreach( $products as $product ) {
			$new_row = array( 'name' => '', 'code' => '', 'link' => '');
			$new_row['name'] = $product->get_title();
			//find trending
			$product_page = new Geovin_Product_Page( 'CLI' );
			$trending = $product_page->get_trending_product_data( $product->get_id() );
			foreach( $trending as $trending_num => $trending_item ) {
				$match_attributes =  array(
					'attribute_pa_dimensions' => 'XXXX',
					'attribute_pa_wood-type' => 'X',
					'attribute_pa_finish' => 'XXX',
					'attribute_pa_hardware-shape' => 'XXX',
					'attribute_pa_hardware-finish' => 'X',
					'attribute_pa_base-finish' => 'X',
					'attribute_pa_doors' => 'XXX',
					'attribute_pa_headboard-panel' => 'XXX',
					'attribute_pa_fabric' => 'XXX'
					
				);
				foreach($trending_item as $attribute_group) {
					foreach($attribute_group as $key => $attribute) {
						$term_code = $attribute['code'];

					    $match_attributes["attribute_pa_" . $key] = $term_code;
					}
				}
				
				$sku = $product->get_sku() . '-' . implode('-', $match_attributes);
				$variation_id = wc_get_product_id_by_sku( $sku );
				$variation_product = wc_get_product( $variation_id );
				if ( $variation_product ) {
					$link = $variation_product ? $variation_product->get_permalink() . '&active_tab=' . $trending_num : 'could not find this product';
					$new_row['code'] = $sku;
					$new_row['link'] = $link;
					$rows[] = $new_row;
				} 
			}
		}

	    $uploads  = wp_upload_dir( null, false );
		$logs_dir = $uploads['basedir'] . '/product-exports/' . date('mY');
		if ( ! is_dir( $logs_dir ) ) {
		    mkdir( $logs_dir, 0755, true );
		}
	    $file_resource = fopen( $logs_dir . '/trending-products.csv', 'a' );
		WP_CLI\Utils\write_csv( $file_resource, $rows, array('name', 'code', 'link') );
	}

	/**
	 * Imports CAD pricing data from CSV
	 *
	 * ## OPTIONS
	 * 
	 * <file>
	 * :the csv file, relative to the root of the site
	 *
	 */
	public function import_pricing( $args, $assoc_args ) {
		$file       = $args[0];
		$data = Geovin_Importer::import_csv($file);
		$headers = Geovin_Importer::one_row_headers($data);
		//remove the header row now
		unset($data[0]);
		
		$data = Geovin_Importer::make_multi_array( $headers, $data );
		Geovin_Importer::save_pricing_data( $data );
	}

	/**
	 * Imports USD pricing data from CSV
	 *
	 * ## OPTIONS
	 * 
	 * <file>
	 * :the csv file, relative to the root of the site
	 *
	 */
	public function import_us_pricing( $args, $assoc_args ) {
		$file       = $args[0];
		$data = Geovin_Importer::import_csv($file);
		$headers = Geovin_Importer::one_row_headers($data);
		//remove the header row now
		unset($data[0]);
		
		$data = Geovin_Importer::make_multi_array( $headers, $data );
		Geovin_Importer::save_us_pricing_data( $data );
	}

	/**
	 * Imports variation specs data from CSV
	 *
	 * ## OPTIONS
	 * 
	 * <file>
	 * :the csv file, relative to the root of the site
	 *
	 */
	public function import_specs( $args, $assoc_args ) {
		$file       = $args[0];
		$data = Geovin_Importer::import_csv($file);
		$headers = Geovin_Importer::one_row_headers($data);
		//remove the header row now
		unset($data[0]);
		
		$data = Geovin_Importer::make_multi_array( $headers, $data );
		
		$data = Geovin_Importer::group_by_product( $data );
		Geovin_Importer::save_spec_data( $data );
	}
}

new CLI();