<?php

namespace Drupal\bfd\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\node\Entity\Node;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;

use Drupal\bfd\Utilities;
use Drupal\bfd\Subsidies\Subsidy;
use Drupal\bfd\Menu\Toc;

/**
 * @Block(
 *
 *  id          = "bfd_subsidy_search",
 *  admin_label = @Translation("Bfd Subsidy Search"),
 *  category    = @Translation("Content"),
 * )
 */
class SubsidySearch extends BlockBase implements ContainerFactoryPluginInterface {

   /**
    * @var Subsidy
    */
    protected $subsidy;

   /**
    * @var Utilities
    */
    protected $utils;

   /**
    * @var Toc
    */
    protected $toc;

    /**
     * @var Request
     */
    private $request;

    /**
     * {@inheritdoc}
     */
    public function build() {
        
        $node   = $this->utils->getCurrentNode();
        $select = $this->request->request->get('terms_index');

        $data   = json_encode(

            array_merge(
                
                $this->subsidy->getFacetsMap(), 
                [
                    'selected'  => json_decode(!empty($select) ? $select : '[]'),
                    'is_front'  => $this->utils->isFront(), 
                    'submit_to' => $this->utils->getUrlFromNodeId($this->toc::SUBSIDY_SEARCH_NID)
                ]
            ), 
            true
        );

        // On caching & render arrays, cf.:
        // - https://drupal.stackexchange.com/questions/199527/how-do-i-correctly-setup-caching-for-my-custom-block-showing-content-depending-o
        // - https://www.drupal.org/docs/8/api/render-api/cacheability-of-render-arrays

        return [

            '#theme' => 'bfd-subsidy-search',
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
            $container->get('bfd.subsidy'),
            $container->get('bfd.toc'),
            $container->get('request_stack')
        );
    }

   /**
    * @param array 
    * @param string
    * @param array 
    * @param Menu 
    */
    public function __construct(array $configuration, string $plugin_id, array $plugin_definition, Utilities $utils, Subsidy $subsidy, Toc $toc, RequestStack $request) {

        parent::__construct($configuration, $plugin_id, $plugin_definition);

        $this->utils   = $utils;
        $this->subsidy = $subsidy;
        $this->toc     = $toc;
        $this->request = $request->getCurrentRequest();
    }
}
