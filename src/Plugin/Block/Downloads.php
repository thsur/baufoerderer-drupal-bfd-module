<?php

namespace Drupal\bfd\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\node\Entity\Node;

use Drupal\bfd\Utilities;
use Drupal\bfd\Content\Media;

/**
 * @Block(
 *
 *  id          = "bfd_downloads",
 *  admin_label = @Translation("Bfd Downloads"),
 *  category    = @Translation("Content"),
 * )
 */
class Downloads extends BlockBase implements ContainerFactoryPluginInterface {

   /**
    * @var Media
    */
    protected $media;

   /**
    * @var Utilities
    */
    protected $utils;

    protected function getFiles() {

        $tags = [Media::CHECKLISTS, Media::GUIDES, Media::WIE];
        $data = $this->media->getFilesByTags($tags);

        return $data;
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
            

            default:
                $data = $this->getFiles();
                break;
        }

        // On caching & render arrays, cf.:
        // - https://drupal.stackexchange.com/questions/199527/how-do-i-correctly-setup-caching-for-my-custom-block-showing-content-depending-o
        // - https://www.drupal.org/docs/8/api/render-api/cacheability-of-render-arrays

        return [

            '#theme' => 'bfd-downloads',
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
            $container->get('bfd.media_service')
        );
    }

   /**
    * @param array 
    * @param string
    * @param array 
    * @param Menu 
    */
    public function __construct(array $configuration, string $plugin_id, array $plugin_definition, Utilities $utils, Media $media) {

        parent::__construct($configuration, $plugin_id, $plugin_definition);

        $this->utils = $utils;
        $this->media = $media;
    }
}
