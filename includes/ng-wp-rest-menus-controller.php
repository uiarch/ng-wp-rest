<?php
/**
 * NGWPRestMenus Endpoints Controller Class for Menus
 *
 * Creates and registers the endpoints for menus data.
 *
 * @since 1.0.0
 * @package NGWP Endpoints
 */

include_once 'ng-wp-interface.php';

/**
 * NGWPRestMenus
 * Json rest api nav menu endpoints.
 *
 */
class NGWPRestMenus implements NGWPInterface
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
     * Registers all of our menu rest enpoints.
     *
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(self::NG_WP_MENU_NAMESPACE, '/menus', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getList'),
            ),
        ));

        register_rest_route(self::NG_WP_MENU_NAMESPACE, '/menus/(?P<id>\d+)', array(
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

        register_rest_route(self::NG_WP_MENU_NAMESPACE, '/menu-locations', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getItemsLocations'),
            ),
        ));

        register_rest_route(self::NG_WP_MENU_NAMESPACE, '/menu-locations/(?P<location>[a-zA-Z0-9_-]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getItemsLocation'),
            ),
        ));
    }

    /**
     * Read single menu by id.
     *
     * http://{url}/wp-json/ng-menu-route/v2/menus/{id}
     *
     * @param object $request
     * @return array filtered
     */
    public function get($request)
    {

        $id = $request['id'];
        $rest_url = get_rest_url() . self::NG_WP_API_NAMESPACE . '/menus/';
        $wp_menu_object = $id ? wp_get_nav_menu_object($id) : array();
        $wp_menu_items = $id ? wp_get_nav_menu_items($id) : array();
        $rest_menu = array();

        if ($wp_menu_object) {
            $menu = (array) $wp_menu_object;
            $rest_menu['ID'] = abs($menu['term_id']);
            $rest_menu['name'] = $menu['name'];
            $rest_menu['slug'] = $menu['slug'];
            $rest_menu['description'] = $menu['description'];
            $rest_menu['count'] = abs($menu['count']);
            $rest_menu_items = array();

            foreach ($wp_menu_items as $wp_menu_item) {
                $rest_menu_items[] = $this->formatRestItem($wp_menu_item);
            }

            $rest_menu_items = $this->nestedMenuItems($rest_menu_items, 0);
            $rest_menu['items'] = $rest_menu_items;
            $rest_menu['meta']['links']['collection'] = $rest_url;
            $rest_menu['meta']['links']['self'] = $rest_url . $id;
        }

        return apply_filters('rest_menus_format_menu', $rest_menu);
    }

    /**
     * Read multiple menus list.
     *
     * http://{url}/wp-json/ng-menu-route/v2/menus
     *
     * @return array filtered
     */
    public function getList()
    {
        $rest_url = trailingslashit(get_rest_url() . self::NG_WP_PLUGIN_NAMESPACE . '/menus/');
        $wp_menus = wp_get_nav_menus();
        $i = 0;
        $rest_menus = array();

        foreach ($wp_menus as $wp_menu) {
            $menu = (array) $wp_menu;
            $rest_menus[$i] = $menu;
            $rest_menus[$i]['ID'] = $menu['term_id'];
            $rest_menus[$i]['name'] = $menu['name'];
            $rest_menus[$i]['slug'] = $menu['slug'];
            $rest_menus[$i]['description'] = $menu['description'];
            $rest_menus[$i]['count'] = $menu['count'];
            $rest_menus[$i]['meta']['links']['collection'] = $rest_url;
            $rest_menus[$i]['meta']['links']['self'] = $rest_url . $menu['term_id'];
            $i++;
        }

        return apply_filters('rest_menus_format_menus', $rest_menus);
    }

    /**
     * Read single menu items location by id.
     *
     * http://{url}/wp-json/ng-menu-route/v2/menu-locations/header-menu
     *
     * @param object $request
     * @return array $rev_menu
     */
    public function getMenusLocation($request)
    {
        $params = $request->get_params();
        $location = $params['location'];
        $locations = get_nav_menu_locations();

        if (!isset($locations[$location])) {
            return array();
        }

        $wp_menu = wp_get_nav_menu_object($locations[$location]);
        $menu_items = wp_get_nav_menu_items($wp_menu->term_id);
        /**
         * wp_get_nav_menu_items() outputs a list that's already sequenced correctly.
         * So the easiest thing to do is to reverse the list and then build our tree
         * from the ground up
         */
        $rev_items = array_reverse($menu_items);
        $rev_menu = array();
        $cache = array();

        foreach ($rev_items as $item) {
            $formatted = array(
                'ID' => abs($item->ID),
                'order' => (int) $item->menu_order,
                'parent' => abs($item->menu_item_parent),
                'title' => $item->title,
                'url' => $item->url,
                'attr' => $item->attr_title,
                'target' => $item->target,
                'classes' => implode(' ', $item->classes),
                'xfn' => $item->xfn,
                'description' => $item->description,
                'object_id' => abs($item->object_id),
                'object' => $item->object,
                'type' => $item->type,
                'type_label' => $item->type_label,
                'children' => array(),
            );
            if (array_key_exists($item->ID, $cache)) {
                $formatted['children'] = array_reverse($cache[$item->ID]);
            }
            $formatted = apply_filters('rest_menus_format_menu_item', $formatted);
            if ($item->menu_item_parent != 0) {
                if (array_key_exists($item->menu_item_parent, $cache)) {
                    array_push($cache[$item->menu_item_parent], $formatted);
                } else {
                    $cache[$item->menu_item_parent] = array($formatted);
                }
            } else {
                array_push($rev_menu, $formatted);
            }
        }

        return array_reverse($rev_menu);
    }

    /**
     * Read multiple menu locations.
     *
     * http://{url}/wp-json/ng-menu-route/v2/menu-locations
     *
     * @param object $request
     * @return object $rest_menus
     */
    public function getMenusLocations($request)
    {
        $locations = get_nav_menu_locations();
        $registered_menus = get_registered_nav_menus();
        $rest_url = get_rest_url() . self::NG_WP_API_NAMESPACE . '/menu-locations/';
        $rest_menus = array();

        if ($locations && $registered_menus) {
            foreach ($registered_menus as $slug => $label) {
                // Sanity check
                if (!isset($locations[$slug])) {
                    continue;
                }
                $rest_menus[$slug]['ID'] = $locations[$slug];
                $rest_menus[$slug]['label'] = $label;
                $rest_menus[$slug]['meta']['links']['collection'] = $rest_url;
                $rest_menus[$slug]['meta']['links']['self'] = $rest_url . $slug;
            }
        }

        return $rest_menus;
    }

    /**
     * Separates our json data into parent children relationships.
     *
     * @param object $menu_items
     * @param integer $parent
     * @return array $parents
     */
    private function nestedMenuItems(&$menu_items, int $parent = null)
    {
        $parents = array();
        $children = array();
        // Separate menu_items into parents & children.
        array_map(function ($i) use ($parent, &$children, &$parents) {
            if ($i['id'] != $parent && $i['parent'] == $parent) {
                $parents[] = $i;
            } else {
                $children[] = $i;
            }
        }, $menu_items);

        foreach ($parents as &$parent) {
            if ($this->hasChildren($children, $parent['id'])) {
                $parent['children'] = $this->nestedMenuItems($children, $parent['id']);
            }
        }

        return $parents;
    }

    /**
     * Checks if json array has children.
     *
     * @param array $items
     * @param integer $id
     * @return boolean
     */
    private function hasChildren(array $items, int $id)
    {
        return array_filter($items, function ($i) use ($id) {
            return $i['parent'] == $id;
        });
    }

    /**
     * Returns json arrays children menu items.
     *
     * @param integer $parent_id
     * @param array $nav_menu_items
     * @param boolean $depth
     * @return array $nav_menu_item_list
     */
    private function getNavMenuItemChildren(int $parent_id, array $nav_menu_items, bool $depth = true)
    {
        $nav_menu_item_list = array();
        foreach ((array) $nav_menu_items as $nav_menu_item):
            if ($nav_menu_item->menu_item_parent == $parent_id):
                $nav_menu_item_list[] = $this->formatRestItem($nav_menu_item, true, $nav_menu_items);
                if ($depth) {
                    if ($children = $this->getNavMenuItemChildren($nav_menu_item->ID, $nav_menu_items)) {
                        $nav_menu_item_list = array_merge($nav_menu_item_list, $children);
                    }
                }
            endif;
        endforeach;
        return $nav_menu_item_list;
    }

    /**
     * Formats our json array, makes it prettier.
     *
     * @param object $menu_item
     * @param boolean $hasChildren
     * @param array $menu
     * @return array filtered
     */
    public function formatRestItem($menu_item, bool $hasChildren = false, array $menu = array())
    {
        $item = (array) $menu_item;
        $menu_item = array(
            'id' => abs($item['ID']),
            'order' => (int) $item['menu_order'],
            'parent' => abs($item['menu_item_parent']),
            'title' => $item['title'],
            'url' => $item['url'],
            'attr' => $item['attr_title'],
            'target' => $item['target'],
            'classes' => implode(' ', $item['classes']),
            'xfn' => $item['xfn'],
            'description' => $item['description'],
            'object_id' => abs($item['object_id']),
            'object' => $item['object'],
            'object_slug' => get_post($item['object_id'])->post_name,
            'type' => $item['type'],
            'type_label' => $item['type_label'],
        );
        if ($hasChildren === true && !empty($menu)) {
            $menu_item['children'] = $this->getNavMenuItemChildren($item['ID'], $menu);
        }
        return apply_filters('rest_menus_format_menu_item', $menu_item);
    }
}
