<?php
/**
 * Media Library class
 *
 * @package R2_Media_Offload
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Media Library class
 */
class R2_Media_Library {
    /**
     * Constructor.
     */
    public function __construct() {
        add_filter('wp_get_attachment_url', array($this, 'get_attachment_url'), 10, 2);
        add_filter('wp_calculate_image_srcset', array($this, 'calculate_image_srcset'), 10, 5);
        add_filter('wp_delete_file', array($this, 'delete_r2_file'));
    }

    /**
     * Get attachment URL.
     *
     * @param string $url Original URL.
     * @param int    $attachment_id Attachment ID.
     * @return string
     */
    public function get_attachment_url($url, $attachment_id) {
        $r2_url = get_post_meta($attachment_id, '_cloudflare_r2_url', true);
        return $r2_url ? $r2_url : $url;
    }

    /**
     * Calculate image srcset.
     *
     * @param array  $sources Sources array.
     * @param array  $size_array Size array.
     * @param string $image_src Image source.
     * @param array  $image_meta Image meta.
     * @param int    $attachment_id Attachment ID.
     * @return array
     */
    public function calculate_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        $r2_url = get_post_meta($attachment_id, '_cloudflare_r2_url', true);
        if (!$r2_url) {
            return $sources;
        }

        // Get the proper R2 base URL from settings instead of deriving from individual file URL
        $config = R2_Media_Offload()->settings()->get_all_settings();
        if (empty($config['public_bucket_url'])) {
            return $sources;
        }

        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        $r2_base_url = $config['public_bucket_url'];

        foreach ($sources as &$source) {
            $source['url'] = str_replace($base_url, $r2_base_url, $source['url']);
        }

        return $sources;
    }

    /**
     * Delete file from R2.
     *
     * @param string $file File path.
     * @return string
     */
    public function delete_r2_file($file) {
        $upload_dir = wp_upload_dir();
        $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', $file);

        $s3_client = R2_Media_Offload()->uploader()->get_s3_client();
        if (!$s3_client) {
            return $file;
        }

        try {
            $config = R2_Media_Offload()->settings()->get_all_settings();
            $s3_client->deleteObject([
                'Bucket' => $config['bucket_name'],
                'Key'    => $object_key,
            ]);
        } catch (Exception $e) {
            r2_log_error('Failed to delete file from R2: ' . $e->getMessage());
        }

        return $file;
    }

    /**
     * Get total size of offloaded media.
     *
     * @return int
     */
    public function get_total_offloaded_size() {
        global $wpdb;
        $total_size = 0;
        $batch_size = 100;
        $offset = 0;

        do {
            $attachments = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, meta_value FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE post_type = 'attachment'
                AND meta_key = '_cloudflare_r2_url'
                LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            ));

            if (empty($attachments)) {
                break;
            }

            foreach ($attachments as $attachment) {
                $metadata = wp_get_attachment_metadata($attachment->ID);
                if (!empty($metadata['filesize'])) {
                    $total_size += (int) $metadata['filesize'];
                }

                if (!empty($metadata['sizes'])) {
                    foreach ($metadata['sizes'] as $size) {
                        if (!empty($size['filesize'])) {
                            $total_size += (int) $size['filesize'];
                        }
                    }
                }

                // Clean up memory
                wp_cache_delete($attachment->ID, 'posts');
                wp_cache_delete($attachment->ID, 'post_meta');
            }

            $offset += $batch_size;
            
            // Clean up memory
            unset($attachments);
            wp_cache_flush();

        } while (true);

        return $total_size;
    }
} 