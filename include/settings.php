<div class="wrap">
    <h1 class="section-title">Secure Signups Settings</h1>
    <form id="secure-signups-settings-form">
        <input type="hidden" name="domain_id" id="domain_id">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="domain_name">Public Message</label></th>
                <td><input type="text" name="message" id="message" class="regular-text" required value="<?php echo $current_setting->message?$current_setting->message:'' ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">Activate Above Message</th>
                <td>
                    <fieldset>

                        <div class="toggle-switch">
                            <input type="checkbox" id="publicly_view" name="publicly_view" class="toggle-input" <?php echo ($current_setting->publicly_view == 1) ? 'checked' : ''; ?>>
                            <label for="publicly_view" class="toggle-label">
                                <span>On</span>
                                <span>Off</span>
                            </label>
                        </div>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">We respect your data privacy. Would you like to retain your existing plugin data upon deactivation?</th>
                <td>
                    <fieldset>
                        <div class="toggle-switch">
                            <input type="checkbox" id="retain_plugin_data" name="retain_plugin_data" class="toggle-input" <?php echo ($current_setting->retain_plugin_data == 1) ? 'checked' : ''; ?>>
                            <label for="retain_plugin_data" class="toggle-label">
                                <span>On</span>
                                <span>Off</span>
                            </label>
                        </div>

                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">Plugins On/Off</th>
                <td>
                    <fieldset>

                        <div class="toggle-switch">
                            <input type="checkbox" id="is_restriction" name="is_restriction" class="toggle-input" <?php echo ($current_setting->is_restriction == 1) ? 'checked' : ''; ?>>
                            <label for="is_restriction" class="toggle-label">
                                <span>On</span>
                                <span>Off</span>
                            </label>
                        </div>
                        <p><strong>Note:</strong> Useful when you wish to deactivate the plugin without uninstalling</p>

                    </fieldset>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="submit_domain" id="submit_domain" class="button button-primary" value="Submit">
        </p>
        <h3>
            Want more flexibility and control over your site signups? Stay tuned for the Secure Signups Pro plugin release. Join the waitlist <a href="https://forms.gle/5ssm5t1ANYFtfrUE9" target="_blank">here</a>
        </h3>
    </form>
    <div id="save-message" class="alert" style="display: none;"></div>
</div>
<script>
    jQuery(document).ready(function($) {
        $('#secure-signups-settings-form').submit(function(e) {
            e.preventDefault();
            var formData = $(this).serialize();
            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: formData + '&action=save_secure_signups_settings',
                success: function(response) {
                    if (response.success) {
                        $('#save-message').removeClass().addClass('alert alert-success').html(response.data).show();
                        setTimeout(function() {
                            $('#save-message').empty().hide();
                        }, 5000);

                        loadDomainList();
                    } else {
                        $('#save-message').removeClass().addClass('alert alert-warning').html(response.data).show();
                        setTimeout(function() {
                            $('#save-message').empty().hide();
                        }, 5000);
                    }
                },
                error: function(errorThrown) {
                    $('#save-message').removeClass().addClass('alert alert-warning').html('Error occurred while saving settings.').show();
                    setTimeout(function() {
                        $('#save-message').empty().hide();
                    }, 5000);
                }
            });
        });
    });
</script>
