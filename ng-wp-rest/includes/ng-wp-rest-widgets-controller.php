<?php
/**
 * NGWPWidgetsController Endpoints Controller Class for Widgets
 *
 * Creates and registers the endpoints for widget data.
 *
 * @since 1.0.0
 * @package NGWP Endpoints
 */

include_once 'ng-wp-interface.php';

/**
 * NGWPWidgetsController
 * Json rest api widget endpoints.
 *
 */
class NGWPWidgetsController implements NGWPInterface
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
     * Registers all of our widget rest enpoints.
     *
     * @return void
     */
    public function registerRoutes()
    {
        // Route for handling widgets collection. /ng-widget-route/v2/widgets.
        register_rest_route(self::NG_WP_WIDGET_NAMESPACE, '/widgets', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getList'),
            ),
        ));

        // Routes for handling individual widgets. /ng-widget-route/v2/widgets/<widget_id>.
        register_rest_route(self::NG_WP_WIDGET_NAMESPACE, '/widgets/(?P<widget_id>[\w-]+-[\d]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get'),
                'args' => array(
                    'context' => array(
                        'default' => 'view',
                    ),
                ),
            ),
        ));
    }

    /**
     * Read single widget by id.
     *
     * http://{url}/wp-json/ng-widget-route/v2/widgets/{id}
     *
     * @param object $request
     * @return array filtered
     */
    public function get($request)
    {
        global $wp_registered_widgets;

        $widget = $wp_registered_widgets[$request['widget_id']];
        $widget = $this->prepare_widget_for_response($widget);
        $data = $widget;

        return apply_filters('rest_widgets_format_widget', $data);
    }

    /**
     * Read multiple widgets list.
     *
     * http://{url}/wp-json/ng-widget-route/v2/widgets
     *
     * @return array filtered
     */
    public function getList()
    {
        global $wp_registered_widgets;
        $data = array();

        // Creates response with registered widgets.
        foreach ($wp_registered_widgets as $widget_id => $widget) {
            // Prepares widget for the REST response with extra information.
            $widget = $this->prepare_widget_for_response($widget);

            // Set up array to hold all data.
            $data[$widget_id] = $widget;
        }

        return apply_filters('rest_widgets_format_widgets', $data);
    }

    /**
     * Prepares widget for response.
     *
     * @param array $widget
     * @return array $widget Returns the new instance of $widget.
     */
    public function prepare_widget_for_response($widget)
    {
        global $wp_registered_sidebars, $sidebars_widget;

        // Sets up arguements eventually passed into WP_Widget::widget().
        $args = array();

        // Find sidebar for widget and add to.
        $widget['in_sidebar'] = $this->get_sidebar_for_widget($widget['id']);

        // Sets up sidebar parameters for the respective widget if it is in a sidebar.
        $widget['sidebar_params'] = false;
        if (false !== $widget['in_sidebar']) {
            $widget['sidebar_params'] = $wp_registered_sidebars[$widget['in_sidebar']];
            if (!empty($widget['sidebar_params']['before_widget'])) {
                // Replace id and widget classname into before_widget. @see dynamic_sidebar() in includes/widgets.php.
                $widget['sidebar_params']['before_widget'] = sprintf($widget['sidebar_params']['before_widget'], $widget['id'], $widget['classname']);
                $args = array(
                    'before_widget' => $widget['sidebar_params']['before_widget'],
                    'after_widget' => $widget['sidebar_params']['after_widget'],
                    'before_title' => $widget['sidebar_params']['before_title'],
                    'after_title' => $widget['sidebar_params']['after_title'],
                );
            }
        }

        // True if widget has an instance false if not.
        $widget['has_output'] = (0 < $widget['params'][0]['number']) ? true : false;

        // Save instance number.
        $widget['instance_number'] = $widget['params'][0]['number'];

        // Returns array of instance if there is no instance returns false.
        $widget['instance'] = $this->get_widget_instance($widget, $widget['params'][0]['number']);

        // Adds widget rendered output into the data array.
        $widget['widget_output'] = $this->get_the_widget($widget, $args);

        // Remove params and callback info to make code cleaner.
        unset($widget['params']);
        unset($widget['callback']);

        return $widget;
    }

    /**
     * Get the particular widget's instance.
     *
     * @access private
     *
     * @param  array $widget Array that exists at $wp_registered_widgets[ $widget_id ].
     * @param  int   $instance_number Integer for widget instance number.
     * @return array|boolean $instance|false If there is not an active instance return false.
     */
    private function get_widget_instance($widget, $instance_number)
    {
        if (true === $widget['has_output']) {
            $instances = get_option($widget['callback'][0]->option_name);
            $instance = $instances[$instance_number];
            return $instance;
        }
        return false;
    }

    /**
     * Gets the widget's rendered output relative to the sidebar it is in.
     *
     * @access private
     *
     * @see $default_args for defaults.
     * @param array $widget Array that exists at $wp_registered_widgets[ $widget_id ].
     * @param array $args {
     *
     *   Display arguments for widget.
     *
     *   @type string 'before_title'   HTML ouput that comes before the widget title,
     *                                 Default '<section id="%1$s" class="widget %2$s">'.
     *   @type string 'after_title'    HTML output that comes after the widget title,
     *                                 Default '</section>'.
     *   @type string 'before_widget'  HTML output that comes before widget instance,
     *                                 Default '<h2 class="widget-title">'.
     *   @type string 'after_widget'   HTML output that comes after widget instance
     *                                 Default '</h2>'.
     * }
     * @return string Widget's output if no output returns empty string.
     */
    private function get_the_widget($widget, $args = array())
    {
        // The output will be an empty string if the widget has no output.
        $the_widget = '';

        // Set up default args for the widget.
        $default_args = array(
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget' => '</section>',
            'before_title' => '<h2 class="widget-title">',
            'after_title' => '</h2>',
        );

        // Parse defaults together with user passed arguments.
        $args = wp_parse_args($args, $default_args);

        // Start output buffering to capture WP_Widget::widget() output.
        ob_start();
        $widget['callback'][0]->display_callback($args, $widget['params'][0]['number']);
        $the_widget = ob_get_contents();
        ob_end_clean();

        // Returns a string of the widgets output.
        return $the_widget;
    }

    /**
     * Returns value of the sidebar id, which the widget is in. If not in sidebar returns false.
     *
     * @access private
     * @global array $sidebars_widgets
     *
     * @param  string $widget_id Unique ID of a widget.
     * @return boolean|string Returns the id of the sidebar that the widget is active in.  If not in a sidebar returns false
     */
    private function get_sidebar_for_widget($widget_id)
    {
        // Array of sidebars and the widgets associated with them.
        $sidebars_widgets = wp_get_sidebars_widgets();

        foreach ($sidebars_widgets as $sidebar_id => $sidebar) {
            // If sidebar is not empty and is an array check to see if the widget is active in it.
            if (is_array($sidebar) && (!empty($sidebar)) && true === in_array($widget_id, $sidebar, true)) {
                // Once value is found exit the loop with value of sidebar_id.
                return $sidebar_id;
            }
        }
        // If the widget is not found return NULL.
        return false;
    }
}
