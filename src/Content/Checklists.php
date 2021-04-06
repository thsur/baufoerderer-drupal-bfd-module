<?php

namespace Drupal\bfd\Content;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Field\FieldItemListInterface;

use Drupal\bfd\Utilities;
use Drupal\bfd\Subsidies\Subsidy;

use stdClass;

/**
 * Service to get what's realted
 */
class Checklists {

    /**
     * @var Utilities
     */
    private $utils;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @var Boolean
     */
    private $cache;

    /**
     * @var  EntityTypeManagerInterface
     */
    private $entityManager;

    /**
     * @var Node
     */
    private $current_node;

    /**
     * @var Subsidy
     */
    protected $subsidy;

    protected function getInfo(): array {

        return $this->db->query(

            "select
              media_data.*,
              media_file.*,
              file.* 
            from
              media__field_tags as media_tags
            join
              taxonomy_term_field_data as term
            on
              media_tags.field_tags_target_id = term.tid
            join
              media_field_data as media_data 
            on
              media_tags.entity_id = media_data.mid
            join
              media__field_media_file as media_file
            on
              media_data.mid = media_file.entity_id
            join
              file_managed as file 
            on
              file.fid = media_file.field_media_file_target_id 
            where
              term.tid = :term # Checklisten
            and
              media_data.bundle = :bundle
            and
              file.filemime = :mime",

            [':bundle' => 'file', ':mime' => 'application/pdf', ':term' => 1275]

        )->fetchAll();
    }

    protected function getMap() {

        // Collect files (as stdClass).

        $checklists = $this->getInfo();

        // Tag checklists by a certain vocab, so get it first. 

        $vocab = $this->subsidy->getVocab('subsidy_purpose');

        // Prepare a map
        
        $map = [

            'by_file_id' => [],
            'by_term_id' => array_fill_keys(array_keys($vocab), []) // term ids pointing to empty arrays 
        ];

        $add = function (array $tids, stdClass $file) use (&$map) {

            foreach ($tids as $term_id) {

                $map['by_term_id'][$term_id][] = $file->fid;
            }

            $file->url                     = file_url_transform_relative(file_create_url($file->uri)); 
            $map['by_file_id'][$file->fid] = $file;
        }; 

        foreach ($checklists as $file) {

            switch ($file->fid) {

                case 431: // 'Ermittlung des Finanzierungsbedarfs für Kaufvorhaben'
                    $add([1281, 1283], $file); // Sanierung, Kauf
                    break;
                
                case 479: // 'Checkliste 1_pdf'
                    $add([1279], $file); // Neubau
                    break;
                
                case 435: // 'Ermittlung der monatlichen finanziellen Belastbarkeit'
                    $add([1279, 1281, 1283], $file); // Neubau, Sanierung, Kauf
                    break;
                
                case 437: // 'Käufer und Verkäufer - Themen vor dem Notartermin'
                    $add([1281, 1283], $file); // Sanierung, Kauf
                    break;

                case 433: // 'Ermittlung des Eigenheimtyps'
                    $add([1279, 1281, 1283], $file); // Neubau, Sanierung, Kauf
                    break;
                
                default:
                    break;
            }
        }

        return $map;
    }

    /**
     * Get checklists mapped to terms.
     * 
     * @return Array
     */
    public function getChecklists(): array {

        $checklists = $this->utils->getFromCache('bfd.checklists');

        if ($this->cache && $checklists) {

            return $checklists;
        }

        $checklists = $this->getMap();
        $this->utils->cache('bfd.checklists', $checklists);

        return $checklists;
    }

    /**
     * Get checklists for a given node.
     * 
     * @return Array - array of stdClass representing files
     */
    public function getChecklistsByNode(Node $node): array {

        if (!$node || $node->bundle() == 'subsidies') {

            return [];
        }

        $checklists = $this->getChecklists();
        $node_terms = $this->subsidy->getNodeTerms($node);

        // Collect file ids first

        $related = [];

        foreach ($checklists['by_term_id'] as $term_id => $fids) {

          if (in_array($term_id, $node_terms)) {

            $related = $related + $fids;
          }
        }

        // Then get the files (still as stdClasses)

        $files = array_map(function ($fid) use ($checklists) {

          return $checklists['by_file_id'][$fid] ?? null;

        }, $related);

        return $files;
    }

    /**
     * @param RequestStack
     */
    public function __construct(Utilities $utils, EntityTypeManagerInterface $entityManager, Connection $db, Subsidy $subsidy, bool $cache) {

        $this->utils         = $utils;
        $this->db            = $db;
        $this->cache         = $cache;
        $this->entityManager = $entityManager;
        $this->subsidy       = $subsidy;

        $this->current_node  = $this->utils->getCurrentNode();
    }
}