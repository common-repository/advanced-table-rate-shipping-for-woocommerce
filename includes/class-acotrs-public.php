<?php

if (!defined('ABSPATH')) {
    exit;
}


if(!class_exists('ACOTRS_Public')){
class ACOTRS_Public
    {

        const METHOD_ID = 'acotrs_shipping';
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
         * The main plugin file.
         *
         * @var     string
         * @access  public
         * @since   1.0.0
         */
        public $file;

        /**
         * The token.
         *
         * @var     string
         * @access  public
         * @since   1.0.0
         */
        public $token;


        /**
         * Constructor function.
         *
         * @access  public
         * @param string $file Plugin root file path.
         * @since   1.0.0
         */
        public function __construct($file = '')
        {
            $this->version = ACOTRS_VERSION;
            $this->token = ACOTRS_TOKEN;
            $this->file = $file;
            
            add_action( 'woocommerce_after_shipping_calculator' , array($this, 'acotrs_shipping_delivery_date_display') );          
            add_action( 'woocommerce_cart_calculate_fees', array($this, 'acotrs_add_shipping_handlingfee') ); 
            add_action( 'woocommerce_after_shipping_rate', array($this, 'acotrs_woocommerce_after_shipping_rate'), 10, 2 );        
        }


        public function acotrs_woocommerce_after_shipping_rate($method, $index){ //error_log(print_r( $method, true));
            $meta = $method->get_meta_data();
            if(isset($meta['description']) && isset($meta['description']->scalar)){
                echo sprintf('<small style="display:block;" class="acotrs-description">%s</small>', $meta['description']->scalar);
            }
        }

        /**
         * Add Handling Fee on Shipping
         * @param NULL
         */
        public function acotrs_add_shipping_handlingfee(){
            global $woocommerce;
            $chosen_shippings = WC()->session->get( 'chosen_shipping_methods' )[0]; // The chosen shipping methods
            $shipping_methods = WC()->session->get('shipping_for_package_0')['rates'];

            $handlingfee = '';
            $tax_status = true;
                if(is_array($shipping_methods) && count($shipping_methods) > 0){
                    foreach ( $shipping_methods as $method_id => $shipping_rate ){
                        // Get the meta data in an unprotected array
                        $meta_data = $shipping_rate->get_meta_data();
                        if(isset($meta_data['handling_fee']) && $method_id == $chosen_shippings && !empty($meta_data['handling_fee'])){        
                            $handlingfee = $meta_data['handling_fee'];
                            $tax_status = $meta_data['tax_status'] == 'non-taxable' ? false : $tax_status;
                        }
                    }
                }

                if(!empty($handlingfee)){
                    update_option( 'test_option', $meta_data );
                    $woocommerce->cart->add_fee( __('Handling Fee', 'advanced-table-rate-shipping-for-woocommerce'), (int)$handlingfee, $tax_status, '' );
                }
        }


        /**
         * Apply Delivery Date on Cart page
         * @param NULL
         */
        public function acotrs_shipping_delivery_date_display(){
            $chosen_shippings = WC()->session->get( 'chosen_shipping_methods' )[0]; // The chosen shipping methods
            $shipping_methods = WC()->session->get('shipping_for_package_0')['rates'];
        
                // Loop through the array
                $delivery_date = '';
                if(is_array($shipping_methods) && count($shipping_methods) > 0){
                    foreach ( $shipping_methods as $method_id => $shipping_rate ){
                        // Get the meta data in an unprotected array
                        $meta_data = $shipping_rate->get_meta_data();
                        if(isset($meta_data['delivery_day']) && $method_id == $chosen_shippings && !empty($meta_data['delivery_day']) ){   
                                $delivery_date  = $meta_data['delivery_day'];
                        }
                    }
                }

                if(!empty($delivery_date)):
                ob_start();
                    ?>
                    <tr class="shipping">
                            <th><?php esc_html_e( 'Delivery on', 'advanced-table-rate-shipping-for-woocommerce' ); ?></th>
                            <td data-title="<?php esc_attr_e( 'Delivery Date', 'advanced-table-rate-shipping-for-woocommerce' ); ?>"><?php echo $delivery_date; ?></td>
                    </tr>
                    <?php 
                $output = ob_get_clean();
                echo $output;
                endif;
        }
        

        /**
         * Ensures only one instance of APIFW_Front_End is loaded or can be loaded.
         *
         * @param string $file Plugin root file path.
         * @return Main APIFW_Front_End instance
         * @since 1.0.0
         * @static
         */
        public static function instance($file = '')
        {
            if (is_null(self::$instance)) {
                self::$instance = new self($file);
            }
            return self::$instance;
        }
    }
}