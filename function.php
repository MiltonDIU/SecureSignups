<?php
/**
 * Plugin Name: Secure Signups
 * Plugin URI:https://daffodilweb.com/secure-signups.php
 * Description: Secure Signups: Safeguard WordPress registrations. Restrict signups to approved domain emails, manage domains from the admin panel.
 * Version: 1.0.0
 * Author: Daffodil Web & E-commerce
 * Author URI: https://daffodilweb.com
 * Text Domain: Secure Signups
 */
register_activation_hook(__FILE__, 'secure_signups_install');
function enqueue_secure_signups_styles() {
    wp_enqueue_style('secure_signups_styles', plugins_url('css/secure_signups_styles.css', __FILE__));
}
add_action('admin_enqueue_scripts', 'enqueue_secure_signups_styles');
//
function enqueue_custom_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('custom-script', get_template_directory_uri() . '/js/custom-script.js', array('jquery'), '1.0.0', true);
    wp_localize_script('custom-script', 'domain_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('domain-ajax-nonce'),
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');

function create_limit_insert_trigger() {
    global $wpdb;
    $list_of_domain_table = $wpdb->prefix . 'list_of_domain_for_secure_signups';

    $create_trigger_sql = "
    CREATE TRIGGER limit_insert_trigger
    BEFORE INSERT ON $list_of_domain_table
    FOR EACH ROW
    BEGIN
        DECLARE row_count INT;
        SELECT COUNT(*) INTO row_count FROM $list_of_domain_table;
        IF row_count >= 10 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot insert more than 10 rows into $list_of_domain_table';
        END IF;
    END;";

    $wpdb->query($create_trigger_sql);
}

function secure_signups_install() {
    global $wpdb;
    $list_of_domain_table = $wpdb->prefix . 'list_of_domain_for_secure_signups';
    $settings_table = $wpdb->prefix . 'settings_for_secure_signups'; // Updated table name
    $charset_collate = $wpdb->get_charset_collate();
    $sql_list_of_domains = "CREATE TABLE IF NOT EXISTS $list_of_domain_table (
    id INT NOT NULL AUTO_INCREMENT,
    domain_name VARCHAR(255) NOT NULL UNIQUE, -- Adding UNIQUE constraint
    is_active INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";
    $sql_settings = "CREATE TABLE IF NOT EXISTS $settings_table (
    id INT NOT NULL AUTO_INCREMENT,
    is_restriction INT NOT NULL DEFAULT 1,
    message TEXT,
    publicly_view INT NOT NULL DEFAULT 0,
    retain_plugin_data INT NOT NULL DEFAULT 0, -- New field for retaining plugin data
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_list_of_domains);
    dbDelta($sql_settings);
    $existing_settings = $wpdb->get_row("SELECT COUNT(*) as count FROM $settings_table");
    if ($existing_settings->count == 0) {
        $wpdb->insert(
            $settings_table,
            array(
                'is_restriction' => 1,
                'publicly_view' => 1,
                'retain_plugin_data' => 1,
                'message' => "Allowed only selected domain"
            )
        );
    }
    create_limit_insert_trigger();
}
register_deactivation_hook(__FILE__, 'secure_signups_uninstall'); // Utilizing uninstall function for deactivation
function secure_signups_uninstall()
{
    global $wpdb;

    $settings_table = $wpdb->prefix . 'settings_for_secure_signups'; // Table name

    // Check if the table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$settings_table'") == $settings_table) {

        // Check the value of retain_plugin_data column
        $retain_data = $wpdb->get_var("SELECT retain_plugin_data FROM $settings_table");

        // If retain_plugin_data is 1, don't delete the tables or the trigger
        if ($retain_data == 1) {
            return; // Exit the function without deleting the table or trigger
        }

        $list_of_domain_table = $wpdb->prefix . 'list_of_domain_for_secure_signups';

        // SQL query to drop the trigger
        $drop_trigger_sql = "DROP TRIGGER IF EXISTS limit_insert_trigger";

        // Execute the query to drop the trigger
        $wpdb->query($drop_trigger_sql);

        // Drop tables only if retain_plugin_data is not set
        $wpdb->query("DROP TABLE IF EXISTS $list_of_domain_table");
        $wpdb->query("DROP TABLE IF EXISTS $settings_table");
    }
}
function secure_signups_menu() {
    add_menu_page('Secure Signups', 'Secure Signups', 'manage_options', 'secure-signups-menu', 'secure_signups_settings_page');
    add_submenu_page('secure-signups-menu', 'Settings', 'Settings', 'manage_options', 'secure-signups-menu', 'secure_signups_settings_page');
    add_submenu_page('secure-signups-menu', 'List of Domain', 'List of Domain', 'manage_options', 'secure-signups-add-new-domain', 'secure_signups_add_new_domain_page');
}
add_action('admin_menu', 'secure_signups_menu');
function secure_signups_settings_page() {
    global $wpdb;
    $settings_table = $wpdb->prefix . 'settings_for_secure_signups';
    $current_setting = $wpdb->get_row("SELECT is_restriction,message,publicly_view,retain_plugin_data FROM $settings_table LIMIT 1");
    include 'include/settings.php';
}

add_action('wp_ajax_save_secure_signups_settings', 'save_secure_signups_settings');
function save_secure_signups_settings() {
    if (isset($_POST['message'])) {
        global $wpdb;

        $settings_table = $wpdb->prefix . 'settings_for_secure_signups';
        $is_restriction =  isset($_POST['is_restriction']) ? 1 : 0;
        $message = sanitize_text_field($_POST['message']);
        $publicly_view =  isset($_POST['publicly_view']) ? 1 : 0;
        $retain_plugin_data = isset($_POST['retain_plugin_data']) ? 1 : 0;

        $wpdb->update(
            $settings_table,
            array(
                'is_restriction' => $is_restriction,
                'message' => $message,
                'publicly_view' => $publicly_view,
                'retain_plugin_data' => $retain_plugin_data,
            ),
            array('id' => 1),
            array('%d', '%s', '%d', '%d'), // Corrected array with the appropriate placeholders
            array('%d')
        );

        wp_send_json_success("Success: The domain settings were successfully updated!");
        wp_die();
    }

}

function secure_signups_add_new_domain_page() {
    global $wpdb;
    $domain_table = $wpdb->prefix . 'list_of_domain_for_secure_signups';
    include 'include/new-domain.php';
    $domains = $wpdb->get_results("SELECT * FROM $domain_table");
    include 'include/list-of-domain.php';
}
function save_new_domain() {
    if (isset($_POST['domain_name'])) {
        global $wpdb;
        $domains_table = $wpdb->prefix . 'list_of_domain_for_secure_signups';

        $domain_name = sanitize_text_field($_POST['domain_name']);

        // Check if the domain already exists in the database
        $existing_domain = $wpdb->get_row($wpdb->prepare("SELECT * FROM $domains_table WHERE domain_name = %s", $domain_name));
        if ($existing_domain) {
            wp_send_json_error("Error: The domain already exists in the list.");
        }

        if (!preg_match("/^[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/", $domain_name)) {
            wp_send_json_error("Invalid: The domain name format is invalid! Please enter a valid domain name.");
        }

        // Check the number of existing rows in the table
        $existing_rows_count = $wpdb->get_var("SELECT COUNT(*) FROM $domains_table");
        $max_allowed_rows = 3;

        if ($existing_rows_count >= $max_allowed_rows) {
            wp_send_json_error("Info: A maximum of $max_allowed_rows domains can be whitelisted in the free version of the plugin.");
        }

        // If validation passes and the row count is within limits, insert the new domain
        $new = $wpdb->insert(
            $domains_table,
            array(
                'domain_name' => $domain_name,
                'is_active' => 1 // Assuming you want to set is_active to 1 always
            ),
            array('%s', '%d')
        );

        if ($new) {
            wp_send_json_success("Success: New domain successfully added!");
        } else {
            wp_send_json_error("Error: Failed to add the domain. Please try again.");
        }
    }
}

add_action('wp_ajax_save_new_domain', 'save_new_domain');
function get_domain_list() {
    global $wpdb;
    $domains_table = $wpdb->prefix . 'list_of_domain_for_secure_signups'; // Replace with your actual table name

    $domains = $wpdb->get_results("SELECT * FROM $domains_table", ARRAY_A);

    wp_send_json_success($domains);
}
add_action('wp_ajax_get_domain_list', 'get_domain_list');

add_action('admin_post_submit_domain', 'submit_domain');
add_action('wp_ajax_update_domain_status', 'update_domain_status');

function update_domain_status() {
    global $wpdb;
    $domain_table = $wpdb->prefix . 'list_of_domain_for_secure_signups';
    $domainId = isset($_POST['domain_id']) ? intval($_POST['domain_id']) : 0;
    $newStatus = isset($_POST['new_status']) ? intval($_POST['new_status']) : 0;

    if ($domainId > 0) {
        $wpdb->update(
            $domain_table,
            array('is_active' => $newStatus),
            array('id' => $domainId),
            array('%d'),
            array('%d')
        );
        wp_send_json_success("Success: The domain status successfully updated!");
    } else {
        wp_send_json_error("Error: The domain status is not successfully updated!");
    }
    wp_die();
}

add_action('wp_ajax_update_domain_name', 'update_domain_name');

function update_domain_name() {
    if (isset($_POST['domain_id']) && isset($_POST['new_domain_name'])) {
        global $wpdb;
        $domain_table = $wpdb->prefix . 'list_of_domain_for_secure_signups';
        $domainId = intval($_POST['domain_id']);
        $newDomainName = sanitize_text_field($_POST['new_domain_name']);
        $validation = true;
        if (!preg_match("/^[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/", $newDomainName)) {
            $validation = false;

            wp_send_json_error("Invalid: The domain name format is invalid! Please enter a valid domain name.");
        }
        if ($validation===true){
            $updated = $wpdb->update(
                $domain_table,
                array('domain_name' => $newDomainName),
                array('id' => $domainId),
                array('%s'),
                array('%d')
            );
        }
        if ($updated === false) {
            wp_send_json_error("Error: ". $wpdb->last_error);
        } else {
            wp_send_json_success("Success: Domain name success full updated!");
        }
    } else {
        wp_send_json_error("Error: Insufficient data!");
    }
    wp_die();
}

function copy_file_to_mu_plugins_folder() {
    $source_file = WP_CONTENT_DIR . '/plugins/SecureSignups/apply_secure_signups.php';
    $destination_folder = WP_CONTENT_DIR . '/mu-plugins';
    if (!file_exists($destination_folder)) {
        mkdir($destination_folder, 0755, true);
        chmod($destination_folder, 0755);
    }
    $destination_file = $destination_folder . '/apply_secure_signups.php';
    if (!file_exists($destination_file)) {
        if (copy($source_file, $destination_file)) {
        }
    }
}
function delete_file_from_mu_plugins_folder() {
    $destination_file = WP_CONTENT_DIR . '/mu-plugins/apply_secure_signups.php';
    if (file_exists($destination_file)) {
        if (unlink($destination_file)) {
            // File deleted successfully
        }
    }
}
register_activation_hook(__FILE__, 'copy_file_to_mu_plugins_folder');

register_deactivation_hook(__FILE__, 'delete_file_from_mu_plugins_folder');
