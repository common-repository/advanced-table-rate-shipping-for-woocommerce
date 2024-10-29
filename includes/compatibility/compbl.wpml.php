<?php 
/**
 * Compatibility for WPML multi language plugin
 */


if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'WooCommerce' ) && class_exists('SitePress') ) {
    if( !class_exists( 'ACOTRCOMPATIBILITY_Wpml' ) ){
        class ACOTRCOMPATIBILITY_Wpml{


            /**
             * __construct function 
             */
            public function __construct(){

                // Modify Settings values throw filters
                add_filter('acotrs_conditional_tertiary_subtotal', array($this, 'wpml_price_currency_conversation'), 10, 1);
                add_filter('acotrs_settings_shipping_class', array($this, 'wpml_settings_shipping_class_filter'), 10, 1);
                add_filter('acotrs_comparison_tertiary_product', array($this, 'wpml_comparison_tertiary_product_filter'), 10, 1);
                add_filter('acotrs_comparison_tertiary_category', array($this, 'wpml_comparison_tertiary_category_filter'), 10, 1);
                add_filter('acotrs_shipping_rate_label', array($this, 'wpml_translate_shipping_label_filter'), 10, 3);
                add_filter('acotrs_shipping_rate_description', array($this, 'wpml_translated_shipping_discription'), 10, 5);
                add_filter( 'woocommerce_package_rates', array( $this, 'wpml_get_translated_shipping_label' ), 1, 1 );
                
            }


            /**
             * Shipping Description Translate by WPML
             * @param $description = description text
             * @param #key = option key
             * @param $instance_id = Method instance_id 
             * @param $row_id = Table rate row id 
             * @param $content_cost = content cost
             */
            public function wpml_translated_shipping_discription($description, $key, $instance_id, $row_id, $content_cost){
                if( function_exists( 'icl_t' ) ) {
                    $option_id = $key;
                    $instance_id = (int) $instance_id;
                    $description = icl_t( 'acotrs_labels', 'instance_' . $instance_id . '-option_' . $option_id . '_' . $row_id . '-desc', $description );
                }
                return $description;
            }


            /**
             * Shipping label filter
             * @param $label = text 
             * @param $option_id = Option ID 
             * @param $instance_id = Method Instance id
             */
            public function wpml_translate_shipping_label_filter( $label, $option_id, $instance_id ) {

                if( function_exists( 'icl_register_string' ) ) {
                    // sanitize vars
                    $label = sanitize_text_field( $label );
                    $option_id = (int) $option_id;
    
                    // register via instance ID
                    icl_register_string( 'acotrs_labels', 'instance_' . $instance_id . '-option_' . $option_id, $label );
    
                    // register via option title
                    $sanitized_title = str_replace(' ', '_', sanitize_title( $label ));
                    icl_register_string( 'acotrs_labels', 'option_title-' . $sanitized_title, $label );
                }
    
                return $label;
            }




            /**
             * Convert Product Cat to Default Languge
             * @param $values = array()
            */
            public function id_conversion_category( $values = array() ) {

                // WPML translate shipping classes
                if( function_exists( 'icl_object_id' ) && function_exists( 'wpml_get_default_language' ) && is_array( $value ) && count($values) > 0 ) {
                    $dfLanguage = wpml_get_default_language();
    
                    foreach( $values as $key => $val ) {
                        $values[ $key ] = icl_object_id( $val, 'product_cat', true, $dfLanguage );
                    }
                }
    
                return $values;
            }




            /**
             * Convert Product ID to Default language ID
             * @param $value = array
             */
            public function wpml_comparison_tertiary_product_filter($value = array()){
                if( function_exists( 'icl_object_id' ) && function_exists( 'wpml_get_default_language' ) && is_array( $value ) && count($value) > 0 ) {
                    $default_lg = wpml_get_default_language();
    
                    foreach( $value as $key => $val ) {
                        $value[ $key ] = icl_object_id( $val, 'product', true, $default_lg );
                    }
                }
    
                return $value;
            }




            /**
             * Acotrs Shipping class compatibility
             * @param $values array
             */
            public function wpml_settings_shipping_class_filter($value ){
                if( function_exists( 'icl_object_id' ) && function_exists( 'wpml_get_default_language' ) && $value ) {
                    $default_language = wpml_get_default_language();
    
                    if( is_array( $value ) && count($value) > 0 ) {
                        foreach( $value as $key => $val ) {
                            $value[ $key ] = icl_object_id( $val, 'product_shipping_class', true, $default_language );
                        }
                    } else {
                        return icl_object_id( $value, 'product_shipping_class', true, $default_language );
                    }
                }
                return $value;
            }


            /**
            * Calculate shipping funciton 
            * @param string
            */
            public function wpml_price_currency_conversation($value){
                return apply_filters( 'wcml_raw_price_amount', $value );
            }


			
			
            function wpml_get_translated_shipping_label( $available_methods ) {

                // check for WPML translation function
                if( ! function_exists( 'icl_t' ) )
                    return $available_methods;
    
                // cycle through methods
                foreach( $available_methods as $key => $method ) {
    
                    // only follow through for this method
                    if( $method->method_id !== 'acotrs_shipping' )
                        continue;
    
                    // sanitize vars
                    $sanitized_label = sanitize_title( $method->label );
                    $instance_id = intval( $method->instance_id );
                    list( $method_id, $option_id ) = explode( '-', sanitize_text_field( $method->id ) );
    
                    // determine which translation to use
                    $trans_label = icl_t( 'acotrs_labels', 'option_title-' . $sanitized_label, $method->label );
                    $trans_instance = icl_t( 'acotrs_labels', 'instance_' . $instance_id . '-option_' . $option_id, $method->label );
                    if( $trans_label != $method->label && $trans_instance == $method->label ) {
                        $available_methods[ $key ]->label = $trans_label;
                    } else {
                        $available_methods[ $key ]->label = $trans_instance;
                    }
    
                }
    
                return $available_methods;
            }










        }

        new ACOTRCOMPATIBILITY_Wpml();
    }
}
