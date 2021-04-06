<?php

namespace Drupal\bfd\Subsidies;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Field\FieldItemListInterface;

use Drupal\bfd\Utilities;
use Drupal\bfd\Menu\Toc;
use Drupal\bfd\Hyphenator\Hyphenator;

/**
 * Subsidy Service
 */
class Subsidy {

    const SUBSIDY_TYPE              = 'subsidy';
    const SUBSIDY_HUB_TYPE          = 'subsidy_hub';
    const SUBSIDY_REGION_NATIONWIDE = '371';

    const PURPOSE_BUILD = 1279;
    const PURPOSE_BUY   = 1283;

    const TYPE_LOAN     = 367;
    const TYPE_GRANT    = 368;

    /**
     * @var Utilities
     */
    private $utils;

    /**
     * @var Toc
     */
    private $toc;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @var Boolean
     */
    private $cache;

    /**
     * @var Array
     */
    private $map;

    /**
     * @var Array
     */
    private $facets_map;

    /**
     * @var  EntityTypeManagerInterface
     */
    private $entityManager;

    /**
     * @var Node
     */
    private $current_node;

    /**
     * @var Hyphenator
     */
    protected $hyphenator;

    /**
     * Get node ids of all subsidy nodes.
     * 
     * @return Array
     */
    protected function getSubsidyNodeIds(): array {

        $storage = $this->entityManager->getStorage('node');
        $query   = $storage->getQuery();

        $query->condition('type', self::SUBSIDY_TYPE);
        $query->condition('status', 1);
        
        return $query->execute();        
    }

    /**
     * Get subsidy terms in use including corresponding node ids, 
     * sort results by vocab.
     * 
     * @return Array
     */
    protected function getActiveSubsidyTerms(): array {

        $term_fields = [

            'subsidy_type',
            'subsidy_region',
            'subsidy_purpose',
            'content_categories',
            'subsidy_provider'
        ];

        $active_terms = [];

        foreach ($term_fields as $field) {

            $active_terms[$field] = $this->db->query(

                "select 
                    info.nid, 
                    term_info.name,
                    type.field_{$field}_target_id tid
                from 
                    node__field_{$field} as type
                join 
                    node_field_data as info on info.nid = type.entity_id 
                join 
                    taxonomy_term_field_data as term_info on term_info.tid = type.field_{$field}_target_id
                where 
                    info.type = :type and info.status = 1 and term_info.tid not in (:exclude[])",

                [':type' => self::SUBSIDY_TYPE, ':exclude[]' => [448, 627]]
            
            )->fetchAll();
        }

        return $active_terms;
    }

    /**
     * Build a map using the data delivered by {@see $this->getActiveSubsidyTerms()}.
     *
     * Or multiple maps, actually:
     *
     * - a vocab map keyed by term ids pointing to their names
     * - a nodes to terms map keyed by node ids pointing to term ids
     * - a terms to nodes map keyed by term ids pointing to node ids 
     * 
     * @return Array
     */
    protected function buildMap(): array {
        
        $raw = $this->getActiveSubsidyTerms();

        if (empty($raw)) {

            return [];
        }

        // Build maps

        $terms_vocab    = [];

        $nodes_to_terms = [];
        $terms_to_nodes = [];

        foreach ($raw as $vocab => $items) {

            foreach ($items as $item) {

                if (!array_key_exists($vocab, $terms_vocab)) {

                    $terms_vocab[$vocab] = array();
                }

                if (!array_key_exists($item->tid, $terms_vocab[$vocab])) {

                    $terms_vocab[$vocab][$item->tid] = $item->name;
                }
                
                if (!array_key_exists($item->tid, $terms_to_nodes)) {

                    $terms_to_nodes[$item->tid] = [];
                }
                
                if (!array_key_exists($item->nid, $nodes_to_terms)) {

                    $nodes_to_terms[$item->nid] = [];
                }
                
                $nodes_to_terms[$item->nid][] = $item->tid;
                $terms_to_nodes[$item->tid][] = $item->nid;
            }
        }

        // Build map

        return [

            'vocab'          => $terms_vocab,
            'nodes_to_terms' => $nodes_to_terms,
            'terms_to_nodes' => $terms_to_nodes,
        ];
    }

    /**
     * Filters what's build by {@see $this->buildMap()}.
     *
     * Provides data to be used front-end side by a UI wishing to
     * interact with our terms and nodes.
     *
     * Should delivery the actually needed data only, so remove all else.
     * 
     * @return Array
     */
    protected function buildFacetsMap(): array {

        $map = $this->buildMap();

        if (empty($map)) {

            return [];
        }

        // Term ids to exclude.

        $exclude_terms = array_keys($map['vocab']['subsidy_provider']);

        // Exclude them from both maps (terms to nodes, nodes to terms).

        foreach ($exclude_terms as $tid) {

            $affected_nodes = $map['terms_to_nodes'][$tid];

            // Clear terms from affected nodes. If no other terms than the excluded ones
            // are set on the node, remove it.
            // 
            // Be aware that the current approach has a minor performance issue since it's 
            // going to visit affected nodes more than once. It should be a very fast operation,
            // though, but it should also benefit from improving its logic.  

            foreach ($affected_nodes as $nid) {

                $map['nodes_to_terms'][$nid] = array_diff($map['nodes_to_terms'][$nid], $exclude_terms);

                if (empty($map['nodes_to_terms'][$nid])) {

                    unset($map['nodes_to_terms'][$nid]);
                }
            }
        }

        // Clear terms map.

        unset($map['terms_to_nodes']);

        // Clear vocab.

        unset($map['vocab']['subsidy_provider']);

        // Add all regions to nationwide subsidies so they always show up. 

        $region_ids = array_map('strval', array_keys($map['vocab']['subsidy_region']));

        foreach ($map['nodes_to_terms'] as &$terms) {

            if (in_array(self::SUBSIDY_REGION_NATIONWIDE, $terms)) {

                $terms = array_values(

                    array_unique(array_merge($terms, $region_ids))
                );
            }
        }
        
        // Sort vocabs

        $lang = $this->utils->getLanguage();

        foreach ($map['vocab'] as $name => &$vocab) {

            uasort($vocab, function ($a, $b) use ($name, $lang) {

                $collator = new \Collator($lang);
                return $collator->compare($a, $b);
            });

            if ($name == 'subsidy_region') {

                $vocab = [self::SUBSIDY_REGION_NATIONWIDE => $vocab[self::SUBSIDY_REGION_NATIONWIDE]] + $vocab;
            }
        }

        foreach ($map['vocab']['content_categories'] as &$item) {

            $item = $this->hyphenator->hyphenate($item);
        }

        // Maintain vocab sort order when JSON encoding/decoding later on by turning all vocab items into arrays.

        foreach ($map['vocab'] as &$vocab) {

            $vocab = array_map(function ($id, $item) {

                return ['id' => "{$id}", 'value' => $item];

            }, array_keys($vocab), $vocab);
        }

        return $map;
    }

    /**
     * Get main subsidy term id attached to the given subsidy hub node.
     * 
     * @param  Node   
     * @return Array - node field name referencing found term vocab, term id
     */
    protected function getSubsidyHubTermId(Node $node): array {

        $categories = [];

        // Get all fields attached to the given node's type potentially referencing 
        // a subsidy-related term from one of four possible categories.

        $fields = array_filter(

            array_map(

                function ($item) {

                    return $item->getName();
                },

                $this->utils->bundleGetTaxonomyReferenceFields($node->getType())
            ),
            function ($item) {
                
                return preg_match('/_(categories|purpose|region|type)$/', $item);
            }
        );

        if (empty($fields)) {

            return $categories;
        }
        
        // Collect attached term categories

        foreach ($fields as $name) {

            $categories[$name] = $node->get($name)->getValue();
        }

        // Remove empty categories

        $categories = array_filter($categories);

        // Subsidy hub pages should display content from one term category only. Let's make the last one the winner.
        
        $category = array_slice($categories, -1); // But let's keep the field name the category belongs to, so slice() it instead of pop'ing() the last value out.
        
        // Collect actual info, i.e. term ids & reference field name
        
        $reference_field = array_keys($category)[0];
        $terms           = [];

        array_walk_recursive($category, function ($term_id) use (&$terms) {

            $terms[] = $term_id;
        });

        return [

            'reference_field' => $reference_field,
            'terms'           => $terms
        ];
    }

    /**
     * Get a subsidy node's most important info.
     * 
     * @param  Node  
     * @return Array
     */
    protected function getSubsidyProfile(Node $node): array {
        
        $nid  = $node->id();

        // Collect all easy-to-get data first

        $data = [

            'id'    => $nid, 
            'url'   => $node->toUrl()->toString(),
            'title' => $node->label(),
        ];

        // Collect already acquired data next (i.e., what terms are set on the node).

        $map    = $this->getMap();
        $tids   = $map['nodes_to_terms'][$nid];

        $vocabs = [

            'subsidy_type',
            'subsidy_provider',
            'subsidy_region'
        ];

        foreach ($vocabs as $vocab) {

            foreach ($tids as $tid) {

                if (isset($map['vocab'][$vocab][$tid])) {

                    $data[$vocab] = $map['vocab'][$vocab][$tid];
                }
            }
        }

        // Collect fields data last
        
        $fields = [

            'field_date',
            'field_subsidy_name',
            'field_subsidy_amount',
            'field_subsidy_coverage',
            'field_subsidy_scope',
            'field_subsidy_unavailable'
        ];

        foreach ($fields as $field_name) {

            $field = $node->get($field_name);

            if ($field instanceof FieldItemListInterface) {

                foreach ($field as $entry) {

                    $value = $entry->getValue();

                    if (is_array($value)) {

                        if (isset($value['value'])) {

                            $data[$field_name] = $value['value'];
                        }
                    }
                }
            }
        }

        return $data;
    }

    /** 
     * Sort subsidy profiles {@see getSubsidyProfiles()} by:
     *
     * - subsidy amount
     * - region (nationwide vs. other, nationwide coming first)
     * - issuer (KfW vs. other, KfW coming first)
     * 
     * @param  Array
     * @return Array
     */
    protected function sortSubsidyProfiles(array $profiles): array {

        // Sort them by subsidy amount.
        
        usort($profiles, function ($a, $b) {

            if (!isset($a['field_subsidy_amount'])) {

                $a['field_subsidy_amount'] = 0;
            }

            if (!isset($b['field_subsidy_amount'])) {

                $b['field_subsidy_amount'] = 0;
            }

            if ($a['field_subsidy_amount'] == $b['field_subsidy_amount']) {
            
                return 0;
            }

            return -($a['field_subsidy_amount'] <=> $b['field_subsidy_amount']);
        });

        // Split them by region (nationwide vs. other).

        $by_region = ['bundesweit' => [], 'other' => []];

        foreach ($profiles as $profile) {

            $region = strtolower($profile['subsidy_region']);

            if ($region != 'bundesweit') {

                $region = 'other';
            }            
            
            $by_region[$region][] = $profile;
        }

        // Sort nationwide by issuer (KfW vs. other)

        usort($by_region['bundesweit'], function ($a, $b) {

            if ($a['subsidy_provider'] == $b['subsidy_provider']) {
            
                return 0;
            }

            return (strtolower($a['subsidy_provider']) == 'kfw') ? -1 : 1;
        });
        
        return array_merge($by_region['bundesweit'], $by_region['other']);
    }

    /** 
     * Get a bunch of subsidy nodes associated with the given terms, and turn them into
     * a sorted array {@see sortSubsidyProfiles()}.
     * 
     * @param  Array
     * @return Array
     */
    public function getSubsidyProfiles(array $terms, bool $exclusive = false): array {

        $profiles = [];

        // Get node ids of published subsidy nodes having one of the given terms set on their end.
        // It's an inclusive search per default (= get nodes having one vs. nodes having all). 
        
        $map  = $this->getMap();
        $nids = [];

        if (!$exclusive) {

            foreach ($terms as $tid) {
                
                if (isset($map['terms_to_nodes'][$tid])) {

                    $nids = array_merge($nids, $map['terms_to_nodes'][$tid]);
                }
            }
        }
        else {

            $nids = $this->getNodesByTerms($terms);
        }

        // Load them. 
        
        return $this->getSubsidyProfilesByNodeIds($nids);
    }

    /** 
     * Get a bunch of subsidy nodes associated with the given subsidy node ids, and turn them into
     * a sorted array {@see sortSubsidyProfiles()}.
     * 
     * @param  Array
     * @return Array
     */
    public function getSubsidyProfilesByNodeIds(array $nids): array {

        $nodes    = $this->entityManager->getStorage('node')->loadMultiple($nids);
        $profiles = [];

        foreach ($nodes as $node) {

            $profiles[] = $this->getSubsidyProfile($node);
        }

        return $this->sortSubsidyProfiles($profiles);
    }

    /**
     * Get a map on term/vocab and term/node relationships:
     *
     * - a vocab map keyed by term ids pointing to their names
     * - a nodes to terms map keyed by node ids pointing to term ids
     * - a terms to nodes map keyed by term ids pointing to node ids
     * 
     * @return Array
     */
    public function getMap() {

        if ($this->map) {

            return $this->map;
        }

        $this->map = $this->utils->getFromCache('bfd.subsidies.map');

        if (!$this->cache || !$this->map) {
        
            $this->map = $this->buildMap();
            $this->utils->cache('bfd.subsidies.map', $this->map);
        }

        return $this->map;
    }

    /**
     * Get a single vocab from map.
     * 
     * @return Array
     */
    public function getVocab($vocab) {

        $map = $this->getMap();

        if (isset($map['vocab']) && isset($map['vocab'][$vocab])) {

            return $map['vocab'][$vocab];
        }
    }

    /**
     * Get node term ids from map.
     * 
     * @return Array
     */
    public function getNodeTerms(Node $node) {

        $map = $this->getMap();
        $id  = $node->id();

        if (isset($map['nodes_to_terms']) && isset($map['nodes_to_terms'][$id])) {

            return $map['nodes_to_terms'][$id];
        }

        return [];
    }

    /**
     * Get a subsidy's purpose(s).
     * 
     * @param  Node  
     * @return Array
     */
    public function getPurpose(Node $node) {

        $terms    = $this->getNodeTerms($node);
        $purposes = $this->getVocab('subsidy_purpose');

        if ($purposes) {

            return array_intersect(array_keys($purposes), $terms);
        }

        return [];
    }

    /**
     * Get a subsidy's type.
     * 
     * @param  Node  
     * @return Array
     */
    public function getType(Node $node) {

        $terms = $this->getNodeTerms($node);
        $types = $this->getVocab('subsidy_type');

        if ($types) {

            return array_intersect(array_keys($types), $terms);
        }

        return [];
    }

    /**
     * Filter {@see $this->map} by given term ids.
     * 
     * @param  Array
     * @return Array
     */
    public function getNodesByTerms(array $term_ids): array {

        $map = $this->getMap();
        $ids = [];

        foreach ($map['nodes_to_terms'] as $nid => $tids) {

            $collect = !array_diff($term_ids, $tids);

            if ($collect) {

                $ids[] = $nid;
            }
        }

        return $ids;
    }

    /**
     * Shrinked version of {@see $this->getMap()}.
     * 
     * @return Array
     */
    public function getFacetsMap() {

        if ($this->facets_map) {

            return $this->facets_map;
        }

        $this->facets_map = $this->utils->getFromCache('bfd.subsidies.facets_map');

        if (!$this->cache || !$this->facets_map) {
        
            $this->facets_map = $this->buildFacetsMap();
            $this->utils->cache('bfd.subsidies.facets_map', $this->facets_map);
        }

        return $this->facets_map;
    }

    /**
     * @param  Int
     * @return String
     */
    public function getSubsidyTermLabel($term_id) {

        $map = $this->getSubsidyTermsMap();

        foreach ($map['terms_by_vocab'] as $vocab) {

            foreach ($vocab as $tid => $label) {

                if ($term_id == $tid) {

                    return $label;
                }
            }

        }        
    }

    /**
     * Get all subsidy nodes considered to belong to the current
     * hub node by sharing a common  term.
     *  
     * @param  Node
     * @return Array
     */
    public function getSubsidyHubTeaser(Node $node): array {

        $profiles = $this->utils->getFromCache('bfd.subsidies.hub-'.$node->id());

        if ($this->cache && $profiles) {

            return $profiles;
        }

        // Get subsidy term set on the current hub node.

        $terms = [];
        
        if ($node->id() == $this->toc::SUBSIDY_SEARCH_NID) {

            $map = $this->getMap();

            if (isset($map['terms_to_nodes'])) {

                $terms = array_keys($map['terms_to_nodes']);
            }
        }
        else {
            
            $terms = $this->getSubsidyHubTermId($node);
            $terms = end($terms);

            // Have all subsidy hubs show nation-wide subsidies

            array_unshift($terms, self::SUBSIDY_REGION_NATIONWIDE);
        }

        if (empty($terms)) {

            return [];
        }

        // Get profiles
        
        $profiles = $this->getSubsidyProfiles($terms);

        // Cache 'em
        
        $this->utils->cache('bfd.subsidies.hub-'.$node->id(), $profiles);

        return $profiles;
    }

    /**
     * @param  Node    
     * @return boolean 
     */
    public function isSubsidyHubNode(Node $node): bool {

        return $node->getType() == self::SUBSIDY_HUB_TYPE;
    }

    /**
     * @param  Node    
     * @return boolean 
     */
    public function isSubsidyNode(Node $node): bool {

        return $node->getType() == self::SUBSIDY_TYPE;
    }

    /**
     * @param  Node    
     * @return boolean 
     */
    public function isKfW(Node $node): bool {

        $provider = $this->getVocab('subsidy_provider');
        $kfw      = array_filter(array_map(function ($item, $id) {

            if (strtolower($item) == 'kfw' || strpos(strtolower($item), 'kfw')) {

                return $id;
            }

        }, $provider, array_keys($provider)));

        return !empty(array_intersect($kfw, $this->getNodeTerms($node)));
    }

    /**
     * @param RequestStack
     */
    public function __construct(Utilities $utils, Toc $toc, EntityTypeManagerInterface $entityManager, Connection $db, bool $cache, Hyphenator $hyphenator) {

        $this->utils         = $utils;
        $this->toc           = $toc;
        $this->db            = $db;
        $this->cache         = $cache;
        $this->entityManager = $entityManager;
        $this->hyphenator    = $hyphenator;

        $this->current_node  = $this->utils->getCurrentNode();
    }
}