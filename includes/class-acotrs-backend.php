<?php

/**
 * Load Backend related actions
 *
 * @class   ACOTRS_Backend
 */


if (!defined('ABSPATH')) {
    exit;
}


class ACOTRS_Backend
{
    private $method_id = 'acotrs_shipping';
    /**
     * Class intance for singleton  class
     *
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
     * The main plugin file.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $file;

    /**
     * The main plugin directory.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $dir;

    /**
     * The plugin assets directory.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_dir;

    /**
     * Suffix for Javascripts.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $script_suffix;

    /**
     * The plugin assets URL.
     *
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_url;
    /**
     * The plugin hook suffix.
     *
     * @var     array
     * @access  public
     * @since   1.0.0
     */
    public $hook_suffix = array();

    /**
     * WP DB
     */
    private $wpdb;


    /**
     * Constructor function.
     *
     * @access  public
     * @param string $file plugin start file path.
     * @since   1.0.0
     */
    public function __construct($file = '')
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->version = ACOTRS_VERSION;
        $this->token = ACOTRS_TOKEN;
        $this->file = $file;
        $this->dir = dirname($this->file);
        $this->assets_dir = trailingslashit($this->dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));
        $this->script_suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        $plugin = plugin_basename($this->file);
        
        // Add woocommerce settings page to $hook_suffix 
        array_push($this->hook_suffix, 'woocommerce_page_wc-settings');

        // add action links to link to link list display on the plugins page.
        add_filter("plugin_action_links_$plugin", array($this, 'pluginActionLinks'));

        // reg activation hook.
        register_activation_hook($this->file, array($this, 'install'));
        
        // reg deactivation hook.
        register_deactivation_hook($this->file, array($this, 'deactivation'));


        if($this->isWoocommerceActivated()){
            // enqueue scripts & styles.
            add_action('admin_enqueue_scripts', array($this, 'adminEnqueueScripts'), 10, 1);
            add_action('admin_enqueue_scripts', array($this, 'adminEnqueueStyles'), 10, 1);

            // add_filter( 'woocommerce_shipping_zone_shipping_methods', array($this, 'acotrs_customize_shipping_zone_shipping_methods'), 10, 4 );
            

            // Admin Menu 
            add_action( 'admin_menu', array($this, 'acotrs_admin_menu_page_hook') );
        }else{
            add_action('admin_notices', array($this, 'acotrs_notice_need_woocommerce'));
        }
    }




    /**
     * @access  public
     * @desc    Admin notice if woocommerce aren't installed
     */
    public function acotrs_notice_need_woocommerce(){
        $error = sprintf(
            __(
                '%s requires <a href="https://wordpress.org/plugins/woocommerce/">WooCommerce</a> to be installed & activated!',
                'advanced-table-rate-shipping-for-woocommerce'
            ),
            ACOTRS_PLUGIN_NAME
        );

        echo ('<div class="error"><p>' . $error . '</p></div>');
    }

    
    /**
     * @access  private
     * @desc    Add saperate admin menu for acotrs shipping table
     * 
    */
    public function acotrs_admin_menu_page_hook(){

        $this->hook_suffix[] = add_submenu_page( 
                'woocommerce', 
                __('Advanced Table Rate Shipping', 'advanced-table-rate-shipping-for-woocommerce'), 
                __('Advanced Table Rate Shipping', 'advanced-table-rate-shipping-for-woocommerce'), 
                'manage_woocommerce', 
                'acotrs', 
                array($this, 'acotrs_admin_page_callback'), 
                90000 
        );
    }


    /**
     * @access  public
     * @return  page content
     * 
     */
    public function acotrs_admin_page_callback(){
        echo (
            '<div id="' . $this->token . '_ui_root" class="bg-white border-round-5 pb-5">
                <div class="' . $this->token . '_loader"><p>' . __('Loading User Interface...', 'advanced-table-rate-shipping-for-woocommerce') . '</p></div>
            </div>'
        );

        wp_localize_script(
            $this->token . '-backend',
            $this->token . 'shipping_settings',
            array(
                'method_id' => $this->method_id
            )
        );
    }


    public function acotrs_customize_shipping_zone_shipping_methods($methods, $raw_methods, $allowed_classes, $instance){
        // $methods_array = array();
        
        foreach($methods as $k => $m){
            if($m->id === 'acotrs_shipping' ){
                // unset($methods[$k]);
                // if(count($methods_array) > 0)
                //     continue;
                // array_push($methods_array, $m);
            }    
        }
        
        // if(count($methods_array) > 0) 
        //     $methods = array_merge($methods, $methods_array);

        return $methods; 
    }


 

    /**
     * Ensures only one instance of Class is loaded or can be loaded.
     *
     * @param string $file plugin start file path.
     * @return Main Class instance
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


    /**
     * Show action links on the plugin screen.
     *
     * @param mixed $links Plugin Action links.
     *
     * @return array
     */
    public function pluginActionLinks($links)
    {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=' . $this->token) . '">'
                . __('Configure', 'advanced-table-rate-shipping-for-woocommerce') . '</a>'
        );

        return array_merge($action_links, $links);
    }

    /**
     * Check if woocommerce is activated
     *
     * @access  public
     * @return  boolean woocommerce install status
     */
    public function isWoocommerceActivated()
    {
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            return true;
        }
        if (is_multisite()) {
            $plugins = get_site_option('active_sitewide_plugins');
            if (isset($plugins['woocommerce/woocommerce.php'])) {
                return true;
            }
        }
        return false;
    }



    /**
     * Installation. Runs on activation.
     *
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function install()
    {
        global $wpdb;
        include_once ABSPATH . '/wp-admin/includes/upgrade.php';
        $table_charset = '';
        $prefix = $wpdb->prefix;
        $users_table = $prefix . 'um_vip_users';
        if ($wpdb->has_cap('collation')) {
            if (!empty($wpdb->charset)) {
                $table_charset = "DEFAULT CHARACTER SET {$wpdb->charset}";
            }
            if (!empty($wpdb->collate)) {
                $table_charset .= " COLLATE {$wpdb->collate}";
            }
        }
        $create_vip_users_sql = "CREATE TABLE {$users_table} (id int(11) NOT NULL auto_increment,user_id int(11) NOT NULL,user_type tinyint(4) NOT NULL default 0,startTime datetime NOT NULL default '0000-00-00 00:00:00',endTime datetime NOT NULL default '0000-00-00 00:00:00',PRIMARY KEY (id),INDEX uid_index(user_id),INDEX utype_index(user_type)) ENGINE = MyISAM {$table_charset};";
        maybe_create_table($users_table, $create_vip_users_sql);
    }


    /**
     * Load admin CSS.
     *
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function adminEnqueueStyles($screen)
    {
        
        if (!isset($this->hook_suffix) || empty($this->hook_suffix)) {    
            return;
        }
        if (in_array($screen, $this->hook_suffix, true)) {
            wp_register_style($this->token . '-admin', esc_url($this->assets_url) . 'css/backend.css', array(), $this->version);
            wp_enqueue_style($this->token . '-admin');
            wp_enqueue_style($this->token . '-admin-wrapper');
        }
    }

    /**
     * Load admin Javascript.
     *
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function adminEnqueueScripts()
    {
        if (!isset($this->hook_suffix) || empty($this->hook_suffix)) {   
            return;
        }

        $screen = get_current_screen();

        if (in_array($screen->id, $this->hook_suffix, true)) {
            // Enqueue WordPress media scripts.
            if (!did_action('wp_enqueue_media')) {
                wp_enqueue_media();
            }

            if (!wp_script_is('wp-i18n', 'registered')) {
                wp_register_script('wp-i18n', esc_url($this->assets_url) . 'js/i18n.min.js', array(), $this->version, true);
            }
            // Enqueue custom backend script.
            wp_enqueue_script($this->token . '-backend', esc_url($this->assets_url) . 'js/backend.js', array('wp-i18n'), $this->version, true);
            // Localize a script.
            wp_localize_script(
                $this->token . '-backend',
                $this->token . '_object',
                array(
                    'api_nonce' => wp_create_nonce('wp_rest'),
                    'root' => rest_url($this->token . '/v1/'),
                    'assets_url' => $this->assets_url,
                    'currency_symbol' => get_woocommerce_currency_symbol(), 
                    'base_url' => get_admin_url( '/' )
                )
            );
        }
    }


    
    /**
     * Deactivation hook
     */
    public function deactivation()
    {
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