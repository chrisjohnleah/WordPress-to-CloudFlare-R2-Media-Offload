<?php
/**
 * Admin class
 *
 * @package R2_Media_Offload
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Admin class
 */
class R2_Admin {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_r2_migrate_media', array($this, 'migrate_media'));
        add_action('wp_ajax_r2_revert_media', array($this, 'revert_media'));
        add_action('wp_ajax_r2_reupload_media', array($this, 'reupload_media'));
        add_action('wp_ajax_r2_delete_local_media', array($this, 'delete_local_media'));
        add_action('wp_ajax_r2_migrate_media_progress', array($this, 'get_migration_progress'));
        add_action('wp_ajax_r2_revert_media_progress', array($this, 'get_revert_progress'));
        add_action('wp_ajax_r2_reupload_media_progress', array($this, 'get_reupload_progress'));
        add_action('wp_ajax_r2_delete_local_media_progress', array($this, 'get_delete_progress'));
        add_action('admin_notices', array($this, 'display_notices'));
        add_filter('plugin_action_links_' . R2_MEDIA_OFFLOAD_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_options_page(
            __('R2 Media Offload Settings', 'r2-media-offload'),
            __('R2 Media Offload', 'r2-media-offload'),
            'manage_options',
            'r2-media-offload',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        R2_Media_Offload()->settings()->register_settings();
    }

    /**
     * Enqueue scripts.
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        if ($screen->id !== 'settings_page_r2-media-offload') {
            return;
        }

        wp_enqueue_style(
            'r2-media-offload-admin',
            R2_MEDIA_OFFLOAD_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            R2_MEDIA_OFFLOAD_VERSION
        );

        wp_enqueue_script(
            'r2-media-offload-admin',
            R2_MEDIA_OFFLOAD_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            R2_MEDIA_OFFLOAD_VERSION,
            true
        );

        wp_localize_script('r2-media-offload-admin', 'r2MediaOffload', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('r2-media-offload'),
            'strings' => array(
                'confirmMigrate' => __('Are you sure you want to migrate all media files to R2? This process cannot be undone.', 'r2-media-offload'),
                'confirmRevert' => __('Are you sure you want to revert all media files from R2? This will download files back to your server.', 'r2-media-offload'),
                'confirmReupload' => __('Are you sure you want to re-upload missing media files to R2?', 'r2-media-offload'),
                'confirmDeleteLocal' => __('Are you sure you want to delete local media files that have been uploaded to R2? This action cannot be undone.', 'r2-media-offload'),
                'migrationComplete' => __('Migration complete!', 'r2-media-offload'),
                'revertComplete' => __('Revert complete!', 'r2-media-offload'),
                'reuploadComplete' => __('Re-upload complete!', 'r2-media-offload'),
                'deleteLocalComplete' => __('Local files deleted!', 'r2-media-offload'),
                'error' => __('An error occurred:', 'r2-media-offload'),
                'unknownError' => __('An unknown error occurred. Please check the debug log for more information.', 'r2-media-offload'),
            ),
        ));
    }

    /**
     * Add settings link to plugin list.
     *
     * @param array $links Plugin action links.
     * @return array
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=r2-media-offload'),
            __('Settings', 'r2-media-offload')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once R2_MEDIA_OFFLOAD_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Migrate media to R2.
     */
    public function migrate_media() {
        try {
            check_ajax_referer('r2-media-offload', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Permission denied.', 'r2-media-offload'));
            }

            // Check if R2 settings are configured
            $settings = R2_Media_Offload()->settings();
            if (!$settings->is_configured()) {
                wp_send_json_error(__('Please configure R2 settings before migrating media.', 'r2-media-offload'));
            }

            $access_key = $settings->get_setting('access_key');
            $secret_key = $settings->get_setting('secret_key');
            $bucket = $settings->get_setting('bucket_name');
            $endpoint = $settings->get_setting('endpoint');

            if (empty($access_key) || empty($secret_key) || empty($bucket) || empty($endpoint)) {
                wp_send_json_error(__('R2 credentials are not properly configured.', 'r2-media-offload'));
            }

            $batch_size = 5; // Reduced batch size
            $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;

            global $wpdb;

            // Get total count for this batch only
            $attachments = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} p 
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_cloudflare_r2_url'
                WHERE post_type = 'attachment' 
                AND pm.meta_value IS NULL
                LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            ));

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            if (empty($attachments)) {
                wp_send_json_success(array(
                    'complete' => true,
                    'processed' => $offset,
                ));
            }

            foreach ($attachments as $attachment_id) {
                try {
                    // Get metadata
                    $metadata = wp_get_attachment_metadata($attachment_id);
                    
                    if ($metadata) {
                        // Process the attachment
                        wp_update_attachment_metadata($attachment_id, $metadata);
                        
                        // Clear memory after each file
                        clean_post_cache($attachment_id);
                        wp_cache_delete($attachment_id, 'posts');
                        wp_cache_delete($attachment_id, 'post_meta');
                    }
                    
                    // Give the system a small break
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                    
                    usleep(100000); // 100ms pause between files
                } catch (Exception $e) {
                    r2_debug_log('Error processing attachment ' . $attachment_id . ': ' . $e->getMessage());
                    continue; // Continue with next attachment even if one fails
                }
            }

            wp_send_json_success(array(
                'complete' => false,
                'processed' => $offset + count($attachments),
            ));

        } catch (Exception $e) {
            r2_debug_log('Migration error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Revert media from R2.
     */
    public function revert_media() {
        check_ajax_referer('r2-media-offload', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'r2-media-offload'));
        }

        $batch_size = 10;
        $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;

        // Store the current offset in a transient for progress tracking
        set_transient('r2_revert_progress_offset', $offset, HOUR_IN_SECONDS);

        $args = array(
            'post_type' => 'attachment',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'post_status' => 'any',
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_cloudflare_r2_url',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $attachments = get_posts($args);
        if (empty($attachments)) {
            // Clear the progress transient when complete
            delete_transient('r2_revert_progress_offset');
            wp_send_json_success(array(
                'complete' => true,
                'processed' => $offset,
            ));
        }

        foreach ($attachments as $attachment_id) {
            $file = get_attached_file($attachment_id);
            $metadata = wp_get_attachment_metadata($attachment_id);

            // Download original file
            if (!file_exists($file)) {
                R2_Media_Offload()->uploader()->download_file(
                    r2_get_relative_upload_path($file),
                    $file
                );
            }

            // Download thumbnails
            if (!empty($metadata['sizes'])) {
                $upload_dir = wp_upload_dir();
                $base_dir = dirname($file) . '/';

                foreach ($metadata['sizes'] as $size) {
                    $thumb_path = $base_dir . $size['file'];
                    if (!file_exists($thumb_path)) {
                        R2_Media_Offload()->uploader()->download_file(
                            r2_get_relative_upload_path($thumb_path),
                            $thumb_path
                        );
                    }
                }
            }

            delete_post_meta($attachment_id, '_cloudflare_r2_url');
        }

        wp_send_json_success(array(
            'complete' => false,
            'processed' => $offset + count($attachments),
        ));
    }

    /**
     * Display admin notices.
     */
    public function display_notices() {
        if (isset($_GET['r2_message'])) {
            $type = isset($_GET['r2_type']) ? $_GET['r2_type'] : 'success';
            $class = 'notice notice-' . $type . ' is-dismissible';
            $message = '';

            switch ($_GET['r2_message']) {
                case 'migration_complete':
                    $message = __('Media migration to R2 completed successfully.', 'r2-media-offload');
                    break;
                case 'revert_complete':
                    $message = __('Media revert from R2 completed successfully.', 'r2-media-offload');
                    break;
                case 'reupload_complete':
                    $message = __('Missing media re-upload completed successfully.', 'r2-media-offload');
                    break;
                case 'delete_local_complete':
                    $message = __('Local media files have been deleted successfully.', 'r2-media-offload');
                    break;
                case 'error':
                    $message = isset($_GET['r2_error']) ? urldecode($_GET['r2_error']) : __('An error occurred.', 'r2-media-offload');
                    break;
            }

            if ($message) {
                printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
            }
        }

        // Display compatibility warnings
        R2_Compatibility::display_activation_errors();
    }

    /**
     * Re-upload missing media to R2.
     */
    public function reupload_media() {
        check_ajax_referer('r2-media-offload', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'r2-media-offload'));
        }

        $batch_size = 10;
        $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;

        $args = array(
            'post_type' => 'attachment',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'post_status' => 'any',
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_cloudflare_r2_url',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => '_wp_attached_file',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $attachments = get_posts($args);
        if (empty($attachments)) {
            wp_send_json_success(array(
                'complete' => true,
                'processed' => $offset,
            ));
        }

        foreach ($attachments as $attachment_id) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            if ($metadata) {
                wp_update_attachment_metadata($attachment_id, $metadata);
            }
        }

        wp_send_json_success(array(
            'complete' => false,
            'processed' => $offset + count($attachments),
        ));
    }

    /**
     * Delete local media files that have been uploaded to R2.
     */
    public function delete_local_media() {
        check_ajax_referer('r2-media-offload', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'r2-media-offload'));
        }

        $batch_size = 10;
        $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;

        $args = array(
            'post_type' => 'attachment',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'post_status' => 'any',
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_cloudflare_r2_url',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $attachments = get_posts($args);
        if (empty($attachments)) {
            wp_send_json_success(array(
                'complete' => true,
                'processed' => $offset,
            ));
        }

        foreach ($attachments as $attachment_id) {
            $file = get_attached_file($attachment_id);
            $metadata = wp_get_attachment_metadata($attachment_id);

            // Delete original file
            if (file_exists($file)) {
                unlink($file);
            }

            // Delete thumbnails
            if (!empty($metadata['sizes'])) {
                $upload_dir = wp_upload_dir();
                $base_dir = dirname($file) . '/';

                foreach ($metadata['sizes'] as $size) {
                    $thumb_path = $base_dir . $size['file'];
                    if (file_exists($thumb_path)) {
                        unlink($thumb_path);
                    }
                }

                // Try to remove empty directory
                @rmdir($base_dir);
            }
        }

        wp_send_json_success(array(
            'complete' => false,
            'processed' => $offset + count($attachments),
        ));
    }

    /**
     * Get migration progress.
     */
    public function get_migration_progress() {
        try {
            if (!isset($_POST['nonce'])) {
                r2_debug_log('Nonce is missing from the request');
                wp_send_json_error('Nonce verification failed - missing nonce');
                return;
            }

            if (!check_ajax_referer('r2-media-offload', 'nonce', false)) {
                r2_debug_log('Nonce verification failed for nonce: ' . $_POST['nonce']);
                wp_send_json_error('Nonce verification failed - invalid nonce');
                return;
            }

            if (!current_user_can('manage_options')) {
                r2_debug_log('User does not have manage_options capability');
                wp_send_json_error(__('Permission denied.', 'r2-media-offload'));
                return;
            }

            global $wpdb;

            // Get total attachments
            $total = $wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$wpdb->posts}
                WHERE post_type = 'attachment'
                AND post_status = 'inherit'"
            );

            // Get migrated attachments
            $migrated = $wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE post_type = 'attachment'
                AND meta_key = '_cloudflare_r2_url'"
            );

            r2_debug_log('Progress check - Total: ' . $total . ', Migrated: ' . $migrated);

            wp_send_json_success(array(
                'total' => (int) $total,
                'current' => (int) $migrated,
                'percentage' => $total > 0 ? round(($migrated / $total) * 100, 2) : 0
            ));
        } catch (Exception $e) {
            r2_debug_log('Error in get_migration_progress: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get revert progress.
     */
    public function get_revert_progress() {
        try {
            if (!isset($_POST['nonce'])) {
                r2_debug_log('Nonce is missing from the request');
                wp_send_json_error('Nonce verification failed - missing nonce');
                return;
            }

            if (!check_ajax_referer('r2-media-offload', 'nonce', false)) {
                r2_debug_log('Nonce verification failed for nonce: ' . $_POST['nonce']);
                wp_send_json_error('Nonce verification failed - invalid nonce');
                return;
            }

            if (!current_user_can('manage_options')) {
                r2_debug_log('User does not have manage_options capability');
                wp_send_json_error(__('Permission denied.', 'r2-media-offload'));
                return;
            }

            global $wpdb;

            // Get initial total (this won't change during the operation)
            $total = $wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE post_type = 'attachment'
                AND meta_key = '_cloudflare_r2_url'"
            );

            // Get the current offset from the transient
            $offset = (int) get_transient('r2_revert_progress_offset');
            
            r2_debug_log('Revert progress check - Total to revert: ' . $total . ', Current offset: ' . $offset);

            wp_send_json_success(array(
                'total' => (int) $total,
                'current' => (int) $offset,
                'percentage' => $total > 0 ? round(($offset / $total) * 100, 2) : 0
            ));
        } catch (Exception $e) {
            r2_debug_log('Error in get_revert_progress: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get reupload progress.
     */
    public function get_reupload_progress() {
        try {
            if (!isset($_POST['nonce'])) {
                r2_debug_log('Nonce is missing from the request');
                wp_send_json_error('Nonce verification failed - missing nonce');
                return;
            }

            if (!check_ajax_referer('r2-media-offload', 'nonce', false)) {
                r2_debug_log('Nonce verification failed for nonce: ' . $_POST['nonce']);
                wp_send_json_error('Nonce verification failed - invalid nonce');
                return;
            }

            if (!current_user_can('manage_options')) {
                r2_debug_log('User does not have manage_options capability');
                wp_send_json_error(__('Permission denied.', 'r2-media-offload'));
                return;
            }

            global $wpdb;

            // Get total attachments without R2 URLs
            $total = $wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_cloudflare_r2_url'
                WHERE post_type = 'attachment'
                AND pm.meta_value IS NULL"
            );

            // Get attachments that have been uploaded (have R2 URL)
            $uploaded = $wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE post_type = 'attachment'
                AND meta_key = '_cloudflare_r2_url'"
            );

            r2_debug_log('Reupload progress check - Total: ' . $total . ', Uploaded: ' . $uploaded);

            wp_send_json_success(array(
                'total' => (int) $total,
                'current' => (int) $uploaded,
                'percentage' => $total > 0 ? round(($uploaded / $total) * 100, 2) : 0
            ));
        } catch (Exception $e) {
            r2_debug_log('Error in get_reupload_progress: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get delete progress.
     */
    public function get_delete_progress() {
        try {
            if (!isset($_POST['nonce'])) {
                r2_debug_log('Nonce is missing from the request');
                wp_send_json_error('Nonce verification failed - missing nonce');
                return;
            }

            if (!check_ajax_referer('r2-media-offload', 'nonce', false)) {
                r2_debug_log('Nonce verification failed for nonce: ' . $_POST['nonce']);
                wp_send_json_error('Nonce verification failed - invalid nonce');
                return;
            }

            if (!current_user_can('manage_options')) {
                r2_debug_log('User does not have manage_options capability');
                wp_send_json_error(__('Permission denied.', 'r2-media-offload'));
                return;
            }

            global $wpdb;

            // Get total attachments with R2 URLs
            $total = $wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE post_type = 'attachment'
                AND meta_key = '_cloudflare_r2_url'"
            );

            // Get attachments that have been processed (files deleted)
            $deleted = $wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE post_type = 'attachment'
                AND meta_key = '_cloudflare_r2_url'
                AND NOT EXISTS (
                    SELECT 1
                    FROM {$wpdb->postmeta} pm2
                    WHERE pm2.post_id = p.ID
                    AND pm2.meta_key = '_wp_attached_file'
                )"
            );

            r2_debug_log('Delete progress check - Total: ' . $total . ', Deleted: ' . $deleted);

            wp_send_json_success(array(
                'total' => (int) $total,
                'current' => (int) $deleted,
                'percentage' => $total > 0 ? round(($deleted / $total) * 100, 2) : 0
            ));
        } catch (Exception $e) {
            r2_debug_log('Error in get_delete_progress: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
} 