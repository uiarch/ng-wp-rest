<?php

/**
 * NG-WP Plugin interface
 *
 * ** Required Methods **
 *
 * - @method get(string $request)
 * - @method getList()
 * - @method getItemsLocation(string $request)
 * - @method getItemsLocations(string $request)
 * - @method registerRoutes()
 * - @method formatForResponse(array $item, bool $hasChildren, array $array)
 */
interface NGWPInterface
{
    const NG_WP_API_NAMESPACE = 'wp/v2';
    const NG_WP_MENU_NAMESPACE = 'ng-menu-route/v2';
    const NG_WP_WIDGET_NAMESPACE = 'ng-widget-route/v2';
    const NG_WP_SIDEBAR_NAMESPACE = 'ng-sidebar-route/v2';

    /**
     * Get method for returning single item.
     *
     * @param object $request
     * @return void
     */
    public function get($request);

    /**
     * Get list method for returning multiple items.
     *
     * @return void
     */
    public function getList();

    /**
     * Registers endpoints for current object.
     *
     * @return void
     */
    public function registerRoutes();
}
