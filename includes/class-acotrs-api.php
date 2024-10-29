<?php

if (!defined('ABSPATH')) {
    exit;
}

class ACOTRS_Api
{


    /**
     * @var    object
     * @access  private
     * @since    1.0.0
     */
    private static $instance = null;

    /**
     * The version number.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $version;
    /**
     * The token.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $token;

    /**
     * Wp dB
     * @var     string
     * @access  private
     *
     */
    private $wpdb;

    /**
     * Item ID for remote api request to acoweb server for API Key
     */
    public $item_id;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->token = ACOTRS_TOKEN;
        $this->item_id = '';


        add_action(
            'rest_api_init',
            function () {

                //Change shipping status from custom page
                register_rest_route(
                    $this->token . '/v1',
                    '/change_shipping_option_status/',
                    array(
                        'methods' => 'POST',
                        'callback' => array($this, 'acotrs_change_shipping_status_callback'),
                        'permission_callback' => array($this, 'getPermission'),
                    )
                );

                //Delete shipping option from custom page
                register_rest_route(
                    $this->token . '/v1',
                    '/delete_shipping_option/',
                    array(
                        'methods' => 'POST',
                        'callback' => array($this, 'acotrs_delete_shipping_callback'),
                        'permission_callback' => array($this, 'getPermission'),
                    )
                );



                //Delete shipping multiple option
                register_rest_route(
                    $this->token . '/v1',
                    '/delete_methods/',
                    array(
                        'methods' => 'POST',
                        'callback' => array($this, 'acotrs_delete_multiple_shipping_callback'),
                        'permission_callback' => array($this, 'getPermission'),
                    )
                );





                // Licenced Info
                register_rest_route(
                    $this->token . '/v1',
                    '/initial_config/',
                    array(
                        'methods' => 'GET',
                        'callback' => array($this, 'acotrs_get_initial_config'),
                        'permission_callback' => array($this, 'getPermission'),
                    )
                );


                register_rest_route(
                    $this->token . '/v1',
                    '/add_new_method/',
                    array(
                        'methods' => 'POST',
                        'callback' => array($this, 'acotrs_add_new_method'),
                        'permission_callback' => array($this, 'getPermission'),
                    )
                );

                register_rest_route(
                    $this->token . '/v1',
                    '/config/',
                    array(
                        'methods' => 'POST',
                        'callback' => array($this, 'getConfig'),
                        'permission_callback' => array($this, 'getPermission'),
                    )
                );



                // List of zones
                register_rest_route(
                    $this->token . '/v1',
                    '/listsof_zones_and_zone_id/',
                    array(
                        'methods' => 'POST',
                        'callback' => array($this, 'acotrs_list_of_zones'),
                        'permission_callback' => array($this, 'getPermission'),
                    )
                );


                // Get All roles
                register_rest_route(
                    $this->token . '/v1',
                    '/user-roles/',
                    array(
                        'methods' => 'GET',
                        'callback' => array($this, 'wpGetUserRoles'),
                        'permission_callback' => array($this, 'getPermission'),
                    )
                );


                // UPdate Acotrs table rate shipping data
                register_rest_route(
                    $this->token . '/v1',
                    '/updatedata/',
                    array(
                        'methods' => 'POST',
                        'callback' => array($this, 'acotrs_UpdateTableRates'),
                        'permission_callback' => array($this, 'getPermission'),
                    )
                );

                // Set Licence Key
                register_rest_route(
                    $this->token . '/v1',
                    '/update_licence_key/',
                    array(
                        'methods' => 'POST',
                        'callback' => array($this, 'acotrs_UpdateLicenceKey'),
                        'permission_callback' => array($this, 'getPermission'),
                    )
                );
            }
        );


        // add_action( 'wp_head', array($this, 'testF') );
    }


    public function testF(){
        $getoption = get_option( 'test_option' );
        echo 'test options <br/><pre>';
        print_r($getoption);
        echo '</pre>';
    }

    /**
     * @param   post/data
     * @return  success message
     */
    public function acotrs_delete_multiple_shipping_callback($data){
        $instance_ids = $data['instance_id'];
        $msg = 'error';
        foreach($instance_ids as $sid){
            $shipping_method = WC_Shipping_Zones::get_shipping_method( $sid );
            $option_key      = $shipping_method->get_instance_option_key();
            $option_key      = str_replace('woocommerce_', '', $option_key);
            if( $this->wpdb->delete( "{$this->wpdb->prefix}woocommerce_shipping_zone_methods", array( 'instance_id' => $sid ) ) ) {
                delete_option( $option_key );
                $msg = 'success';
            }
        }

        $return = array(
            'msg' => $msg
        );

        return new WP_REST_Response($return, 200);
    }




    /**
     * @param   post_array
     * @return  json data
     */
    public function acotrs_delete_shipping_callback($data){
        $instance_id = $data['instance_id'];
        $shipping_method = WC_Shipping_Zones::get_shipping_method( $instance_id );
        $option_key      = $shipping_method->get_instance_option_key();
        $option_key      = str_replace('woocommerce_', '', $option_key);
        $msg             = 'error';
        if ( $this->wpdb->delete( "{$this->wpdb->prefix}woocommerce_shipping_zone_methods", array( 'instance_id' => $instance_id ) ) ) {
            delete_option( $option_key );
            $msg = 'success';
        }

        $return = array(
            'msg' => $msg
        );

        return new WP_REST_Response($return, 200);
    }


    /**
     * @access  public
     * @return  message
     * @param   array
     */
    public function acotrs_change_shipping_status_callback($data){

        $is_enabled = absint( 'yes' === $data['enabled'] );
        $instance_id = $data['instance_id'];

        $msg = 'error';
		if ( $this->wpdb->update( "{$this->wpdb->prefix}woocommerce_shipping_zone_methods", array( 'is_enabled' => $is_enabled ), array( 'instance_id' => absint( $instance_id ) ) ) ) {
            $msg = 'success';
		}

        return new WP_REST_Response($msg, 200);
    }

    /**
     * @access  public
     * @return  option_name
     *
     */
    public function acotrs_get_optionName($shipping_id, $instance_id){
        $option_name    = $shipping_id . '_' . $instance_id . '_'. 'settings';
        return apply_filters( 'acotrs_option_name', $option_name, $instance_id );
    }

    /**
     * @param   postdata
     * @return  string
     * @desc    Add new mehtod id for acotrs
     */
    public function acotrs_add_new_method($data){

        $zone_id     = wc_clean( wp_unslash( $data['zone_id'] ) );
		$zone        = new WC_Shipping_Zone( $zone_id );
		$instance_id = $zone->add_shipping_method( wc_clean( wp_unslash( $data['method_id'] ) ) );

        $return = false;
        if($instance_id){
            $methods = $this->acotrs_zone_methods($data['zone_id']);
            $return = array(
                'msg' => 'success',
                'lists' => @$methods['methods'],
                'instance_id' => $instance_id
            );
        }

        return new WP_REST_Response($return, 200);
    }



    /**
     * @access private
     * @return zone methods by zone id
     */
    private function acotrs_zone_methods($method_id){
        $zone_ids = $this->wpdb->prepare( 'SELECT `zone_id` FROM '.$this->wpdb->prefix.'woocommerce_shipping_zone_methods WHERE `method_id`=%s GROUP BY `zone_id`', $method_id);
        $zone_ids = $this->wpdb->get_results($zone_ids, OBJECT);



        $return_methods = array();
        foreach($zone_ids as $k => $s):
            $zone    = new WC_Shipping_Zone( $s->zone_id );
            $methods = $zone->get_shipping_methods( false, 'json' );

            $methods = array_map(function($v) use($zone){
                if($v->id == 'acotrs_shipping'){
                    return array(
                        'method_title' => $v->method_title,
                        'enabled' => $v->enabled,
                        'zone_id' => $v->zone_id,
                        'zone_name' => $zone->get_zone_name(),
                        'title' => $v->title,
                        'instance_id' => $v->instance_id,
                        'no_of_options' => isset($v->config) && isset($v->config['table_of_rates']) ? count($v->config['table_of_rates']) : 0
                    );
                }else{
                    return false;
                }
            }, $methods);
            $methods = array_values(array_filter($methods));
            $return_methods = array_merge($return_methods, $methods);
        endforeach;


        return $return_methods;
    }



    /**
     * @access  public
     * @param   NULL
     * @return  array
    */
    public function acotrs_zone_lists(){
        $zones = WC_Shipping_Zones::get_zones();
        $zone_array = array(
            0 => __('Locations not covered by your other zones', 'advanced-table-rate-shipping-for-woocommerce')
        );
        foreach($zones as $szone){
            $zone_array[$szone['id']] = $szone['zone_name'];
        }
        return $zone_array;
    }



   /**
     * @access  public
     * @param   post_array
     * @return  list of zones
     */
    public function acotrs_list_of_zones($data){
        $zones   = $this->acotrs_zone_lists();
        $instance_id = $data['instance_id'];

        $zone_id = $this->wpdb->prepare( 'SELECT `zone_id` FROM '.$this->wpdb->prefix.'woocommerce_shipping_zone_methods WHERE `instance_id`=%d', $instance_id);
        $zone_id = $this->wpdb->get_row($zone_id, OBJECT);

        $returnArray = array(
            'zones' => $zones,
            'zone_id' => $zone_id->zone_id
        );
        return new WP_REST_Response($returnArray, 200);
    }





    /**
     * @param $_POST Data
     * Save licence key to DB
     */
    public function acotrs_UpdateLicenceKey($data){

        $licence_key = trim(sanitize_text_field($data['licence_key']));
        update_option('acotrs_activation_license_key', $licence_key);

         // data to send in our API request
         $api_params = array(
            'edd_action' => 'activate_license',
            'license' => $licence_key,
            'item_id' => $this->item_id, // The ID of the item in EDD
            'url' => home_url()
        );
        // Call the custom API.
        $response = wp_remote_post(ACOTRS_STORE_URL, array('timeout' => 15, 'sslverify' => false, 'body' => $api_params));


        // make sure the response came back okay
        $message = '';
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            if (is_wp_error($response)) {
                $temp = $response->get_error_message();
                if(!empty($temp)) {
                    $message = $response->get_error_message();
                } else {
                    $message = __('An error occurred, please try again.', 'advanced-table-rate-shipping-for-woocommerce');
                }
            }
        } else {
            $license_data = json_decode(wp_remote_retrieve_body($response));

            if (false === $license_data->success) {
                switch ($license_data->error) {
                    case 'expired' :
                        $message = sprintf(
                            __('Your license key expired on %s.', 'advanced-table-rate-shipping-for-woocommerce'), date_i18n(get_option('date_format'), strtotime($license_data->expires, current_time('timestamp')))
                        );
                        break;
                    case 'revoked' :
                        $message = __('Your license key has been disabled.', 'advanced-table-rate-shipping-for-woocommerce');
                        break;
                    case 'missing' :
                        $message = __('Invalid license.', 'advanced-table-rate-shipping-for-woocommerce');
                        break;
                    case 'invalid' :
                    case 'site_inactive' :
                        $message = __('Your license is not active for this URL.', 'advanced-table-rate-shipping-for-woocommerce');
                        break;
                    case 'item_name_mismatch' :
                        $message = sprintf(__('This appears to be an invalid license key for %s.', 'advanced-table-rate-shipping-for-woocommerce'), ACOTRS_PLUGIN_NAME);
                        break;
                    case 'no_activations_left':
                        $message = __('Your license key has reached its activation limit.', 'advanced-table-rate-shipping-for-woocommerce');
                        break;
                    default :
                        $message = __('An error occurred, please try again.', 'advanced-table-rate-shipping-for-woocommerce');
                        break;
                }
            }

            if(empty($message)){
                update_option('acotrs_activation_license_status', $license_data->license);
            }
        }



        $data = array(
            'licenced' => get_option( 'acotrs_activation_license_status', false) == 'valid' ? true : false,
            'msg' => $message,
            'response' => $response
        );


        return new WP_REST_Response($data, 200);
    }




    public function get_optionName($shipping_id, $instance_id){
        $option_name    = $shipping_id . '_' . $instance_id . '_'. 'settings';
        return apply_filters( 'acotrs_option_name', $option_name, $instance_id );
    }


    public function acotrs_UpdateTableRates($data){
        // Get all data from backend.js
        $shipping_id    = $data['shipping_id'];
        $instance_id    = $data['instance_id'];
        $option_name    = $this->get_optionName($shipping_id, $instance_id);
        $config         = $data['config'];


        $update = update_option( $option_name, $config, true );


        //update zone id if change
        $this->wpdb->update(
            "{$this->wpdb->prefix}woocommerce_shipping_zone_methods",
            array( 'zone_id' => $data['zone_id'] ),
            array('instance_id' => $instance_id),
            array('%d'),
            array('%d')
        );

        $msg = 'true';
        if(!$update) $msg = 'error';
        $data = array(
            'config' => $config,
            'msg' => $msg
        );
        return new WP_REST_Response($data, 200);
    }

    public function wpGetUserRoles(){
        global $wp_roles;
        $roles = $wp_roles->get_names();

        return new WP_REST_Response($roles, 200);
    }


    /**
     * Return Licence true/false
     */
    public function acotrs_getLicenced(){

        return true;
        $license_status = get_option('acotrs_activation_license_status');

        if ($license_status == 'valid')
            return true;

        return FALSE;

    }


    /**
     * @access  public
     * @return list of shipping method for shipping zone
     */
    public function acotrs_get_initial_config(){
        return new WP_REST_Response(array(
            'licenced' => $this->acotrs_getLicenced()
        ), 200);
    }


    /**
     * @access  public
     * @return Single Shipping mehod configration as json
     */
    public function getConfig($data)
    {
        
        $shipping_id    = $data['shipping_id'];
        $instance_id    = $data['instance_id'];
        $option_name    = $this->get_optionName($shipping_id, $instance_id);

        $config = get_option( $option_name, array('error' => 'error'));

        //Zone id
        $zone_id = $this->wpdb->prepare( 'SELECT `zone_id` FROM '.$this->wpdb->prefix.'woocommerce_shipping_zone_methods WHERE `instance_id`=%d', $instance_id);
        $zone_id = $this->wpdb->get_row($zone_id, OBJECT);

        // Get all wc product
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        $posts = get_posts($args);
        $products = array();
        foreach($posts as $product) array_push($products, array('id' => $product, 'title' => get_the_title( $product )));

        // get all coupons
        $args = array(
            'post_type' => 'shop_coupon',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        $posts = get_posts($args);
        $coupons = array();
        foreach($posts as $scoupon) array_push($coupons, array('id' => $scoupon, 'title' => get_the_title( $scoupon )));



        // Product category
        $categories = get_terms( ['taxonomy' => 'product_cat'] );
        $catArray = array();
        foreach($categories as $cat) array_push($catArray, array('term_id' => $cat->term_id, 'name' => $cat->name));

        // Get all shipping class
        $shipping_classes = get_terms( array('taxonomy' => 'product_shipping_class', 'hide_empty' => false ) );
        $shippingArray = array();
        foreach($shipping_classes as $cat) array_push($shippingArray, array('sclass_id' => $cat->term_id, 'name' => $cat->name));

        // Get all taxonomy
        $taxArray = array_map(function ($value) {
            return $value->name = $value->label;
        }, get_object_taxonomies('product', 'objects') );





        $terms = array();
        foreach($taxArray as $k => $singTerm){
            $term = array_map(function ($value) {
                return [
                    'term_id' => $value->term_id,
                    'name' => $value->name
                ];
            }, get_terms($k, array('hide_empty' => false)));
            $terms[$k] = $term;
        }



        $methods = $this->acotrs_zone_methods($shipping_id);
        $zones   = $this->acotrs_zone_lists();

        return new WP_REST_Response(array(
            'config' => $config,
            'products' => $products,
            'cat' => $catArray,
            'shipping_class' => $shippingArray,
            'coupons' => $coupons,
            'taxonomy' => $taxArray,
            'terms' => $terms,
            'zone_id' => @$zone_id->zone_id,
            'methods' => $methods,
            'zones' => $zones
        ), 200);
    }

    /**
     *
     * Ensures only one instance of APIFW is loaded or can be loaded.
     *
     * @param string $file Plugin root path.
     * @return Main APIFW instance
     * @see WordPress_Plugin_Template()
     * @since 1.0.0
     * @static
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Permission Callback
     **/
    public function getPermission()
    {
        if (current_user_can('administrator') || current_user_can('manage_woocommerce')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), $this->_version);
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), $this->_version);
    }
}
