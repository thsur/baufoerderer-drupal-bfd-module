<?php

namespace Drupal\bfd\Hyphenator;

use Vanderlee\Syllable\Syllable;
use Vanderlee\Syllable\Hyphen\Soft;
use Vanderlee\Syllable\Hyphen\ZeroWidthSpace;

use Drupal\bfd\Utilities;
use Drupal\Core\Extension\ModuleHandler;

/**
 * Cf.:
 * - https://github.com/vanderlee/phpSyllable
 * - https://github.com/bramstein/hypher
 * - https://github.com/ytiurin/hyphen
 */
class Hyphenator {

    /**
     * @var Utilities
     */
    protected $utils;

    /**
     * @var string
     */
    protected $cache_dir;

    /**
     * @var string
     */
    protected $lang_dir;

    /**
     * @var Syllable
     */
    protected $syllable;

    protected function setSyllable() {

        // Create a new instance for the language
        $this->syllable = new Syllable($this->utils->getLanguage());

        // Set the directory where the .tex files are stored
        $this->syllable->getSource()->setPath($this->lang_dir);

        // Set the directory where Syllable can store cache files
        $this->syllable->getCache()->setPath($this->cache_dir);

        // Set the minimum length required for a word to be hyphenated
        $this->syllable->setMinWordLength(12);

        // Set the hyphen style
        $this->syllable->setHyphen(new Soft);
    }

    public function hyphenate($content) {

        $syllable = $this->syllable;

        // Syllable doesn't seem to get UTF-8 encoded german umlauts right. While we could
        // use any of PHP's character conversion functions to tackle the problem, which we will
        // do in a moment, we first need to make sure they'll operate on words only - as opposed
        // to special characters, which might get encoded wrongly.
        
        // Split content on word boundaries
        $content  = preg_split('/\b/u', $content);
        
        // Traverse content & operate on words only - but take Unicode into account while doing
        // so, cf.:
        // 
        // - https://stackoverflow.com/a/44278105/3323348
        // - https://stackoverflow.com/a/36366635/3323348
        // - https://www.php.net/manual/en/regexp.reference.unicode.php#118693
        foreach ($content as $key => $part) {

            if (preg_match('/^[\p{L}]+$/u', $part)) {

                $hypenated     = $syllable->hyphenateWord(utf8_decode($part));
                $content[$key] = utf8_encode($hypenated);
            }
        }

        $content = html_entity_decode(implode('', $content));
        return $content;
    }

    public function __construct(Utilities $utils, string $drupal_root) {

        $this->utils     = $utils;
        $this->lang_dir  = realpath($drupal_root.'/../vendor/vanderlee/syllable/languages');
        $this->cache_dir = realpath(dirname(__FILE__).'/cache');

        $this->setSyllable();
    }
}