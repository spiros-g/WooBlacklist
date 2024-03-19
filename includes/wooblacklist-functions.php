<?php
/*
Plugin Name: WooBlacklist
Description: A WooCommerce plugin to blacklist users who don't pick up their orders.
Version: 1.0.0
Author: Spiros G.
Author URI: https://www.spirosg.dev/
*/

function wooblacklist_user_profile_fields($user) {
    $is_blacklisted = get_user_meta($user->ID, 'is_blacklisted', true);
    ?>
    <table class="form-table">
        <tr>
            <th><label for="is_blacklisted">Blacklisted</label></th>
            <td>
                <input type="checkbox" name="is_blacklisted" id="is_blacklisted" value="1" <?php checked($is_blacklisted, '1'); ?>>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'wooblacklist_user_profile_fields');
add_action('edit_user_profile', 'wooblacklist_user_profile_fields');

// Save blacklist status from user profile
function wooblacklist_save_user_profile_fields($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }

    $is_blacklisted = isset($_POST['is_blacklisted']) && $_POST['is_blacklisted'] ? '1' : '0';
    update_user_meta($user_id, 'is_blacklisted', $is_blacklisted);
}
add_action('personal_options_update', 'wooblacklist_save_user_profile_fields');
add_action('edit_user_profile_update', 'wooblacklist_save_user_profile_fields');

// Add blacklist dropdown select to order edit page
function wooblacklist_order_edit_fields($order) {
    $user_id = $order->get_user_id();
    $is_blacklisted = get_user_meta($user_id, 'is_blacklisted', true);
    ?>
    <div class="order_data_column" >
        <h4><?php _e('In the blacklist', 'order-blacklist'); ?></h4>
        <p class="form-field  order-blacklist-checkbox">
            <label for="is_blacklisted">
                <select name="is_blacklisted" id="is_blacklisted">
                    <option value="0" <?php selected($is_blacklisted, '0'); ?>>No</option>
                    <option value="1" <?php selected($is_blacklisted, '1'); ?>>Yes</option>
                </select>         
            </label>
        </p>
    </div>
    <?php
}
add_action('woocommerce_admin_order_data_after_order_details', 'wooblacklist_order_edit_fields');


// Save blacklist status from order edit page
function wooblacklist_save_order_fields($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();

    if ($user_id) {
        $is_blacklisted = isset($_POST['is_blacklisted']) && $_POST['is_blacklisted'] ? '1' : '0';
        update_user_meta($user_id, 'is_blacklisted', $is_blacklisted);
    }
}
add_action('woocommerce_process_shop_order_meta', 'wooblacklist_save_order_fields');


// Send email for blacklisted orders
function wooblacklist_send_email($order_id, $order) {
    $user_id = $order->get_user_id();
    $is_blacklisted = get_user_meta($user_id, 'is_blacklisted', true);

    if ($is_blacklisted == '1') {
        $user = get_user_by('ID', $user_id);
        $store_email = get_option('admin_email');
        $subject = 'New order (Blacklisted) #'. $order_id;
        $message = 'A new order has been received from a user who is in the blacklist:' . "\n\n";
        $message .= 'Order Number: ' . $order_id . "\n";
        $message .= 'User: ' . $user->display_name . ' (' . $user->user_email . ')' . "\n";
        // Get user phone number
        $phone_number = get_user_meta($user_id, 'billing_phone', true);
        if ($phone_number) {
            $message .= 'Phone: ' . $phone_number . "\n";
        }
        $message .= 'In the blacklist: Yes' . "\n";

        // Send email to admin
        wp_mail($store_email, $subject, $message);
    }
}
add_action('woocommerce_new_order', 'wooblacklist_send_email', 10, 2);


// Include blacklisted status in WooCommerce REST API order JSON
function wooblacklist_rest_api_order_response($response, $order) {
    $user_id = $order->get_user_id();
    $is_blacklisted = get_user_meta($user_id, 'is_blacklisted', true);
    $response->data['is_blacklisted'] = ($is_blacklisted == '1') ? '1' : '0';
    return $response;
}
add_filter('woocommerce_rest_prepare_shop_order_object', 'wooblacklist_rest_api_order_response', 10, 2);

// Update blacklisted status through REST API
function wooblacklist_rest_api_update_user_meta($value, $object, $field_name) {
    if ($field_name === 'is_blacklisted') {
        $user_id = $object->get_user_id();
        $is_blacklisted = ($value === '1') ? '1' : '0';
        update_user_meta($user_id, 'is_blacklisted', $is_blacklisted);
        $order_id = $object->get_id();
        update_post_meta($order_id, 'is_blacklisted', $is_blacklisted);
        $value = $is_blacklisted;
    }
    return $value;
}
add_filter('woocommerce_rest_shop_order_object_update_meta_field', 'wooblacklist_rest_api_update_user_meta', 10, 3);

// Add blacklisted status to users table
function wooblacklist_user_column_header($columns) {
    $columns['is_blacklisted'] = 'Blacklisted';
    return $columns;
}
add_filter('manage_users_columns', 'wooblacklist_user_column_header');

function wooblacklist_user_column_content($value, $column_name, $user_id) {
    if ($column_name === 'is_blacklisted') {
        $is_blacklisted = get_user_meta($user_id, 'is_blacklisted', true);
        $value = ($is_blacklisted == '1') ? '<span style="color: red;">Yes</span>' : 'No';
    }
    return $value;
}
add_action('manage_users_custom_column', 'wooblacklist_user_column_content', 10, 3);

// Filter users by blacklisted status
function wooblacklist_filter_users($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $screen = get_current_screen();
    if ($screen->id !== 'users') {
        return;
    }

    if (isset($_GET['is_blacklisted']) && $_GET['is_blacklisted'] === '1') {
        $query->set('meta_key', 'is_blacklisted');
        $query->set('meta_value', '1');
    }
}
add_action('pre_get_users', 'wooblacklist_filter_users');

// Disable payment methods for blacklisted users
function wooblacklist_disable_payment_methods($available_gateways) {
    $user_id = get_current_user_id();
    $is_blacklisted = get_user_meta($user_id, 'is_blacklisted', true);
    $blacklisted_payment_methods = array('payinstore', 'cod'); // Add the IDs of the blacklisted payment methods

    if ($is_blacklisted == '1') {
        foreach ($blacklisted_payment_methods as $payment_method) {
            if (isset($available_gateways[$payment_method])) {
                unset($available_gateways[$payment_method]);
            }
        }
    }

    return $available_gateways;
}
add_filter('woocommerce_available_payment_gateways', 'wooblacklist_disable_payment_methods');

// Add popup warning for blacklisted users
function wooblacklist_add_popup_warning() {
	echo 'Popup function called';
    global $pagenow;

    // Check if we are on the order edit page
    if ( $pagenow === 'post.php' && isset( $_GET['post'] ) ) {
        $post_type = get_post_type( $_GET['post'] );

        // Check if the post type is 'shop_order'
        if ( $post_type === 'shop_order' ) {
            $order_id = $_GET['post'];
            $order = wc_get_order( $order_id );
            $user_id = $order->get_user_id();
            $is_blacklisted = get_user_meta( $user_id, 'is_blacklisted', true );

            // Check if the user is blacklisted
            if ( $is_blacklisted == '1' ) {
                ?>
                <div class="order-blacklist-popup">
                    <div class="order-blacklist-popup-content">
                        <h2>Warning</h2>
                        <p>This user is in the blacklist.</p>
                        <a href="#" class="button">OK</a>
                    </div>
                </div>
                <?php
            }
        }
    }
}
add_action( 'admin_footer', 'wooblacklist_add_popup_warning' );

?>
