<?php

namespace Drupal\bfd\Content;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;

use Drupal\media\Entity\Media as MediaEntity;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\Image;
use Drupal\media\MediaSourceBase;
use Drupal\file\FileInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystem;

use Drupal\bfd\Utilities;
use Drupal\bfd\Content\Field;

use Tightenco\Collect\Support\Arr;

/**
 * Media Service
 *
 * Cf.:
 * - https://www.drupal.org/docs/8/core/modules/media
 * - https://www.drupal.org/docs/8/core/modules/media/creating-and-configuring-media-types
 */
class Media {

    const GUIDES     = 1415;
    const WIE        = 1413;
    const CHECKLISTS = 1275;
    const SUBSIDIES  = 1417;

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
     * @var FileSystem
     */
    private $filesystem;

    /**
     * @var EntityTypeManagerInterface
     */
    private $entityManager;

    /**
     * Get data about a given file.
     * 
     * @param  FileInterface 
     * @return Array
     */
    public function getFileInfo(FileInterface $file): array {

        $data   = [];
        $fields = array_keys($file->getFields());

        array_walk($fields, function ($field) use (&$data, $file) {

            $values       = $this->field->getValues($file->get($field));
            $data[$field] = end($values);
        });

        if ($data['uri']) {
                    
            $data['url']  = file_create_url($data['uri']); 
            $data['path'] = $this->filesystem->realpath($data['uri']);                    
        }

        return $data;
    }

    /**
     * Get infos about a media entity & the actual item it points to.
     * 
     * @return Array
     */
    public function info(MediaInterface $media): array {

        $source = $media->getSource();

        if (!$source instanceof MediaSourceBase) {

            return [];
        }
        
        // Set defaults

        $data = [

            'label'    => $media->label(),
            'mid'      => $media->id(),
            'fileinfo' => []
        ];

        // Add copyright info & description, if any

        foreach (['field_description', 'field_copyright'] as $field) {

            if ($media->hasField($field)) {

                $values       = $this->field->getValues($media->get($field));
                $data[$field] = end($values);
            }
        }

        // Add source metadata attributes, like mimetyp & filesize
        
        $attributes = array_keys($source->getMetadataAttributes());

        array_walk($attributes, function ($attribute) use (&$data, $source, $media) {

            $data[$attribute] = $source->getMetadata($media, $attribute);
        });

        // The name of the field referencing the media item 
        
        $field_name = $source->getConfiguration()['source_field'];
        
        // The field item itself
        
        $field = $media->get($field_name)->first();    

        // Collect its values, like alt & title attributes

        $data = array_merge(

            $field->getValue(), 
            $data
        );

        // Finally, get the media item(s) itself
        
        $items = $media->get($field_name)->referencedEntities();

        array_walk($items, function ($item) use (&$data) {

            if ($item instanceof File) {

                $data['fileinfo'][] = $this->getFileInfo($item);
            }
        });

        return $data;
    }

    /**
     * Create a managed file in the public file system space and set or update
     * its file db entry.
     * 
     * @param  String  - data to write
     * @param  String  - path to write to w/o prepended scheme
     * @param  Integer - what to do of the file exist
     * @return Mixed   - FileInterface | Boolean
     */
    public function createPublicFile(string $data, string $path, $mode = FILE_EXISTS_REPLACE) {

        $scheme = 'public://';

        if (strpos($scheme, $path) === false) {

            $path = $scheme.trim($path, '/');
        } 

        return file_save_data($data, $path, $mode);
    }

    /**
     * Get all media references pointing to a given file.
     * 
     * @param  FileInterface - file to look up
     * @param  String        - media field name
     * @return Array
     */
    public function getMediaFileReferences(FileInterface $file, string $media_field_name = 'field_media_file'): array {

        $references = file_get_file_references($file);
        $field      = $media_field_name;

        if (empty($references) || !isset($references[$field]) || !isset($references[$field]['media'])) {

            return [];
        }

        return $references[$field]['media'];
    }

    /**
     * Create or update a media entity for the given file, and create or update 
     * its usage db entry.
     * 
     * @param  FileInterface - the file object to operate with
     * @param  String        - the media entity's name
     * @param  Boolean       - whether to publish the media item or not
     * @return Array         - array of MediaEntity objects
     */
    public function createMediaEntryFromFile(FileInterface $file, string $name, bool $publish = true) {

        $owner      = $this->utils->getCurrentUser()->id();
        $fid        = $file->id();
        $mime       = $file->getMimeType();

        $references = $this->getMediaFileReferences($file);
        
        if (empty($references)) {
    
            $references[] = MediaEntity::create([
              
                'bundle'             => 'file',
                'uid'                => $owner,
                'field_media_file'   => [

                    'target_id' => $fid,
                    'display'   => 1
                ],
            ]);
        }

        $thumbnail = [

            'alt'       => $name, 
            'title'     => $name,
            'target_id' => strpos($mime, 'image') !== false ? $fid : 1 
        ];

        foreach ($references as $key => $reference) {

            $reference->setName($name)
                      ->setPublished($publish)
                      ->set('thumbnail', $thumbnail)
                      ->save();
        }

        return $references;
    }

    /**
     * Tag a media item.
     * 
     * @param  MediaEntity 
     * @param  Array       
     * @return void
     */
    public function tagMediaItem(MediaEntity $media, array $tags){

        if ($media->hasField('field_tags')) {

            $media->set('field_tags', $tags);
            $media->save();
        }
    }

    /**
     * @param  Array
     * @return Array
     */
    public function getFilesByTags(array $tags): array {

        $data = [];

        // Get media ids filtered by given tags 

        $storage = $this->entityManager->getStorage('media');
        $mids    = $storage->getQuery()
                           ->condition('bundle', 'file')
                           ->condition('field_tags', $tags, 'in')
                           ->sort('name' , 'asc')
                           ->execute();

        if (empty($mids)) {

            return $data;
        }

        // Collect media items & their corresponding sources

        foreach($storage->loadMultiple($mids) as $media) {

            // Get source

            $fid   = $media->getSource()->getSourceFieldValue($media); // source field could be e.g. field_media_file_target_id
            $file  = File::load($fid);
            
            if (!$file) {

                continue;
            }

            // Prepare mapping

            $mid = $media->id();

            if (!isset($data['items'])) {

                $data['items']   = [];        
                $data['by_tags'] = [];        
            }

            // Collect media items & their corresponding sources

            $data['items'][$mid] = ['item' => $media, 'source' => $file];
                
            // Provide a map of tag ids pointing to the collected media items & sources

            foreach ($this->field->getValues($media->field_tags) as $tag) {

                if (!isset($data['by_tags'][$tag])) {

                    $data['by_tags'][$tag] = [];
                }

                $data['by_tags'][$tag][] = $mid;
            }
        }

        return $data;
    }

    /**
     * @param RequestStack
     */
    public function __construct(Utilities $utils, EntityTypeManagerInterface $entityManager, Connection $db, Field $field, FileSystem $fs) {

        $this->utils         = $utils;
        $this->db            = $db;
        $this->entityManager = $entityManager;
        $this->field         = $field;
        $this->filesystem    = $fs;
    }
}