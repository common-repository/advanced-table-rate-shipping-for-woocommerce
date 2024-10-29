<?php
/*
 * Table Rate Shipping Method Extender Class
 */

if ( ! defined( 'ABSPATH' ) )
	exit;

// Check if WooCommerce is active
if ( class_exists( 'WooCommerce' ) ) {

	if ( class_exists( 'ACOTRS_ShippingCalculation' ) ) return;

	class ACOTRS_ShippingCalculation {

        /*
        * Package Information 
        */
        private $acotrs_method;

        /**
		 * __construct function.
		 *
		 * @access public
		 * @return void
		 */
		function __construct( $acotrs_method ) {

			$this->acotrs_method  = $acotrs_method;
			
			
        }
		

        /**
		 * calculate_shipping function.
		 *
		 * @access public
		 * @param array $package (default: array())
		 * @return array
		 */
		public function acotrs_calculate_shipping( $package = array() ) {
			if( is_array( $package ) && ! empty( $package ) && isset( $package['contents'] ) ) {
				
				// Check allowed city if Enabled shipping by city
				if($this->acotrs_method->city_enable){
					$destination_city = strtolower($package['destination']['city']);
					$citys = explode("\n", str_replace("\r", "", strtolower($this->acotrs_method->allowed_city)));
					
					if(count($citys) > 0){
						switch($this->acotrs_method->region_is){
							case 'excluding':
								if(in_array($destination_city, $citys)){
									return false;
								}
							break;
							default:
								if(!in_array($destination_city, $citys)){
									return false;
								}
						}
					}
				}



				$cart_data = array(); // Calculate cart statistics
				switch( $this->acotrs_method->base_rule_condition ) {
                    case 'per_order':
						$cart_data = array( 'per-order' => $this->acotrs_calculate_totals_order( $package['contents'] ) );
						break;

					case 'per_item':
						$cart_data = $this->acotrs_calculate_total_item( $package['contents'] );
						break;

					case 'per_line_item':
						$cart_data = $this->acotrs_calculate_total_item( $package['contents'], true );
						break;
					
					case 'per_class':
						$cart_data = $this->acotrs_cartdata_basedon_class( $package['contents'] );
						break;
					
					default:
						$cart_data = apply_filters( 'acotrs_calculate_method_totals', $cart_data, $this->acotrs_method->base_rule_condition, $package, $this->acotrs_method );
				}



				

				// send to calculator for processing
				$contents_cost = 0;
				if( ! empty( $cart_data ) ) {
					$rates = array();
					
					foreach( $cart_data as $key => $data ) {
						$rates[ $key ] = $this->acotrs_process_table_rates( $data );
						$contents_cost += $data['subtotal'];
					}
					
				}
				
				
				$rates = array_map('array_filter', $rates);
				$rates = array_filter($rates);
				
				$shipping_options = $first_option = array();
				// ensure that all rates cover all items in the cart
				if( is_array( $rates ) && count( $rates ) > 0 ) {
					$n = 0;
					foreach( $rates as $key => $rate ) {
						if( $n == 0 ) {
							$first_option = $rate;
							$first_key = $key;
							$n++;
							continue;
						}

						$rates[ $key ] = array_intersect_key( $rate, $first_option );
						$first_option = array_intersect_key( $first_option, $rate );
					}
					$rates[ $first_key ] = $first_option;
				}

				

				// Adjust for single class only if necessary per_class
				if( $this->acotrs_method->base_rule_condition == 'per_class' && isset($this->acotrs_method->ad_single_class) ) {
					switch( $this->acotrs_method->ad_single_class ) {
						case 'priority':
							$highest_key = $highest_priority = 0;
							foreach( $rates as $class_key => $options ) {
								$get_priority = get_term_meta( $class_key, 'priority', true );

								if( $get_priority >= $highest_priority ) {
									$highest_priority = $get_priority;
									$highest_key = $class_key;
								}

							}

							// add contents cost for subtotal shortcode
							foreach( $rates[ $highest_key ] as $op_id => $op_val ) {
								$rates[ $highest_key ][ $op_id ]['contents_cost'] = $cart_data[ $highest_key ]['subtotal'];
							}

							return $rates[ $highest_key ];							
							break;
						
						case 'cost_high':
						case 'cost_low':
							$options_for_return = array();
							$op_class = false;

							foreach( $rates as $class_key => $options ) {

								foreach( $options as $op_key => $option ) {

									if( ! isset( $options_for_return[ $op_key] ) ) {
										// initialize an option for this ID
										$options_for_return[ $op_key ] = $option;

									} else {
										// determine if this is the option to be returned based on cost
										if( $this->acotrs_method->ad_single_class == 'cost_low' ) {
											if( $option['cost'] < $options_for_return[ $op_key ]['cost'] ) {
												$options_for_return[ $op_key ] = $option;
												$op_class = $class_key;
											}
										} else {
											if( $option['cost'] > $options_for_return[ $op_key ]['cost'] ) {
												$options_for_return[ $op_key ] = $option;
												$op_class = $class_key;
											}
										}
									}
								}

							}

							// add contents cost for subtotal shortcode
							foreach( $options_for_return as $op_id => $op_val ) {
								$options_for_return[ $op_id ]['contents_cost'] = $cart_data[ $class_key ]['subtotal'];
							}

							return $options_for_return;
							break;

						case 'disabled':
							break;
						
						default:
							return apply_filters( 'acotrs_condition_single_class_' . $this->acotrs_method->ad_single_class, $rates );
							break;
					}
				}

				
				// compile for return (combine Per Item and Per Class data to one price)
				
				$shipping_options = array();
				foreach( $rates as $key => $rate ) {
					foreach( $rate as $op_key => $op ) {
						if( ! isset( $shipping_options[ $op_key ] ) ) {
							$shipping_options[ $op_key ] = $op;
							$shipping_options[ $op_key ]['contents_cost'] = $contents_cost;

							// Set title to Method Title when left empty
							if( $shipping_options[ $op_key ]['label'] == '' ){ 
								$shipping_options[ $op_key ]['label'] = $this->method->method_title;
							}
							continue;
						}

						$shipping_options[ $op_key ]['cost'] += $op['cost'];
						$shipping_options[ $op_key ]['description'] = $op['description'];
						$shipping_options[ $op_key ]['row_id'] = $op['row_id'];
					}
				}

				return $shipping_options;


            }    
            // return $this->acotrs_method->table_of_rates;
        }
        



		/**
		 * Find valid options for calculated cart data.
		 *
		 * @access public
		 * @param array $cart_data (default: array())
		 * @return array
		 */
		public function acotrs_process_table_rates( $cart_data ) {
			// setup necessary variables
			$rates = array();
			if( ! empty( $cart_data ) && ! empty( $this->acotrs_method ) ) {

				
				$custom_costs = array();
				// step through each table rate row

				foreach( $this->acotrs_method->table_of_rates as $a_key => $option ) {
					$cost = false;
					$description = "";
					
					// skip processing if option is disabled
					if(isset( $option['disable_option'] ) && (int)$option['disable_option'] === 1 ) continue;
					if(!isset($option['rows'])) continue;
					if(isset($option['rows']) && count($option['rows']) <= 0) continue;

				
					$costArray = array();

					
					foreach( $option['rows'] as $r_key => $row ) {
						
						if(count($row['costs']) <= 0)
							continue;

						$row_contidions = apply_filters( 'acotrs_row_conditions', $row['conditions'] );
						if( ! empty( $row_contidions ) && count($row_contidions) > 0 ) {
							$qualifies = false;
							$results = array();

							foreach( $row_contidions as $cond ) {

								$results[] = $this->acotrs_determine_conditional_result( $cond, $cart_data );
								
								
							}

							if( in_array( true, $results ) && !in_array( false, $results ) ) $qualifies = true;

							if( $qualifies === true ) {
								// calculate costs for qualifying row
								$cost = $this->acotrs_calculate_shipping_cost( $row['costs'], $cart_data );
								
								$description = isset($row['description']) ? $row['description'] : '';
								$row_id = $r_key;
								if((int)$cost > 0){
									array_push($costArray, array(
										'cost' => $cost, 
										'description' => $description, 
										'row_id' => $row_id
									));
								}
							}
						} else {
							$cost = $this->acotrs_calculate_shipping_cost( $row['costs'], $cart_data );
							$description = isset($row['description']) ? $row['description'] : '';
							$row_id = $r_key;
							if((int)$cost > 0){
								array_push($costArray, array(
									'cost' => $cost, 
									'description' => $description, 
									'row_id' => $row_id
								));
							}
						}
						

						$delivery_date = 0;
						if($this->acotrs_method->ad_set_delivery_date_automatically !== 0 && ACOTRS_PLUGIN_TYPE != 'free'){
							if(isset($this->acotrs_method->ad_set_delivery_date) && $this->acotrs_method->ad_set_delivery_date > 0)
								$delivery_date = $this->acotrs_method->ad_set_delivery_date;	
						}
						
						$delivery_day = isset($row['conditions'][0]['delivery_day']) && (int)$row['conditions'][0]['delivery_day'] > 0 ? $row['conditions'][0]['delivery_day'] : $delivery_date;

					}
					

					if(count($costArray) > 0){
						if((int)$this->acotrs_method->ad_get_minimum_value_from_condition){
							 $targetRow = $costArray[array_search( min(array_column($costArray, 'cost')), array_column($costArray, 'cost') )];
						}else{
							$targetRow = $costArray[array_search( max(array_column($costArray, 'cost')), array_column($costArray, 'cost') )];
						}

						$cost = $targetRow['cost'];
						$row_id = $targetRow['row_id'];
						
						if(isset($option['combine_descriptions']) && (int)$option['combine_descriptions'] === 1){
							$description = wp_list_pluck( $costArray, 'description' );
							$description = implode('; ', $description);
						}else{
							$description = $targetRow['description'];
						}
					}


					// Check if today special Day and Special day are activeted 
					if($this->acotrs_method->ad_free_shipping_on_special_day && count($this->acotrs_method->ad_special_days)){
						$matchDate = array_search(strtolower(date('d-F')), array_column( $this->acotrs_method->ad_special_days, 'date' ));	
						if($matchDate !== false){

							if((int)$this->acotrs_method->ad_special_day_persent > 0){
								$persentagecost = ( (int)$this->acotrs_method->ad_special_day_persent / 100 ) * $cost;
								$cost = $cost - $persentagecost;
							}else{
								$cost = 0;
								$option['title'] = $option['title'] .' : '. __('Free Shipping', 'advanced-table-rate-shipping-for-woocommerce');
							}
						}
					}
					
					// setup shipping option if cart qualifies option_id
					if( $cost !== false ) {
						$option_id = $this->acots_generate_new_option_id( $option['option_id'] );
						$rates[ $option['option_id'] ] = array(
							'id'        	=> $option_id,
							'label'     	=> stripslashes( $option['title'] ),
							'cost'      	=> $cost,
							'description' 	=> $description,
							'default'	 	=> isset($option['default_selection']) ? (int)$option['default_selection'] : 0,
							'hide_ops'	 	=> isset($option['hide_other_options']) ? (int)$option['hide_other_options'] : 0,
							'row_id'		=> $row_id,
							'delivery_day' 	=> (int)$delivery_day > 0 ? (int)$delivery_day : 0
							);
					}	
				}
			}


			if(count($rates) <= 0 && (int)$this->acotrs_method->flatrate > 0){
				$rates[$this->acotrs_method->id] = array(
					'id' => $this->acotrs_method->id, 
					'label' => $this->acotrs_method->title,
					'cost' => $this->acotrs_method->flatrate, 
				);
			}

			
			return $rates;
		}




		/**
		 * Setup shipping option ID tag.
		 *
		 * @access public
		 * @param int $option_id
		 * @return string
		 */
		public function acots_generate_new_option_id( $option_id ) {
			$option_id = (int) $option_id;

			return $this->acotrs_method->id . ':' . $this->acotrs_method->acotrs_instance_id . '-' . $option_id;
		}

		/**
		 * Determine if cart information qualifies for given condition.
		 *
		 * @access public
		 * @param array $cond, array $cart_data
		 * @return float
		 */
		public function acotrs_calculate_shipping_cost( $costs = array(), $cart_data = array() ) {
			if( ! is_array( $costs ) ) return 0;

			// cycle through the different cost options
	        $cost_ops = apply_filters( 'acotrs_shipping_cost_options', array(
	                ''          => get_woocommerce_currency_symbol(),
	                '%'         => '%',
	                'x'         => __( 'Multiplied by', 'advanced-table-rate-shipping-for-woocommerce' ),
	                'every'     => __( 'For every', 'advanced-table-rate-shipping-for-woocommerce' ),
	            ) );
	        $calcs = array();

	        foreach( $costs as $cost ) {

		        switch ( $cost['cost_unit'] ) {
		        	case '':
		        		$calcs[] = $cost['cost'];
		        		break;
		        	case '%':
		        		$calcs[] = $cart_data['subtotal'] * ( $cost['cost'] / 100 );
		        		break;
		        	case 'x':
						$cost['cost_multipliedby'] = !isset($cost['cost_multipliedby']) ? 'quantity' : $cost['cost_multipliedby'];
		        		$calcs[] = $cost['cost'] * $cart_data[ $cost['cost_multipliedby'] ];
		        		break;
		        	case 'every':
		        		if( $cost['cost_forevery_condition'] === 'dimensions' ) {
		        			// determine which dimensional value to multiply by
		        			if( isset( $cart_data[ $cost['cost_forevery_extra_secondary'] ] ) ) {
		        				$calcs[] = ceil( $cart_data[ $cost['cost_forevery_extra_secondary'] ] / $cost['cost_forevery_unit'] ) * $cost['cost'];
		        			}
		        		} else {
		        			// calculate the value based on select data
							$cost['cost_forevery_condition'] = isset($cost['cost_forevery_condition']) ? $cost['cost_forevery_condition'] : 'subtotal';
		        			if( isset( $cart_data[ $cost['cost_forevery_condition'] ] ) ) {
		        				$calcs[] = ceil( $cart_data[ $cost['cost_forevery_condition'] ] / $cost['cost_forevery_unit'] ) * $cost['cost'];
		        			}
		        		}
		        		break;
						
		        	default:
		        		$calcs[] = (float) apply_filters( 'acotrs_cost_final_result', $cost['cost_value'], $cost['cost_unit'], $cost );
		        		break;
		        }

	        }
			
	        return apply_filters('acotrs_costs_returns', array_sum( array_map( 'floatval', $calcs ) ), $costs, $cart_data);
		}


		/**
		 * Return all taxonomy of product
		 * @return taxonomy_list
		 */
		private function acotrs_product_taxonomy_lists(){
			$taxonomy_Array = array_map(function ($value) {
				return ($value->name);
			}, get_object_taxonomies('product', 'objects') );
			return apply_filters('acotrs_taxonomy_lists', array_keys($taxonomy_Array));
		} 


		/**
		 * Determine if cart information qualifies for given condition.
		 *
		 * @access public
		 * @param array $cond, array $cart_data
		 * @return bool
		 */
		public function acotrs_determine_conditional_result( $cond, $cart_data ) {			
			if( is_array( $cond ) && isset( $cond['condition'] ) ) {
				$cond_type = sanitize_title( $cond['condition'] );
				$product_taxonomys = $this->acotrs_product_taxonomy_lists();
				array_push($product_taxonomys, 'product');

				// perform the correct check based on condition type
				if( in_array( $cond_type, array( 'subtotal', 'quantity', 'weight', 'height', 'width', 'length', 'area', 'volume' ) ) ) {
					
					// allow any third party plugins to adjust number
					$cvalue = apply_filters( 'acotrs_conditional_tertiary_' . $cond_type, floatval( $cond['cvalue'] ), $cond );


					if( isset( $cart_data[ $cond_type ] ) ){
						$comparison = $cart_data[ $cond_type ];
					}else{
						return false;
					}
						
					
					switch( $cond['compair'] ) {
						case 'greater_than':
							if( $comparison >= $cvalue ){
								return true;
							}
							break;
						case 'less_than':
							if( $comparison <= $cvalue )
								return true;
							break;
						case 'equal_to':
							if( $comparison == $cvalue )
								return true;
							break;
						
						default:
							return apply_filters( 'acotrs_conditional_secondary_numbers', false, $cond, $cart_data );
							break;
					}

				} elseif( in_array( $cond_type, $product_taxonomys ) ) {

					// Check if conmrasion in comparasion selection
					$comparison = array();
					$cond['compair'] = !in_array($cond['compair'], array('includes', 'excludes')) ? 'includes' : $cond['compair'];

					$cvalue = apply_filters( 'acotrs_conditional_tertiary_' . $cond_type, $cond['cvalue'], $cond );

					

					
					switch( $cond_type ) {
						case 'product_shipping_class':
							$comparison = apply_filters( 'acotrs_comparison_tertiary_' . $cond_type, $cart_data['shipping_classes'], $cond );
							break;
						case 'product':
							$comparison = apply_filters( 'acotrs_comparison_tertiary_' . $cond_type, $cart_data['products'], $cond );
						break;
						case 'product_cat':
							$comparison = apply_filters( 'acotrs_comparison_tertiary_' . $cond_type, $cart_data['categories'], $cond );
							break;
						default:
							$comparison = isset($cart_data[$cond_type]) ? apply_filters( 'acotrs_comparison_tertiary_' . $cond_type, $cart_data[$cond_type], $cond ) : array();
					}


					$temp = true;
					if( is_array( $comparison ) ) {
						if( is_array( $cvalue ) ) {
							$cvalue = array_map(function($value){
								return isset($value['term_id']) ? $value['term_id'] : $value['id'];
							}, $cvalue);

							if( $cond['compair'] === 'includes' ) {
								if(empty(array_intersect($comparison, $cvalue))){
									$temp = false;
								}
								return $temp;
							}

							if( $cond['compair'] === 'excludes' ) {
								if(!empty(array_intersect($comparison, $cvalue))){
									$temp = false;
								}
								
								return $temp;
							}
						} else {
							if( $cond['compair'] === 'includes' && in_array( $cvalue, $comparison ) )
								return true;

							if( $cond['compair'] === 'excludes' && ! in_array( $cvalue, $comparison ) )
								return true;
							
						}
					}else{
						return $temp;
					}

				} elseif( $cond_type == 'dates' ) {
					$cond['compair'] = in_array($cond['compair'], array('excludes', 'includes')) ? $cond['compair'] : 'includes';

					if( isset( $cond['cvalue']['from'] ) && isset( $cond['cvalue']['to'] ) ) {
						// convert dates to timestamps
						$start_date = strtotime( $cond['cvalue']['from'] );
						$end_date = strtotime( $cond['cvalue']['to'] );
						$now = strtotime("now");

						// Check that user date is between start & end
						switch( sanitize_title( $cond['compair'] ) ) {
							case 'includes': 
								return ( ( $now >= $start_date ) && ( $now <= $end_date ) );
							break;
							case 'excludes':
							default:
								return ( ( $now <= $start_date ) || ( $now >= $end_date ) );
						}
						return false;
						
					}

				} elseif( $cond_type == 'times' ) {
					$cond['compair'] = in_array($cond['compair'], array('before', 'after')) ? $cond['compair'] : 'before';

					$time_current = time();
					$time_conditional = strtotime( sanitize_text_field( $cond['cvalue'] ) );
					

					switch( sanitize_title( $cond['compair'] ) ) {
						case 'after':
							if( $time_current > $time_conditional )
								return true;
							break;
						case 'before':
						default:
							if( $time_current < $time_conditional )
								return true;
							break;
					}

					return false;

				} elseif( $cond_type == 'dayweek' ) {
					$daysArray = array_map(function($value){
						return ($value['name']);
					}, $cond['cvalue']);

					
					$cond['compair'] = in_array($cond['compair'], array('excludes', 'includes')) ? $cond['compair'] : 'includes';

					if( ( $cond['compair'] === 'excludes' && ! in_array(strtolower(date('l')), $daysArray) ) ||
					( $cond['compair'] === 'includes' && in_array(strtolower(date('l')), $daysArray) )
					){
						return true;
					}


				} elseif( $cond_type == 'coupon' ) {
					$coupon = $this->acotrs_has_coupon_discount($cond['cvalue']);
					$cond['compair'] = in_array($cond['compair'], array('excludes', 'includes')) ? $cond['compair'] : 'includes';
					if( ( $cond['compair'] === 'excludes' && ! $coupon ) ||
						( $cond['compair'] === 'includes' && $coupon ) ){
							return true;
						}

					return false;

				} else {

					return apply_filters( 'acotrs_determine_conditional_result', false, $cond, $cart_data );
				}

			}

			return false;
		}

		


		/**
		*@param: $cvalue = array()
		* return true/false
		*/
		public function acotrs_has_coupon_discount($cvalue){
			foreach($cvalue as $singleV){
				if(WC()->cart->has_discount(strtolower($singleV['title']))){
					return true;
					continue;
				}
			}
			return false;
		}

        /**
		 * calculate order totals (Per Order).
		 *
		 * @access public
		 * @param array $package (default: array())
		 * @return array
		 */
		public function acotrs_calculate_totals_order( $items ) {
			// setup initialized variables
			$subtotal = $quantity = $weight = $height = $width = $length = $area = $volume = 0;
			$products = $shipping_classes = $categories = $taxonomys = array();

			// cycle through cart items
			foreach( $items as $item_ar ) {
				// only count the ones that apply to shipping
				if( isset( $item_ar['data'] ) && $item_ar['data']->needs_shipping() ) {
					$item = $item_ar['data'];

					// manage measurement calculations
					$t_height = (float) $item->get_height();
					$t_width = (float) $item->get_width();
					$t_length = (float) $item->get_length();

					$height += $t_height * $item_ar['quantity'];
					$width += $t_width * $item_ar['quantity'];
					$length += $t_length * $item_ar['quantity'];
					$area += $t_height * $t_width * $item_ar['quantity'];
					$volume += $t_height * $t_width * $t_length * $item_ar['quantity'];

					// adjust number data
					$subtotal += $this->acotrs_line_item_price_get( $item_ar );
					$quantity += $item_ar['quantity'];
					$weight += $this->acotrs_get_line_item_weight( $item ) * $item_ar['quantity'];

					// add additional product information
					if( $item->get_type() == 'variation' ){
						$parent_id = ( version_compare( WC_VERSION, '3.0', ">=" ) ) ? $item->get_parent_id(): $item->parent->id;
					}

					$products[] = ( $item->get_type() == 'variation' ) ? $parent_id : $item->get_id();
					$shipping_classes[] = apply_filters( 'acotrs_settings_shipping_class', $item->get_shipping_class_id() );
					$get_categories = ( $item->get_type() == 'variation' ) ? get_the_terms( $parent_id, 'product_cat' ) : get_the_terms( $item->get_id(), 'product_cat' );
					if( $get_categories ) {
						foreach( $get_categories as $cat ){
                           array_push($categories, $cat->term_id);
						}
					}


					// Taxonomys
					$taxonomyLists = $this->acotrs_product_taxonomy_lists();
					unset( $taxonomyLists[array_search('product_shipping_class', $taxonomyLists)]);
					$taxonomyLists = array_values($taxonomyLists);

					foreach($taxonomyLists as $taxny){
						$taxonomyArray = ( $item->get_type() == 'variation' ) ? get_the_terms( $parent_id, $taxny ) : get_the_terms( $item->get_id(), $taxny );
						if($taxonomyArray){
							$taxonomys[$taxny] = !isset($taxonomys[$taxny]) ? array() : $taxonomys[$taxny];
							foreach($taxonomyArray as $taxoArray){
								array_push($taxonomys[$taxny], $taxoArray->term_id); 
							}
						}
						
					}
				}
			}

			$shipping_classes = array_values(array_unique( $shipping_classes ));
			$categories = array_values(array_unique( $categories ));

			// $taxonomys = array_map(function($value), array_unique(array_map("serialize", $taxonomys)));
			$taxonomys = array_map(function ($value) {
				return array_values(array_unique($value));
			}, $taxonomys );

			$weight =  $this->acotrs_method->ad_round_weight === 'yes'  ? ceil( $weight ) : $weight;

			// setup outgoing data for return
			$data = array(
				'subtotal' 			=> $subtotal,
				'quantity' 			=> $quantity,
				'weight' 			=> $weight,
				'height' 			=> $height,
				'width' 			=> $width,
				'length' 			=> $length,
				'area' 				=> $area,
				'volume' 			=> $volume,
				'products' 			=> $products,
				'shipping_classes' 	=> $shipping_classes,
				'categories' 		=> $categories,
			);
			
			$data = array_merge($data, $taxonomys);
			
			return $data;

		} // acotrs_calculate_totals_order
		


		/**
		 * Combine numeric values of array.
		 *
		 * @access public
		 * @param array $array_1, array $array_2
		 * @return array
		 */
		private function array_add( $array_1, $array_2 ) {
			if( is_array( $array_1 ) && is_array( $array_2 ) ) {
				foreach( $array_1 as $key => $value ) {
					if( isset( $array_2[ $key ] ) && is_numeric( $array_1[ $key ] ) && is_numeric( $array_2[ $key ] ) ) {
						$array_1[ $key ] += $array_2[ $key ];
					}
				}
			}

			return $array_1;
		}


		/**
		 * calculate order totals (Per Item and Per Line Item).
		 *
		 * @access public
		 * @param array $package (default: array())
		 * @return array
		 */
		public function acotrs_calculate_total_item( $items, $per_line = false ) {
			// setup initialized variables
			$data = array();

			// cycle through cart items
			foreach( $items as $item_ar ) {

				// only count the ones that apply to shipping
				if( isset( $item_ar['data'] ) && $item_ar['data']->needs_shipping() ) {
					$item = $item_ar['data'];
					$categories = array();

					// add additional product information
					if( $item->get_type() == 'variation' )
						$parent_id = ( version_compare( WC_VERSION, '3.0', ">=" ) ) ? $item->get_parent_id(): $item->parent->id;

					// manage measurement calculations
					$t_height = (float) $item->get_height();
					$t_width = (float) $item->get_width();
					$t_length = (float) $item->get_length();

					// add additional product information
					$get_categories = ( $item->get_type() == 'variation' ) ? get_the_terms( $parent_id, 'product_cat' ) : get_the_terms( $item->get_id(), 'product_cat' );
					if( $get_categories ) {
						foreach( $get_categories as $cat ){
						   $categories[] = $cat->term_id;
						}
					}

					// get proper product ID
					$product_id = $item->get_id();
					if( $item->get_type() == 'variation' ) {
						$product_id = ( version_compare( WC_VERSION, '3.0', ">=" ) ) ? $item->get_parent_id(): $item->parent->id;
					}

					// adjust numbers if Per Line Item is the selected condition
					if( $per_line === true ) {
						$weight = $this->acotrs_get_line_item_weight( $item ) * $item_ar['quantity'];
						$weight = ( $this->acotrs_method->ad_round_weight === '1' ) ? ceil( $weight ) : $weight;

						// setup outgoing data for return
						$temp_ar = array(
							'subtotal' 			=> $this->acotrs_line_item_price_get( $item_ar ),
							'quantity' 			=> $item_ar['quantity'],
							'weight' 			=> $weight,
							'height' 			=> $t_height * $item_ar['quantity'],
							'width' 			=> $t_width * $item_ar['quantity'],
							'length' 			=> $t_length * $item_ar['quantity'],
							'area' 				=> $t_height * $t_width * $item_ar['quantity'],
							'volume' 			=> $t_height * $t_width * $t_length * $item_ar['quantity'],
							'products' 			=> array( $product_id ),
							'shipping_classes' 	=> apply_filters( 'acotrs_settings_shipping_class', array( $item->get_shipping_class_id() ) ),
							'categories' 		=> $categories,
							);

						if( isset( $data[ $item->get_id() ] ) ) {
							$data[ $item->get_id() ] += $temp_ar;
						} else {
							$data[ $item->get_id() ] = $temp_ar;
						}
					} else {
						$weight = $this->acotrs_get_line_item_weight( $item );
						$weight = ( $this->acotrs_method->ad_round_weight === '1' ) ? ceil( $weight ) : $weight;

						// setup outgoing data for return
						$temp_ar = array(
							'subtotal' 			=> $this->acotrs_line_item_price_get( $item_ar ) / $item_ar['quantity'],
							'quantity' 			=> $item_ar['quantity'],
							'weight' 			=> $weight,
							'height' 			=> $t_height,
							'width' 			=> $t_width,
							'length' 			=> $t_length,
							'area' 				=> $t_height * $t_width,
							'volume' 			=> $t_height * $t_width * $t_length,
							'products' 			=> array( $product_id ),
							'shipping_classes' 	=> apply_filters( 'acotrs_settings_shipping_class', array( $item->get_shipping_class_id() ) ),
							'categories' 		=> $categories,
							);

						if( isset( $data[ $item->get_id() ] ) ) {
							$data[ $item->get_id() ] = $this->array_add( $data[ $item->get_id() ], $temp_ar );
						} else {
							$data[ $item->get_id() ] = $temp_ar;
						}
					}
				}
			}

			return $data;
		}





		/**
		 * determine item price based on TRS tax and coupon settings.
		 *
		 * @access public
		 * @param array $item
		 * @return float
		 */
		function acotrs_line_item_price_get( $item ) {
			$return_cost = 0;
			if( (int)$this->acotrs_method->ad_include_coupons === 1 ) {
				if( (int)$this->acotrs_method->ad_includingtax === 1 ) {
					$return_cost = $item['line_total'] + $item['line_tax'];
				} else {
					$return_cost = $item['line_total'];
				}
			} else {
				if( (int)$this->acotrs_method->ad_includingtax === 1 ) {
					$return_cost = $item['line_subtotal'] + $item['line_subtotal_tax'];
				} else {
					$return_cost = $item['line_subtotal'];
				}
			}

			return wc_format_decimal( $return_cost, wc_get_price_decimals() );
		}




		/**
		 * determine item weight based on TRS volumetric settings.
		 *
		 * @access public
		 * @param array $item
		 * @return float
		 */
		private function acotrs_get_line_item_weight( $item ) {

			if( isset( $this->acotrs_method->volume ) && is_numeric( $this->acotrs_method->volume ) && $this->acotrs_method->volume > 0 ) {

				// manage measurement calculations
				$height = ( $item->get_height() === '' ) ? 1 : $item->get_height();
				$width = ( $item->get_width() === '' ) ? 1 : $item->get_width();
				$length = ( $item->get_length() === '' ) ? 1 : $item->get_length();

				$volume = $height * $width * $length;


				switch($this->acotrs_method->operand){
					case "*":
						$volumetric = $volume * $this->acotrs_method->volume;
					break;
					default:
						$volumetric = $volume / $this->acotrs_method->volume;
				}
				

				if( $volumetric > $item->get_weight() )
					return $volumetric;

			}

			return (float) $item->get_weight();
		}




		/**
		 * calculate order totals.
		 *
		 * @access public
		 * @param array $package (default: array())
		 * @return array
		 */
		private function acotrs_cartdata_basedon_class( $items = array() ) {
			// setup empty return value
			$data = array();
			// cycle through cart items
			foreach( $items as $item_ar ) {

				// only count the ones that apply to shipping
				if( isset( $item_ar['data'] ) && $item_ar['data']->needs_shipping() ) {
					// initialize necessary variables
					$item = $item_ar['data'];
					$shipping_class_id = apply_filters( 'acotrs_settings_shipping_class', $item->get_shipping_class_id() );

					// add additional product information
					if( $item->get_type() == 'variation' )
						$parent_id = ( version_compare( WC_VERSION, '3.0', ">=" ) ) ? $item->get_parent_id(): $item->parent->id;

					if( ! isset( $data[ $shipping_class_id ] ) ) {

						$data[ $shipping_class_id ] = array(
							'subtotal' 			=> 0,
							'quantity' 			=> 0,
							'weight' 			=> 0,
							'height' 			=> 0,
							'width' 			=> 0,
							'length' 			=> 0,
							'area' 				=> 0,
							'volume' 			=> 0,
							'products' 			=> array(),
							'shipping_classes' 	=> array( $shipping_class_id ),
							'categories' 		=> array(),
							);
					}

					// manage measurement calculations
					$t_height = (float) $item->get_height();
					$t_width = (float) $item->get_width();
					$t_length = (float) $item->get_length();

					// add additional product information
					$get_categories = ( $item->get_type() == 'variation' ) ? get_the_terms( $parent_id, 'product_cat' ) : get_the_terms( $item->get_id(), 'product_cat' );
					if( $get_categories ) {
						foreach( $get_categories as $cat ){
						   $data[ $shipping_class_id ]['categories'][] = $cat->term_id;
						}
					}

					// calculate product weight based on settings
					$weight = $this->acotrs_get_line_item_weight( $item ) * $item_ar['quantity'];
					$weight = ( $this->acotrs_method->ad_round_weight === '1' ) ? ceil( $weight ) : $weight;

					// retrieve correct product ID
					$product_id = $item->get_id();
					if( $item->get_type() == 'variation' )
						$product_id = ( version_compare( WC_VERSION, '3.0', ">=" ) ) ? $item->get_parent_id(): $item->parent->id;

					// setup outgoing data for return
					$data[ $shipping_class_id ][ 'subtotal' ]		+= $this->acotrs_line_item_price_get( $item_ar );
					$data[ $shipping_class_id ][ 'quantity' ]		+= $item_ar['quantity'];
					$data[ $shipping_class_id ][ 'weight' ]			+= $weight;
					$data[ $shipping_class_id ][ 'height' ]			+= $t_height * $item_ar['quantity'];
					$data[ $shipping_class_id ][ 'width' ]			+= $t_width * $item_ar['quantity'];
					$data[ $shipping_class_id ][ 'length' ]			+= $t_length * $item_ar['quantity'];
					$data[ $shipping_class_id ][ 'area' ]			+= $t_height * $t_width * $item_ar['quantity'];
					$data[ $shipping_class_id ][ 'volume' ]			+= $t_height * $t_width * $t_length * $item_ar['quantity'];
					$data[ $shipping_class_id ][ 'products' ][]		= $product_id;

				}

			}

			// clear out unnecessary data
			foreach( $data as $key => $value ) {
				$data[ $key ]['categories'] = array_unique( $value['categories'] );
			}

			

			return $data;
		}


		/**
		 * Acotrs Order Delivery Date
		 * @param string / int (day number)
		 */
		public function acotrs_order_delivery_date($afterDay){
			
				if( $this->acotrs_method->ad_weekends && count($this->acotrs_method->ad_weekends) > 0){
					$dayname        = date('l', strtotime(date('Y-m-d') . ' + '.$afterDay.' days'));
					$targetDate     = date('Y-m-d', strtotime(date('Y-m-d') . ' + '.$afterDay.' days'));
					$exist_holiday  = array_search(strtolower($dayname), array_column( $this->acotrs_method->ad_weekends, 'name' ));
					

					
					if($exist_holiday === false ) {
						$delivery_date = date('F\ d, Y', strtotime($targetDate));
						return $delivery_date;
					}

					for($i=1; empty($delivery_date); $i++){
						$temDayName  = date('l', strtotime($targetDate . ' +'.$i.'days'));  
						if(array_search(strtolower($temDayName), array_column( $this->acotrs_method->ad_weekends, 'name' )) === false){
							$delivery_date = date('F\ d, Y', strtotime($targetDate . ' + '.$i.'days')); 
						}
						if($i > 8) $delivery_date = __('There have no fixed deliver date. Please contact to seller.', 'advanced-table-rate-shipping-for-woocommerce');
					}
					
				}else{
					$delivery_date  = date('F\ d, Y', strtotime(date('Y-m-d') . ' + '.$afterDay.' days'));
				}
				return $delivery_date;
		}


        

    }
} // check WooCommerce