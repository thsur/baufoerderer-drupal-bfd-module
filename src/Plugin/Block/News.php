<?php

namespace Drupal\bfd\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\node\Entity\Node;

use Drupal\bfd\Utilities;
use Drupal\bfd\Content\News as NewsService;
use Drupal\bfd\Content\Node as NodeService;

/**
 * @Block(
 *
 *  id          = "bfd_news",
 *  admin_label = @Translation("Bfd News"),
 *  category    = @Translation("Content"),
 * )
 */
class News extends BlockBase implements ContainerFactoryPluginInterface {

   /**
    * @var NewsService
    */
    protected $news;

   /**
    * @var NodeService
    */
    protected $node_service;

   /**
    * @var Utilities
    */
    protected $utils;

    /**
     * @var Array - most recent news
     */
    protected $recent;

    /**
     * Calculate the difference between two dates.
     *
     * Code borrowed from:
     * https://www.php.net/manual/en/function.date-diff.php#115065
     * 
     * @param  String - date('Y-m-d', ts)
     * @param  String - date('Y-m-d', ts)
     * @param  String - cf. https://www.php.net/manual/en/function.strftime.php
     * @return Numeric
     */
    protected function dateDiff($date_1, $date_2, $diff_by = '%a' ) {
    
        $interval = date_diff(

            date_create($date_1), 
            date_create($date_2)
        );
       
        return $interval->format($diff_by);
    }

    /**
     * Get, categorize & set most recent news.
     * 
     * @return Array
     */
    protected function getRecent() {

        if (is_array($this->recent)){

            return $this->recent;
        }

        $ids    = $this->news->getCurrent();
        $nodes  = $this->utils->loadNodes($ids);

        $recent = [

            'top_news' => [],
            'current'  => [],
        ];

        $order  = array_keys($nodes);

        foreach ($nodes as $key => $node) {

            $is_first = (array_search($key, $order) == 0);

            if ($is_first) {

                $date = $this->node_service->getFieldValues($node, 'field_date');
                $date = end($date);
                $now  = date('Y-m-d');

                if ($this->dateDiff($date, $now) <= 21) {

                    $recent['top_news'][] = $node;
                }
                else {

                    $recent['current'][] = $node;
                }
            }
            else {

                $recent['current'][] = $node;
            }
        }

        $this->recent = $recent;
        return $this->recent;
    }

    /**
     * Get all news.
     * 
     * @return Array
     */
    protected function getNews() {

        $ids   = $this->news->getAll();
        $nodes = $this->utils->loadNodes($ids);

        return $nodes;
    } 

    /**
     * Get most recent news, but only if it's not older than x days.
     * 
     * @return Array
     */
    protected function getTopNews() {

        $recent = $this->getRecent();
        return isset($recent['top_news']) ? $recent['top_news'] : [];
    }

    /**
     * Get most recent news.
     * 
     * @return Array
     */
    protected function getCurrentNews() {

        $recent = $this->getRecent();
        return isset($recent['current']) ? $recent['current'] : [];
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
    
        $node   = $this->utils->getCurrentNode();
        $config = $this->getConfiguration();
        
        $type   = $config['type'] ?? null;
        $data   = [];

        switch ($type) {
            
            case 'topnews':
                $data = $this->getTopNews() ?? [];
                break;

            case 'current':
                $data = $this->getCurrentNews() ?? [];
                break;

            default:
                $data = $this->getNews();
                break;
        }

        // On caching & render arrays, cf.:
        // - https://drupal.stackexchange.com/questions/199527/how-do-i-correctly-setup-caching-for-my-custom-block-showing-content-depending-o
        // - https://www.drupal.org/docs/8/api/render-api/cacheability-of-render-arrays

        return [

            '#theme' => 'bfd-news',
            '#data'  => [

                'type' => $type, 
                'data' => $data
            ],
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
            $container->get('bfd.news'),
            $container->get('bfd.node_service')
        );
    }

   /**
    * @param array 
    * @param string
    * @param array 
    * @param Menu 
    */
    public function __construct(array $configuration, string $plugin_id, array $plugin_definition, Utilities $utils, NewsService $news, NodeService $node_service) {

        parent::__construct($configuration, $plugin_id, $plugin_definition);

        $this->utils         = $utils;
        $this->news          = $news;
        $this->node_service  = $node_service;
    }
}
