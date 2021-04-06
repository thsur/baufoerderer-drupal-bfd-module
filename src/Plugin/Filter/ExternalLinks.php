<?php

namespace Drupal\bfd\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * @Filter(
 *   id = "filter_external_links",
 *   title = @Translation("External Links Filter"),
 *   description = @Translation("Parse external links and add some markup to them"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class ExternalLinks extends FilterBase {

    /**
     * Augment external links.
     * 
     * @return String
     */
    protected function filterExternal($text) {

        $links     = '/<a href=.https?:\/\/[^>]+>(?![<])/';
        $has_links = preg_match_all($links, $text, $matches);

        if (!$has_links) {

            return $text;
        }

        $matches = array_unique($matches[0]);

        foreach ($matches as $match) {

            $filtered = str_replace(

                '>', 
                ' class="link--external" target="_blank"><i class="fas fa-external-link-alt fa-xs"></i>', 
                $match
            );

            $text = str_replace($match, $filtered, $text);
        }

        return $text;
    } 

    public function process($text, $langcode) {
        
        return new FilterProcessResult($this->filterExternal($text));
    }
}