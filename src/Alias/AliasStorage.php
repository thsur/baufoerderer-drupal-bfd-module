<?php

namespace Drupal\bfd\Alias;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;

use Drupal\Core\Path\AliasStorage as CoreAliasStorage;

/**
 * Decorate alias storage service.
 *
 * See:
 * - https://www.axelerant.com/resources/team-blog/drupal-8-service-decorators
 * - https://www.previousnext.com.au/blog/decorated-services-drupal-8
 * - https://symfony.com/doc/current/service_container/service_decoration.html
 * - https://www.drupal.org/docs/8/api/services-and-dependency-injection/altering-existing-services-providing-dynamic-services
 */
class AliasStorage extends CoreAliasStorage {
    
    /**
     * @var CoreAliasStorage
     */
    protected $core_alias_storage;

    /**
     * {@inheritdoc}
     */
    public function save($source, $alias, $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED, $pid = null) {
        
        $this->core_alias_storage->save($source, $alias, $langcode, $pid);
    }

    /**
     *
     * @param CoreAliasStorage 
     * @param Connection 
     * @param ModuleHandlerInterface 
     */
    public function __construct(CoreAliasStorage $core_alias_storage, Connection $connection, ModuleHandlerInterface $module_handler) {
    
        $this->core_alias_storage = $core_alias_storage;
        parent::__construct($connection, $module_handler);
    }

}