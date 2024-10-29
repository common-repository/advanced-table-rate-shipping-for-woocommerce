<?php  
/*
* Acotrs All admin hook 
*/


if(!class_exists('ACOTRS_Compatibility')){
    class ACOTRS_Compatibility{
        /**
         * Constructor function.
         *
         * @access  public
         * @param string $file plugin start file path.
         * @since   1.0.0
        */


        /**
         * Plugin Path
         */
        protected $plugin_path;


        public function __construct()
        {
            $this->plugin_path = plugin_dir_path(ACOTRS_FILE);
            add_action( 'woocommerce_init', array( $this, 'compatibilityFileIncludes' ) );
        }

        /**
         * Add Compatibility Class
         * @param NULL
         */
        public function compatibilityFileIncludes(){
            require_once($this->plugin_path . 'includes/compatibility/compbl.wpml.php');
        }
    }

}