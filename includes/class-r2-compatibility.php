<?php
/**
 * Compatibility class
 *
 * @package R2_Media_Offload
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Compatibility class
 */
class R2_Compatibility {
    /**
     * Check if the system is compatible with the plugin.
     *
     * @return bool
     */
    public static function is_compatible() {
        $compatible = true;
        $errors = array();

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $compatible = false;
            $errors[] = sprintf(
                __('R2 Media Offload requires PHP version 7.4 or higher. Your current version is %s.', 'r2-media-offload'),
                PHP_VERSION
            );
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, R2_MEDIA_OFFLOAD_MINIMUM_WP_VERSION, '<')) {
            $compatible = false;
            $errors[] = sprintf(
                __('R2 Media Offload requires WordPress version %s or higher. Your current version is %s.', 'r2-media-offload'),
                R2_MEDIA_OFFLOAD_MINIMUM_WP_VERSION,
                $wp_version
            );
        }

        // Check required PHP extensions
        $required_extensions = array(
            'curl',
            'json',
            'mbstring',
            'xml',
            'simplexml'
        );

        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $compatible = false;
                $errors[] = sprintf(
                    __('R2 Media Offload requires the PHP %s extension.', 'r2-media-offload'),
                    $extension
                );
            }
        }

        // Check if WordPress upload directory is writable
        $upload_dir = wp_upload_dir();
        if (!wp_is_writable($upload_dir['basedir'])) {
            $compatible = false;
            $errors[] = __('WordPress upload directory is not writable.', 'r2-media-offload');
        }

        // Store errors in a transient for later display
        if (!empty($errors)) {
            set_transient('r2_media_offload_activation_errors', $errors, 5 * MINUTE_IN_SECONDS);
        }

        return $compatible;
    }

    /**
     * Display activation errors.
     */
    public static function display_activation_errors() {
        $errors = get_transient('r2_media_offload_activation_errors');
        if ($errors) {
            delete_transient('r2_media_offload_activation_errors');
            foreach ($errors as $error) {
                echo '<div class="error"><p>' . esc_html($error) . '</p></div>';
            }
        }
    }
} 