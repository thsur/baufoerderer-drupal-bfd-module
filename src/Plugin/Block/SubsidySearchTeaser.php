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
use Drupal\bfd\Content\Node as NodeService;

/**
 * @Block(
 *
 *  id          = "bfd_subsidy_search_teaser",
 *  admin_label = @Translation("Bfd Subsidy Search Teaser"),
 *  category    = @Translation("Content"),
 * )
 */
class SubsidySearchTeaser extends BlockBase implements ContainerFactoryPluginInterface {

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
     * @var NodeService
     */
    private $node_service;

    /**
     * @return String
     */
    protected function getSubsidySearchUrl (): string {

        return $this->utils->getUrlFromNodeId($this->toc::SUBSIDY_SEARCH_NID);
    }

    /**
     * Get request referrer.
     * 
     * @return String|void
     */
    protected function getReferrer () {

        $referrer = $this->request->headers->get('referer');

        if ($referrer && is_string($referrer)) {

            return parse_url($referrer, PHP_URL_PATH); 
        }
    }

    /**
     * Get first found subsidy term id of the current node, if any.
     * 
     * @return Array - term id, label
     */
    protected function getPreselect (Node $node): array {

       $categories = $this->node_service->getFieldValues($node, 'field_content_categories');
       $map        = $this->subsidy->getVocab('content_categories');

       foreach ($categories as $id) {

            if (array_key_exists($id, $map)) {

                return [

                    'id'    => $id,
                    'value' => $map[$id]
                ];
            }
       }

       return [];
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
        
        $config = $this->getConfiguration();

        $node   = $this->node_service->getCurrentNode();
        $data   = [];

        $type     = $config['type'] ?? 'form';
        $fallback = $config['fallback'] ?? null;

        if ($type == 'history_back' && $this->getReferrer() == $this->getSubsidySearchUrl()) {

            $data  = [

                'type' => $type
            ];
        }

        if ($type == 'form' || $fallback == 'form') {

            $data  = [

                'type'   => 'form',
                'action' => $this->getSubsidySearchUrl()
            ];

            if (isset($config['preselect']) && $config['preselect']) {

                $select = $this->getPreselect($node);

                if (!empty($select)) {

                    $data['preselect'] = [

                        'id'    => json_encode([$select['id']]),
                        'value' => $select['value'],
                    ];
                }
            }
        }

        // On caching & render arrays, cf.:
        // - https://drupal.stackexchange.com/questions/199527/how-do-i-correctly-setup-caching-for-my-custom-block-showing-content-depending-o
        // - https://www.drupal.org/docs/8/api/render-api/cacheability-of-render-arrays

        return [

            '#theme' => 'bfd-subsidy-search-teaser',
            '#data'  => $data,
            '#cache' => [
                
                'tags'     => $node ? $node->getCacheTags() : null,
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
            $container->get('request_stack'),
            $container->get('bfd.node_service')
        );
    }

   /**
    * @param array 
    * @param string
    * @param array 
    * @param Menu 
    */
    public function __construct(array $configuration, string $plugin_id, array $plugin_definition, Utilities $utils, Subsidy $subsidy, Toc $toc, RequestStack $request, NodeService $node_service) {

        parent::__construct($configuration, $plugin_id, $plugin_definition);

        $this->utils        = $utils;
        $this->subsidy      = $subsidy;
        $this->toc          = $toc;
        $this->request      = $request->getCurrentRequest();
        $this->node_service = $node_service;
    }
}
