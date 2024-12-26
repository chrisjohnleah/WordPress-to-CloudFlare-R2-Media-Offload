<?php
/*
Plugin Name: R2 Media Offload
Plugin URI: https://github.com/andrejsrna/R2-Media-Offload
Description: Offload WordPress media uploads to R2-compatible object storage for efficient and cost-effective storage.
Version: 1.0
Author: Andrej Srna
Author URI: https://andrejsrna.sk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: r2-media-offload
Domain Path: /languages
*/

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Debug function
function r2_debug_log($message) {
    error_log('[R2 Media Offload] ' . $message);
}

r2_debug_log('Plugin initialization started');

if (!defined('ABSPATH')) {
    r2_debug_log('ABSPATH not defined - direct access attempt');
    exit; // Exit if accessed directly
}

// Define plugin constants
if (!defined('R2_MEDIA_OFFLOAD_VERSION')) {
    define('R2_MEDIA_OFFLOAD_VERSION', '1.0');
}
if (!defined('R2_MEDIA_OFFLOAD_MINIMUM_WP_VERSION')) {
    define('R2_MEDIA_OFFLOAD_MINIMUM_WP_VERSION', '5.3');
}
if (!defined('R2_MEDIA_OFFLOAD_PLUGIN_DIR')) {
    define('R2_MEDIA_OFFLOAD_PLUGIN_DIR', trailingslashit(plugin_dir_path(__FILE__)));
}
if (!defined('R2_MEDIA_OFFLOAD_PLUGIN_URL')) {
    define('R2_MEDIA_OFFLOAD_PLUGIN_URL', trailingslashit(plugin_dir_url(__FILE__)));
}
if (!defined('R2_MEDIA_OFFLOAD_PLUGIN_BASENAME')) {
    define('R2_MEDIA_OFFLOAD_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

r2_debug_log('Constants defined');

// Load required files first
require_once R2_MEDIA_OFFLOAD_PLUGIN_DIR . 'includes/functions.php';
require_once R2_MEDIA_OFFLOAD_PLUGIN_DIR . 'includes/logging.php';
require_once R2_MEDIA_OFFLOAD_PLUGIN_DIR . 'includes/class-r2-compatibility.php';
require_once R2_MEDIA_OFFLOAD_PLUGIN_DIR . 'includes/class-r2-settings.php';
require_once R2_MEDIA_OFFLOAD_PLUGIN_DIR . 'includes/class-r2-uploader.php';
require_once R2_MEDIA_OFFLOAD_PLUGIN_DIR . 'includes/class-r2-media-library.php';
require_once R2_MEDIA_OFFLOAD_PLUGIN_DIR . 'admin/class-r2-admin.php';

r2_debug_log('Core files loaded');

// Load Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    r2_debug_log('Loading Composer autoloader');
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Fallback for direct installation without Composer
    if (!class_exists('Aws\S3\S3Client')) {
        r2_debug_log('Loading AWS SDK autoloader');
        require_once __DIR__ . '/aws-sdk/aws-autoloader.php';
    }
}

// Load the main plugin class
r2_debug_log('Loading main plugin class');
require_once R2_MEDIA_OFFLOAD_PLUGIN_DIR . 'includes/class-r2-media-offload.php';

/**
 * Main instance of R2 Media Offload.
 *
 * Returns the main instance of R2_Media_Offload to prevent the need to use globals.
 *
 * @return R2_Media_Offload
 */
function R2_Media_Offload() {
    static $instance = null;
    
    if (is_null($instance)) {
        r2_debug_log('Creating new plugin instance');
        $instance = R2_Media_Offload::instance();
    }
    
    return $instance;
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    r2_debug_log('plugins_loaded action triggered');
    R2_Media_Offload();
});

// Register activation hook
register_activation_hook(__FILE__, 'r2_media_offload_activate');

/**
 * Plugin activation callback.
 */
function r2_media_offload_activate() {
    r2_debug_log('Plugin activation started');
    
    // Check compatibility
    if (!R2_Compatibility::is_compatible()) {
        r2_debug_log('Compatibility check failed');
        deactivate_plugins(R2_MEDIA_OFFLOAD_PLUGIN_BASENAME);
        wp_die(
            __('R2 Media Offload could not be activated. Please check the system requirements.', 'r2-media-offload'),
            __('Plugin Activation Error', 'r2-media-offload'),
            array('back_link' => true)
        );
    }
    
    r2_debug_log('Plugin activated successfully');
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'r2_media_offload_deactivate');

/**
 * Plugin deactivation callback.
 */
function r2_media_offload_deactivate() {
    r2_debug_log('Plugin deactivation started');
    
    // Get all attachments that have been offloaded to R2
    $args = array(
        'post_type'      => 'attachment',
        'posts_per_page' => 20, // Process in smaller batches
        'post_status'    => 'any',
        'meta_query'     => array(
            array(
                'key'     => '_cloudflare_r2_url',
                'compare' => 'EXISTS',
            ),
        ),
    );
    
    $page = 1;
    do {
        $args['paged'] = $page;
        $attachments = get_posts($args);
        
        if (empty($attachments)) {
            break;
        }
        
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            $upload_dir = wp_upload_dir();
            $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);

            // Download file from R2 if it doesn't exist locally
            if (!file_exists($file_path)) {
                wp_mkdir_p(dirname($file_path));
                R2_Media_Offload()->uploader()->download_file($object_key, $file_path);
            }

            // Download thumbnails
            $metadata = wp_get_attachment_metadata($attachment->ID);
            if (!empty($metadata['sizes'])) {
                $base_dir = dirname($file_path) . '/';
                foreach ($metadata['sizes'] as $size => $size_info) {
                    $thumb_path = $base_dir . $size_info['file'];
                    $thumb_key = str_replace(trailingslashit($upload_dir['basedir']), '', $thumb_path);
                    
                    if (!file_exists($thumb_path)) {
                        R2_Media_Offload()->uploader()->download_file($thumb_key, $thumb_path);
                    }
                }
            }

            // Remove R2 URL from metadata
            delete_post_meta($attachment->ID, '_cloudflare_r2_url');
            
            // Clean up memory
            wp_cache_delete($attachment->ID, 'posts');
            wp_cache_delete($attachment->ID, 'post_meta');
        }
        
        // Clean up memory
        unset($attachments);
        wp_cache_flush();
        
        $page++;
    } while (true);
    
    r2_debug_log('Plugin deactivation completed');
}

// Add notice to warn users about deactivation
add_action('admin_notices', 'r2_media_offload_deactivation_warning');

/**
 * Display deactivation warning.
 */
function r2_media_offload_deactivation_warning() {
    $screen = get_current_screen();
    if ($screen->id === 'plugins') {
        ?>
        <div class="notice notice-warning">
            <p><?php _e('When deactivating R2 Media Offload, the plugin will attempt to download all media files from R2 back to your server. Please ensure you have enough disk space available.', 'r2-media-offload'); ?></p>
        </div>
        <?php
    }
}

r2_debug_log('Plugin initialization completed');


