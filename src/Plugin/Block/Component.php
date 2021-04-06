<?php

namespace Drupal\bfd\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\node\Entity\Node;

use Drupal\bfd\Utilities;
use Drupal\bfd\Subsidies\Subsidy;

/**
 * @Block(
 *
 *  id          = "bfd_component",
 *  admin_label = @Translation("Bfd micro-content component"),
 *  category    = @Translation("Content"),
 * )
 */
class Component extends BlockBase implements ContainerFactoryPluginInterface {

   /**
    * @var Utilities
    */
    protected $utils;

    /**
     * {@inheritdoc}
     */
    public function build() {
        
        $node   = $this->utils->getCurrentNode();
        $config = $this->getConfiguration();

        $data   = $config;

        // On caching & render arrays, cf.:
        // - https://drupal.stackexchange.com/questions/199527/how-do-i-correctly-setup-caching-for-my-custom-block-showing-content-depending-o
        // - https://www.drupal.org/docs/8/api/render-api/cacheability-of-render-arrays

        return [

            '#theme' => 'bfd-component',
            '#data'  => $data,
            '#cache' => [
                
                'tags'     => $node->getCacheTags(),
                'contexts' => ['route'] 
            ]
        ];
    }

   /**
    * {@inheritdoc}
    */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {

        return new static(
          
            $configuration, $plugin_id, $plugin_definition,
            $container->get('bfd.utilities')
        );
    }

   /**
    * @param array 
    * @param string
    * @param array 
    * @param Menu 
    */
    public function __construct(array $configuration, string $plugin_id, array $plugin_definition, Utilities $utils) {

        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->utils = $utils;
    }
}
