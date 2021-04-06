<?php

namespace Drupal\bfd\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Controller\NodeViewController;

use Drupal\bfd\Menu\Toc;

/**
 * Node front controller.
 */
class FrontController extends NodeViewController {

    /**
     * @var Toc
     */
    protected $menu;

    /**
     * @return void
     */
    protected function init() {}

    public function dispatch(EntityInterface $node, Request $request) {

        $view = parent::view($node);
        return $view;
    }
}
