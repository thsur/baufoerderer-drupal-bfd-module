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
 *  id          = "bfd_kfw_contact",
 *  admin_label = @Translation("Bfd KfW Point of Contact"),
 *  category    = @Translation("Content"),
 * )
 */
class KfWPointOfContact extends BlockBase implements ContainerFactoryPluginInterface {

   /**
    * @var Subsidy
    */
    protected $subsidy;

   /**
    * @var Utilities
    */
    protected $utils;

    /**
     * @param  Node
     * @return String|void
     */
    public function getPoC(Node $node) {

        // Get KfW program number from node title

        preg_match_all('/[0-9]{3}(.[0-9]{3})?/', $node->getTitle(), $kfw_num);

        if (empty($kfw_num[0])) {

            return;
        }

        $kfw_num = $kfw_num[0][0];

        // Tracking links

        $links = array(

            '153'     => 'https://www.kfw.de/inlandsfoerderung/Privatpersonen/Neubau/Finanzierungsangebote/Energieeffizient-Bauen-(153)/?kfwmc=vt.kooperationen|per.baufoerderer.ebs-au.microsite.button|153&wt_cc1=wohnen&wt_cc2=pri|neubau#4',
            '151/152' => 'https://www.kfw.de/inlandsfoerderung/Privatpersonen/Bestandsimmobilien/Finanzierungsangebote/Energieeffizient-Sanieren-Kredit-(151-152)/?kfwmc=vt.kooperationen|per.baufoerderer.ebs-au.microsite.button|151&wt_cc1=wohnen&wt_cc2=pri|bestandimmobilie#9',
            '124'     => 'https://www.kfw.de/inlandsfoerderung/Privatpersonen/Neubau/Finanzierungsangebote/Wohneigentumsprogramm-(124)/?kfwmc=vt.kooperationen|per.baufoerderer.ebs-au.microsite.button|124&wt_cc1=wohnen&wt_cc2=pri|neubau',
            '159'     => 'https://www.kfw.de/inlandsfoerderung/Privatpersonen/Bestandsimmobilien/Finanzierungsangebote/Altersgerecht-umbauen-(159)/index-2.html?kfwmc=vt.kooperationen|per.baufoerderer.ebs-au.microsite.button|159&wt_cc1=wohnen&wt_cc2=pri|bestandimmobilie',
            '167'     => 'https://www.kfw.de/inlandsfoerderung/Privatpersonen/Bestandsimmobilien/Finanzierungsangebote/Energieeffizient-Sanieren-Erg%C3%A4nzungskredit-(167)/?kfwmc=vt.kooperationen|per.baufoerderer.ebs-au.microsite.button|167&wt_cc1=wohnen&wt_cc2=pri|bestandimmobilie',
            '430'     => 'https://www.kfw.de/inlandsfoerderung/Privatpersonen/Bestandsimmobilien/Finanzierungsangebote/Energieeffizient-Sanieren-Zuschuss-(430)/?kfwmc=vt.kooperationen|per.baufoerderer.ebs-au.microsite.button|430&wt_cc1=wohnen&wt_cc2=pri|bestandimmobilie#9',
            '433'     => 'https://www.kfw.de/inlandsfoerderung/Privatpersonen/Bestandsimmobilie/F%C3%B6rderprodukte/Energieeffizient-Bauen-und-Sanieren-Zuschuss-Brennstoffzelle-(433)/?kfwmc=vt.kooperationen|per.baufoerderer.ebs-au.microsite.button|433&wt_cc1=wohnen&wt_cc2=pri|bestandimmobilie#5',
            '424'     => 'https://www.kfw.de/inlandsfoerderung/Privatpersonen/Bestandsimmobilie/Zuschussportal/Online-Antrag-Baukindergeld/?kfwmc=vt.kooperationen|per.baufoerderer.ebs-au.microsite.button|424&wt_cc1=wohnen&wt_cc2=pri|bestandimmobilie',
            '455'     => 'https://www.kfw.de/inlandsfoerderung/Privatpersonen/Bestandsimmobilie/Zuschussportal/Online-Antrag-Energieeffizienz-Einbruchschutz-Barrierereduzierung/?kfwmc=vt.kooperationen|per.baufoerderer.ebs-au.microsite.button|455&wt_cc1=wohnen&wt_cc2=pri|bestandimmobilie',
            '431'     => 'https://www.kfw.de/inlandsfoerderung/Privatpersonen/Bestandsimmobilien/Finanzierungsangebote/Energieeffizient-Sanieren-Baubegleitung-(431)/?kfwmc=vt.kooperationen|per.baufoerderer.ebs-au.microsite.button|431&wt_cc1=wohnen&wt_cc2=pri|bestandimmobilie',
            '440'     => 'https://www.kfw.de/440?kfwmc=vt.kooperationen|per.baufoerderer.ebs-au.microsite.button|440&wt_cc1=wohnen&wt_cc2=pri|bestandimmobilie<https://www.kfw.de/inlandsfoerderung/Privatpersonen/Energieeffizient-Sanieren-mit-Tilgungszuschuss?kfwmc=vt.kooperationen|per.immonet.ees.sel-expose-kombi.sticky-skyscraper|typomotiv-zuschuss&wt_cc1=wohnen&wt_cc2=pri|bestandimmobilie'
        );
  
        return $links[$kfw_num] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
        
        $node     = $this->utils->getCurrentNode();
        $config   = $this->getConfiguration();

        $data     = [];
     
        if ($node->bundle() == 'subsidy' && $this->subsidy->isKfW($node)) {

            $type = $this->subsidy->getType($node);
            $data = [

                'url'    => 'https://www.kfw.de/inlandsfoerderung/Privatpersonen',
                'text'   => 'So beantragen Sie Ihren KfW-'.(in_array(Subsidy::TYPE_LOAN, $type) ? 'Kredit' : 'Zuschuss'),
                'button' => [

                    'label'      => 'Nächste Schritte', 
                    'aria_label' => 'Nächste Schritte zur Beantragung des Fördermittels'
                ]
            ];
            
            $poc = $this->getPoC($node);
            
            if ($poc) {

                $data['url'] = $poc;
            }
        }

        // On caching & render arrays, cf.:
        // - https://drupal.stackexchange.com/questions/199527/how-do-i-correctly-setup-caching-for-my-custom-block-showing-content-depending-o
        // - https://www.drupal.org/docs/8/api/render-api/cacheability-of-render-arrays

        return [

            '#theme' => 'bfd-component--cta',
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
            $container->get('bfd.subsidy')
        );
    }

   /**
    * @param array 
    * @param string
    * @param array 
    * @param Menu 
    */
    public function __construct(array $configuration, string $plugin_id, array $plugin_definition, Utilities $utils, Subsidy $subsidy) {

        parent::__construct($configuration, $plugin_id, $plugin_definition);

        $this->utils   = $utils;
        $this->subsidy = $subsidy;
    }
}
