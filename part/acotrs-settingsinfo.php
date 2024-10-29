<?php  
/**
 * Acotrs Settings Info pages
 */
?>

<div id="acotrsSettingInfo">
    <div id="wrap">
        <div class="wrap-inner">
            <h2><?php _e('How to use ACOTRS Shipping?', 'advanced-table-rate-shipping-for-woocommerce'); ?></h2>
            <ol>
                <li><?php echo sprintf('First go to <a target="_blank" href="%s">Shipping zones</a> and add your shipping area.', admin_url( 'admin.php?page=wc-settings&tab=shipping&section' )); ?></li>
                <li><?php _e('You can start the configuration by clicking the ACOTRS Shipping title link in the Shipping methods table.', 'advanced-table-rate-shipping-for-woocommerce'); ?></li>
            </ol>

            <h3><?php _e('Quick Video Overview', 'advanced-table-rate-shipping-for-woocommerce'); ?></h3>
            <iframe width="560" height="315" src="https://www.youtube.com/embed/C0DPdy98e4c" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

            <h4><?php _e('More Resources') ?></h4>
            <ul class="resorce">
                <li><a target="_blank" href="#"><?php _e('How to add a new shipping method handled by ACOTRS Shipping?', 'advanced-table-rate-shipping-for-woocommerce'); ?></a></li>
                <li><a target="_blank" href="#"><?php _e('A complete guide to shipping methods', 'advanced-table-rate-shipping-for-woocommerce') ?></a></li>
                <li><a target="_blank" href="#"><?php _e('Currency Support', 'advanced-table-rate-shipping-for-woocommerce') ?></a></li>
                <li><a target="_blank" href="#"><?php _e('Weight Based Shipping', 'advanced-table-rate-shipping-for-woocommerce') ?></a></li>
                <li><a target="_blank" href="#"><?php _e('City Based Shipping', 'advanced-table-rate-shipping-for-woocommerce') ?></a></li>
                <li><a target="_blank" href="#"><?php _e('Default Method & Volumetric Settings', 'advanced-table-rate-shipping-for-woocommerce') ?></a></li>
                <li><a target="_blank" href="#"><?php _e('Additional Options for Shipping', 'advanced-table-rate-shipping-for-woocommerce') ?></a></li>
                <li><a target="_blank" href="#"><?php _e('Conditional Cash on Delivery', 'advanced-table-rate-shipping-for-woocommerce') ?></a></li>
            </ul>
        </div>
    </div>
</div>