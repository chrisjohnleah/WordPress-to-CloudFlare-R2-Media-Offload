<?php
/**
 * Logging functions
 *
 * @package R2_Media_Offload
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Log message to debug.log
 *
 * @param string $message Message to log.
 */
function r2_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        error_log('[R2 Media Offload] ' . $message);
    }
}

/**
 * Log error message to debug.log
 *
 * @param string $message Error message to log.
 */
function r2_log_error($message) {
    r2_log('Error: ' . $message);
}

/**
 * Log warning message to debug.log
 *
 * @param string $message Warning message to log.
 */
function r2_log_warning($message) {
    r2_log('Warning: ' . $message);
}

/**
 * Log info message to debug.log
 *
 * @param string $message Info message to log.
 */
function r2_log_info($message) {
    r2_log('Info: ' . $message);
}

/**
 * Log debug message to debug.log
 *
 * @param string $message Debug message to log.
 */
function r2_log_debug($message) {
    r2_log('Debug: ' . $message);
}

/**
 * Log variable to debug.log
 *
 * @param mixed  $var Variable to log.
 * @param string $label Label for the variable.
 */
function r2_log_var($var, $label = '') {
    if ($label) {
        $label = $label . ': ';
    }
    r2_log($label . print_r($var, true));
}

/**
 * Log backtrace to debug.log
 *
 * @param int $limit Limit the number of stack frames returned.
 */
function r2_log_backtrace($limit = 0) {
    $backtrace = debug_backtrace();
    if ($limit > 0) {
        $backtrace = array_slice($backtrace, 0, $limit);
    }
    r2_log('Backtrace:' . PHP_EOL . print_r($backtrace, true));
}

/**
 * Log memory usage to debug.log
 */
function r2_log_memory_usage() {
    $memory_usage = memory_get_usage(true);
    $peak_memory_usage = memory_get_peak_usage(true);
    r2_log(sprintf(
        'Memory Usage: %s (Peak: %s)',
        size_format($memory_usage),
        size_format($peak_memory_usage)
    ));
}

/**
 * Log execution time to debug.log
 *
 * @param float $start_time Start time from microtime(true).
 */
function r2_log_execution_time($start_time) {
    $execution_time = microtime(true) - $start_time;
    r2_log(sprintf('Execution Time: %.4f seconds', $execution_time));
}

/**
 * Log database query to debug.log
 *
 * @param string $query Database query.
 * @param array  $args Query arguments.
 */
function r2_log_query($query, $args = array()) {
    global $wpdb;
    if (!empty($args)) {
        $query = $wpdb->prepare($query, $args);
    }
    r2_log('Database Query: ' . $query);
}

/**
 * Log HTTP request to debug.log
 *
 * @param string $url Request URL.
 * @param array  $args Request arguments.
 */
function r2_log_http_request($url, $args = array()) {
    r2_log(sprintf(
        'HTTP Request: %s%s',
        $url,
        !empty($args) ? PHP_EOL . print_r($args, true) : ''
    ));
}

/**
 * Log HTTP response to debug.log
 *
 * @param array|WP_Error $response HTTP response or WP_Error object.
 */
function r2_log_http_response($response) {
    if (is_wp_error($response)) {
        r2_log_error('HTTP Response Error: ' . $response->get_error_message());
    } else {
        r2_log(sprintf(
            'HTTP Response: %s %s%s',
            wp_remote_retrieve_response_code($response),
            wp_remote_retrieve_response_message($response),
            PHP_EOL . print_r(wp_remote_retrieve_headers($response), true)
        ));
    }
}

/**
 * Log file operation to debug.log
 *
 * @param string $operation Operation type (read, write, delete, etc.).
 * @param string $file_path File path.
 * @param mixed  $result Operation result.
 */
function r2_log_file_operation($operation, $file_path, $result = null) {
    $message = sprintf(
        'File Operation: %s - %s%s',
        strtoupper($operation),
        $file_path,
        isset($result) ? ' - Result: ' . print_r($result, true) : ''
    );
    r2_log($message);
}

/**
 * Log plugin action to debug.log
 *
 * @param string $action Action name.
 * @param mixed  $data Action data.
 */
function r2_log_action($action, $data = null) {
    $message = sprintf(
        'Plugin Action: %s%s',
        $action,
        isset($data) ? ' - Data: ' . print_r($data, true) : ''
    );
    r2_log($message);
}

/**
 * Log plugin filter to debug.log
 *
 * @param string $filter Filter name.
 * @param mixed  $value Filter value.
 * @param mixed  $args Filter arguments.
 */
function r2_log_filter($filter, $value, $args = null) {
    $message = sprintf(
        'Plugin Filter: %s - Value: %s%s',
        $filter,
        print_r($value, true),
        isset($args) ? ' - Args: ' . print_r($args, true) : ''
    );
    r2_log($message);
} 