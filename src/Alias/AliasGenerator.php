<?php

namespace Drupal\bfd\Alias;

use Drupal\node\Entity\Node;

use Drupal\bfd\Utilities;
use Drupal\bfd\PathHelper;

class AliasGenerator {
    
    /**
     * @var Utilities
     */
    protected $utils;

    /**
     * @var PathHelper
     */
    protected $path_helper;

    /**
     * @param  Node
     * @return string
     */
    public function buildPathAlias(Node $node): ?string {

        $trail         = $this->path_helper->nodeGetTermTrail($node);
        $include_label = !in_array($node->bundle(), ['main_section_hub', 'sub_section_hub']);
        
        if (empty($trail)) {

            \Drupal::logger('bfd')->warning(

                'Unable to generate alias for node @nid.', 
                ['@nid' => $node->id()]
            );
            return false;
        }

        // Build path 
        
        $path  = [];
        
        if ($node->bundle() == 'page') {  // Pages are considered to have no trail at all

            $trail = [];
        }

        foreach ($trail as $term) {

            if (property_exists($term, 'path_title')) {
            
                $path[] = $term->path_title; 
            }
            else {

                $path[] = $term->name;
            }
        }

        if ($include_label) {

            $path[] = $node->label();
        }


        // Sanitize

        return $this->utils->getCleanUrl($path);
    }

    /**
     * @param  Utilities
     * @param  PathHelper
     */
    public function __construct(Utilities $utils, PathHelper $path_helper) {

        $this->utils       = $utils;
        $this->path_helper = $path_helper;
    }
}