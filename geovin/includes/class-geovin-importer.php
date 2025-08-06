<?php
namespace Geovin;

class Geovin_Importer {

	/**
	 * Converts a csv file into an array.
	 * 
	 * @param string $file The path to the csv file.
	 * @return array The data from the csv file as an array.
	 */
	public static function import_csv( $file ) {
		$csv_file = file( $file );
		$data     = [];
		foreach ( $csv_file as $line ) {
			$data[] = str_getcsv( $line );
		}
		return $data;
	}

	/**
	 * Converts a two-row header csv file into an array.
	 * Geovin provides a two-row header csv file
	 * 
	 * @param array $data The data from the csv file as an array.
	 * @return array The headers from the csv file as an array.
	 */
	public static function two_row_headers( $data ) {
		$headers = array();
		$latest = '';
		foreach ( $data[0] as $key => $value ) {
			
			if ( $value !== '' ) {
				$latest = $value;
			}
			$headers[$key] = $data[1][$key] !== '' ? $latest . ' -- ' . $data[1][$key] : $latest;
		}
		return $headers;
	}

	/**
	 * Converts a one-row header csv file into an array.
	 * 
	 * @param array $data The data from the csv file as an array.
	 * @return array The headers from the csv file as an array.
	 */
	public static function one_row_headers( $data ) {
		$headers = array();
		$latest = '';
		foreach ( $data[0] as $key => $value ) {
			
			if ( $value !== '' ) {
				$latest = $value;
			}
			$headers[$key] = $latest;
		}
		return $headers;
	}

	/**
	 * Converts a csv file into a multi-dimensional array.
	 * 
	 * @param array $headers The headers from the csv file as an array.
	 * @param array $data The data from the csv file as an array.
	 * @return array The data from the csv file as a multi-dimensional array.
	 */
	public static function make_multi_array( $headers, $data ) {
		$data_array = array();
		foreach( $data as $i => $line ) {
			foreach( $line as $key => $value ) {
				$data_array[$i][$headers[$key]] = $value;
			}
		}
		return $data_array;
	}

	/**
	 * Groups the data by product SKU.
	 * 
	 * @param array $data The data from the csv file as a multi-dimensional array.
	 * @return array The data grouped by product SKU.
	 */
	public static function group_by_product( $data ) {
		$product_array = array();

		foreach ( $data as $line_item ) {
			$sku = $line_item['C01'] . '-' . $line_item['C02'];
			$product_id = wc_get_product_id_by_sku( $sku );

			
			// create array looks like this
			/*[0] => Array
		        (
		            [spec_name] => wood
		            [variable] => Array
		                (
		                    [0] => WP_Term Object
		                        (
		                            [term_id] => 33
		                            [name] => Ash
		                            [slug] => ash
		                            [term_group] => 0
		                            [term_taxonomy_id] => 33
		                            [taxonomy] => pa_wood-type
		                            [description] => 
		                            [parent] => 0
		                            [count] => 37
		                            [filter] => raw
		                        )

		                )

		            [spec_value] => <img src=""/> White Ash
		            [spec_unit] => 
		        )
		        */
	        $term = self::get_term_from_code( $line_item['C03'], 'C03' );
		    foreach( $line_item as $key => $item ) {
		    	if ( $item ) {
			    	if ( $key === 'Collection' || $key === 'Description' || $key === 'C01' || $key === 'C02' || $key === 'C03' ) {
			    		//skip
			    	} else {
				    	$key = str_replace(' (Base)', '', $key);
				    	$key_array = explode('(',$key);
				    	$key_label = $key_array[0];
				    	$key_units = isset( $key_array[1] ) ? str_replace( ')', '', $key_array[1] ) : '';
				    	$product_array[$sku][] = array(
							'spec_name' => $key_label,
							'variable' =>  $term[0]->term_id,
							'spec_value' => $item,
							'spec_unit' => $key_units,
						); 
				    }
				}
		    }
		    
		}

		foreach( $product_array as $key => &$product ) {
			$term = self::get_term_from_code( 'A', 'C04' );
		    $product[] = array(
				'spec_name' => 'wood',
				'variable' =>  $term[0]->term_id,
				'spec_value' => '<img src="/wp-content/uploads/2021/12/whiteash.jpg" /> White Ash',
				'spec_unit' => '',
			); 
			$term = self::get_term_from_code( 'M', 'C04' );
		    $product[] = array(
				'spec_name' => 'wood',
				'variable' =>  $term[0]->term_id,
				'spec_value' => '<img src="/wp-content/uploads/2021/12/maple.jpg" /> Maple',
				'spec_unit' => '',
			);
			$term = self::get_term_from_code( 'W', 'C04' );
		    $product[] = array(
				'spec_name' => 'wood',
				'variable' =>  $term[0]->term_id,
				'spec_value' => '<img src="/wp-content/uploads/2021/12/walnut.jpg" /> Walnut',
				'spec_unit' => '',
			);
			$term = self::get_term_from_code( 'P', 'C04' );
		    $product[] = array(
				'spec_name' => 'wood',
				'variable' =>  $term[0]->term_id,
				'spec_value' => '<img src="/wp-content/uploads/2021/12/poplar.jpg" /> Poplar',
				'spec_unit' => '',
			);
			if ( strpos( $key, 'G06' ) !== false || strpos( $key, 'G07' ) !== false  ) {
				//add door specs
				$term = self::get_term_from_code( 'D00', 'C09' );
			    $product[] = array(
					'spec_name' => 'door',
					'variable' =>  $term[0]->term_id,
					'spec_value' => '<img src="" /> Selected Finish',
					'spec_unit' => '',
				);
				$term = self::get_term_from_code( 'D01', 'C09' );
			    $product[] = array(
					'spec_name' => 'door',
					'variable' =>  $term[0]->term_id,
					'spec_value' => '<img src="/wp-content/uploads/2021/12/D01-Striped-White.jpg" /> Striped White',
					'spec_unit' => '',
				); 
				$term = self::get_term_from_code( 'D02', 'C09' );
			    $product[] = array(
					'spec_name' => 'door',
					'variable' =>  $term[0]->term_id,
					'spec_value' => '<img src="/wp-content/uploads/2021/12/D02-Striped-Gray.jpg" /> Striped Gray',
					'spec_unit' => '',
				);
				$term = self::get_term_from_code( 'D03', 'C09' );
			    $product[] = array(
					'spec_name' => 'door',
					'variable' =>  $term[0]->term_id,
					'spec_value' => '<img src="/wp-content/uploads/2021/12/D03-Striped-Black.jpg" /> Striped Black',
					'spec_unit' => '',
				);
				$term = self::get_term_from_code( 'D04', 'C09' );
			    $product[] = array(
					'spec_name' => 'door',
					'variable' =>  $term[0]->term_id,
					'spec_value' => '<img src="/wp-content/uploads/2021/12/D04-Striped-Sand.jpg" /> Striped Sand',
					'spec_unit' => '',
				);
			}
		}
		return $product_array;
	}

	/**
	 * Get a term based on the code and label.
	 * 
	 * @param string $term_code The code of the term.
	 * @param string $label The label of the term.
	 * @return object The term object.
	 */
	private static function get_term_from_code( $term_code, $label ) {
		if ( $label === 'Code-03' || $label === 'C03' ) {
			$taxonomy = 'pa_dimensions';
			$term_code = str_pad($term_code, 4, '0', STR_PAD_LEFT);
		} elseif ( $label === 'Code-04' || $label === 'C04' ) {
			$taxonomy = 'pa_wood-type';
		} elseif ( $label === 'Code-05' || $label === 'C05' ) {
			$taxonomy = 'pa_finish';
		} elseif ( $label === 'Code-06' || $label === 'C06' ) {
			$taxonomy = 'pa_hardware-shape';
		} elseif ( $label === 'Code-07' || $label === 'C07' ) {
			$taxonomy = 'pa_hardware-finish';
		} elseif ( $label === 'Code-08' || $label === 'C08' ) {
			$taxonomy = 'pa_base-finish';
		} elseif ( $label === 'Code-09' || $label === 'C09' ) {
			$taxonomy = 'pa_doors';
		} elseif ( $label === 'Code-10' || $label === 'C10' ) {
			$taxonomy = 'pa_headboard-panel';
		} elseif ( $label === 'Code-11' || $label === 'C11' ) {
			$taxonomy = 'pa_fabric';
		}
		
		//look up term by code
		$term = get_terms(array(
			'taxonomy'          => $taxonomy,
			'meta_query'		=> array(
				'relation'		=> 'AND',
				array(
					'key'			=> 'code',
					'value'			=> $term_code,
					'compare'		=> '='
				)
			)
		));

		return $term;
	}

	/**
	 * Save the spec data to the product.
	 * 
	 * @param array $data The data to be saved.
	 * @return void
	 */
	public static function save_spec_data ( $data ) {
		foreach ( $data as $sku => $value ) {
			$product_id = wc_get_product_id_by_sku( $sku );
			update_field( 'specs', $value, $product_id );
		}
	}

	/**
	 * Save the pricing data to the product.
	 * 
	 * @param array $data The data to be saved.
	 * @return void
	 */
	public static function save_pricing_data( $data ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$count = count($data);
			$progress = \WP_CLI\Utils\make_progress_bar( 'Updating Prices', $count );
		}
		foreach( $data as $product_line ) {
			//find parent product by SKU
			$product_id = wc_get_product_id_by_sku( $product_line['Code-01'] . '-' . $product_line['Code-02'] );
			$product = wc_get_product($product_id);
			if ( $product ) {
				$variations = $product->get_children();
				$variation_count = 0;
				foreach( $variations as $variation ) {
					$variant = wc_get_product($variation);
					$sku = $variant->get_sku();
					$sku_parts = explode('-', $sku);
					$code_01 = $sku_parts[0];
					$code_02 = $sku_parts[1];
					$code_03 = $sku_parts[2];
					$code_04 = $sku_parts[3];
					$code_08 = $sku_parts[7];
					$code_09 = $sku_parts[8];

					if ( $product_line['Code-01'] === $code_01 && $product_line['Code-02'] === $code_02 && $product_line['Code-03'] === $code_03 && $product_line['Code-08'] === $code_08 ) {
						 $price = floatval( trim( str_replace( ',', '', str_replace('$','', $product_line['CS' . $code_04] ) ) ) );
						 if ( $code_09 === 'D01' || $code_09 === 'D02' || $code_09 === 'D03' || $code_09 === 'D04' ) {
						 	$price = $price * 1.2;
						 }
						 update_post_meta($variation,'_price',$price);
						 update_post_meta($variation,'_regular_price',$price);
						 $variation_count++;
					}
					
				}
			} 
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				\WP_CLI::success('updated '. $product_line['Description'] . ' with ' . $variation_count . ' variations.');
				$progress->tick();
			}
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$progress->finish();
			\WP_CLI::success('import complete');
		}
	}

	/**
	 * Save the USD pricing data to the product.
	 * 
	 * @param array $data The data to be saved.
	 * @return void
	 */
	public static function save_us_pricing_data( $data ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$count = count($data);
			$progress = \WP_CLI\Utils\make_progress_bar( 'Updating Prices', $count );
		}
		foreach( $data as $product_line ) {
			//find parent product by SKU
			$product_id = wc_get_product_id_by_sku( $product_line['Code-01'] . '-' . $product_line['Code-02'] );
			$product = wc_get_product($product_id);
			$variations = $product->get_children();
			$variation_count = 0;
			foreach( $variations as $variation ) {
				$variant = wc_get_product($variation);
				$sku = $variant->get_sku();
				
				$sku_parts = explode('-', $sku);
				$code_01 = $sku_parts[0];
				$code_02 = $sku_parts[1];
				$code_03 = $sku_parts[2];
				$code_04 = $sku_parts[3];
				$code_08 = $sku_parts[7];
				$code_09 = $sku_parts[8];
				
				if ( $product_line['Code-01'] === $code_01 && $product_line['Code-02'] === $code_02 && $product_line['Code-03'] === $code_03 && $product_line['Code-08'] === $code_08 ) {
					 $price = floatval( trim( str_replace( ',', '', str_replace('$','', $product_line['US' . $code_04] ) ) ) );
					 if ( $code_09 === 'D01' || $code_09 === 'D02' || $code_09 === 'D03' || $code_09 === 'D04' ) {

					 	$price = $price * 1.2;
					 }
					 update_post_meta($variation,'us_price',$price);
					 $variation_count++;
				}
				
			}

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				\WP_CLI::success('updated '. $product_line['Description'] . ' with ' . $variation_count . ' variations.');
				$progress->tick();
			}
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$progress->finish();
			\WP_CLI::success('import complete');
		}
	}

	/* Not being used
	public static function save_dynamic_pricing_data( $data ) {

		foreach( $data as $product_line ) {
			//find product by sku
			$product_id = wc_get_product_id_by_sku( $product_line['Code-01'] . '-' . $product_line['Code-02'] );
			$attribute_adjustments = array();

			foreach ($product_line as $key => $value) {
				if ( $key === 'Collection' || $key === 'Description' || $key === 'Code-01' ||  $key === 'Code-02' ) {
					//skip
				} elseif ( $key === 'Base Price' ) {
					update_post_meta( $product_id, '_base_price', str_replace( "$", "", $value ) );
				} else {
					$header_array = explode(' -- ',$key);
					$term = self::get_term_from_code( $header_array[1], $header_array[0] );
					
					
					$value = str_replace( "$", "", $value );
					if ( $value !== ''  && $value != 0 && ! empty( $term ) ) {
						$attribute_adjustments[] = array(
							'attribute_for_adjustment' => $term[0],
							'price_adjust_by' => $value,
						);
					}
					

				}
			}
			update_field( 'attributes', $attribute_adjustments, $product_id );
		}
	}*/
}