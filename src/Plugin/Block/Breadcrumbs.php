<?php

namespace Drupal\bfd\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Block\BlockBase;

use Drupal\bfd\Menu\Menu;
use Drupal\bfd\Utilities;

/**
 * @Block(
 *
 *  id          = "bfd_breadcrumbs",
 *  admin_label = @Translation("Bfd Breadcrumbs"),
 *  category    = @Translation("Menus"),
 * )
 */
class Breadcrumbs extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var Menu
   */
  protected $menu;

  /**
   * @var Utilities
   */
  protected $utils;

  /**
   * {@inheritdoc}
   */
  public function build() {

    $data = $this->menu->getBreadcrumbs();

    if (count($data) == 1) {

      $node = $this->utils->getCurrentNode();
      
      // Target audience pages are not part of the term trail
      // the page is build upon {@see Drupal\bfd\Menu\Toc},
      // so we have to tread them differently.

      if ($node && $node->bundle() == 'target_audience_hub') { 

        $data[] = ['name' => $node->getTitle()];
      }
      else {

        $data = []; // We're on home, so don't show a trail
      }

    }

    return [

      '#theme' => 'bfd-breadcrumbs',
      '#data'  => $data
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    
    return new static(
      
      $configuration, $plugin_id, $plugin_definition,
      $container->get('bfd.menu'),
      $container->get('bfd.utilities')
    );
  }

  /**
   * @param array 
   * @param string
   * @param array 
   * @param Menu 
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, Menu $menu, Utilities $utils) {
    
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->menu  = $menu;
    $this->utils = $utils;
  }
}
