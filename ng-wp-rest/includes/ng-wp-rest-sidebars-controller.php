<?php
/**
 * WPNGSidebarsController Endpoints Controller Class for Sidebars
 *
 * Creates and registers the endpoints for Sidebar data.
 *
 * @since 1.0.0
 * @package NGWP Endpoints
 */

include_once 'ng-wp-interface.php';

/**
 * WPNGSidebarsController
 * Json rest api sidebar endpoints.
 *
 */
class WPNGSidebarsController implements NGWPInterface
{

    /**
     * Class constructor.
     *
     * When class is instantiated WP REST API routes are registered.
     */
    public function __construct()
    {
        add_action('ng_wp_rest_register_endpoints', array($this, 'registerRoutes'));
    }

    /**
     * Registers all of our sidbar rest enpoints.
     *
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(self::NG_WP_SIDEBAR_NAMESPACE, '/sidebars', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getList'),
            ),
        ));

        register_rest_route(self::NG_WP_SIDEBAR_NAMESPACE, '/sidebars/(?P<sidebar_id>[^/]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get'),
            ),
        ));
    }

    /**
     * Read single menu by id.
     *
     * http://{url}/wp-json/ng-sidbar-route/v2/sidebars/{id}
     *
     * @param object $request
     * @return array filtered
     */
    public function get($request)
    {
        global $wp_registered_sidebars;

        $sidebar = $wp_registered_sidebars[$request['sidebar_id']];
        $sidebars_widgets = wp_get_sidebars_widgets();
        $data = $this->prepare_sidebar_for_response($sidebar, $request['sidebar_id'], $sidebars_widgets);

        return apply_filters('rest_sidebars_format_sidebars', $data);
    }

    /**
     * Read multiple sidebar list.
     *
     * http://{url}/wp-json/ng-sidebar-route/v2/sidebars
     *
     * @return array filtered
     */
    public function getList()
    {
        global $wp_registered_sidebars;

        $sidebars_widgets = wp_get_sidebars_widgets();
        $data = array();

        foreach ($wp_registered_sidebars as $sidebar_id => $sidebar) {
            $sidebar_data = $this->prepare_sidebar_for_response($sidebar, $sidebar_id, $sidebars_widgets);
            $data[$sidebar_id] = $sidebar_data;
        }

        return apply_filters('rest_sidebars_format_sidebars', $data);
    }

    /**
     * Checks for active widgets on sidebar and adds them to
     * an array otherwise returns active_widgets = false.
     *
     * @param array $sidebar
     * @param int $sidebar_id
     * @param array $sidebars_widgets
     * @return array $sidebar
     */
    public function prepare_sidebar_for_response($sidebar, $sidebar_id, $sidebars_widgets)
    {
        $sidebar['active_widgets'] = false;

        if (array_key_exists($sidebar_id, $sidebars_widgets)) {
            if (!empty($sidebars_widgets[$sidebar_id])) {
                $sidebar['active_widgets'] = $sidebars_widgets[$sidebar_id];
            }
        }
        return $sidebar;
    }
}
