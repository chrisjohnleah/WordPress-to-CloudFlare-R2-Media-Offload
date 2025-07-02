<?php
/**
 * Uploader class
 *
 * @package R2_Media_Offload
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Aws\S3\S3Client;

/**
 * Uploader class
 */
class R2_Uploader {
    /**
     * S3 client instance.
     *
     * @var S3Client
     */
    private $s3_client = null;

    /**
     * Constructor.
     */
    public function __construct() {
        add_filter('wp_generate_attachment_metadata', array($this, 'upload_media'), 10, 2);
    }

    /**
     * Get S3 client instance.
     *
     * @return S3Client|null
     */
    public function get_s3_client() {
        if (!is_null($this->s3_client)) {
            return $this->s3_client;
        }

        $settings = R2_Media_Offload()->settings();
        if (!$settings->is_configured()) {
            return null;
        }

        $config = $settings->get_all_settings();
        
        try {
            $this->s3_client = new S3Client([
                'version' => 'latest',
                'region' => 'auto',
                'endpoint' => $config['endpoint'],
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key'    => $config['access_key'],
                    'secret' => $config['secret_key'],
                ],
            ]);
        } catch (Exception $e) {
            r2_log_error('Failed to initialize S3 client: ' . $e->getMessage());
            return null;
        }

        return $this->s3_client;
    }

    /**
     * Upload media to R2.
     *
     * @param array $metadata Attachment metadata.
     * @param int   $attachment_id Attachment ID.
     * @return array
     */
    public function upload_media($metadata, $attachment_id) {
        $s3_client = $this->get_s3_client();
        if (!$s3_client) {
            return $metadata;
        }

        $settings = R2_Media_Offload()->settings();
        $config = $settings->get_all_settings();
        $upload_dir = wp_upload_dir();
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

        $upload_failed = false;
        foreach ($upload_files as $upload) {
            try {
                $s3_client->putObject([
                    'Bucket' => $config['bucket_name'],
                    'Key'    => $upload['key'],
                    'SourceFile' => $upload['file'],
                    'ACL'    => 'public-read',
                ]);
            } catch (Exception $e) {
                r2_log_error('Failed to upload file: ' . $e->getMessage());
                $upload_failed = true;
                break;
            }
        }

        if (!$upload_failed) {
            // Update attachment meta with the R2 URL
            $r2_url = $config['public_bucket_url'] . '/' . $upload_files[0]['key'];
            update_post_meta($attachment_id, '_cloudflare_r2_url', $r2_url);

            // Delete local files if configured
            if ($config['keep_local_media'] !== 'yes') {
                foreach ($upload_files as $upload) {
                    if (file_exists($upload['file'])) {
                        unlink($upload['file']);
                    }
                }
                // Remove empty directories
                $upload_dir_path = dirname($upload_files[0]['file']);
                @rmdir($upload_dir_path);
            }
        }

        return $metadata;
    }

    /**
     * Download file from R2.
     *
     * @param string $key Object key.
     * @param string $destination Destination path.
     * @return bool
     */
    public function download_file($key, $destination) {
        $s3_client = $this->get_s3_client();
        if (!$s3_client) {
            return false;
        }

        try {
            $config = R2_Media_Offload()->settings()->get_all_settings();
            $s3_client->getObject([
                'Bucket' => $config['bucket_name'],
                'Key'    => $key,
                'SaveAs' => $destination
            ]);
            return true;
        } catch (Exception $e) {
            r2_log_error('Failed to download file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle attachment metadata update.
     *
     * @param array $metadata      Attachment metadata.
     * @param int   $attachment_id Attachment ID.
     * @return array
     */
    public function handle_attachment_metadata($metadata, $attachment_id) {
        if (!is_array($metadata)) {
            return $metadata;
        }

        try {
            // Get the main file
            $file = get_attached_file($attachment_id);
            if (!file_exists($file)) {
                return $metadata;
            }

            // Upload the main file
            $upload_dir = wp_upload_dir();
            $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', $file);
            $this->upload_file($file, $object_key);

            // Store R2 URL in post meta
            $r2_url = $this->get_r2_url($object_key);
            update_post_meta($attachment_id, '_cloudflare_r2_url', $r2_url);

            // Handle thumbnails
            if (!empty($metadata['sizes'])) {
                $base_dir = dirname($file) . '/';
                foreach ($metadata['sizes'] as $size => $size_info) {
                    $thumb_path = $base_dir . $size_info['file'];
                    if (file_exists($thumb_path)) {
                        $thumb_key = str_replace(trailingslashit($upload_dir['basedir']), '', $thumb_path);
                        $this->upload_file($thumb_path, $thumb_key);
                    }
                }
            }
        } catch (Exception $e) {
            r2_debug_log('Error handling attachment metadata: ' . $e->getMessage());
        }

        return $metadata;
    }

    /**
     * Upload file to R2.
     *
     * @param string $file_path Local file path.
     * @param string $object_key Object key in R2.
     * @return bool
     */
    public function upload_file($file_path, $object_key) {
        $s3_client = $this->get_s3_client();
        if (!$s3_client) {
            throw new Exception('S3 client not initialized');
        }

        try {
            $config = R2_Media_Offload()->settings()->get_all_settings();
            $s3_client->putObject([
                'Bucket' => $config['bucket_name'],
                'Key'    => $object_key,
                'SourceFile' => $file_path,
                'ACL'    => 'public-read',
            ]);
            return true;
        } catch (Exception $e) {
            r2_debug_log('Failed to upload file: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get R2 URL for an object.
     *
     * @param string $object_key Object key in R2.
     * @return string
     */
    public function get_r2_url($object_key) {
        $config = R2_Media_Offload()->settings()->get_all_settings();
        return trailingslashit($config['public_bucket_url']) . $object_key;
    }

    /**
     * Handle file upload.
     *
     * @param array $upload Upload data.
     * @return array
     */
    public function handle_upload($upload) {
        if (!empty($upload['error'])) {
            return $upload;
        }

        try {
            $file_path = $upload['file'];
            $upload_dir = wp_upload_dir();
            $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);
            
            $this->upload_file($file_path, $object_key);
            
            // Add R2 URL to the upload data
            $upload['r2_url'] = $this->get_r2_url($object_key);
            
            // Delete local file if configured
            $config = R2_Media_Offload()->settings()->get_all_settings();
            if ($config['keep_local_media'] !== 'yes') {
                unlink($file_path);
            }
        } catch (Exception $e) {
            r2_debug_log('Error handling upload: ' . $e->getMessage());
        }

        return $upload;
    }

    /**
     * Delete attachment from R2.
     *
     * @param int $attachment_id Attachment ID.
     * @return mixed
     */
    public function delete_attachment($attachment_id) {
        $s3_client = $this->get_s3_client();
        if (!$s3_client) {
            return $attachment_id;
        }

        try {
            $config = R2_Media_Offload()->settings()->get_all_settings();
            $metadata = wp_get_attachment_metadata($attachment_id);
            $upload_dir = wp_upload_dir();
            $file = get_attached_file($attachment_id);
            
            // Delete original file
            $object_key = str_replace(trailingslashit($upload_dir['basedir']), '', $file);
            $s3_client->deleteObject([
                'Bucket' => $config['bucket_name'],
                'Key'    => $object_key
            ]);

            // Delete thumbnails
            if (!empty($metadata['sizes'])) {
                $base_dir = dirname($file) . '/';
                foreach ($metadata['sizes'] as $size) {
                    $thumb_path = $base_dir . $size['file'];
                    $thumb_key = str_replace(trailingslashit($upload_dir['basedir']), '', $thumb_path);
                    
                    $s3_client->deleteObject([
                        'Bucket' => $config['bucket_name'],
                        'Key'    => $thumb_key
                    ]);
                }
            }

            delete_post_meta($attachment_id, '_cloudflare_r2_url');
        } catch (Exception $e) {
            r2_debug_log('Error deleting attachment from R2: ' . $e->getMessage());
        }

        return $attachment_id;
    }
} 