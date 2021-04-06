<?php

namespace Drupal\bfd\Menu;

use Symfony\Component\HttpFoundation\RequestStack;

use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;

use Drupal\bfd\Menu\Toc;
use Drupal\bfd\Subsidies\Subsidy;
use Drupal\bfd\Utilities;
use Drupal\bfd\PathHelper;

use \stdClass;
use \ArrayObject;

/**
 * Menu Service
 */
class Menu {

    /**
     * @var Utilities
     */
    private $utils;

    /**
     * @var Toc
     */
    private $toc;

    /**
     * @var Subsidy
     */
    private $subsidy;

    /**
     * @var PathHelper
     */
    private $path_helper;

    /**
     * @var Boolean
     */
    private $cache;

    /**
     * @var Node
     */
    private $current_node;

    /**
     * @var String
     */
    private $current_path;

    /**
     * @var Array
     */
    private $current_trail;

    /**
     * @param  string  - nav item name
     * @param  integer - nav item term id
     * @param  string  - nav item url
     * @param  string  - nav item anchor
     * @param  string  - taxonomy vocabulary
     * @return array
     */
    protected function getNavItem(string $name, int $tid, string $url = null, string $fragment = null, string $vocab = null, string $class = null): array {

        return [

            'name'     => $name,
            'tid'      => $tid,
            'url'      => $url,
            'fragment' => $fragment,
            'vocab'    => $vocab,
            'type'     => 'nav-item',
            'class'    =>  $class
        ];
    }

    /**
     * Denotes a nav group separator.
     * 
     * @return array
     */
    protected function getNavSeparator(): array {

        return ['type' => 'separator'];
    }

    /**
     * @param  Node
     * @return string
     */
    protected function getUrl(Node $node): string {

        return $node->toUrl()->toString();
    }

    /**
     * @param  stdClass
     * @return string
     */
    protected function getTermAsUrlFragment(stdClass $term): string {

        return trim($this->utils->getCleanUrl($term->name), '/');
    }

    /**
     * @param  stdClass
     * @return boolean
     */
    protected function termHasNode(stdClass $term): bool {

        return property_exists($term, 'node') && $term->node;
    }

    /**
     * @param  stdClass
     * @return boolean
     */
    protected function termHasChildren(stdClass $term): bool {

        return property_exists($term, 'children') && !empty($term->children);
    }

    /**
     * @param  stdClass
     * @return boolean
     */
    protected function isTopLevelTerm(stdClass $term): bool {

        return property_exists($term, 'depth') && ($term->depth == 0);
    }

    /**
     * Whether or not a given menu item is part of the current node's term trail.
     *  
     * @param  array   - term trail
     * @param  array   - menu item
     * @return boolean       
     */
    protected function isItemInTrail(array $trail, array $item): bool {

        $term_id = $item['tid'] ?? null;

        foreach ($trail as $term) {

            if ($term && $term->tid == $term_id) {

                return true;
            }
        }

        return false;
    }

    /**
     * @param  array    
     * @param  callable - callback
     * @return void
     */
    protected function walkTerms(array $terms, callable $callback) {

        foreach ($terms as $term) {

            if ($term->tid == $this->toc::SUBSIDY_MAIN_TID) {

                $term   = clone $term;

                $search = $this->toc->getTerm($terms, $this->toc::SUBSIDY_SEARCH_TID);
                $hubs   = $this->toc->getTerm($terms, $this->toc::SUBSIDY_HUBS_TID);

                if ($this->termHasNode($search)) {

                    $term->node = $search->node; 
                }

                if ($this->termHasChildren($hubs)) {
                    
                    $term->children = $hubs->children; 
                } 
            }

            $node = $this->termHasNode($term) ? $term->node : null;

            if (!$node) {

                continue;
            } 
            
            $callback($term, $node);

            if ($this->termHasChildren($term)) {

                foreach ($term->children as $child) {

                    $node = $this->termHasNode($child) ? $child->node : null;

                    if ($node) {

                        $callback($child, $node, $term);
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    protected function buildMainMenu(): array {

        $toc  = $this->toc->getMainToc();
        $menu = [];

        $this->walkTerms($toc, function (stdClass $term, Node $node, stdClass $parent = null) use (&$menu) {

            // Top level menu items
            
            if (!$parent) {

                $name  = strtolower($term->name);
                $url   = $this->getUrl($node);
                $class = trim($this->utils->getCleanUrl($name), '/');

                if ($name == 'startseite') {

                    $menu[] = $this->getNavItem($term->name, $term->tid, '/', null, null, 'frontpage');
                    return;
                }

                if ($name == 'service') {

                    $menu[] = $this->getNavItem($term->name, $term->tid, $url, null, null, $class);
                    return;
                }

                $item         = $this->getNavItem($term->name, $term->tid, $url, null, null, $class);
                $item['main'] = $this->getNavItem(

                    'Übersicht '.$term->name, 
                    $term->tid, 
                    $url
                );

                if ($term->tid == $this->toc::SUBSIDY_MAIN_TID) {

                    $item['main']['name'] = $node->label();
                }
                
                $menu[] = $item;
            }

            // Children

            if ($parent) {

                $menu_last_key = count($menu) -1;

                if (!isset($menu[$menu_last_key]['children'])) {

                    $menu[$menu_last_key]['children'] = [];
                }

                if (strtolower($parent->name) == 'service' ) {

                    $menu[$menu_last_key]['children'][] = $this->getNavItem(

                        $term->name, 
                        $term->tid, 
                        $this->getUrl($term->node)
                    );
                    return;
                }

                // Non-subsidies
                
                if ($parent->tid != $this->toc::SUBSIDY_MAIN_TID) {

                    $anchor                             = $this->getTermAsUrlFragment($term);
                    $menu[$menu_last_key]['children'][] = $this->getNavItem(

                        $term->name, 
                        $term->tid, 
                        $menu[$menu_last_key]['main']['url'].'#'.$anchor,
                        $anchor
                    );
                }

                // Subsidies

                if ($parent->tid == $this->toc::SUBSIDY_MAIN_TID) {

                    // Exclude some vocabularies and/or terms

                    if (!in_array($term->vid, ['region', 'categories', 'subsidy_purpose'])) {

                        return;
                    }

                    if ($term->vid == 'categories' && !preg_match('/^(alters|barriere|einbruch|erneuerbare)/i', $term->name)) {

                        return;
                    }

                    // Inject a separator 

                    if (!empty($menu[$menu_last_key]['children'])) {

                        $last_child = end($menu[$menu_last_key]['children']);

                        if ($last_child['vocab'] != $term->vid) {

                            $menu[$menu_last_key]['children'][] = $this->getNavSeparator();
                        }
                    }    

                    // Build nav entry                

                    $menu[$menu_last_key]['children'][] = $this->getNavItem(

                        'Fördermittel '.$term->name, 
                        $term->tid, 
                        $this->getUrl($term->node),
                        null,
                        $term->vid
                    );
                }
            }
        });
        
        // Sort subsidy sub menu, or rather push our nationwide subsidy entry to top.     

        foreach ($menu as &$entry) {

            if ($entry['tid'] == $this->toc::SUBSIDY_MAIN_TID && isset($entry['children'])) {

                for ($i = 0, $k = count($entry['children']); $i < $k; $i++) {

                    $item = $entry['children'][$i];

                    if ($item['tid'] == $this->subsidy::SUBSIDY_REGION_NATIONWIDE) {

                        unset($entry['children'][$i]);
                        array_unshift($entry['children'], $item);  
                        break;      
                    }
                }
            }
        }

        return $menu;
    }
    
    /**
     * @return array
     */
    protected function buildSubsidyMenu(): array {

        $toc  = $this->toc->getMainToc();
        $menu = [];

        $this->walkTerms($toc, function (stdClass $term, Node $node, stdClass $parent = null) use (&$menu) {

            if (!$parent || $parent->tid != $this->toc::SUBSIDY_MAIN_TID) {

                return;                
            }


            if ($term->vid == 'region') {

                return;
            }

            if (!isset($menu[$term->vid])) {

                $menu[$term->vid] = [];
            }

            $menu[$term->vid][] = $this->getNavItem(

                $term->name, 
                $term->tid, 
                $this->getUrl($term->node),
                null,
                $term->vid
            );
        });

        return array_merge($menu['categories'], $menu['subsidy_types']);
    }
    
    /**
     * @return array
     */
    protected function buildMetaMenu(): array {

        $toc  = $this->toc->getMetaToc();
        $menu = [];

        $this->walkTerms($toc, function (stdClass $term, Node $node, stdClass $parent = null) use (&$menu) {

            $menu[] = $this->getNavItem(

                $term->name, 
                $term->tid, 
                $this->getUrl($term->node)
            );
        });

        return $menu;
    }
    
    /**
     * Whether or not a given menu item is active can depend on its
     * attached term OR its path. Check both.
     * 
     * @param  array   
     * @return boolean 
     */
    protected function isMenuItemActive(array $item): bool {

        if (!isset($item['url'])) {

            return false;
        }

        return $item['url'] == $this->current_path || $this->isItemInTrail($this->current_trail, $item); 
    }

    /**
     * Set active menu item(s).
     * 
     * @param  array  
     * @return void
     */
    protected function menuSetActive(array &$menu) {

        $node = $this->utils->getCurrentNode();

        if (!$node) {

            return;
        }

        foreach ($menu as &$item) {

            $item['is_active'] = $this->isMenuItemActive($item);

            if ($item['is_active']) {
                
                if (isset($item['children'])) {

                    $has_active_child = false;

                    foreach ($item['children'] as &$child) {

                        $child['is_active'] = $this->isMenuItemActive($child);        

                        if ($child['is_active']) {

                            $has_active_child = true;
                        }            
                    }

                    if (!$has_active_child && isset($item['main'])) {

                        $item['main']['is_active'] = $this->isMenuItemActive($item['main']); 
                    }                
                }
            }
        }
    }

    /**
     * Get main menu.
     * 
     * @return array
     */
    public function getMainMenu(): array {
        
        $menu = $this->utils->getFromCache('bfd.main_menu');

        if (!$this->cache || !$menu) {

            $menu = $this->buildMainMenu();
            $this->utils->cache('bfd.main_menu', $menu);
        }

        $this->menuSetActive($menu);

        return $menu;
    }

    /**
     * Get subsidy footer menu.
     * 
     * @return array
     */
    public function getSubsidyMenu(): array {

        $menu = $this->utils->getFromCache('bfd.subsidy_menu');

        if (!$this->cache || !$menu) {
       
            $menu = $this->buildSubsidyMenu();
            $this->utils->cache('bfd.subsidy_menu', $menu);
        }

        $this->menuSetActive($menu);

        return $menu;
    }

    /**
     * Get meta menu.
     * 
     * @return array
     */
    public function getMetaMenu(): array {

        $menu = $this->utils->getFromCache('bfd.meta_menu');

        if (!$this->cache || !$menu) {
        
            $menu = $this->buildMetaMenu();
            $this->utils->cache('bfd.meta_menu', $menu);
        }

        $this->menuSetActive($menu);

        return $menu;
    }

    /**
     * Get toc menu.
     *
     * @param  array
     * @return array
     */
    public function getTocMenu(array $terms): array {

        $menu = [];

        foreach ($terms as $term) {

            $menu[] = $this->getNavItem(

                $term->name, 
                $term->tid, 
                null,
                $this->getTermAsUrlFragment($term)
            );
        } 
        
        return $menu;
    }

    /**
     * Get breadcrumbs.
     *
     * @param  array
     * @return array
     */
    public function getBreadcrumbs(): array {

        $navs = [

            $this->getMainMenu(),
            $this->getMetaMenu(),
            $this->getSubsidyMenu()
        ];

        $trail = [

            $navs[0][0] // Home
        ];

        // Build trail
        // 
        // For some insights in what's possible with closures in PHP (like
        // adding new behaviour to a class without modifying it), see:
        // 
        // https://codeinphp.github.io/post/exploring-lambda-functions-and-closures-in-php/
        
        $build_trail = function($nav, &$trail) use (&$build_trail) {

            foreach ($nav as $item) {

                if (isset($item['is_active']) && $item['is_active']) {

                    $trail[] = [

                        'name' => isset($item['main']) ? $item['main']['name'] : $item['name'], 
                        'url'  => isset($item['main']) ? $item['main']['url']  : $item['url']

                    ];

                    if (isset($item['children'])) {

                        $build_trail($item['children'], $trail);
                    }

                    return;
                }
            }
        };

        foreach ($navs as $nav) {

            $build_trail($nav, $trail);

            if (count($trail) > 1) {

                break;
            }
        }

        // Remove duplicates
        
        for ($i = 1; $i < count($trail); $i++) {

            $item        = $trail[$i];
            $predecessor = $trail[$i - 1];

            if ($item['url'] == $predecessor['url']) {

                unset($trail[$i]);
            }
        }

        // Build current active entry

        $trail_count = count($trail);

        if ($trail_count > 1) {

            $last = $trail[$trail_count - 1];

            if ($this->current_path == $last['url']) {
                
                unset($trail[$trail_count - 1]['url']);
            }
            else {

                $trail[] = [

                    'name' => $this->current_node->getTitle()
                ];
            }
        }
        
        return $trail;
    }

    /**
     * @param RequestStack
     */
    public function __construct(Utilities $utils, Toc $toc, PathHelper $path_helper, Subsidy $subsidy, bool $cache) {

        $this->utils         = $utils;
        $this->toc           = $toc;
        $this->subsidy       = $subsidy;
        $this->path_helper   = $path_helper;
        $this->cache         = $cache;

        $this->current_node  = $this->utils->getCurrentNode();
        $this->current_path  = $this->utils->getCurrentPath();
        $this->current_trail = [];

        if ($this->current_node) {

            $this->current_trail = $path_helper->nodeGetTermTrail($this->current_node);
        }
    }
}