<?php
/**
 * Helper functions
 *
 * @package R2_Media_Offload
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Format file size.
 *
 * @param int $bytes File size in bytes.
 * @return string
 */
function r2_format_size($bytes) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Get total size of offloaded media.
 *
 * @return string
 */
function r2_get_total_offloaded_size() {
    $total_size = 0;
    global $wpdb;

    $attachments = $wpdb->get_results(
        "SELECT ID FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE post_type = 'attachment'
        AND meta_key = '_cloudflare_r2_url'"
    );

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
    }

    return r2_format_size($total_size);
}

/**
 * Get total number of offloaded files.
 *
 * @return int
 */
function r2_get_total_offloaded_files() {
    global $wpdb;
    return (int) $wpdb->get_var(
        "SELECT COUNT(*)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE post_type = 'attachment'
        AND meta_key = '_cloudflare_r2_url'"
    );
}

/**
 * Check if file is offloaded.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function r2_is_file_offloaded($attachment_id) {
    return (bool) get_post_meta($attachment_id, '_cloudflare_r2_url', true);
}

/**
 * Get file URL from R2.
 *
 * @param int $attachment_id Attachment ID.
 * @return string|false
 */
function r2_get_file_url($attachment_id) {
    return get_post_meta($attachment_id, '_cloudflare_r2_url', true);
}

/**
 * Get file path relative to uploads directory.
 *
 * @param string $file_path Full file path.
 * @return string
 */
function r2_get_relative_upload_path($file_path) {
    $upload_dir = wp_upload_dir();
    return str_replace(trailingslashit($upload_dir['basedir']), '', $file_path);
}

/**
 * Get file path from URL.
 *
 * @param string $url File URL.
 * @return string|false
 */
function r2_get_file_path_from_url($url) {
    $upload_dir = wp_upload_dir();
    $base_url = $upload_dir['baseurl'];
    $file_path = str_replace($base_url, $upload_dir['basedir'], $url);
    return $file_path;
}

/**
 * Get file extension.
 *
 * @param string $file_path File path.
 * @return string
 */
function r2_get_file_extension($file_path) {
    return strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
}

/**
 * Check if file is image.
 *
 * @param string $file_path File path.
 * @return bool
 */
function r2_is_image($file_path) {
    $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    return in_array(r2_get_file_extension($file_path), $image_extensions, true);
}

/**
 * Get file size.
 *
 * @param string $file_path File path.
 * @return int|false
 */
function r2_get_file_size($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    return filesize($file_path);
}

/**
 * Get file mime type.
 *
 * @param string $file_path File path.
 * @return string|false
 */
function r2_get_file_mime_type($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    return mime_content_type($file_path);
}

/**
 * Get file dimensions.
 *
 * @param string $file_path File path.
 * @return array|false
 */
function r2_get_file_dimensions($file_path) {
    if (!r2_is_image($file_path) || !file_exists($file_path)) {
        return false;
    }
    $size = getimagesize($file_path);
    if (!$size) {
        return false;
    }
    return array(
        'width' => $size[0],
        'height' => $size[1]
    );
}

/**
 * Get file hash.
 *
 * @param string $file_path File path.
 * @return string|false
 */
function r2_get_file_hash($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    return md5_file($file_path);
}

/**
 * Get file modified time.
 *
 * @param string $file_path File path.
 * @return int|false
 */
function r2_get_file_modified_time($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    return filemtime($file_path);
}

/**
 * Get file created time.
 *
 * @param string $file_path File path.
 * @return int|false
 */
function r2_get_file_created_time($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    return filectime($file_path);
}

/**
 * Get file owner.
 *
 * @param string $file_path File path.
 * @return int|false
 */
function r2_get_file_owner($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    return fileowner($file_path);
}

/**
 * Get file group.
 *
 * @param string $file_path File path.
 * @return int|false
 */
function r2_get_file_group($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    return filegroup($file_path);
}

/**
 * Get file permissions.
 *
 * @param string $file_path File path.
 * @return string|false
 */
function r2_get_file_permissions($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    return substr(sprintf('%o', fileperms($file_path)), -4);
}

/**
 * Check if file is readable.
 *
 * @param string $file_path File path.
 * @return bool
 */
function r2_is_file_readable($file_path) {
    return file_exists($file_path) && is_readable($file_path);
}

/**
 * Check if file is writable.
 *
 * @param string $file_path File path.
 * @return bool
 */
function r2_is_file_writable($file_path) {
    return file_exists($file_path) && is_writable($file_path);
}

/**
 * Check if file is executable.
 *
 * @param string $file_path File path.
 * @return bool
 */
function r2_is_file_executable($file_path) {
    return file_exists($file_path) && is_executable($file_path);
}

/**
 * Get file type.
 *
 * @param string $file_path File path.
 * @return string|false
 */
function r2_get_file_type($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    return filetype($file_path);
}

/**
 * Get file info.
 *
 * @param string $file_path File path.
 * @return array|false
 */
function r2_get_file_info($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }

    return array(
        'name' => basename($file_path),
        'path' => $file_path,
        'extension' => r2_get_file_extension($file_path),
        'mime_type' => r2_get_file_mime_type($file_path),
        'size' => r2_get_file_size($file_path),
        'dimensions' => r2_get_file_dimensions($file_path),
        'hash' => r2_get_file_hash($file_path),
        'modified_time' => r2_get_file_modified_time($file_path),
        'created_time' => r2_get_file_created_time($file_path),
        'owner' => r2_get_file_owner($file_path),
        'group' => r2_get_file_group($file_path),
        'permissions' => r2_get_file_permissions($file_path),
        'is_readable' => r2_is_file_readable($file_path),
        'is_writable' => r2_is_file_writable($file_path),
        'is_executable' => r2_is_file_executable($file_path),
        'type' => r2_get_file_type($file_path),
    );
} 