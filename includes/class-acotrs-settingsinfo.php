<?php  
/*
* Acotrs Settings Info class
*/

class ACOTRS_Settingsinfo extends WC_Shipping_Method{

	/**
	 * Plugin root path
	*/
	protected $plugin_path;


	/**
	 * Plugin Asset Directory
	*/
	protected $plugin_asset;
	

    const METHOD_ID = 'acotrs_shipping_info';

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
		$this->id           = self::METHOD_ID;
		$this->enabled      = 'no';
		$this->method_title = ACOTRS_PLUGIN_NAME;
		$this->plugin_path 	= plugin_dir_path(ACOTRS_FILE);
		$this->plugin_asset = plugin_dir_url(ACOTRS_FILE); 

		$this->supports = array(
			'settings',
		);

	}



	/**
	* Admin Panel Options
	*
	* @access public
	* @return void
	*/
	public function admin_options() {
		global $woocommerce;
		// $this->reactComponent();
		require_once($this->plugin_path . 'part/acotrs-settingsinfo.php');
		
	}

}