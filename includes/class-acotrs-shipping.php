<?php  
/*
* Acotrs Settings Info class
*/

class ACOTRS_Shipping extends WC_Shipping_Method{
	
    const METHOD_ID = 'acotrs_shipping';

	const WOOCOMMERCE_PAGE_WC_SETTINGS = 'wc-settings';

    const WOOCOMMERCE_SETTINGS_SHIPPING_URL = 'admin.php?page=wc-settings&tab=shipping';
    
    /**
	 * ACOTRS_Settingsinfo constructor.
	 *
	 * @param int $acotrs_instance_id Instance id.
	 */
	public function __construct(
		$acotrs_instance_id = 0
	) {
		parent::__construct( $acotrs_instance_id );
		$this->id           			= self::METHOD_ID;
		$this->enabled      			= 'no';
		$this->acotrs_instance_id 		= absint( $acotrs_instance_id );
		$this->method_title 			= __('Advanced table rate shipping', 'advanced-table-rate-shipping-for-woocommerce');
		$this->config 					= array();
		$this->zone_id 					= WC_Shipping_Zones::get_zone_by('instance_id', $this->acotrs_instance_id)->get_id();

		$this->method_description 		= __( 'Table rate shipping allows you to set numerous rates based on location and specified conditions. Click the headlines on left to expand or hide additional settings.', 'advanced-table-rate-shipping-for-woocommerce' );
		$this->table_rate_options 		= 'woocommerce_table_rates';
		

		$this->class_priorities_options = 'woocommerce_class_priorities';
		$this->handling_rates_options 	= 'woocommerce_handling_rates';
		$this->title_order_options 		= 'woocommerce_trshipping_title_orders';
		$this->option_name 				= $this->id . '_'.$this->acotrs_instance_id.'_settings';
		$this->default 					= "";

		$this->handling = 0;


		$this->supports = array(
			'shipping-zones',
			'instance-settings'
		);

		
		$this->init();

		add_filter( 'acotrs_calculated_table_rate_otheroptions', array($this, 'acotrs_ControlOtherOptions'), 100, 1 );
		add_filter( 'woocommerce_package_rates', array( $this, 'acotrs_hide_shipping_if_free_method_is_available' ), 100 );
		add_filter( 'acotrs_calculated_table_rate_return_array', array( $this, 'acotrs_hide_other_options' ), 100, 1 );
		add_filter( 'woocommerce_shipping_chosen_method', array( $this, 'acotrs_select_default_rate' ), 10, 2 );
		add_filter('acotrs_custom_restriction', array($this, 'acotrs_custom_restriction_callback'), 10, 3);
		
	}




	public function acotrs_custom_restriction_callback($result_array, $package, $method){
		
		if( count( $method->method_condtion ) <= 0 ) return $result_array;

		$method_condtions = $method->method_condtion;
		// Get calculate class
		$calculateCls = new ACOTRS_ShippingCalculation( $method );


		// calculate order statistics
		$results = array();
		$cart_data = array( 'per-order' => $calculateCls->acotrs_calculate_totals_order( $package['contents'] ) );
		foreach( $method_condtions as $cond ) {			
			$results[$cond['condition']] = $calculateCls->acotrs_determine_conditional_result( $cond, $cart_data['per-order'] );
		}
		

		if( in_array( true, $results ) && !in_array( false, $results ) ){
			$result_array[] = true;
		}else{
			$result_array[] = false;
		}
		return $result_array;
	}
	


	/**
	 * alter the default rate if one is chosen in settings.
	 * @access public
	 * @param array $_available_methods
	 * @return bool $chosen_method
	*/
	public function acotrs_select_default_rate($chosen_method, $_available_methods){
		if( array_key_exists( $this->default, $_available_methods ) ) {
			return $this->default;
		}
		return $chosen_method;
	}


	/**
	 * Hide Other shipping option from Acotrs option if hide other option is selected
	 * 
	 * @access public
	 * @param array $rates
	 * @return array
	*/
	public function acotrs_hide_other_options($rates){
		if($rates && count($rates) > 0){
			$array_keys = array_keys($rates); 
			$array_colmn = array_column($rates, 'hide_ops'); 
			
			if(count($array_keys) == count($array_colmn)){
				$array_combine = array_combine( $array_keys, $array_colmn );
				$hideother = array_keys($array_combine, 1);
				if($hideother && count($hideother) > 0){
					$newRates = array();
					foreach($hideother as $srates){
						$newRates[$srates] = $rates[$srates];
					}
					$rates = $newRates;
				}
			}
		}
		return $rates;
	}


	/**
		* Hide shipping rates when one has option enabled.
		*
	    * @access public
		* @param array $rates Array of rates found for the package.
		* @return array
	*/
	public function acotrs_ControlOtherOptions($rates){
		$hide_key = false;

		// return if no rates have been added
		if( ! isset( $rates ) || empty( $rates ) )
			return $rates;

		// cycle through available rates
		foreach( $rates as $key => $rate ) {
			if( $rate['hide_ops'] === 'on' ) {
				$hide_key = $key;
			}
		}

		if( $hide_key ) {
			return array( $hide_key => $rates[ $hide_key ] );
		}

		return $rates;
	}

	/*
	* Get Shipping Config
	* @return method configration
	*/
	public function acotrs_GetSippingConfig(){
		$this->config = apply_filters( 'acotrs_tablerates_config', get_option( $this->option_name, array() ) );
	}


	/**
		* init function.
		* initialize variables to be used
		*
		* @access public
		* @return void
	*/
	public function init() {
			//if($this->id == 'acotrs_shipping') $GLOBALS['hide_save_button'] = true;
			
			// Get all configaration
			$this->acotrs_GetSippingConfig();

			//General Settings
			$this->title 								= isset($this->config['general']) && isset($this->config['general']['title']) ? $this->config['general']['title'] : $this->method_title;
			$this->type 								= isset($this->config['general']) && isset($this->config['general']['type']) ? $this->config['general']['type'] : '';
			$this->tax_status 							= isset($this->config['general']) && isset($this->config['general']['tax_status']) && $this->config['general']['tax_status'] != '' ? $this->config['general']['tax_status'] : 'taxable';
			$this->base_rule_condition 					= isset($this->config['general']) && !empty($this->config['general']['base_rule']) ? $this->config['general']['base_rule'] : 'per_order';
			$this->handlingfree 						= isset($this->config['general']) && isset($this->config['general']['handlingfree']) ? $this->config['general']['handlingfree'] : '';
			$this->flatrate 							= isset($this->config['general']) && isset($this->config['general']['flatrate']) ? $this->config['general']['flatrate'] : '';
			$this->shipping_option_appear_for 			= isset($this->config['general']) && isset($this->config['general']['shipping_option_appear_for']) ? $this->config['general']['shipping_option_appear_for'] : '';
			$this->ship_to_role 						= isset($this->config['general']) && isset($this->config['general']['ship_to_role']) ? $this->config['general']['ship_to_role'] : '';
			
			// Shipping by City
			$this->city_enable 							= isset($this->config['shipping_by_city']) && isset($this->config['shipping_by_city']['enable']) ? $this->config['shipping_by_city']['enable'] : 0;
			$this->desable_other 						= isset($this->config['shipping_by_city']) && isset($this->config['shipping_by_city']['desable_other']) ? $this->config['shipping_by_city']['desable_other'] : '';
			$this->region_is 							= isset($this->config['shipping_by_city']) && isset($this->config['shipping_by_city']['region_is']) ? $this->config['shipping_by_city']['region_is'] : '';
			$this->allowed_city 						= isset($this->config['shipping_by_city']) && isset($this->config['shipping_by_city']['allowed_city']) ? $this->config['shipping_by_city']['allowed_city'] : array();

			// Method
			$this->volume 								= isset($this->config['method']) && isset($this->config['method']['volume']) ? $this->config['method']['volume'] : '';
			$this->operand 								= isset($this->config['method']) && isset($this->config['method']['operand']) ? $this->config['method']['operand'] : '/';
			$this->exclude_weight 						= isset($this->config['method']) && isset($this->config['method']['exclude_weight']) ? $this->config['method']['exclude_weight'] : '';
			$this->method_condtion 						= isset($this->config['method']) && isset($this->config['method']['method_condtion']) ? $this->config['method']['method_condtion'] : array();

			// Additional Settings
			$this->ad_includingtax 						= isset($this->config['additional_settings']['ad_includingtax']) ? $this->config['additional_settings']['ad_includingtax'] : '';
			$this->ad_exclude_weight 					= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_exclude_weight']) ? $this->config['additional_settings']['ad_exclude_weight'] : '';
			$this->ad_include_coupons 					= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_include_coupons']) ? $this->config['additional_settings']['ad_include_coupons'] : '';
			$this->ad_round_weight 						= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_round_weight']) ? $this->config['additional_settings']['ad_round_weight'] : '';
			$this->ad_hide_this_method 					= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_hide_this_method']) ? $this->config['additional_settings']['ad_hide_this_method'] : '';
			$this->ad_hide_other_method 				= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_hide_other_method']) ? $this->config['additional_settings']['ad_hide_other_method'] : '';
			$this->ad_set_delivery_date_automatically 	= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_set_delivery_date_automatically']) ? $this->config['additional_settings']['ad_set_delivery_date_automatically'] : '';
			$this->ad_set_delivery_date 				= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_set_delivery_date']) ? $this->config['additional_settings']['ad_set_delivery_date'] : 0;
			$this->ad_get_minimum_value_from_condition 	= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_get_minimum_value_from_condition']) ? $this->config['additional_settings']['ad_get_minimum_value_from_condition'] : '';
			$this->ad_weekend_days 						= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_weekend_days']) ? $this->config['additional_settings']['ad_weekend_days'] : false;
			$this->ad_weekends 							= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_weekends']) ? $this->config['additional_settings']['ad_weekends'] : array();
			$this->ad_free_shipping_on_special_day 		= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_free_shipping_on_special_day']) ? $this->config['additional_settings']['ad_free_shipping_on_special_day'] : 0;
			$this->ad_special_days 						= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_special_days']) ? $this->config['additional_settings']['ad_special_days'] : 0;
			$this->ad_special_day_persent				= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_special_day_persent']) ? $this->config['additional_settings']['ad_special_day_persent'] : 0;
			$this->ad_single_class 						= isset($this->config['additional_settings']) && isset($this->config['additional_settings']['ad_single_class_only']) ? $this->config['additional_settings']['ad_single_class_only'] : 'disabled';



			// Table of Rates 
			$this->table_of_rates 						= isset($this->config['table_of_rates']) ? $this->config['table_of_rates'] : array();
	}



		/**
	     * check if current user qualifies for this method.
	     *
	     * @access public
	     * @param null
	     * @return bool
	     */
	    private function acotrs_user_restrictions_qualified() {
			switch( $this->shipping_option_appear_for ) {
				case 'specific':
					if( empty( $this->ship_to_role ) || count($this->ship_to_role) < 1 ) return false;
					$ship_to_role = wp_list_pluck( $this->ship_to_role, 'role' );

					// retrieve user's roles if logged in
					if( is_user_logged_in() ):
						$currentUserData = get_userdata( get_current_user_id(  ) );
						$currentUserData = $currentUserData->roles;
					else:
						$currentUserData = array( "guest" );
					endif;

					$array_dif = array_intersect($currentUserData, $ship_to_role);

					// Check if user's role is accepted or not
					if(count($array_dif) > 0) return true;
							
				break;

				case 'everyone':
					return true;
				break;
				default:
					return (bool) apply_filters( 'acotrs_user_restraction_conditions', true, $this->shipping_option_appear_for );

			}
			return false;
		}






	/**
	* calculate_shipping function.
		*
		* @access public
		* @param array $package (default: array())
		* @return void
	*/
	public function calculate_shipping( $package = array() ) {
		// don't calculate if user permissions are not set and not qualified
		if( ! $this->acotrs_user_restrictions_qualified() ) return;

		// check for any other external requirement by any other function using filter hook
		$restrictions = apply_filters( 'acotrs_custom_restriction', array( true ), $package, $this );

	
		if( ! in_array( true, (array) $restrictions ) || in_array( false, (array) $restrictions ) ) return;

		// Setup necessary class for calculation acotrs method
		$calculationCls = new ACOTRS_ShippingCalculation( $this );

		// get qualified shipping rates
		$rates = $calculationCls->acotrs_calculate_shipping( $package );
		$rates = apply_filters( 'acotrs_calculated_table_rate_return_array', $rates );


		$keyArray = array();
		// send shipping rates to WooCommerce
		if( is_array( $rates ) && count( $rates ) > 0 ) {
			// cycle through rates to send and alter post-add settings
			foreach( $rates as $key => $rate ) {
				$keyArray[] = $rate['id'];
				$this->add_rate( 
					array(
					'id' 		=>	$rate['id'],
					'label'     => 	apply_filters( 'acotrs_shipping_rate_label', $rate['label'], $key, $this->acotrs_instance_id ),
					'cost'      => 	$rate['cost'],
					'meta_data' => 	array(
							'handling_fee' => $this->handlingfree, 
							'delivery_day' => isset($rate['delivery_day']) && (int)$rate['delivery_day'] > 0 ? $calculationCls->acotrs_order_delivery_date($rate['delivery_day']) : '',
							'description' => isset($rate['description']) ? (object) apply_filters( 'acotrs_shipping_rate_description', $rate['description'], $key, $this->acotrs_instance_id, $rate['row_id'], $rate['contents_cost'] ) : '', 
							'tax_status' => $this->tax_status
					),
					'package'   => 	$package,
					)
				);

				if( isset($rate['default']) && (int)$rate['default'] == 1 ) $this->default = $rate['id'];

			}
			$this->key = $keyArray;

		}

	}
       
  

	/**
	* Admin Panel Options
	*
	* @access public
	* @return void
	*/
	public function admin_options() {
			global $woocommerce;
            $this->reactComponent();
    }
        


	/*
	* React Component
	*/
	public function reactComponent(){
		
		wp_redirect( admin_url('admin.php?page=acotrs#/'.$this->acotrs_instance_id.'/general') );
		exit;
	}


	/**
		* Hide shipping rates when free shipping is available.
		* Updated to support WooCommerce 2.6 Shipping Zones.
		*
	    * @access public
		* @param array $rates Array of rates found for the package.
		* @return array
	*/
	public function acotrs_hide_shipping_if_free_method_is_available( $rates ) {
			global $woocommerce;
			
			// determine if free shipping is available
			$free_shipping = false;
			if(!$this->desable_other && $this->ad_hide_this_method && count($rates) > 0){
				foreach ( $rates as $rate_id => $rate ) {
					if ( 'free_shipping' === $rate->method_id ) {
						$free_shipping = true;
						break;
					}
				}
			}
			
			// if available, remove all options from this method
			if( $free_shipping ) {
				foreach ( $rates as $rate_id => $rate ) {
					if ( $this->id === $rate->method_id && strpos( $rate_id, $this->id . ':' . $this->acotrs_instance_id . '-') !== false ) {
						unset( $rates[ $rate_id ] );
					}
				}
			}


			// Hide other method if Desable other method is active based on selected city
			if($this->desable_other){
				$citys = explode("\n", str_replace("\r", "", $this->allowed_city));
				
				foreach ( $rates as $rate_id => $rate ) {
					if ( strpos( $rate_id, $this->id . ':' . $this->acotrs_instance_id) === false && in_array($woocommerce->customer->get_shipping_city(), $citys) ) {
						unset( $rates[ $rate_id ] );
					}
					
				}
			}


			// Desable Other Method if ad_hide_other_method from additional tab
			if($this->ad_hide_other_method){
				foreach ( $rates as $rate_id => $rate ) {
					if ( $this->id !== $rate->method_id && strpos( $rate_id, $this->id . ':' . $this->acotrs_instance_id . '-') !== true ) {
						unset( $rates[ $rate_id ] );
					}
				}
			}
			return $rates;
		}


} // End Class 