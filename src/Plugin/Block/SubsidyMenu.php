<?php

namespace Drupal\bfd\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Block\BlockBase;

use Drupal\bfd\Menu\Menu;

/**
 * @Block(
 *
 *  id          = "bfd_subsidy_menu",
 *  admin_label = @Translation("Bfd Subsidy Footer Menu"),
 *  category    = @Translation("Menus"),
 * )
 */
class SubsidyMenu extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var Menu
   */
  protected $menu;

  /**
   * {@inheritdoc}
   */
  public function build() {
    
    return [

      '#theme' => 'bfd-subsidy-footer-menu',
      '#data'   => $this->menu->getSubsidyMenu()
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    
    return new static(
      
      $configuration, $plugin_id, $plugin_definition,
      $container->get('bfd.menu')
    );
  }

  /**
   * @param array 
   * @param string
   * @param array 
   * @param Menu 
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, Menu $menu) {
    
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menu = $menu;
  }
}
