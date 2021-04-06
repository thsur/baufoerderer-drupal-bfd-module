<?php

namespace Drupal\bfd\Content;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;

use Drupal\bfd\Utilities;

/**
 * Terms
 */
class Terms {

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
     * @var EntityTypeManagerInterface
     */
    private $entityManager;

    /**
     * Get category terms containing any of the given values.
     *
     * @param  Array - array of values to look for
     * @return Array 
     */
    public function getCategoriesMatching(array $match, array $exclude = []): array {

        $terms = $this->db->query(

            "select tid, name from taxonomy_term_field_data where vid = :vid",
            [':vid' => 'categories']

        )->fetchAllKeyed();

        $matches = array_filter($terms, function($v) use ($match, $exclude) {

            $matches = false;

            foreach ($match as $what) {

                if (stripos($v, $what) !== false) {

                    $matches = true;
                }
            }

            foreach ($exclude as $what) {

                if (stripos($v, $what) !== false) {

                    $matches = false;
                }
            }

            return $matches;
        });

        return $matches;
    }

    /**
     * @param RequestStack
     */
    public function __construct(Utilities $utils, EntityTypeManagerInterface $entityManager, Connection $db, bool $cache) {

        $this->utils         = $utils;
        $this->db            = $db;
        $this->cache         = $cache;
        $this->entityManager = $entityManager;
    }
}