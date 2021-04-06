<?php

namespace Drupal\bfd\Twig;

use Drupal\bfd\Hyphenator\Hyphenator;
use Drupal\bfd\Utilities;
use Drupal\bfd\Content\Node;
use Drupal\bfd\Content\Field;
use Drupal\bfd\Content\Related;
use Drupal\bfd\Content\Media as MediaService;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\paragraphs\Entity\Paragraph;

use Drupal\media\MediaInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node as DrupalNode;

use Tightenco\Collect\Support\Arr;
use Twig_Environment;

/**
 * Decorate alias storage service.
 *
 * See:
 * - https://www.axelerant.com/resources/team-blog/drupal-8-service-decorators
 * - https://www.previousnext.com.au/blog/decorated-services-drupal-8
 * - https://symfony.com/doc/current/service_container/service_decoration.html
 * - https://www.drupal.org/docs/8/api/services-and-dependency-injection/altering-existing-services-providing-dynamic-services
 */
class TwigExtension extends \Twig_Extension {
    
    /**
     * @var Hyphenator
     */
    protected $hyphenator;

    /**
     * @var Utilities
     */
    protected $utils;

    /**
     * @var Node
     */
    protected $node;

    /**
     * @var Field
     */
    protected $field;

    /**
     * @var MediaService
     */
    protected $media;

    /**
     * @var Related
     */
    protected $related;

    /**
     * {@inheritdoc}
     */
    public function getName() {
    
        return 'bfd_twig';
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions() {
    
        return [

            new \Twig_SimpleFunction('bfd_chunk',                 [$this, 'chunk']),
            new \Twig_SimpleFunction('bfd_is_front',              [$this, 'is_front']),
            new \Twig_SimpleFunction('bfd_enable_tracking',       [$this, 'enable_tracking']),
            new \Twig_SimpleFunction('bfd_is_authenticated_user', [$this, 'is_authenticated_user']),
            new \Twig_SimpleFunction('bfd_node_get_meta_desc',    [$this, 'node_get_meta_desc']),
            new \Twig_SimpleFunction('bfd_is_url_external',       [$this, 'is_url_external'])
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters() {
    
        return [
          
            new \Twig_SimpleFilter('bfd_hyphenate',              [$this, 'hyphenate']),
            new \Twig_SimpleFilter('bfd_truncate',               [$this, 'truncate'], array('needs_environment' => true)),
            new \Twig_SimpleFilter('bfd_truncate_words',         [$this, 'truncate_words']),
            new \Twig_SimpleFilter('bfd_entity_decode',          [$this, 'entity_decode']),
            new \Twig_SimpleFilter('bfd_strip_nbsp',             [$this, 'strip_nbsp']),
            new \Twig_SimpleFilter('bfd_strip_tags',             [$this, 'strip_tags']),
            new \Twig_SimpleFilter('bfd_date',                   [$this, 'date']),
            new \Twig_SimpleFilter('bfd_number',                 [$this, 'number']),
            new \Twig_SimpleFilter('bfd_theme',                  [$this, 'suggest_theme']),
            new \Twig_SimpleFilter('bfd_column',                 [$this, 'column']),
            new \Twig_SimpleFilter('bfd_field_label',            [$this, 'field_label']),
            new \Twig_SimpleFilter('bfd_field_values',           [$this, 'field_values']),
            new \Twig_SimpleFilter('bfd_field_references',       [$this, 'field_references']),
            new \Twig_SimpleFilter('bfd_field_paragraphs',       [$this, 'field_paragraphs']),
            new \Twig_SimpleFilter('bfd_without_paragraph_type', [$this, 'without_paragraph_type']),
            new \Twig_SimpleFilter('bfd_only_paragraph_type',    [$this, 'only_paragraph_type']),
            new \Twig_SimpleFilter('bfd_apply_html',             [$this, 'apply_html']),
            new \Twig_SimpleFilter('bfd_media_info',             [$this, 'media_info']),
            new \Twig_SimpleFilter('bfd_media_url',              [$this, 'media_url']),
            new \Twig_SimpleFilter('bfd_filter_url',             [$this, 'filter_url']),
            new \Twig_SimpleFilter('bfd_you_tube',               [$this, 'you_tube']),
            new \Twig_SimpleFilter('bfd_to_hyphens',             [$this, 'to_hyphens']),
            new \Twig_SimpleFilter('bfd_dump',                   [$this, 'dump']),
        ];
    }

    /**
     * Quick & dirty dumper.
     */
    function dump($what, $settings = []) {

        $exit = $settings['exit'] ?? false;

        dump($what);

        if ($exit) {

            exit;
        }
    }

    /**
     * Code adapted from:
     * https://gist.github.com/mcrumley/10396818
     * 
     * @param  Array
     * @param  Int
     * @return Array
     */
    public function chunk($list, $num_of_chunks) {

        // $bites = ceil(count($list) / $num_of_chunks);
        // return array_chunk($list, $bites);
        
        $total = count($list);
        
        if ($total < 1 || $num_of_chunks < 1) {
        
            return array();
        }

        $bites     = floor($total / $num_of_chunks);
        $remainder = $total % $num_of_chunks;
        
        if (!$remainder || $num_of_chunks >= $total) {
            
            return array_chunk($list, $bites);
        }

        if ($remainder == $num_of_chunks - 1) {
            
            return array_chunk($list, $bites + 1);
        }

        $split  = $remainder * ($bites + 1);
        $result = array_merge(

            array_chunk(array_slice($list, 0, $split), $bites + 1), // Make first bite the largest
            array_chunk(array_slice($list, $split), $bites)
        );

        return $result;

    }

    /**
     * Wrapper for our hyphenation service.
     * 
     * @param  String
     * @return String
     */
    public function hyphenate($content) {

        return $this->hyphenator->hyphenate($content);
    }

    /**
     * Implement a column filter until Twig's v2.8 own column filter becomes availabe for Drupal 8.
     * 
     * @param  String
     * @return String
     */
    public function column(array $data, string $column) {

        $filtered = [];

        array_walk_recursive($data, function ($item, $key) use ($column, &$filtered) {

            if ($key == $column) {

                $filtered[] = $item;
            }
        });

        return $filtered;
    }

    /**
     * Wrapper for Twig's text extension, see:
     * https://twig-extensions.readthedocs.io/en/latest/text.html
     *
     * For an alternative preserving HTML tags, see:
     * https://github.com/bluetel/twig-truncate-extension/blob/master/src/TruncateExtension.php
     *
     * See also:
     * - https://www.the-art-of-web.com/php/truncate/
     * - https://stackoverflow.com/questions/1193500/truncate-text-containing-html-ignoring-tags
     * - https://github.com/cakephp/cakephp/blob/master/src/Utility/Text.php
     * - https://gist.github.com/leon/2857883
     * 
     * @param  Twig_Environment 
     * @param  String           
     * @param  Integer          
     * @param  Boolean          
     * @param  String           
     * @return String
     */
    public function truncate(Twig_Environment $env, $value, $length = 30, $preserve = false, $separator = '...') {

        // The function we need is defined as a global function inside a class definition. Make sure the class
        // file itself is loaded, otherwise we would not be able to call or function.
        class_exists('Twig_Extensions_Extension_Text');

        return twig_truncate_filter($env, $value, $length, $preserve, $separator);
    }

    /**
     * Truncate a string by word boundaries.
     * 
     * @param  String
     * @param  Int
     * @param  String
     * @return String
     */
    public function truncate_words($input, $numwords, $padding = '...') {

        $output = strtok($input, " \n");
    
        while (--$numwords > 0) {

            $output .= ' ' . strtok(" \n");
        } 

        if ($output != $input) {

            $output .= $padding;
        } 

        return $output;
    }

    /**
     * Decode all entities.
     * 
     * @param  String
     * @return String
     */
    public function entity_decode($value) {

        return html_entity_decode($value);
    }
    
    /**
     * Replace underscores with hypens in a given name.
     * 
     * @param  String
     * @return String
     */
    public function to_hyphens($value) {

        return str_replace('_', '-', $value);
    }

    /**
     * Strip non-breaking spaces, and replace consecutive white spaces with only one whitespace.
     *
     * For an extensive discussion on how to remove non-printable characters, see:
     * https://stackoverflow.com/questions/1176904/php-how-to-remove-all-non-printable-characters-in-a-string
     * 
     * @param  String
     * @return String
     */
    public function strip_nbsp($value) {

        if (!is_string($value)) {

            return;
        }

        $value = preg_replace('/[\xA0]/u', ' ', $value);
        return preg_replace('/ +/i', ' ', $value);
    }

    /**
     * Strip tags, but replace them with a space character to avoid adjacent words without
     * a space between them when stripping tags.
     *
     * @param  String
     * @return String
     */
    public function strip_tags($value) {

        if (!is_string($value)) {

            return;
        }
        
        // Prepend a white space character to all opening tags, strip all tags, 
        // remove excessive whitespace.
        return trim(preg_replace('/ +/i', ' ', strip_tags(str_replace('<', ' <', $value))));
    }

    /**
     * Format a timestamp.
     * 
     * @param  String - timestamp
     * @return String
     */
    public function date($date_string, $format_string = '%A, %e. %B %Y') {

        setlocale(LC_TIME, 'de_DE.UTF-8');
        return strftime($format_string, $date_string);
    }

    /**
     * Format a decimal number.
     * 
     * @param  String - number
     * @return String
     */
    public function number($number) {

        return number_format($number, 0, ',', '.');
    }

    /**
     * Whether or not we're on the front page.
     * 
     * @return Boolean
     */
    public function is_front() {

        return $this->utils->isFront();
    }

    /**
     * Whether or not to enable page tracking w/ Matomo et al.
     * 
     * @return Boolean
     */
    public function enable_tracking () {

        $user = $this->utils->getCurrentUser();

        if ($user->isAuthenticated()) {

            return false;
        }

        $request = $this->utils->getCurrentRequest();
        $host    = $request->server->get('HTTP_HOST');
        
        $deny    = [

            'baufoerderer.test',
            'test.baufoerderer.de',
            'baufoerderer-v3',
        ];

        foreach ($deny as $name) {

            if (strpos($host, $name) !== false) {

                return false;
            }
        }

        return true;
    }

    /**
     * Whether or not someone's logged in.
     * 
     * @return Boolean
     */
    public function is_authenticated_user() {

        return $this->utils->getCurrentUser()->isAuthenticated();
    }

    /**
     * Set an element's formatter to 'basic_html', so its HTML wouldn't get escaped
     * by Twig's auto-escape mechanism.
     *
     * Other options would be to use Twigs 'raw' filter on the element in question (though that
     * might be a bit too broad & risky), or the set the field's format directly in the database.
     * 
     * @param  Array
     * @return Array
     */
    public function apply_html($element) {

        $element['#format'] = 'basic_html';
        return $element;
    }

    /**
     * Adds a theme suggestion to the element.
     *
     * Cf. https://www.lullabot.com/articles/level-your-twiggery
     *
     * @param  Array|Null - an element render array.
     * @param  String     - the theme suggestion, without the base theme hook.
     *
     * @return Array - The element with the theme suggestion added as the highest priority.
     */
    public function suggest_theme($element, $suggestion) {
  
        // Ignore empty render arrays (e.g. empty fields with #cache and #weight).

        if (empty($element['#theme'])) {

            return $element;
        }

        // Avoid this annoying hyphen vs. underscore thing between naming the suggestion
        // (use underscores) and naming the file (use hyphens). 

        $suggestion = str_replace('-', '_', $suggestion);
        
        // Transform the theme hook to a format that supports multiple suggestions.
          
        if (!is_iterable($element['#theme'])) {
          
          $element['#theme'] = [$element['#theme']];
        }
      
        // The last item in the list of theme hooks has the lowest priority; assume
        // it's the base theme hook.
      
        $base_theme_hook = end($element['#theme']);
    
        // Add the suggestion to the front.
      
        array_unshift($element['#theme'], "{$base_theme_hook}__$suggestion");

        return $element;
    }

    /**
     * Get media info.
     * 
     * @param  EntityReferenceFieldItemListInterface
     * @return Array
     */
    public function media_info($element) {

        if (!$element instanceof EntityReferenceFieldItemListInterface) {

            return [];
        }

        $references = $element->referencedEntities();
        $info       = [];

        foreach ($references as $reference) {

            if ($reference instanceof MediaInterface) {

                $info[] = $this->media->info($reference);
            }

        }

        return $info;
    }

    /**
     * Get field label.
     * 
     * @param  FieldItemListInterface|Array
     * @return Array
     */
    public function field_label($element) {

        if ($element instanceof FieldItemListInterface) {

            $element = $element->view();
        }

        if (is_array($element)) {

            return $element['#title'] ?? null;
        }
    }

    /**
     * Get field values.
     * 
     * @param  FieldItemListInterface
     * @return Array
     */
    public function field_values($field) {

        return $this->field->getProcessedValues($field);
    }

    /**
     * Get field references.
     * 
     * @param  EntityReferenceFieldItemListInterface
     * @return Array
     */
    public function field_references($field) {

        if (!$field instanceof EntityReferenceFieldItemListInterface) {

            return [];
        }

        return $this->field->getReferencedValues($field);
    }

    /**
     * Get field paragraphs.
     * 
     * @param  EntityReferenceFieldItemListInterface
     * @return Array
     */
    public function field_paragraphs($field) {

        if (!$field instanceof EntityReferenceFieldItemListInterface) {

            return [];
        }

        $items = [];

        foreach ($field->getIterator() as $pos => $item) {

            $view = $item->view();

            if (isset($view['#paragraph']) && $view['#paragraph'] instanceof Paragraph) {

                $paragraph = $view['#paragraph'];

                $info = [

                    'type'   => $paragraph->getType(),
                    'values' => $paragraph->getIterator()->getArrayCopy(),
                    'view'   => $view,
                    'index'  => $pos
                ];

                $items[] = $info;
            }
        }

        return $items;
    }

    /**
     * Replace all items of a given render array (a.k.a. content.some_field)
     * with the given replacements.
     * 
     * @param  Array
     * @param  Array
     * @return Array
     */
    protected function replace_list_render_items($content, $replacement) {

        // Apparently, Drupal accepts only a zero-based items array to
        // render the items contained in it.
        
        // So, first, remove *all* numerically indexed items. 

        foreach (array_keys($content) as $key) {
            
            if (is_numeric($key)) {

                unset($content[$key]);
            }
        }

        // Afterwards, put all items that should be rendered back in. 
        
        $base = 0;

        foreach ($replacement as $item) {

            $content[$base] = $item['view'];
            $base++;
        }

        return $content;
    }

    /**
     * Filter paragraph types from a paragraph content field (i.e., exclude 
     * given paragraph type from being rendered).
     * 
     * @param  Array  - a content.some_paragraph_field array
     * @param  String - the paragraph type to look for
     * @return Array
     */
    public function without_paragraph_type($content, $type) {

        if (!isset($content['#items'])) {

            return $content;
        }

        if (!$content['#items'] instanceof EntityReferenceFieldItemListInterface) {

            return $content;
        }

        // Get all paragraphs, filter them by type 
        
        $filtered = array_filter($this->field_paragraphs($content['#items']), function ($item) use ($type) {

            return $item['type'] != $type;
        });

        return $this->replace_list_render_items($content, $filtered);
    }

    /**
     * Opposite of {@see without_paragraph_type(()} above: Render
     * only paragraphs of a given type.
     * 
     * @param  Array - a content.some_paragraph_field array
     * @param  String - the paragraph type to look for
     * @return Array
     */
    public function only_paragraph_type($content, $type) {

        if (!isset($content['#items'])) {

            return $content;
        }

        if (!$content['#items'] instanceof EntityReferenceFieldItemListInterface) {

            return $content;
        }

        // Get all paragraphs, filter them by type 

        $filtered = array_filter($this->field_paragraphs($content['#items']), function ($item) use ($type) {

            return $item['type'] == $type;
        });

        return $this->replace_list_render_items($content, $filtered);
    }

    /**
     * Filter YouTube urls.
     * 
     * @param  EntityReferenceFieldItemListInterface
     * @return Array
     */
    public function you_tube($url) {

        $url = (string) $url;

        // Try to get the YouTube video id
        
        preg_match_all('/(v=|embed\/)([-a-z0-9]+)(&|\/|$)/i', $url, $info);

        $id = $info[2][0] ?? null; 

        if ($id) {

            return 'https://www.youtube-nocookie.com/embed/'.$id;
        }

        return $url;
    }

    /**
     * Filter (external) URLs.
     * 
     * @param  EntityReferenceFieldItemListInterface
     * @return Array
     */
    public function filter_url($url) {

        if (!$url instanceof Url) {

            return $url;
        }

        if (!$url->isExternal()) {

            return $url;
        }

        $url->setOption('attributes', ['target' => '_blank']);
        return $url;
    }

    /**
     * Whether or not the given URL points to a place outside our site.
     * 
     * @param  String
     * @return Boolean
     */
    public function is_url_external($url) {

        global $base_url;

        if (substr($url, 0, 1) == '/' && substr($url, 0, 2) != '//') {

            return false;
        }

        $host = parse_url($base_url, PHP_URL_HOST);

        if (preg_match("!^(https?:?)?//{$host}(/.*)?$!", $url)) {

            return false;
        }

        return true;
    }

    /**
     * @return String | Boolean
     */
    public function node_get_meta_desc($node) {

        if (!$node instanceof DrupalNode) {

            return false;
        }

        $meta = $this->node->getMeta($node);

        if (isset($meta['description'])) {

            return trim($meta['description']);
        }

        return false; 
    }

   /**
     *
     * @param Hyphenator 
     */
    public function __construct(Hyphenator $hyphenator, Utilities $utils, Node $node, Field $field, MediaService $media, Related $related) {
    
        $this->hyphenator = $hyphenator;
        $this->utils      = $utils;
        $this->node       = $node;
        $this->field      = $field;
        $this->media      = $media;
        $this->related    = $related;
    }
}