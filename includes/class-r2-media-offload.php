<?php
/**
 * Main plugin class
 *
 * @package R2_Media_Offload
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Main plugin class
 */
class R2_Media_Offload {
    /**
     * The single instance of the class.
     *
     * @var R2_Media_Offload
     */
    protected static $instance = null;

    /**
     * Settings instance.
     *
     * @var R2_Settings
     */
    protected $settings = null;

    /**
     * Uploader instance.
     *
     * @var R2_Uploader
     */
    protected $uploader = null;

    /**
     * Media Library instance.
     *
     * @var R2_Media_Library
     */
    protected $media_library = null;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin.
     */
    private function init() {
        $this->settings = new R2_Settings();
        $this->uploader = new R2_Uploader();
        $this->media_library = new R2_Media_Library();

        // Initialize admin
        if (is_admin()) {
            new R2_Admin();
        }

        // Add upload filters
        add_filter('wp_handle_upload', array($this->uploader, 'handle_upload'));
        add_filter('wp_update_attachment_metadata', array($this->uploader, 'handle_attachment_metadata'), 10, 2);
        add_filter('delete_attachment', array($this->uploader, 'delete_attachment'));
    }

    /**
     * Get settings instance.
     *
     * @return R2_Settings
     */
    public function settings() {
        return $this->settings;
    }

    /**
     * Get uploader instance.
     *
     * @return R2_Uploader
     */
    public function uploader() {
        return $this->uploader;
    }

    /**
     * Get media library instance.
     *
     * @return R2_Media_Library
     */
    public function media_library() {
        return $this->media_library;
    }

    /**
     * Main R2_Media_Offload Instance.
     *
     * Ensures only one instance of R2_Media_Offload is loaded or can be loaded.
     *
     * @return R2_Media_Offload - Main instance.
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
} 