<?php
add_filter('registration_errors', 'secure_signups_email_registration', 10, 3);
function secure_signups_email_registration($errors, $sanitized_user_login, $user_email) {
    global $wpdb;
    $settings_table = $wpdb->prefix . 'settings_for_secure_signups';
    $is_restriction_active = $wpdb->get_var("SELECT is_restriction FROM $settings_table LIMIT 1");
    $message = $wpdb->get_var("SELECT message FROM $settings_table LIMIT 1");
    $publicly_view = $wpdb->get_var("SELECT publicly_view FROM $settings_table LIMIT 1");
    if ($is_restriction_active != 1) {
        return $errors;
    }
    $domains_table = $wpdb->prefix . 'list_of_domain_for_secure_signups';
    $allowed_domains = $wpdb->get_col("SELECT domain_name FROM $domains_table WHERE is_active = 1");
    $user_email_parts = explode('@', $user_email);
    $domain = end($user_email_parts);
    if (!in_array($domain, $allowed_domains)) {
        $allowed_domains_str = implode(', ', $allowed_domains);
        if ($publicly_view==1){
            $errors->add('invalid_email', __($message, 'text-domain'));
        }else{
            $errors->add('invalid_email', __('Only ' . $allowed_domains_str . ' email addresses are allowed for registration.', 'text-domain'));
        }
    }
    return $errors;
}
