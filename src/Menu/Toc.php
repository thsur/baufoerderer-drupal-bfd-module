<?php

namespace Drupal\bfd\Menu;

use Drupal\Core\Database\Connection;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;

use Drupal\bfd\Utilities;
use Drupal\bfd\Content\Field;

use \stdClass;

/**
 * Service providing table of contents-like lists build upon term-node-relationships.
 */
class Toc {

    const MAIN_MENU_TID      = 1407;
    const META_MENU_TID      = 1409;

    const SUBSIDY_SEARCH_TID = 1387;
    const SUBSIDY_HUBS_TID   = 1373;
    const SUBSIDY_MAIN_TID   = 1416;

    const SUBSIDY_SEARCH_NID = 554;

    /**
     * @var Utilities
     */
    private $utils;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @var Field
     */
    private $field;

    /**
     * @var Boolean
     */
    private $cache;

    /**
     * @var array - an array of node ids pointing to term ids
     */
    private $nodeTermMap;

    /**
     * @var array 
     */
    private $tocs;

    /**
     * @param  array  $terms 
     * @return void
     */
    protected function sortByDepth(array &$terms) {

        usort($terms, function ($a, $b) {

            if ($a->depth == $b->depth) {
            
                return 0;
            }

            return ($a->depth < $b->depth) ? -1 : 1;
        });
    }
    
    /**
     * @param  array  $items 
     * @return void
     */
    protected function sortByWeight(array &$items) {

        usort($items, function ($a, $b) {

            if ($a->weight == $b->weight) {
            
                return 0;
            }

            return ($a->weight < $b->weight) ? -1 : 1;
        });
    }

    /**
     * Sort items alphabetically.
     *
     * Cf. https://blog.liplex.de/order-values-in-alphabetical-order/
     * 
     * @param  array  $items 
     * @param  string $lang 
     * @return void
     */
    protected function sortByAlphabet(array &$items, string $lang) {

        usort($items, function ($a, $b) use ($lang) {

            $collator = new \Collator($lang);
            return $collator->compare($a->name, $b->name);
        });
    }

    /**
     * Build a term tree two levels deep.
     * 
     * @param  array  $terms 
     * @return void
     */
    protected function buildTermTree(array &$terms) {

        for ($i = 0, $count = count($terms); $i < $count; $i++) {

            $term = $terms[$i];

            if ($term->depth == 0) {

                continue;
            }

            foreach ($terms as $parent) {

                if ($parent->depth == 0 && in_array($parent->tid, $term->parents)) {

                    if (!property_exists($parent, 'children')) {

                        $parent->children = [];
                    }

                    $parent->children[] = $term;
                    unset($terms[$i]);
                }
            }
        }
    }

    /**
     * Remove terms from given set that have no nodes attached.
     *
     * @param  array 
     * @param  array 
     */
    protected function injectNodesToTerms(array &$terms, array $nodes) {

        $nodes = array_values($nodes);
        $map   = $this->getNodeTermMap();

        for ($i = 0, $count = count($nodes); $i < $count; $i++) {

            $node    = $nodes[$i];
            $term_id = $map[$node->id()];

            foreach ($terms as $term) {

                if ($term->tid == $term_id) {

                    $term->node = $node;
                    unset($nodes[$i]);
                }
                else if (property_exists($term, 'children') && !empty($term->children)) {

                    $this->injectNodesToTerms($term->children, $nodes);
                }
            }
        }
    }

    /**
     * Load top-level nodes.
     * 
     * @return array
     */
    protected function loadTopLevelNodes() {

        return $this->utils->getPublished(

            $this->utils->loadNodesByType('main_section_hub')
        );
    }
    
    /**
     * Load top-level nodes.
     * 
     * @return array
     */
    protected function loadSubLevelNodes() {

        return $this->utils->getPublished(

            $this->utils->loadNodesByType('sub_section_hub')
        );
    }

    /**
     * Load articles.
     *
     * We do this because some articles are meant to be top-level pages.
     *
     * @todo: Turn them into real top-level pages. 
     * 
     * @return array
     */
    protected function loadArticles() {

        return $this->utils->getPublished(

            [Node::load(658)] // 'Energieberatung'
        );
    }

    /**
     * Load subsidy hub nodes.
     * 
     * @return array
     */
    protected function loadSubsidyHubNodes() {

        return $this->utils->getPublished(

            $this->utils->loadNodesByType('subsidy_hub')
        );
    }

    /**
     * Build subsidy table of contents.
     *
     * Returns an array of arrays keyed by taxonomy vocabularies. The inner arrays are
     * similar to the one returned by {@see buildToc()}, though.
     *
     * @return array
     */
    protected function buildSubsidyToc(): array {

        // Unlike buildToc(), we start by getting the (subsidy hub) nodes first,
        // and derive the terms from them, not the other way around.
        
        $nodes = $this->loadSubsidyHubNodes();

        // Return early if subsidy search node is absent.
        // 
        // @todo: This would be a major incident, so better log it, at least.

        if (!isset($nodes[self::SUBSIDY_SEARCH_NID])) {

            return [];
        }

        // Get all fields pointing to a taxonomy vocabulary

        $fields = $this->utils->bundleGetTaxonomyReferenceFields('subsidy_hub');

        // Get field names only

        foreach ($fields as $key => $field) {

            $fields[$key] = $field->getName();
        }

        // Collect all fields pointing to a subsidy taxonomy vocabulary

        $fields = array_filter($fields, function ($field) {

            foreach (['region', 'categories', 'purpose', 'type'] as $vocab) {

                // Implementation of an endsWith-like check, 
                // cf. https://stackoverflow.com/questions/834303/startswith-and-endswith-functions-in-php
                if (substr($field, -strlen($vocab)) == $vocab) {

                    return true;
                }
            }
        });

        // Build up tocs
        
        $tocs = [];
        
        foreach ($nodes as $node) {

            if ($node->id() == self::SUBSIDY_SEARCH_NID) {

                $tocs['search'] = $nodes[self::SUBSIDY_SEARCH_NID];
                continue;
            }

            foreach ($fields as $field) {

                if (!$node->get($field)->isEmpty()) {

                    $entities = $node->{$field}->referencedEntities();
                    $term     = array_pop($entities);

                    // Build a mock term, so we get a data structure 
                    // similar to term manager service's getTree().

                    $toc_term = new stdClass();

                    // Inject basic infos

                    $toc_term->name    = $term->getName();
                    $toc_term->vid     = $term->getVocabularyId();
                    $toc_term->tid     = $term->id();
                    $toc_term->parents = [self::SUBSIDY_HUBS_TID];
                    $toc_term->node    = $node;

                    // Push term to tocs

                    $vocab = $toc_term->vid;

                    if (!isset($tocs[$vocab])) {

                        $tocs[$vocab] = [];
                    }
                    
                    $tocs[$vocab][] = $toc_term;
                }
            }
        }

        foreach ($tocs as &$toc) {

            if (is_array($toc)) {

                $this->sortByAlphabet($toc, $this->utils->getLanguage());
            }
        }
        
        return $tocs;
    }

    /**
     * Build main table of contents.
     *
     * Returns an array of terms, each term having a node attached  
     * so that we're able to link to it later on.
     *
     * Some terms also have a bunch of child terms attached, so we 
     * basically get a two-dimensional array of top and second level nodes.
     *
     * @return array
     */
    protected function buildMainToc(): array {

        // Get main menu term items two levels deep
        
        $toc  = $this->utils->getTermTree('toc', self::MAIN_MENU_TID, 2);
        $lang = $this->utils->getLanguage();

        // Inject additional fields

        $path_titles = $this->db->query(

            'select entity_id, field_path_title_value from taxonomy_term__field_path_title where bundle = :vocab',
            [':vocab' => 'toc']
        
        )->fetchAllKeyed();

        foreach ($toc as $term) {

            if (array_key_exists($term->tid, $path_titles)) {

                $term->path_title = $path_titles[$term->tid];
            }
        }
        
        // Push top-level items to top of stack

        $this->sortByDepth($toc);

        // Collect second-level items underneath their respective parents
        
        $this->buildTermTree($toc);
        
        // Sort top-level items by user-defined sorting order

        $this->sortByWeight($toc);

        // Sort second-level items by alphabet

        foreach ($toc as $term) {

            if (property_exists($term, 'children') && !empty($term->children)) {

                $this->sortByAlphabet($term->children, $lang);
            }
        }

        // Inject nodes

        $this->injectNodesToTerms($toc, array_merge($this->loadTopLevelNodes(), $this->loadSubLevelNodes(), $this->loadArticles()));

        // Inject subsidy search & subsidy hub pages

        $term_search = $this->getTerm($toc, self::SUBSIDY_SEARCH_TID);
        $term_hubs   = $this->getTerm($toc, self::SUBSIDY_HUBS_TID);

        foreach ($this->buildSubsidyToc() as $index => $item) {

            if ($index == 'search' && $term_search) {

                $term_search->node = $item;
            }
            
            if ($index != 'search' && $term_hubs) {

                if (!property_exists($term_hubs, 'children')) {

                    $term_hubs->children = [];
                }

                $term_hubs->children = array_merge($term_hubs->children, $item);
            }
        }

        return $toc;
    }

    /**
     * Build meta table of contents.
     *
     * Roughly the same as {@see buildMainToc()}, though the 
     * returned term-node-relationship is only one level deep.
     *
     * @param  array - an array of node ids pointing to term ids
     * @return array
     */
    protected function buildMetaToc(): array {

        $toc = $this->utils->getTermTree('toc', self::META_MENU_TID);
        $this->sortByWeight($toc);

        // Get nodes by term ids

        $tids = [];

        foreach ($toc as $term) {

            $tids[] = $term->tid;
        }

        $nodes = array_filter($this->utils->loadNodesByTerms('field_toc', $tids), function ($node) {

            return $node->isPublished() && in_array($node->getType(), ['main_section_hub', 'page']);
        });

        // Inject nodes 

        $this->injectNodesToTerms($toc, $nodes);
        return $toc;
    }

    /**
     * @return array
     */
    public function getToc(): array {

        if (!$this->tocs) {

            $tocs = $this->utils->getFromCache('bfd.toc');

            if (!$this->cache || !$tocs) {
            
                $tocs = [

                    'main'    => $this->buildMainToc(),
                    'meta'    => $this->buildMetaToc(),
                ];

                $this->utils->cache('bfd.toc', $tocs);
            }

            $this->tocs = $tocs;
        }

        return $this->tocs;
    }

    /**
     * @return array
     */
    public function getMainToc(): array {

        $toc = $this->getToc();
        return isset($toc['main']) ? $toc['main'] : [];
    }

    /**
     * @return array
     */
    public function getMetaToc(): array {

        $toc = $this->getToc();
        return isset($toc['meta']) ? $toc['meta'] : [];
    }

    /**
     * Get a map keyed by node ids pointing to term ids.
     *
     * @return array
     */
    public function getNodeTermMap() {

        if (!$this->nodeTermMap) {

            $query             = $this->db->query('select entity_id, field_toc_target_id from node__field_toc');
            $this->nodeTermMap = $query->fetchAllKeyed();
        }

        return $this->nodeTermMap;
    }

    /**
     * Filter a set of (toc) terms by the given term id.
     * 
     * @param  array  
     * @return stdClass - the term to look up
     */
    public function getTerm(array $terms, $term_id) {

        foreach ($terms as $term) {

            if ($term->tid == $term_id) {

                return $term;
            }
            else if (property_exists($term, 'children')) {

                $term = $this->getTerm($term->children, $term_id);

                if ($term) {

                    return $term;
                }
            }
        }
    }

    /**
     * Get the node attached to a toc term.
     * 
     * @param  stdClass  
     * @return Node
     */
    public function getTermNode(stdClass $term): ?Node {
    
        return property_exists($term, 'node') ? $term->node : null;
    }

    /**
     * Get a nodes toc term.
     * 
     * @param  Node 
     * @return stdClass | void
     */
    public function getNodeToc(Node $node): ?stdClass {

        if (!$node->hasField('field_toc')) {

            return [];
        }

        $tid  = $this->field->getValues($node->field_toc)[0] ?? null;
        $map  = $this->getToc();

        foreach ($map as $set) {

            $term = $this->getTerm($set, $tid);

            if ($term) {

                return $term;
            }  
        }
    }

    /**
     * @param Utilities
     */
    public function __construct(Utilities $utils, Connection $db, Field $field, bool $cache) {

        $this->utils = $utils;
        $this->db    = $db;
        $this->cache = $cache;
        $this->field = $field;
    }
}