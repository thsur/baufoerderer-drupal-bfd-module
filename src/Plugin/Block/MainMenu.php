<?php

namespace Drupal\bfd\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Block\BlockBase;

use Drupal\bfd\Menu\Menu;

/**
 * Display the main menu. 
 * 
 * By also implementing ContainerFactoryPluginInterface, we're able to get hold of the service container.
 *
 * For the annotation stuff below, see:
 * 
 * - https://www.drupal.org/docs/8/api/plugin-api/annotations-based-plugins
 * - https://www.doctrine-project.org/projects/doctrine-orm/en/2.6/reference/annotations-reference.html
 * - https://www.drupal.org/docs/8/api/block-api/block-api-overview
 *
 * On how to create blocks, see:
 *
 * - https://www.webwash.net/programmatically-create-block-drupal-8/
 * - https://www.valuebound.com/resources/blog/drupal-8-how-to-create-a-custom-block-programatically
 */

/**
 * @Block(
 *
 *  id          = "bfd_main_menu",
 *  admin_label = @Translation("Bfd Main Menu"),
 *  category    = @Translation("Menus"),
 * )
 */
class MainMenu extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var Menu
   */
  protected $menu;

  /**
   * {@inheritdoc}
   */
  public function build() {
    
    $data = $this->menu->getMainMenu();

    return [

      '#theme' => 'bfd-main-menu',
      '#data'   => $data
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
