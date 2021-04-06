<?php

namespace Drupal\bfd\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Block\BlockBase;

use Drupal\bfd\Menu\Menu;
use Drupal\bfd\Menu\Toc;
use Drupal\bfd\Utilities;

use \Collator;

/**
 * @Block(
 *
 *  id          = "bfd_hub_page_tocs",
 *  admin_label = @Translation("Bfd Hub Page Tocs"),
 *  category    = @Translation("Content"),
 * )
 */
class HubPageTocs extends BlockBase implements ContainerFactoryPluginInterface {

   /**
    * @var Menu
    */
    protected $menu;

   /**
    * @var Toc
    */
    protected $toc;

   /**
    * @var Utilities
    */
    protected $utils;

    /**
     * {@inheritdoc}
     */
    public function build() {
    
        $node = $this->utils->getCurrentNode();
        $toc  = [];

        // Collect all child terms attached to the current node  

        foreach ($this->toc->getMainToc() as $item) {

            if (property_exists($item, 'node') && $item->node->id() == $node->id() && property_exists($item, 'children')) {

                foreach ($item->children as $child) {

                    $toc[] = clone $child; 
                }
            } 
        }

        if (empty($toc)) {

            return;
        }  

        // Collect all nodes tagged by those terms  
        
        foreach ($toc as $key => $item) {

            $nodes = $this->utils->loadNodesByTerms('field_toc', [$item->tid]);

            // Filter them by publishing status and type 
            
            $nodes = array_filter(

                $this->utils->getPublished($nodes),

                function ($node) { 

                    return in_array($node->bundle(), ['article', 'guide']);
                }
            );

            if (empty($nodes)) {

                continue;
            }

            // Sort them alphabetically

            $collator = new Collator($this->utils->getLanguage());

            usort($nodes, function ($a, $b) use ($collator) {

                return $collator->compare($a->get('title')->value, $b->get('title')->value);
            });

            // Attach them to their respective term  

            $item->nodes  = $nodes;

            // Get term teaser text

            if (property_exists($item, 'node')) {

                $item->teaser = $item->node->get('body')->value;
            }

            // Get term nav entry

            $item->nav = $this->menu->getTocMenu([$item])[0];
        }

        return [

            '#theme' => 'bfd-hub-page-tocs',
            '#data'  => $toc
        ];
    }

   /**
    * {@inheritdoc}
    */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {

        return new static(
          
            $configuration, $plugin_id, $plugin_definition,
            $container->get('bfd.menu'),
            $container->get('bfd.toc'),
            $container->get('bfd.utilities')
        );
    }

   /**
    * @param array 
    * @param string
    * @param array 
    * @param Menu 
    */
    public function __construct(array $configuration, string $plugin_id, array $plugin_definition, Menu $menu, Toc $toc, Utilities $utils) {

        parent::__construct($configuration, $plugin_id, $plugin_definition);

        $this->menu  = $menu;
        $this->toc   = $toc;
        $this->utils = $utils;
    }
}
