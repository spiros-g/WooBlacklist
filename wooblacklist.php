<?php
/*
Plugin Name: WooBlacklist
Description: A WooCommerce plugin to blacklist users who don't pick up their orders.
Version: 1.0.0
Author: Spiros G.
Author URI: https://www.spirosg.dev/
*/

function wooblacklist_enqueue_assets() {
    wp_register_style('wooblacklist-style', plugins_url('/assets/style.css', __FILE__), array(), '1.0.0');
    wp_enqueue_style('wooblacklist-style');

    wp_register_script('wooblacklist-script', plugins_url('/assets/script.js', __FILE__), array('jquery'), '1.0.0', true);
    wp_enqueue_script('wooblacklist-script');
}

add_action('admin_enqueue_scripts', 'wooblacklist_enqueue_assets');

require_once plugin_dir_path(__FILE__) . 'includes/wooblacklist-functions.php';

