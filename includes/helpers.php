<?php
/*
* Helper functions for aco-table-rate-shipping
*/

function acoTableAdminErrorNotification(){
    ob_start();
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo sprintf( '<a target="_blank" href="%s">WooCommerce</a> are required for %s.', 'https://wordpress.org/plugins/woocommerce/', ACOTRS_PLUGIN_NAME ); ?></p>
    </div>
    <?php 
    $aco_output = ob_get_clean();
    echo $aco_output;
}


function acotrs_debug($data){
    $debugfile = fopen(plugin_dir_path(ACOTRS_FILE) . 'includes/debug_json.json', 'w') or die("can't open file");
	fwrite($debugfile, json_encode($data));
	fclose($debugfile);
}