<?php

namespace Drupal\bfd\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\node\Entity\Node;

use Drupal\bfd\Utilities;
use Drupal\bfd\Content\Node as NodeService;
use Drupal\bfd\Content\Terms as TermsService;

use Symfony\Component\Yaml\Yaml;

/**
 * @Block(
 *
 *  id          = "bfd_calculator",
 *  admin_label = @Translation("Bfd Generic Calculator"),
 *  category    = @Translation("Content"),
 * )
 */
class Calculator extends BlockBase implements ContainerFactoryPluginInterface {

   /**
    * @var NodeService
    */
    protected $node_service;

   /**
    * @var TermsService
    */
    protected $terms_service;

   /**
    * @var Utilities
    */
    protected $utils;

   /**
    * @var ModuleHandler
    */
    protected $module_handler;

    protected function getData(Node $node) {

        // Get data for certain node types only

        if (!in_array($node->bundle(), ['article', 'guide'])) {

            return false;
        }

        // And only for those having certain values

        $categories = $this->node_service->getFieldValues($node, 'field_content_categories');
        $valid      = $this->terms_service->getCategoriesMatching(['bau', 'kauf'], ['umbau']);

        $is_valid   = !empty(array_intersect(array_values($categories), array_keys($valid)));

        if (!$valid) {

            return false;
        }

        // Try to get the data from cache

        $data = $this->utils->getFromCache('bfd.calc.data_buy_build');

        if ($data) {

            return $data;
        }

        // Otherwise, get it from file storage

        $file = $this->module_handler->getModule('bfd')->getPath().'/data/calc_buy_build.yml';

        if (is_readable($file)) {

            try {

                $data = Yaml::parse(file_get_contents($file));
                $this->utils->cache('bfd.calc.data_buy_build', $data);

                return $data;
            }
            catch (\Exception $e) {

                \Drupal::logger('bfd')->warn('Unable to parse file '.$file);
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
        
        $node   = $this->utils->getCurrentNode();
        $data   = $this->getData($node) ?? [];

        if (!empty($data)) {

            $data = json_encode($data, true);
        }

        // On caching & render arrays, cf.:
        // - https://drupal.stackexchange.com/questions/199527/how-do-i-correctly-setup-caching-for-my-custom-block-showing-content-depending-o
        // - https://www.drupal.org/docs/8/api/render-api/cacheability-of-render-arrays

        return [

            '#theme' => 'bfd-calculator',
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
            $container->get('bfd.utilities'),
            $container->get('bfd.node_service'),
            $container->get('bfd.terms_service'),
            $container->get('module_handler')
        );
    }

   /**
    * @param array 
    * @param string
    * @param array 
    * @param Menu 
    */
    public function __construct(array $configuration, string $plugin_id, array $plugin_definition, 
                                Utilities $utils, NodeService $node_service, TermsService $terms_service, ModuleHandler $handler) {

        parent::__construct($configuration, $plugin_id, $plugin_definition);

        $this->utils          = $utils;
        $this->node_service   = $node_service;
        $this->terms_service  = $terms_service;
        $this->module_handler = $handler;
    }
}
