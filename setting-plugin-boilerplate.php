<?php
/**
 * Plugin Name: Settings Plugin Boilderplate
 * Description: A boilerplate plugin with tabbed admin settings, REST API support, and data management.
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WP_Plugin_Boilerplate
{
    private $options;
    private $option_name = 'wp_plugin_settings';
    private $default_options = [];
    private $cached_options = null; // Cache variable for options
    private $default_schema;

    public function __construct()
    {
        // Set the default options using a filter to allow customization
        // error_log('Initializing default options');
        $this->default_schema = apply_filters('wp_plugin_option_schema', [
            'type' => 'object',
            'properties' => [
                'general' => [
                    'type' => 'object',
                    'properties' => [
                        'site_name' => [
                            'type' => 'string',
                            'description' => 'The name of the site.',
                            'default' => '',
                        ],
                        'email_id' => [
                            'type' => 'string',
                            'description' => 'Email ID of the site.',
                            'sanitize_callback' => 'sanitize_email',
                            'default' => '',
                        ],
                        'enable_feature' => [
                            'type' => 'boolean',
                            'description' => 'Enable or disable the feature.',
                            'default' => false,
                        ],
                    ],
                ],
                'advanced' => [
                    'type' => 'object',
                    'properties' => [
                        'api_key' => [
                            'type' => 'string',
                            'description' => 'API key used for authentication.',
                            'default' => '',
                        ],
                        'cache_duration' => [
                            'type' => 'integer',
                            'description' => 'Cache duration in minutes.',
                            'default' => 60,
                        ],
                    ],
                ],
            ],
        ]);

        // Add actions to initialize the plugin's admin menu, settings, and REST API endpoints
        add_action('admin_enqueue_scripts', [$this, 'plugin_script_style']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('rest_api_init', [$this, 'register_rest_api_endpoints']);
    }

    public function plugin_script_style($hook)
    {
        if ($hook != 'toplevel_page_wp_plugin_settings') {
            return;
        }

        wp_register_style('plugin-setting', plugin_dir_url(__FILE__) . 'dist/plugin-settings.css', [], '1.0', 'all');
        wp_register_script('plugin-preline', plugin_dir_url(__FILE__) . 'node_modules/preline/dist/preline.js', [], '1.0', true);

        wp_enqueue_style('plugin-setting');
        wp_enqueue_script('plugin-preline');
    }

    // Adds the plugin's admin menu page
    public function add_admin_menu()
    {
        // error_log('Adding admin menu');
        add_menu_page(
            'Plugin Setting Options',
            'WP Setting Plugins',
            'manage_options',
            'wp_plugin_settings',
            [$this, 'settings_page']
        );
    }

    // Registers the plugin's settings with validation callback and default values
    public function register_settings()
    {
        // error_log('Registering settings');
        register_setting('wp_plugin_settings_group', $this->option_name, [
            'default' => $this->default_schema['properties'],
            'sanitize_callback' => [$this, 'validate_options']
        ]);
    }

    // Displays an error notice if validation fails
    private function settings_error_notice($field, $message)
    {
        add_settings_error('wp_plugin_settings', $field, $message, 'error');
    }

    // Renders the settings page with tabbed navigation for General and Advanced settings
    private function settings_page()
    {
        // Fetch the current options, or use default values if not set
        // error_log('Rendering settings page');
        // delete_transient('wp_plugin_settings_cache');

        $this->options = $this->get_transient_cached_options();
        ?>
        <form method="post" action="options.php" class="wrap space-y-8 font-sans" id="plugin-settings">
            <header class="plugin-header">
                <h1>WP Plugin Settings</h1>
                <p>Manage your plugin options</p>
            </header>
            <hr>
            <?php
            if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
                echo set_toast();
            }
            ?>

            <!-- Tab Navigation -->
            <nav class="flex gap-x-1 bg-white rounded-md w-fit" aria-label="Tabs" role="tablist" aria-orientation="horizontal">
                <?php foreach ($this->default_schema['properties'] as $section => $section_schema): ?>
                    <button type="button"
                        class="hs-tab-active:bg-[#2271b1] hs-tab-active:text-white hs-tab-active:hover:text-white py-3 px-4 inline-flex items-center gap-x-2 bg-transparent text-lg font-medium text-center text-gray-500 hover:text-[#2271b1] focus:outline-none focus:text-[#2271b1] rounded-md disabled:opacity-50 disabled:pointer-events-none <?php echo $section === 'general' ? 'active' : ''; ?>"
                        id="<?php echo $section; ?>-tab-items"
                        aria-selected="<?php echo $section === 'general' ? 'true' : 'false'; ?>"
                        data-hs-tab="#<?php echo $section; ?>-tab" aria-controls="<?php echo $section; ?>-tab" role="tab">
                        <?php echo ucwords($section); ?>
                    </button>
                <?php endforeach; ?>
            </nav>

            <?php settings_fields('wp_plugin_settings_group'); ?>
            <?php wp_nonce_field('wp_plugin_settings_nonce_action', 'wp_plugin_settings_nonce'); ?>

            <!-- Tab Content Container -->
            <div class="my-6 border rounded-md p-6 shadow-md bg-white">
                <?php foreach ($this->default_schema['properties'] as $section => $section_schema): ?>
                    <div id="<?php echo $section; ?>-tab" role="tabpanel" aria-labelledby="<?php echo $section; ?>-tab-items"
                        class="<?php echo $section === 'general' ? '' : 'hidden'; ?> space-y-5 divide-y">
                        <h3 class="text-2xl font-semibold text-[#2271b1]"><?php echo ucwords($section); ?> Settings</h3>
                        <table class="form-table overflow-y-auto">
                            <?php foreach ($section_schema['properties'] as $field => $field_schema): ?>
                                <tr valign="top">
                                    <th scope="row"><?php echo esc_html($field_schema['description']); ?></th>
                                    <td>
                                        <?php
                                        $field_name = esc_attr($this->option_name . "[$section][$field]");
                                        $field_value = isset($this->options[$section][$field]) ? $this->options[$section][$field] : $field_schema['default'];

                                        switch ($field_schema['type']) {
                                            case 'string':
                                                ?>
                                                <input type="text" name="<?php echo $field_name; ?>"
                                                    value="<?php echo esc_attr($field_value); ?>" />
                                                <?php
                                                break;

                                            case 'integer':
                                                ?>
                                                <input type="number" name="<?php echo $field_name; ?>"
                                                    value="<?php echo esc_attr($field_value); ?>" />
                                                <?php
                                                break;

                                            case 'boolean':
                                                ?>
                                                <input type="checkbox" id="<?php echo esc_attr($field_name); ?>" class="plugin-cb"
                                                    name="<?php echo $field_name; ?>" <?php checked(true, (bool) $field_value); ?> />
                                                <label for="<?php echo esc_attr($field_name); ?>"
                                                    class="text-sm text-gray-500 ml-3"><?php echo $field_schema['description']; ?></label>
                                                <?php
                                                break;
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Submit Button -->
            <div class="actions">
                <?php submit_button(); ?>
            </div>


        </form>
        <?php
    }

    // Registers the REST API endpoints for getting and updating plugin settings
    public function register_rest_api_endpoints()
    {
        // error_log('Registering REST API endpoints');

        register_rest_route('wp-plugin/v1', '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => function () {
                return true;
                // return current_user_can('manage_options');
            },
        ]);

        register_rest_route('wp-plugin/v1', '/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => $this->default_schema['properties'], // Use schema directly
        ]);
    }

    // Callback function for the GET REST API endpoint to retrieve settings
    public function get_settings()
    {
        // error_log('Getting settings via REST API');
        return rest_ensure_response($this->get_transient_cached_options());
    }

    // Callback function for the POST REST API endpoint to update settings
    public function update_settings(WP_REST_Request $request)
    {
        // error_log(print_r($request, true));
        // error_log('Updating settings via REST API');
        $params = $request->get_json_params();
        $validated = $this->validate_options($params);
        update_option($this->option_name, $validated);
        delete_transient('wp_plugin_settings_cache'); // Clear transient cache
        $this->cached_options = $validated; // Update cache
        set_transient('wp_plugin_settings_cache', $validated, 12 * HOUR_IN_SECONDS); // Update transient cache
        return rest_ensure_response($validated);
    }

    // Validates the options before saving them to the database
    public function validate_options($input)
    {
        error_log(print_r($input, true));
        // error_log(print_r($input, true));
        if (!isset($_POST['wp_plugin_settings_nonce']) || !check_admin_referer('wp_plugin_settings_nonce_action', 'wp_plugin_settings_nonce')) {
            wp_die('Nonce check failed in validation');
        }
        $this->options = $this->get_transient_cached_options();

        foreach ($this->default_schema['properties'] as $section => $section_schema) {
            if (isset($input[$section]) && $section_schema['type'] === 'object') {
                foreach ($section_schema['properties'] as $field => $field_schema) {
                    if (!isset($input[$section][$field])) {
                        $input[$section][$field] = $field_schema['default'];
                    } else {
                        if (isset($field_schema['sanitize_callback']) && is_callable($field_schema['sanitize_callback']) && !empty($input[$section][$field])) {
                            $sanitized_value = $field_schema['sanitize_callback']($input[$section][$field]);
                            if ($sanitized_value === '') {
                                $this->settings_error_notice($field, "Invalid value for {$field} in section {$section}.");
                                $input[$section][$field] = $this->options[$section][$field] ?: $field_schema['default'];
                            } else {
                                $input[$section][$field] = $sanitized_value;
                            }
                        } else {
                            // Apply basic sanitization based on field type
                            switch ($field_schema['type']) {
                                case 'string':
                                    $input[$section][$field] = sanitize_text_field($input[$section][$field]);
                                    break;
                                case 'boolean':
                                    $input[$section][$field] = (bool) $input[$section][$field];
                                    break;
                                case 'integer':
                                    $input[$section][$field] = absint($input[$section][$field]);
                                    break;
                                default:
                                    $this->settings_error_notice($field, "Invalid value for {$field} in section {$section}.");
                                    $input[$section][$field] = $field_schema['default'];
                                    break;
                            }
                        }
                    }
                }
            }
        }

        delete_transient('wp_plugin_settings_cache');

        return $input;
    }

    // Returns the cached options or fetches them from the transient/database if not cached
    private function get_transient_cached_options()
    {
        if (is_null($this->cached_options)) {
            $cached = get_transient('wp_plugin_settings_cache');
            if ($cached !== false) {
                // error_log('Fetching options from transient cache');
                $this->cached_options = $cached;
            } else {
                // error_log('Fetching options from database');
                $this->cached_options = get_option($this->option_name, $this->default_options);
                set_transient('wp_plugin_settings_cache', $this->cached_options, 12 * HOUR_IN_SECONDS);
            }
        }
        return $this->cached_options;
    }
}


function set_toast()
{
    // Check for settings errors or success status
    $errors = get_settings_errors('wp_plugin_settings');
    $is_success = isset($_GET['settings-updated']) && $_GET['settings-updated'] && empty($errors);

    // Define the message and style based on the status
    $message = $is_success ? 'Settings saved successfully!' : (isset($errors[0]) ? $errors[0]['message'] : 'An error occurred.');
    $bg_color = $is_success ? 'bg-teal-100 border-teal-200 text-teal-800' : 'bg-red-100 border-red-200 text-red-800';

    ?>
    <div id="dismiss-toast"
        class="absolute top-0 end-0 hs-removing:translate-x-5 hs-removing:opacity-0 transition duration-300 max-w-md w-full <?php echo $bg_color; ?> text-sm rounded-lg shadow-lg"
        role="alert" tabindex="-1" aria-labelledby="hs-toast-dismiss-button-label">
        <div class="flex p-4">
            <p id="hs-toast-dismiss-button-label" class="text-sm text-gray-700">
                <?php echo esc_html($message); ?>
            </p>

            <div class="ms-auto">
                <button type="button"
                    class="inline-flex shrink-0 justify-center items-center size-5 rounded-lg text-gray-800 opacity-50 hover:opacity-100 focus:outline-none focus:opacity-100"
                    aria-label="Close" data-hs-remove-element="#dismiss-toast">
                    <span class="sr-only">Close</span>
                    <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
<?php
}


// Initialize the plugin
new WP_Plugin_Boilerplate();