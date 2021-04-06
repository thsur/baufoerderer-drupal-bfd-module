<?php

namespace Drupal\bfd\Content;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItemInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

use Drupal\bfd\Utilities;

use Tightenco\Collect\Support\Arr;

/**
 * Field Service
 */
class Field {

    /**
     * @var Utilities
     */
    private $utils;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @var  EntityTypeManagerInterface
     */
    private $entityManager;

    /**
     * Get raw value(s) of a given field.
     * 
     * @return Array
     */
    public function getValues(FieldItemListInterface $field): array {

        $data = [];

        foreach ($field as $entry) {

            $value = $entry->getValue();

            if (is_array($value)) {

                if (isset($value['value'])) {

                    $data[] = $value['value'];
                }
                else if (isset($value['target_id'])) {

                    $data[] = $value['target_id'];
                }
            }
        }

        return $data;
    }

    /**
     * Get values of referenced entites.
     * 
     * @param  EntityReferenceFieldItemListInterface
     * @return Array
     */
    public function getReferencedValues(EntityReferenceFieldItemListInterface $field): array {

        $references = $field->referencedEntities();
        $data       = [];

        foreach ($references as $reference) {

            $values = $reference->toArray();

            // Flatten values as much as possible

            foreach ($values as $key => $item) {

                if (is_array($item)) {

                    if (empty($item)) {

                        $values[$key] = null;
                    }
                    else {

                        // Since Drupal array's are deeply stacked, flatten them first

                        $collection = collect($item)->flatten();

                        if (count($collection) == 1) {

                            $values[$key] = $collection->first();
                        }
                    }
                }
            }

            $data[] = $values;
        }

        return $data;
    }

    /**
     * Process field values.
     * 
     * @param  FieldItemListInterface
     * @return Array
     */
    public function getProcessedValues(FieldItemListInterface $field): array {

        $data = [];

        if ($field instanceof EntityReferenceFieldItemListInterface) {

            return $this->getReferencedValues($field);
        }

        foreach ($field as $entry) {

            if ($entry instanceof DateTimeItemInterface) {

                $data[] = $entry->date->getTimestamp();
            }
            else {

                $value = $entry->getValue();
            
            }
        }

        return $data;
    }

    /**
     * @param RequestStack
     */
    public function __construct(Utilities $utils, EntityTypeManagerInterface $entityManager, Connection $db) {

        $this->utils         = $utils;
        $this->db            = $db;
        $this->entityManager = $entityManager;
    }
}