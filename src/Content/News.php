<?php

namespace Drupal\bfd\Content;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Field\FieldItemListInterface;

use Drupal\bfd\Utilities;
use Drupal\bfd\Hyphenator\Hyphenator;

/**
 * News
 */
class News {

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
     * @var Hyphenator
     */
    protected $hyphenator;

    /**
     * @var  Field
     */
    protected $field;

    /**
     * @var Array - most recent news ids
     */
    protected $current;

    /**
     * Get most recent news ids sorted by date.
     * 
     * @return Array - array of node ids
     */
    public function getCurrent(): array {

        if (!$this->current) {

            $ids = $this->entityManager
                        ->getStorage('node')
                        ->getQuery()
                        ->condition('type', 'news')
                        ->condition('status', 1)
                        ->sort('field_date' , 'desc')
                        ->range(0,4)
                        ->execute();
            
            $this->current = $ids;
        }

        return $this->current;
    }

    /**
     * Get all news ids sorted by date.
     * 
     * @return Array - array of node ids
     */
    public function getAll(): array {

        $ids = $this->entityManager
                    ->getStorage('node')
                    ->getQuery()
                    ->condition('type', 'news')
                    ->condition('status', 1)
                    ->sort('field_date' , 'desc')
                    ->execute();
        
        return $ids;
    }

    /**
     * @param RequestStack
     */
    public function __construct(Utilities $utils, EntityTypeManagerInterface $entityManager, Connection $db, Hyphenator $hyphenator, bool $cache) {

        $this->utils         = $utils;
        $this->db            = $db;
        $this->cache         = $cache;
        $this->entityManager = $entityManager;
        $this->hyphenator    = $hyphenator;
    }
}