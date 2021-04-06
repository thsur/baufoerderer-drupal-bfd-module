<?php

namespace Drupal\bfd;

use Drupal\node\Entity\Node;

use Drupal\bfd\Utilities;
use Drupal\bfd\Menu\Toc;

class PathHelper {
    
    /**
     * @var Utilities
     */
    protected $utils;

    /**
     * @var Toc
     */
    protected $toc;

    /**
     * @param  Node
     * @return array
     */
    public function nodeGetTermTrail(Node $node): array {

        $type  = $node->bundle();
        
        // Get a point of departure 
        
        $map     = $this->toc->getNodeTermMap();
        $term_id = null;

        switch ($type) {

            case 'subsidy':
                $term_id = $this->toc::SUBSIDY_SEARCH_TID;
                break;

            default:
                $term_id = $map[$node->id()]; 
                break;
        }

        if ($node->id() == $this->toc::SUBSIDY_SEARCH_NID) {

            $term_id = $this->toc::SUBSIDY_MAIN_TID;
        }

        // Figure out the right toc

        $tocs = $this->toc->getMainToc();
        $base = $this->toc->getTerm($tocs, $term_id);

        if (!$base) {

            $tocs = $this->toc->getMetaToc();
            $base = $this->toc->getTerm($tocs, $term_id);
        }

        if (!$base) {

            \Drupal::logger('bfd')->notice(

                'No trail found for toc term @term on node @nid.', 
                ['@nid' => $node->id(), '@term' => $term_id]
            );

            return [];
        }

        // Get trail        
        
        $trail = [

            $base
        ];

        for ($i = 0; $i < count($trail); $i++) {

            $term = $trail[$i];

            if (is_object($term) && property_exists($term, 'parents')) {

                foreach ($term->parents as $parent_id) {

                    $parent = $this->toc->getTerm($tocs, $parent_id);

                    if ($parent) {

                        $trail[] = $parent;
                    }
                }
            }
        }

        return array_reverse($trail);
    }

    /**
     * Get parent node of a given node based on their term relationships.
     * 
     * @return array
     */
    public function getNodeParent(Node $node) {

        $bundle = $node->bundle();

        // Return early if a node's type isn't supposed to have a parent node:
        // 
        // - main_section_hub nodes are top-level nodes
        // - subsidy_hub nodes (subsidy search & subsidy landing pages) are part 
        //   of the overall hierarchy when it comes to generate their url aliases,
        //   but don't have a physical entry page above them  

        if (in_array($bundle, ['main_section_hub', 'subsidy_hub'])) {

            return;
        }

        // Return early if the node has no term attached
        
        $map      = $this->toc->getNodeTermMap();
        $node_toc = $map[$node->id()];
        
        if (!$node_toc) {

            return;
        }

        // Evaluate only the main menu part of our tocs, since it's the only one
        // with more than one level. 

        $main_toc = $this->toc->getMainToc();

        foreach ($main_toc as $term) {

            if (property_exists($term, 'children')) {

                foreach ($term->children as $child) {

                    if ($child->tid == $node_toc) {

                        return $term->node;
                    }
                }
            }
        }
    }

    /**
     * @param  Utilities
     * @param  Toc
     */
    public function __construct(Utilities $utils, Toc $toc) {

        $this->utils = $utils;
        $this->toc   = $toc;
    }
}