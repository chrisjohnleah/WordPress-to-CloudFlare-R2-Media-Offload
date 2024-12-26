<?php
/**
 * Settings class
 *
 * @package R2_Media_Offload
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Settings class
 */
class R2_Settings {
    /**
     * Settings array.
     *
     * @var array
     */
    private $settings = null;

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting('r2_media_offload_settings', 'r2_media_offload_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings'),
        ));
    }

    /**
     * Sanitize settings.
     *
     * @param array $input Settings array.
     * @return array
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['endpoint'])) {
            $sanitized['endpoint'] = esc_url_raw($input['endpoint']);
        }

        if (isset($input['access_key'])) {
            $sanitized['access_key'] = sanitize_text_field($input['access_key']);
        }

        if (isset($input['secret_key'])) {
            $sanitized['secret_key'] = sanitize_text_field($input['secret_key']);
        }

        if (isset($input['bucket_name'])) {
            $sanitized['bucket_name'] = sanitize_text_field($input['bucket_name']);
        }

        if (isset($input['public_bucket_url'])) {
            $sanitized['public_bucket_url'] = esc_url_raw(rtrim($input['public_bucket_url'], '/'));
        }

        if (isset($input['keep_local_media'])) {
            $sanitized['keep_local_media'] = sanitize_text_field($input['keep_local_media']);
        }

        return $sanitized;
    }

    /**
     * Get all settings.
     *
     * @return array
     */
    public function get_all_settings() {
        if (is_null($this->settings)) {
            $this->settings = get_option('r2_media_offload_settings', array());
        }
        return $this->settings;
    }

    /**
     * Get a single setting.
     *
     * @param string $key Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_setting($key, $default = '') {
        $settings = $this->get_all_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Check if all required settings are configured.
     *
     * @return bool
     */
    public function is_configured() {
        $required_settings = array(
            'endpoint',
            'access_key',
            'secret_key',
            'bucket_name',
            'public_bucket_url'
        );

        $settings = $this->get_all_settings();
        foreach ($required_settings as $key) {
            if (empty($settings[$key])) {
                return false;
            }
        }

        return true;
    }
} 