<?php

namespace Drupal\bfd\Content;

use Drupal\node\Entity\Node as DrupalNode;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;

use Drupal\bfd\Utilities;
use Drupal\bfd\Content\Node as NodeService;
use Drupal\bfd\Subsidies\Subsidy;

use Tightenco\Collect\Support\Arr;

/**
 * Service to get what's related
 */
class Related {

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
     * @var DrupalNode
     */
    private $current_node;

    /**
     * @var Subsidy
     */
    protected $subsidy;

    /**
     * @var Checklists
     */
    protected $checklists;

    /**
     * @var  NodeService
     */
    protected $node;

    /**
     * @var  Array - terms associated with the current node
     */
    protected $terms;

    /**
     * @var  Array - how many terms per node
     */
    protected $terms_count;

    /**
     * @var  Array - array of node ids associated with the current node per term set, ordered by bundle
     */
    protected $related;

    /**
     * @return DrupalNode
     */
    protected function getCurrentNode() {

        if (!$this->current_node) {

            $this->current_node = $this->utils->getCurrentNode();
        }

        return $this->current_node;
    }

    /**
     * Get the current node's assorted terms.
     * 
     * @return Array
     */
    protected function getTerms(): array {

        if ($this->terms) {

            return $this->terms;
        }

        $fields = [

            'field_content_categories', 
            'field_subsidy_purpose', 
            'field_subsidy_region'  
        ];

        $this->terms = $this->node->getFieldsValues($this->getCurrentNode(), $fields);
        return $this->terms;
    }

    /**
     * Count terms by node.
     * 
     * @return Array - map of node ids pounting to the num of terms
     */
    protected function getTermsCount(): array {

        $this->terms_count = $this->utils->getFromCache('bfd.related.terms_count');

        if ($this->cache && $this->terms_count) {
        
            return $this->terms_count;
        }

        $this->terms_count = $this->db->query(

            "
             select node.nid, count(*) as count from node_field_data as node
             join taxonomy_index as terms on node.nid = terms.nid
             where node.status = 1 and node.type in(:bundle[])
             group by node.nid
            ",
            [':bundle[]' => ['article', 'guide']]

        )->fetchAllKeyed();

        $this->utils->cache('bfd.related.terms_count', $this->terms_count);

        return $this->terms_count;
    }

    /**
     * Get related subsidies.
     * 
     * @param  Int   - id of the current node
     * @param  Array - terms to filter by (exclusively - match only, if all terms are met)
     * @return Array - array of stdClass
     */
    protected function getRelatedSubsidies(int $current_nid, array $terms): array {

        $subsidies = [];

        // For subsidies, define an extra term set.
        // Keep it short with the categories to get a better chance of a bigger result set.

        $subsidy_terms = array_merge(

            array_slice($terms['field_content_categories'], 0, 1)
            // [Subsidy::SUBSIDY_REGION_NATIONWIDE],  // Fetch only nationwide subsidies.
        );
        
        // When relating subsidies to with a subsidy, their purposes need to match, too.
        
        $is_subsidy = $this->getCurrentNode()->bundle() == 'subsidy';

        if ($is_subsidy) {

            $subsidy_terms = array_merge($subsidy_terms, $terms['field_subsidy_purpose']); 
        }
        
        // Get related, and filter them further 

        $related = $this->subsidy->getSubsidyProfiles($subsidy_terms, true);
        
        if (empty($related)) {

            return $subsidies;
        }

        $map = $this->subsidy->getMap()['nodes_to_terms'];

        $subsidies = array_filter(

            array_map(

                function ($item) {

                    return (object) $item;

                }, 
                $related
            ),

            function ($item) use ($current_nid, $is_subsidy, $terms, $map) {

                // Exclude current node

                if ($item->id == $current_nid || $item->field_subsidy_unavailable) {

                    return false;
                }

                if ($is_subsidy) {

                    $matches_nationwide = in_array(Subsidy::SUBSIDY_REGION_NATIONWIDE, $map[$item->id]);
                    $matches_region     = !array_diff($terms['field_subsidy_region'], $map[$item->id]);

                    return $matches_region || $matches_nationwide;
                }

                return true;
            }
        );

        return $subsidies;
    }

    /**
     * Get related non-subsidy nodes.
     * 
     * @param  Int   - id of the current node
     * @param  Array - terms to filter by (exclusively - match only, if all terms are met)
     * @return Array - array of stdClass
     */
    protected function getRelatedNodes(int $current_nid, array $terms, array $bundles = ['article', 'guide']): array {

        // Get all nodes having either one of our categories set, or are in our set of matching subsidy nodes.
        // We could have done this in separate steps, but opted for performance before readability. 
        
        $nodes = $this->db->query(

            "
             select node.nid, node.title, node.type, terms.tid from node_field_data as node
             join taxonomy_index as terms on node.nid = terms.nid
             where node.status = 1 and node.nid != :we and node.type in(:bundle[]) and terms.tid in(:tids[])
             order by node.title
            ",

            [
                ':bundle[]' => $bundles, 
                ':tids[]'   => $terms['field_content_categories'],
                ':we'       => $current_nid // Don't get us
            ]

        )->fetchAllAssoc('nid');

        return $nodes;
    }

    /**
     * Get content related the the current node.
     *  
     * @return Array
     */
    protected function getRelated(): array {

        if ($this->related) {

            return $this->related;
        }

        $this->related = [];

        // Get node terms from all relevant fields.
        // 
        // Our main measurement to establish relationship between nodes are their category tags. 

        $terms = $this->getTerms();

        // Return early if we don't have a category

        if (empty($terms['field_content_categories'])) {

            return $this->related;
        }

        // Identify the current node

        $current_nid = $this->getCurrentNode()->id();
        
        // Get all subsidy and other nodes considered to match our terms. 
        
        $subsidies = $this->getRelatedSubsidies($current_nid, $terms);
        $other     = $this->getRelatedNodes($current_nid, $terms);

        // Separate nodes by type

        $related = ['subsidy' => $subsidies];

        foreach ($other as $nid => $node) {

            if (!isset($related[$node->type])) {

                $related[$node->type] = [];
            }

            $related[$node->type][] = $node;
        }

        // Amend results
        
        array_walk($related, function ($items, $type) {

            if ($type !== 'subsidy') {

                foreach ($items as $item) {

                    $item->url    = Url::fromRoute('entity.node.canonical', ['node' => $item->nid]);
                    $item->weight = $counter[$item->nid] ?? 0;
                }
            }
        });

        // Get a counter: How many terms are set on non-subsidy nodes? 

        $counter = $this->getTermsCount();

        // Weigh nodes by terms count.
        // 
        // Reasoning:
        // A node having more terms should point to far more further content then a node
        // with fewer terms. Thus, getting the user around is the main goal here.   

        foreach (['article', 'guide'] as $type) {

            if (!isset($related[$type])) {

                continue;
            }

            usort($related[$type], function ($a, $b) {

                return $b->weight <=> $a->weight;
            });
        }

        $this->related = $related;
        return $this->related;
    }

    /**
     * Get related checklists.
     * 
     * @return Array - array of stdClasses
     */
    public function getRelatedChecklists(): array {

        return $this->checklists->getChecklistsByNode($this->getCurrentNode());
    }

    /**
     * Get related articles.
     * 
     * @return Array - array of stdClasses
     */
    public function getRelatedByType(string $type): array {

        $related = $this->getRelated();
        return $related[strtolower($type)] ?? [];
    }

    /**
     * @param RequestStack
     */
    public function __construct(Utilities $utils, Connection $db, Subsidy $subsidy, NodeService $node, Checklists $checklists,bool $cache) {

        $this->utils         = $utils;
        $this->db            = $db;
        $this->cache         = $cache;
        $this->subsidy       = $subsidy;
        $this->checklists    = $checklists;
        $this->node          = $node;
    }
}