<?php

namespace Drupal\bfd;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;

use Drupal\Core\Database\Connection;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\LanguageInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\node\TermStorageInterface;

use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Path\AliasManagerInterface;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;

use Drupal\Core\Entity\EntityInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;

use Drupal\Core\Session\AccountProxyInterface;

use Drupal\Core\Transliteration\PhpTransliteration;
use URLify;

/**
 * Utilities
 *
 * https://webdev.iac.gatech.edu/blog/drupal8_render_twig
 * https://www.drupal.org/docs/8/api/entity-api/working-with-the-entity-api
 * https://www.metaltoad.com/blog/drupal-8-entity-api-cheat-sheet
 */
class Utilities {

    /**
     * @var RequestStack
     */
    private $request;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @var EntityTypeManagerInterface
     */
    private $entity_manager;

    /**
     * @var NodeStorageInterface
     */
    private $node_manager;

    /**
     * @var TermStorageInterface
     */
    private $term_manager;

    /**
     * @var LanguageManagerInterface
     */
    private $language_manager;

    /**
     * @var EntityFieldManagerInterface
     */
    private $field_manager;

    /**
     * @var CacheBackendInterface
     */
    private $cache;

    /**
     * @var PhpTransliteration
     */
    private $transliteration;

    /**
     * @var AccountProxyInterface
     */
    private $current_user;

    /**
     * @var PathMatcherInterface
     */
    private $path_matcher;

    /**
     * @var AliasManagerInterface
     */
    private $alias_manager;

    /**
     * @return Node
     */
    public function getCurrentNode() {

        return $this->request->getCurrentRequest()->attributes->get('node'); 
    }

    /**
     * @return Request
     */
    public function getCurrentRequest() {

        return $this->request->getCurrentRequest(); 
    }

    /**
     * @return int
     */
    public function getCurrentNodeId(): ?int {

        $node = $this->getCurrentNode();
        return $node ? $node->id() : null; 
    }

    /**
     * @return Node
     */
    public function loadNode(int $id): Node {

        return $this->node_manager->load($id); 
    }

    /**
     * @return array
     */
    public function loadNodes(array $ids): array {

        return $this->node_manager->loadMultiple($ids); 
    }

    /**
     * @param  string
     * @param  array - an array of term ids
     * @return array - an array of nodes keyed by node ids
     */
    public function loadNodesByTerms(string $reference_field_name, array $terms): array {

        return $this->node_manager->loadByProperties([$reference_field_name => $terms]); 
    }

    /**
     * @param  string
     * @param  array  - an array of term ids
     * @param  string - node type
     * @return array  - an array of nodes keyed by node ids
     */
    public function loadBundledNodesByTerms(string $reference_field_name, array $terms, string $type): array {

        return $this->node_manager->loadByProperties([$reference_field_name => $terms, 'type' => $type]); 
    }

    /**
     * @return array
     */
    public function loadNodesByType(string $type): array {

        return $this->node_manager->loadByProperties(['type' => $type]); 
    }

    /**
     * @return array
     */
    public function getPublished(array $entities): array {

        return array_filter($entities, function ($entity) {

            return $entity->isPublished();
        });
    }

    /**
     * @return bool|Term
     */
    public function nodeGetTocTerm(Node $node) {

        if (!$node->hasField('field_toc')) {

            return false;
        }

        $toc = $node->get('field_toc');

        if ($toc->isEmpty()) {

            return false;
        }

        foreach ($toc as $term) {

            return $term->entity;
        }

        return false;
    }

    /**
     * Get all non-base fields of a given content type.
     * 
     * @param  string 
     * @return array
     */
    public function bundleGetFields(string $content_type): array {

        $fields = array_filter($this->field_manager->getFieldDefinitions('node', $content_type), function ($field) {

            return !$field->getFieldStorageDefinition()->isBaseField();
        });

        return $fields;
    }

    /**
     * Get all taxonomy reference fields of a given content type.
     * 
     * @param  string 
     * @return array
     */
    public function bundleGetTaxonomyReferenceFields(string $content_type): array {

        $fields = array_filter($this->bundleGetFields($content_type), function ($field) {

            $dependencies = $field->getDependencies();
            
            $is_reference = ($field->getType() == 'entity_reference');
            $is_taxonomy  = false;

            array_walk_recursive($dependencies, function ($dependency) use (&$is_taxonomy) {

                if (stripos($dependency, 'taxonomy.vocabulary') === 0) {
                    
                    $is_taxonomy = true;
                }

            });

            return $is_reference && $is_taxonomy;
        });

        return $fields;
    }

    /**
     * @return array - an array of terms keyed by term ids
     */
    public function termGetParents(Term $term): array {

        return $this->term_manager->loadParents($term->id());
    }

    /**
     * @return array - an array of terms keyed by term ids
     */
    public function termGetAncestors(Term $term): array {

        return $this->term_manager->loadAllParents($term->id());
    }

    /**
     * @return array - array of stdClasses
     */
    public function getTermTree(string $vid, int $parent = 0, int $depth = null): array {

        return $this->term_manager->loadTree($vid, $parent, $depth, false);
    }
    
    /**
     * @return array - array of Terms
     */
    public function getTerms(array $term_ids): array {

        return $this->term_manager->loadMultiple($term_ids);
    }

    /**
     * @return Term
     */
    public function getTerm($term_id) {

        return $this->term_manager->load($term_id);
    }

    /**
     * @return string
     */
    public function getLanguage(): string {

        return $this->language_manager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    }

    /**
     * See:
     * - https://github.com/jbroadway/urlify/blob/master/URLify.php
     * - https://stackoverflow.com/questions/2668854/sanitizing-strings-to-make-them-url-and-filename-safe
     * 
     * @param  mixed
     * @return string
     */
    public function getCleanUrl($path): string {

        if (is_array($path)) {

            foreach ($path as $key => $part) {

                $path[$key] = URLify::filter($part, 255, $this->getLanguage());
            }

            $path = implode('/', $path);
        }
        else if (is_string($path)) {

            $path = URLify::filter($path, 255, $this->getLanguage());
        }

        return '/'.trim($path, '/');
    }

    /**
     * Get current request path.
     * 
     * @return string
     */
    public function getCurrentPath(): string {

        return $this->request->getCurrentRequest()->getPathInfo();
    }

    /**
     * Whether or not the front page is requested.
     * 
     * @return Boolean
     */
    public function isFront(): bool {

        return $this->path_matcher->isFrontPage();
    }

    /**
     * Get a node's url.
     * 
     * @param  int    - node id
     * @return string
     */
    public function getUrlFromNodeId (int $nid) {

        return $this->alias_manager->getAliasByPath('/node/'.$nid);
    }

    /**
     * @return  mixed
     */
    public function getFromCache(string $cache_id) {

        $cache = $this->cache->get($cache_id);
        return $cache ? $cache->data : false;
    }

    /**
     * @return  void
     */
    public function cache(string $cache_id, $data) {

        $this->cache->set($cache_id, $data);
    }

    /**
     * Get current user account.
     *
     * For loading a user (entity/profile), do:
     * Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
     * 
     * 
     * @return  AccountProxyInterface
     */
    public function getCurrentUser() : AccountProxyInterface {

        return $this->current_user;
    }

    /**
     * Get current user account.
     *
     * For getting at a user entity/profile, cf.:
     * https://api.drupal.org/comment/63486#comment-63486
     *
     * @return  AccountProxyInterface
     */
    public function isAdminUser(AccountProxyInterface $account) : bool {

        return $account->isAuthenticated() && in_array('administrator', $account->getRoles());
    }

    /**
     * @param RequestStack
     */
    public function __construct(ContainerInterface $container) {

        $this->request           = $container->get('request_stack'); 
        $this->language_manager  = $container->get('language_manager'); 
        $this->db                = $container->get('database'); 
        $this->cache             = $container->get('cache.default'); 
        $this->current_user      = $container->get('current_user'); 
        $this->path_matcher      = $container->get('path.matcher'); 

        $this->alias_manager     = $container->get('path.alias_manager'); 
        $this->entity_manager    = $container->get('entity_type.manager'); 
        $this->field_manager     = $container->get('entity_field.manager'); 
        $this->node_manager      = $this->entity_manager->getStorage('node');
        $this->term_manager      = $this->entity_manager->getStorage('taxonomy_term');

        $this->transliteration   = $container->get('transliteration');
    }
}