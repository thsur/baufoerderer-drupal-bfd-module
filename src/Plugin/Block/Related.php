<?php

namespace Drupal\bfd\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Block\BlockBase;

use Drupal\bfd\Utilities;
use Drupal\bfd\Content\Related as RelatedService;

/**
 * @Block(
 *
 *  id          = "bfd_related",
 *  admin_label = @Translation("Bfd Related Content"),
 *  category    = @Translation("Content"),
 * )
 */
class Related extends BlockBase implements ContainerFactoryPluginInterface {

   /**
    * @var Utilities
    */
    protected $utils;

   /**
    * @var RelatedService
    */
    protected $related;

    public function checklists() {

        $checklists = $this->related->getRelatedChecklists();
        return $checklists;
    }

    public function related(string $type) {

        $related = $this->related->getRelatedByType($type);

        // Shorten result set
        return array_slice($related, 0, 7);
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
        
        $node   = $this->utils->getCurrentNode();
        $config = $this->getConfiguration();

        $data   = [];

        $type   = strtolower($config['type']) ?? null;
        $valid  = in_array($type, ['checklist', 'article', 'subsidy', 'guide', 'target_audience_hub']);

        if ($valid) {

            $data[] = [

                'type'  => $type, 
                'label' => $config['label'] ?? null, 
                'items' => $type == 'checklist' ? $this->checklists() : $this->related($type)
            ];
        }

        // On caching & render arrays, cf.:
        // - https://drupal.stackexchange.com/questions/199527/how-do-i-correctly-setup-caching-for-my-custom-block-showing-content-depending-o
        // - https://www.drupal.org/docs/8/api/render-api/cacheability-of-render-arrays

        return [

            '#theme' => 'bfd-related',
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
            $container->get('bfd.related')
        );
    }

   /**
    * @param array 
    * @param string
    * @param array 
    * @param Menu 
    */
    public function __construct(array $configuration, string $plugin_id, array $plugin_definition, Utilities $utils, RelatedService $related) {

        parent::__construct($configuration, $plugin_id, $plugin_definition);

        $this->utils   = $utils;
        $this->related = $related;
    }
}
