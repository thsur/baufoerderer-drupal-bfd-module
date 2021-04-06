<?php

namespace Drupal\bfd\Routing;

use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Routing\RouteSubscriberBase;

/**
 * Listen to route events.
 *
 * Test with 'drush cr', since routes are generated only when the cache is cold.
 *
 * Cf.:
 * - https://drupal.stackexchange.com/questions/215141/take-over-display-of-a-content-type-node-route
 * - https://www.drupal.org/node/2187643
 * - https://cipix.nl/understanding-drupal-8-part-3-routing
 * - https://thinktandem.io/blog/2017/12/03/handling-dynamic-routes-in-drupal-8/
 * - https://thinkshout.com/blog/2016/07/drupal-8-routing-tricks-for-better-admin-urls/
 * - https://drupal.stackexchange.com/questions/221755/custom-validation-of-route-parameters
 */
class RouteSubscriber extends RouteSubscriberBase {

    /**
     * {@inheritdoc}
     */
    public function alterRoutes(RouteCollection $collection) {

        // Replace controller for route entity.node.canonical.
        // Cf. https://drupal.stackexchange.com/a/215149/93901
        
        if ($route = $collection->get('entity.node.canonical')) {

            $route->setDefault('_controller', '\Drupal\bfd\Controller\FrontController::dispatch');
        }

        // Always deny access to unwanted routes.
        // Cf. https://drupal.stackexchange.com/a/272234/93901
        
        $disallow_routes = ['user.register', 'user.pass'];

        foreach ($disallow_routes as $disallow_route) {
          
            if ($route = $collection->get($disallow_route)) {
                
                $route->setRequirement('_access', 'FALSE');
            }
        }
    }
}