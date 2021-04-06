<?php

namespace Drupal\bfd\Content;

use Drupal\node\Entity\Node as DrupalNode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Field\FieldItemListInterface;

use Drupal\bfd\Utilities;
use Drupal\metatag\MetatagManagerInterface;

/**
 * Node Service
 */
class Node {

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
     * @var  Field
     */
    protected $field;

    /**
     * @var  MetatagManagerInterface 
     */
    protected $metatagManager;

    /**
     * Get current node
     *
     * @return DrupalNode
     */
    public function getCurrentNode()     {

        if (!$this->current_node) {

            $this->current_node = $this->utils->getCurrentNode();
        }

        return $this->current_node;
    }

    /**
     * Get value(s) of a given field.
     * 
     * @return Array
     */
    public function getFieldValues(DrupalNode $node, string $field_name): array {

        if (!$node->hasField($field_name)) {

            return [];
        }

        $field = $node->get($field_name);

        if (!$field instanceof FieldItemListInterface) { 

            return [];
        }

        return $this->field->getValues($field);
    }

    /**
     * Get value(s) of multiple fields.
     * 
     * @return Array
     */
    public function getFieldsValues(DrupalNode $node, array $fields): array {

        $data = [];

        foreach ($fields as $field) {
        
            $data[$field] = $this->getFieldValues($node, $field);
        }

        return $data;
    }

    /**
     * Return meta tags from given node *without* defaults.
     * 
     * @param  DrupalNode 
     * @return Array
     */
    public function getMeta(DrupalNode $node): array {

        return $this->metatagManager->tagsFromEntity($node);
    }

    /**
     * @param RequestStack
     */
    public function __construct(Utilities $utils, EntityTypeManagerInterface $entityManager, Connection $db, Field $field, MetatagManagerInterface $metatagManager, bool $cache) {

        $this->utils          = $utils;
        $this->db             = $db;
        $this->cache          = $cache;
        $this->entityManager  = $entityManager;
        $this->field          = $field;
        $this->metatagManager = $metatagManager;
    }
}