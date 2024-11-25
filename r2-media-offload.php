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
Tags: R2, media, storage, offload, S3-compatible

== Screenshots ==
1. **Media Upload Configuration**  
   Screenshot of the settings page where users can configure their R2-compatible storage credentials.

== Description ==
R2 Media Offload helps WordPress users seamlessly offload their media uploads to R2-compatible object storage solutions. By doing so, it reduces server load and leverages cost-effective and globally distributed object storage.

**Features:**
- Automatically upload media files to R2-compatible storage upon upload.
- Configure and manage storage buckets directly from WordPress.
- Support for S3-compatible APIs for streamlined integration.
- Reduce server storage usage and improve performance.

**Visit the [author's website](https://andrejsrna.sk) for more details and updates.**

== Installation ==
1. Download and install the plugin via the WordPress dashboard or manually upload it to your `wp-content/plugins` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to 'Settings > R2 Media Offload' to configure your API credentials and bucket settings.
4. Save changes and start offloading media to R2-compatible storage.

*/


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include the AWS SDK
require 'aws-sdk/aws-autoloader.php';

use Aws\S3\S3Client;

// Hook to add settings page
add_action('admin_menu', 'cloudflare_r2_offload_settings_menu');

function cloudflare_r2_offload_settings_menu() {
    add_options_page(
        'Cloudflare R2 Offload',
        'Cloudflare R2 Offload',
        'manage_options',
        'cloudflare-r2-offload',
        'cloudflare_r2_offload_settings_page'
    );
}

// Render settings page
function cloudflare_r2_offload_settings_page() {
    ?>
    <div class="wrap">
        <h1>R2 Offload Settings</h1>
        <?php if (isset($_GET['cloudflare_r2_migration']) && $_GET['cloudflare_r2_migration'] == 'success'): ?>
            <div id="message" class="updated notice is-dismissible">
                <p>Media migration to R2 completed successfully.</p>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['cloudflare_r2_local_deletion']) && $_GET['cloudflare_r2_local_deletion'] == 'success'): ?>
            <div id="message" class="updated notice is-dismissible">
                <p>Local media files have been deleted successfully.</p>
            </div>
        <?php endif; ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('cloudflare_r2_offload_settings');
            do_settings_sections('cloudflare_r2_offload_settings');
            submit_button();
            ?>
        </form>
        <hr>
        <h2>Migrate Existing Media</h2>
        <p>You can migrate your existing media library to R2.</p>
        <form method="post">
            <?php wp_nonce_field('cloudflare_r2_migrate_media', 'cloudflare_r2_migrate_media_nonce'); ?>
            <?php submit_button('Migrate Media to R2', 'primary', 'cloudflare_r2_migrate_media'); ?>
        </form>
        <hr>
        <h2>Media Management</h2>
        <p>You can manage your media files that have been migrated to R2.</p>
        <form method="post">
            <?php wp_nonce_field('cloudflare_r2_delete_local_media', 'cloudflare_r2_delete_local_media_nonce'); ?>
            <?php submit_button('Delete Local Media Files Already on R2', 'secondary', 'cloudflare_r2_delete_local_media', false, [
                'onclick' => 'return confirm("Are you sure you want to delete all local media files that have been migrated to R2? This action is irreversible.")',
            ]); ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'cloudflare_r2_offload_settings');

function cloudflare_r2_offload_settings() {
    register_setting('cloudflare_r2_offload_settings', 'cloudflare_r2_access_key');
    register_setting('cloudflare_r2_offload_settings', 'cloudflare_r2_secret_key');
    register_setting('cloudflare_r2_offload_settings', 'cloudflare_r2_bucket_name');
    register_setting('cloudflare_r2_offload_settings', 'cloudflare_r2_endpoint');
    register_setting('cloudflare_r2_offload_settings', 'cloudflare_r2_public_bucket_url');
    register_setting('cloudflare_r2_offload_settings', 'cloudflare_r2_keep_local_media');

    // Existing add_settings_section and add_settings_field calls

    add_settings_section('cloudflare_r2_settings_section', 'R2 API Settings', null, 'cloudflare_r2_offload_settings');
    
    add_settings_field('cloudflare_r2_keep_local_media', 'Keep Local Media Files', 'cloudflare_r2_keep_local_media_callback', 'cloudflare_r2_offload_settings', 'cloudflare_r2_settings_section');
    add_settings_field('cloudflare_r2_public_bucket_url', 'Public Bucket URL', 'cloudflare_r2_public_bucket_url_callback', 'cloudflare_r2_offload_settings', 'cloudflare_r2_settings_section');
    add_settings_field('cloudflare_r2_access_key', 'Access Key', 'cloudflare_r2_access_key_callback', 'cloudflare_r2_offload_settings', 'cloudflare_r2_settings_section');
    add_settings_field('cloudflare_r2_secret_key', 'Secret Key', 'cloudflare_r2_secret_key_callback', 'cloudflare_r2_offload_settings', 'cloudflare_r2_settings_section');
    add_settings_field('cloudflare_r2_bucket_name', 'Bucket Name', 'cloudflare_r2_bucket_name_callback', 'cloudflare_r2_offload_settings', 'cloudflare_r2_settings_section');
    add_settings_field('cloudflare_r2_endpoint', 'R2 Endpoint URL', 'cloudflare_r2_endpoint_callback', 'cloudflare_r2_offload_settings', 'cloudflare_r2_settings_section');
}

function cloudflare_r2_access_key_callback() {
    $value = get_option('cloudflare_r2_access_key', '');
    echo '<input type="text" name="cloudflare_r2_access_key" value="' . esc_attr($value) . '" class="regular-text">';
}

function cloudflare_r2_secret_key_callback() {
    $value = get_option('cloudflare_r2_secret_key', '');
    echo '<input type="password" name="cloudflare_r2_secret_key" value="' . esc_attr($value) . '" class="regular-text">';
}

function cloudflare_r2_bucket_name_callback() {
    $value = get_option('cloudflare_r2_bucket_name', '');
    echo '<input type="text" name="cloudflare_r2_bucket_name" value="' . esc_attr($value) . '" class="regular-text">';
}

function cloudflare_r2_endpoint_callback() {
    $value = get_option('cloudflare_r2_endpoint', 'https://<your-account-id>.r2.cloudflarestorage.com');
    echo '<input type="text" name="cloudflare_r2_endpoint" value="' . esc_attr($value) . '" class="regular-text">';
}

function cloudflare_r2_public_bucket_url_callback() {
    $value = get_option('cloudflare_r2_public_bucket_url', '');
    echo '<input type="text" name="cloudflare_r2_public_bucket_url" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">e.g., https://your-public-bucket-url.com</p>';
}

function cloudflare_r2_keep_local_media_callback() {
    $value = get_option('cloudflare_r2_keep_local_media', 'yes');
    echo '<label><input type="checkbox" name="cloudflare_r2_keep_local_media" value="yes"' . checked($value, 'yes', false) . '> Keep local copies of media files after uploading to R2</label>';
    echo '<p class="description">Uncheck to delete local media files after uploading to R2. Be cautious, as this action is irreversible.</p>';
}

add_filter('wp_generate_attachment_metadata', 'cloudflare_r2_upload_media', 10, 2);

function cloudflare_r2_upload_media($metadata, $attachment_id) {
    // Retrieve settings
    $access_key = get_option('cloudflare_r2_access_key');
    $secret_key = get_option('cloudflare_r2_secret_key');
    $bucket_name = get_option('cloudflare_r2_bucket_name');
    $endpoint = get_option('cloudflare_r2_endpoint');
    $public_bucket_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/');
    $keep_local_media = get_option('cloudflare_r2_keep_local_media', 'yes');

    if (!$access_key || !$secret_key || !$bucket_name || !$endpoint || !$public_bucket_url) {
        return $metadata; // Do not proceed without necessary credentials
    }

    // Configure S3 client
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => 'auto',
        'endpoint' => $endpoint,
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key'    => $access_key,
            'secret' => $secret_key,
        ],
    ]);

    // Get upload directory info
    $upload_dir = wp_upload_dir();

    // Get file path of the original image
    $file = get_attached_file($attachment_id);

    // Create array of files to upload (original + sizes)
    $upload_files = [];

    // Add original image
    $upload_files[] = [
        'file' => $file,
        'key' => str_replace(trailingslashit($upload_dir['basedir']), '', $file),
    ];

    // Add image sizes
    if (isset($metadata['sizes']) && !empty($metadata['sizes'])) {
        $file_info = pathinfo($file);
        $base_dir = trailingslashit($file_info['dirname']);

        foreach ($metadata['sizes'] as $size) {
            $file_path = $base_dir . $size['file'];
            $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);

            $upload_files[] = [
                'file' => $file_path,
                'key' => $object_key,
            ];
        }
    }

    foreach ($upload_files as $upload) {
        try {
            $s3Client->putObject([
                'Bucket' => $bucket_name,
                'Key'    => $upload['key'],
                'SourceFile' => $upload['file'],
                'ACL'    => 'public-read',
            ]);
        } catch (Exception $e) {
            error_log('R2 upload failed for ' . $upload['file'] . ': ' . $e->getMessage());
            // If upload fails and we're deleting local files, do not delete
            $upload_failed = true;
        }
    }

    // Update attachment meta with the R2 URL of the original image
    $r2_url = $public_bucket_url . '/' . $upload_files[0]['key'];
    update_post_meta($attachment_id, '_cloudflare_r2_url', $r2_url);

    // Delete local files if the user opted not to keep them and uploads were successful
    if ($keep_local_media !== 'yes' && empty($upload_failed)) {
        foreach ($upload_files as $upload) {
            if (file_exists($upload['file'])) {
                unlink($upload['file']);
            }
        }
        // Optionally, remove empty directories
        $upload_dir_path = dirname($upload_files[0]['file']);
        @rmdir($upload_dir_path); // Suppress warnings if directory is not empty
    }

    return $metadata;
}


add_filter('wp_get_attachment_url', 'replace_media_url_with_r2', 10, 2);

function replace_media_url_with_r2($url, $attachment_id) {
    $r2_url = get_post_meta($attachment_id, '_cloudflare_r2_url', true);
    if ($r2_url) {
        return $r2_url;
    }
    return $url;
}
add_filter('wp_get_attachment_url', 'replace_media_url_with_r2', 10, 2);

add_filter('image_downsize', 'cloudflare_r2_image_downsize', 10, 3);

function cloudflare_r2_image_downsize($downsize, $attachment_id, $size) {
    $r2_url = get_post_meta($attachment_id, '_cloudflare_r2_url', true);

    if (!$r2_url) {
        return false; // Use default handling
    }

    $meta = wp_get_attachment_metadata($attachment_id);

    if (!$meta) {
        return false;
    }

    $upload_dir = wp_upload_dir();

    // Check if the requested size exists
    if ($size == 'full' || !isset($meta['sizes'][$size])) {
        // Full size image
        $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', get_attached_file($attachment_id));
        $image_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/') . '/' . $object_key;
        $width = $meta['width'];
        $height = $meta['height'];
        $is_intermediate = false;
    } else {
        // Resized image
        $size_meta = $meta['sizes'][$size];
        $file_info = pathinfo(get_attached_file($attachment_id));
        $file_path = trailingslashit($file_info['dirname']) . $size_meta['file'];
        $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);
        $image_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/') . '/' . $object_key;
        $width = $size_meta['width'];
        $height = $size_meta['height'];
        $is_intermediate = true;
    }

    return array($image_url, $width, $height, $is_intermediate);
}

function cloudflare_r2_handle_migration() {
    if (isset($_POST['cloudflare_r2_migrate_media'])) {
        // Verify nonce
        if (!isset($_POST['cloudflare_r2_migrate_media_nonce']) || !wp_verify_nonce($_POST['cloudflare_r2_migrate_media_nonce'], 'cloudflare_r2_migrate_media')) {
            wp_die('Nonce verification failed');
        }

        // Perform migration
        cloudflare_r2_migrate_existing_media();

        // Redirect to settings page with success message
        wp_redirect(add_query_arg('cloudflare_r2_migration', 'success', menu_page_url('cloudflare-r2-offload', false)));
        exit;
    }
}
add_action('admin_init', 'cloudflare_r2_handle_migration');

function cloudflare_r2_migrate_existing_media() {
    // Retrieve settings
    $access_key = get_option('cloudflare_r2_access_key');
    $secret_key = get_option('cloudflare_r2_secret_key');
    $bucket_name = get_option('cloudflare_r2_bucket_name');
    $endpoint = get_option('cloudflare_r2_endpoint');
    $public_bucket_url = rtrim(get_option('cloudflare_r2_public_bucket_url'), '/');
    $keep_local_media = get_option('cloudflare_r2_keep_local_media', 'yes');


    if (!$access_key || !$secret_key || !$bucket_name || !$endpoint || !$public_bucket_url) {
        return; // Do not proceed without necessary credentials
    }

    // Configure S3 client
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => 'auto',
        'endpoint' => $endpoint,
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key'    => $access_key,
            'secret' => $secret_key,
        ],
    ]);

    // Set batch size
    $batch_size = 50;

    // Initialize offset
    $offset = 0;

    // Loop until all attachments are processed
    do {
        // Query for a batch of attachments
        $args = [
            'post_type'      => 'attachment',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'post_status'    => 'any',
            'meta_query'     => [
                [
                    'key'     => '_cloudflare_r2_url',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ];
        $attachments = get_posts($args);

        // Process each attachment
        foreach ($attachments as $attachment) {
            $attachment_id = $attachment->ID;

            // Get metadata
            $metadata = wp_get_attachment_metadata($attachment_id);

            // Call the upload function
            cloudflare_r2_upload_media($metadata, $attachment_id);
        }

        // Increase offset
        $offset += $batch_size;

    } while (count($attachments) == $batch_size);
}

function cloudflare_r2_handle_local_deletion() {
    if (isset($_POST['cloudflare_r2_delete_local_media'])) {
        // Verify nonce
        if (!isset($_POST['cloudflare_r2_delete_local_media_nonce']) || !wp_verify_nonce($_POST['cloudflare_r2_delete_local_media_nonce'], 'cloudflare_r2_delete_local_media')) {
            wp_die('Nonce verification failed');
        }

        // Perform local media deletion
        cloudflare_r2_delete_local_media_files();

        // Redirect to settings page with success message
        wp_redirect(add_query_arg('cloudflare_r2_local_deletion', 'success', menu_page_url('cloudflare-r2-offload', false)));
        exit;
    }
}
add_action('admin_init', 'cloudflare_r2_handle_local_deletion');

function cloudflare_r2_delete_local_media_files() {
    // Get all attachments that have been migrated to Cloudflare R2
    $args = [
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_query'     => [
            [
                'key'     => '_cloudflare_r2_url',
                'compare' => 'EXISTS',
            ],
        ],
    ];
    $attachments = get_posts($args);

    // Process each attachment
    foreach ($attachments as $attachment) {
        $attachment_id = $attachment->ID;

        // Get the file path of the original image
        $file = get_attached_file($attachment_id);

        // Create array of files to delete (original + sizes)
        $files_to_delete = [];

        // Add original image
        if ($file && file_exists($file)) {
            $files_to_delete[] = $file;
        }

        // Get metadata
        $metadata = wp_get_attachment_metadata($attachment_id);

        // Add image sizes
        if (isset($metadata['sizes']) && !empty($metadata['sizes'])) {
            $file_info = pathinfo($file);
            $base_dir = trailingslashit($file_info['dirname']);

            foreach ($metadata['sizes'] as $size) {
                $file_path = $base_dir . $size['file'];
                if (file_exists($file_path)) {
                    $files_to_delete[] = $file_path;
                }
            }
        }

        // Delete files
        foreach ($files_to_delete as $file_path) {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // Optionally, remove empty directories
        $upload_dir_path = dirname($file);
        @rmdir($upload_dir_path); // Suppress warnings if directory is not empty
    }
}
