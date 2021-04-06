<?php

namespace Drupal\bfd\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;

use Drupal\bfd\Utilities;
use Drupal\bfd\Subsidies\Subsidy;
use Drupal\bfd\Content\Media;
use Drupal\bfd\Content\PDF as Pdf;
use Drupal\bfd\Content\Related as RelatedService;

/**
 * @Block(
 *
 *  id          = "bfd_subsidy_infopackage",
 *  admin_label = @Translation("Bfd Subsidy Info Package"),
 *  category    = @Translation("Content"),
 * )
 */
class SubsidyInfoPackage extends BlockBase implements ContainerFactoryPluginInterface {

   /**
    * @var Subsidy
    */
    protected $subsidy;

   /**
    * @var Utilities
    */
    protected $utils;

    /**
     * @var RelatedService
     */
    protected $related;

    /**
     * @var Pdf
     */
    protected $pdf;

    /**
     * @var Media
     */
    protected $media;

    protected function getChecklists() {

        $checklists = $this->related->getRelatedChecklists();

        // Push least important checklist(s) to bottom.

        $to_bottom  = [433]; // Checklist by file id

        usort($checklists, function ($a, $b) use ($to_bottom) {

            if (!in_array($a->fid, $to_bottom) && !in_array($b->fid, $to_bottom)) {
            
                return 0;
            }

            if (in_array($a->fid, $to_bottom) && in_array($b->fid, $to_bottom)) {
            
                return 0;
            }

            return in_array($a->fid, $to_bottom) ? 1 : -1;
        });

        return $checklists;
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
    
        $node = $this->utils->getCurrentNode();
        $data = [];

        if ($node->getType() == 'subsidy') {

            $checklists = $this->getChecklists();
            $pdf        = $this->pdf->get($node) ?? null;

            if (!empty($checklists)) {

                $data['checklists'] = $checklists;
            }

            if ($pdf) {

                list($media, $file) = $pdf;

                $file          = $this->media->getFileInfo($file);

                $file['label'] = $node->label(); 
                $file['url']   = Url::fromRoute('bfd.download', ['fn' => $file['filename']])->toString();

                $data['pdf']   = $file;
            }
        }

        // On caching & render arrays, cf.:
        // - https://drupal.stackexchange.com/questions/199527/how-do-i-correctly-setup-caching-for-my-custom-block-showing-content-depending-o
        // - https://www.drupal.org/docs/8/api/render-api/cacheability-of-render-arrays

        return [

            '#theme' => 'bfd-subsidy-infopackage',
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
            $container->get('bfd.related'),
            $container->get('bfd.pdf'),
            $container->get('bfd.media_service')
        );
    }

   /**
    * @param array 
    * @param string
    * @param array 
    * @param Menu 
    */
    public function __construct(array $configuration, string $plugin_id, array $plugin_definition, Utilities $utils, Subsidy $subsidy, RelatedService $related, Pdf $pdf, Media $media) {

        parent::__construct($configuration, $plugin_id, $plugin_definition);

        $this->utils   = $utils;
        $this->subsidy = $subsidy;
        $this->related = $related;
        $this->pdf     = $pdf;
        $this->media   = $media;
    }
}
