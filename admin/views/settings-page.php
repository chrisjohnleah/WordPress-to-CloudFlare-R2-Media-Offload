<?php
/**
 * Settings page template
 *
 * @package R2_Media_Offload
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$settings = R2_Media_Offload()->settings();
$config = $settings->get_all_settings();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="r2-media-offload-settings">
        <form method="post" action="options.php">
            <?php settings_fields('r2_media_offload_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="r2_media_offload_settings[endpoint]">
                            <?php esc_html_e('R2 Endpoint URL', 'r2-media-offload'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="url" id="r2_media_offload_settings[endpoint]" 
                            name="r2_media_offload_settings[endpoint]" 
                            value="<?php echo esc_attr($config['endpoint'] ?? ''); ?>" 
                            class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Your Cloudflare R2 endpoint URL.', 'r2-media-offload'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="r2_media_offload_settings[access_key]">
                            <?php esc_html_e('Access Key', 'r2-media-offload'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="r2_media_offload_settings[access_key]" 
                            name="r2_media_offload_settings[access_key]" 
                            value="<?php echo esc_attr($config['access_key'] ?? ''); ?>" 
                            class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Your Cloudflare R2 access key.', 'r2-media-offload'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="r2_media_offload_settings[secret_key]">
                            <?php esc_html_e('Secret Key', 'r2-media-offload'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="password" id="r2_media_offload_settings[secret_key]" 
                            name="r2_media_offload_settings[secret_key]" 
                            value="<?php echo esc_attr($config['secret_key'] ?? ''); ?>" 
                            class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Your Cloudflare R2 secret key.', 'r2-media-offload'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="r2_media_offload_settings[bucket_name]">
                            <?php esc_html_e('Bucket Name', 'r2-media-offload'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="r2_media_offload_settings[bucket_name]" 
                            name="r2_media_offload_settings[bucket_name]" 
                            value="<?php echo esc_attr($config['bucket_name'] ?? ''); ?>" 
                            class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Your Cloudflare R2 bucket name.', 'r2-media-offload'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="r2_media_offload_settings[public_bucket_url]">
                            <?php esc_html_e('Public Bucket URL', 'r2-media-offload'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="url" id="r2_media_offload_settings[public_bucket_url]" 
                            name="r2_media_offload_settings[public_bucket_url]" 
                            value="<?php echo esc_attr($config['public_bucket_url'] ?? ''); ?>" 
                            class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Your Cloudflare R2 public bucket URL.', 'r2-media-offload'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="r2_media_offload_settings[keep_local_media]">
                            <?php esc_html_e('Keep Local Media', 'r2-media-offload'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="r2_media_offload_settings[keep_local_media]" 
                            name="r2_media_offload_settings[keep_local_media]">
                            <option value="yes" <?php selected($config['keep_local_media'] ?? 'yes', 'yes'); ?>>
                                <?php esc_html_e('Yes', 'r2-media-offload'); ?>
                            </option>
                            <option value="no" <?php selected($config['keep_local_media'] ?? 'yes', 'no'); ?>>
                                <?php esc_html_e('No', 'r2-media-offload'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Keep local copies of media files after uploading to R2.', 'r2-media-offload'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <?php if ($settings->is_configured()): ?>
            <div class="r2-media-offload-actions">
                <h2><?php esc_html_e('Media Management', 'r2-media-offload'); ?></h2>

                <div class="r2-media-offload-stats">
                    <p>
                        <?php
                        printf(
                            /* translators: 1: Number of files, 2: Total size */
                            esc_html__('Total offloaded files: %1$d (%2$s)', 'r2-media-offload'),
                            r2_get_total_offloaded_files(),
                            r2_get_total_offloaded_size()
                        );
                        ?>
                    </p>
                </div>

                <div class="r2-media-offload-buttons">
                    <button type="button" class="button button-primary" id="r2-migrate-media">
                        <?php esc_html_e('Migrate Media to R2', 'r2-media-offload'); ?>
                    </button>

                    <button type="button" class="button" id="r2-revert-media">
                        <?php esc_html_e('Revert Media from R2', 'r2-media-offload'); ?>
                    </button>

                    <button type="button" class="button" id="r2-reupload-media">
                        <?php esc_html_e('Re-upload Missing Media', 'r2-media-offload'); ?>
                    </button>

                    <button type="button" class="button" id="r2-delete-local-media">
                        <?php esc_html_e('Delete Local Media Files', 'r2-media-offload'); ?>
                    </button>
                </div>

                <div class="r2-media-offload-progress" style="display: none;">
                    <div class="r2-media-offload-progress-bar">
                        <div class="r2-media-offload-progress-bar-inner"></div>
                    </div>
                    <div class="r2-media-offload-progress-text"></div>
                </div>
            </div>

            <hr>

            <div class="r2-media-offload-system-info">
                <h2><?php esc_html_e('System Information', 'r2-media-offload'); ?></h2>
                <table class="widefat" style="margin-top: 1em;">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('PHP Version', 'r2-media-offload'); ?></strong></td>
                            <td><?php echo esc_html(PHP_VERSION); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('WordPress Version', 'r2-media-offload'); ?></strong></td>
                            <td><?php echo esc_html($GLOBALS['wp_version']); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Plugin Version', 'r2-media-offload'); ?></strong></td>
                            <td><?php echo esc_html(R2_MEDIA_OFFLOAD_VERSION); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('cURL Version', 'r2-media-offload'); ?></strong></td>
                            <td><?php echo esc_html(function_exists('curl_version') ? curl_version()['version'] : __('Not Available', 'r2-media-offload')); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Memory Limit', 'r2-media-offload'); ?></strong></td>
                            <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Upload Max Filesize', 'r2-media-offload'); ?></strong></td>
                            <td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Post Max Size', 'r2-media-offload'); ?></strong></td>
                            <td><?php echo esc_html(ini_get('post_max_size')); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Max Execution Time', 'r2-media-offload'); ?></strong></td>
                            <td><?php echo esc_html(ini_get('max_execution_time')); ?> <?php esc_html_e('seconds', 'r2-media-offload'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if (WP_DEBUG): ?>
                <hr>
                <div class="r2-media-offload-debug-log">
                    <h2><?php esc_html_e('Debug Log', 'r2-media-offload'); ?></h2>
                    <div class="r2-media-offload-log-viewer" style="background: #fff; padding: 10px; border: 1px solid #ccd0d4; max-height: 300px; overflow-y: auto;">
                        <?php
                        $log_file = WP_CONTENT_DIR . '/debug.log';
                        if (file_exists($log_file) && is_readable($log_file)) {
                            $logs = array_slice(array_filter(
                                array_map('trim', file($log_file)),
                                function($line) {
                                    return strpos($line, '[R2 Media Offload]') !== false;
                                }
                            ), -50);
                            
                            if (!empty($logs)) {
                                echo '<pre style="margin: 0; white-space: pre-wrap;">';
                                foreach ($logs as $log) {
                                    echo esc_html($log) . "\n";
                                }
                                echo '</pre>';
                            } else {
                                echo '<p>' . esc_html__('No R2 Media Offload logs found.', 'r2-media-offload') . '</p>';
                            }
                        } else {
                            echo '<p>' . esc_html__('Debug log file not found or not readable.', 'r2-media-offload') . '</p>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div> 