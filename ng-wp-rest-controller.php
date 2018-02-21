<?php

/**
 * This plugin adds menu, widget and sidebar endpoints to the WP REST API v2.
 *
 * @link
 * @since   1.0.0
 * @package NG-WP Endpoints
 * @version 1.0.0
 *
 * @wordpress-plugin
 * Plugin Name: NG-WP Endpoints
 * Pugin URI: http://theuiarch.com
 * Description: A plugin that adds WP REST API endpoints for menus, sidebars and widgets.
 *  Useful for theme and plugin authors who want to access widget information via the WP REST API.
 *  Currently uses WP REST API v2 but will expand over time with additional functionality.
 * Version: 1.0.0
 * Author: Anthony Allen
 * Author: http://theuiarch.com
 * License: MIT
 * Text Domain: ng-wp-endpoints
 * Domain Path: /i18n/languages/
 */

defined('ABSPATH') or die("Access Denied!");

class NGWPController
{

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version = '1.0.0';

    public function __construct()
    {
        // Used to check version of WP to make sure it is greater than 4.4!
        add_action('admin_init', array($this, 'checkVersion'));
        // If you are using an unsupported version of wordpress then don't do anything.
        if (!$this->compatibleVersion()) {
            return;
        }

        $this->includes();
        $this->addEndpoints();
        $this->initHooks();
    }

    /**
     * Include required core files.
     */
    private function includes()
    {
        // Loads enpoint controller classes.
        if (class_exists('WP_REST_Controller')) {
            include_once 'includes/ng-wp-rest-sidebars-controller.php';
            include_once 'includes/ng-wp-rest-widgets-controller.php';
            include_once 'includes/ng-wp-rest-menus-controller.php';
        }
    }

    /**
     * Adds custom endpoints to WP REST API.
     *
     * Currently adds basic endpoints for Sidebars and Widgets.
     * Will expand in the future.
     *
     * @see $this->init_hooks This function is only called if WP_REST_Controller exists.
     * @see add_action( 'rest_api_init', array( $this, 'add_endpoints' ) ) hooked.
     */
    public function addEndpoints()
    {
        // Default actions for registering WP REST API Endpoints.
        $restMenus = new NGWPRestMenus();
        $restWidgets = new NGWPWidgetsController();
        $restSidebars = new WPNGSidebarsController();

        // Action where endpoint routes are registered for this plugin.
        do_action('ng_wp_rest_register_endpoints');
    }

    /**
     * Hook into actions and filters
     */
    private function initHooks()
    {
        register_activation_hook(__FILE__, array($this, 'activatePlugin'));

        if (class_exists('WP_REST_Controller')) {
            add_action('rest_api_init', array($this, 'addEndpoints'));
        }
    }

    /**
     * Document
     */
    public function checkVersion()
    {
        if (!self::compatibleVersion()) {
            if (is_plugin_active(plugin_basename(__FILE__))) {
                deactivate_plugins(plugin_basename(__FILE__));
                add_action('admin_notices', array($this, 'disabledNotice'));
                if (isset($_GET['activate'])) {
                    unset($_GET['activate']);
                }
            }
        }
    }

    /**
     * Compare wordpress version.
     *
     * @return bool {true|false}
     */
    public static function compatibleVersion()
    {
        if (version_compare($GLOBALS['wp_version'], '4.4', '<')) {
            return false;
        }
        // Add sanity checks for other version requirements here.
        return true;
    }

    /**
     * Echos an error notification.
     */
    public function disabledNotice()
    {
        echo '<div class="error"><p>', esc_html__('NG-WP REST Endpoints requires WordPress 4.4 or higher!', 'ng-wp-rest-endpoints'), '</p></div>';
    }

    // This function runs an activation check to make sure plugin runs correctly.
    public static function activation_check()
    {
        if (!self::compatibleVersion()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(esc_html__('NG-WP REST Endpoints requires WordPress 4.4 or higher!', 'ng-wp-rest-endpoints'));
        }
    }

    /**
     * Fires on plugin activation.
     *
     * Currently does nothing.
     *
     * @todo Eventually set up site options.
     */
    public function activatePlugin()
    {
        self::activation_check();
    }

    /**
     * Fires on plugin deactivation.
     *
     * Currently does nothing.
     *
     * @todo add functionality to notify deactivation.
     */
    public function deactivatePlugin()
    {

    }
}

function pluginInit()
{
    $rest = new NGWPController();
}

add_action('init', 'pluginInit');
