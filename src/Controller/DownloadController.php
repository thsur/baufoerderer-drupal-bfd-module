<?php

namespace Drupal\bfd\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystem;
use Drupal\file\Entity\File;
use Drupal\Core\Url;

use Drupal\bfd\Content\Media;
use stdClass;

/**
 * Download controller.
 */
class DownloadController extends ControllerBase {

    /**
     * @var Media
     */
    protected $media;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @var  FileSystem
     */
    private $fs;

    /**
     * @param  ContainerInterface 
     * @return DownloadController
     */
    public static function create(ContainerInterface $container)
    {
        $media = $container->get('bfd.media_service');
        $db    = $container->get('database');
        $fs    = $container->get('file_system');

        // For new self() vs new static(), cf. https://stackoverflow.com/a/5197655
        return new static($media, $db, $fs);
    }

    /**
     * @param Media
     * @param Connection
     */
    public function __construct(Media $media, Connection $db, FileSystem $fs) {

        $this->media = $media;
        $this->db    = $db;
        $this->fs    = $fs;
    }

    /**
     * Get a file by its name.
     * 
     * @param  String - filename
     * @return Array 
     */
    protected function getFileByFn(string $fn) {

        return $this->db->query(

            "select * from file_managed where uri like :fn",
            [':fn' => "%{$fn}"]

        )->fetchAll();
    }

    /**
     * Get a node id by matching its alias.
     * 
     * @param  String - (partial) alias
     * @return Integer 
     */
    protected function getNodeIdByAlias(string $alias) {

        $result = $this->db->query(

            "select * from url_alias where alias like :alias",
            [':alias' => "%{$alias}"]

        )->fetchAll();

        foreach ($result as $row) {

            return (int) substr($row->source, strrpos($row->source, '/') + 1);
        }
    }


    /**
     * Get all nodes having the given file id attached.
     * 
     * @param  Integer
     * @param  String
     * @return Array
     */
    protected function getNodesByAttachment(int $fid, string $bundle): array {

        return $this->db->query(

            "
             select attachments.* from media__field_media_file as media
             join node__field_attachments as attachments 
             on attachments.field_attachments_target_id = media.entity_id
             join node_field_data as node on node.nid = attachments.entity_id
             where media.field_media_file_target_id = :fid and attachments.bundle = :bundle and node.status = 1
            ",
            [':fid' => $fid, ':bundle' => $bundle]

        )->fetchAll();
    }

    /**
     * Send a file.
     * 
     * @param  stdClass 
     * @param  String
     * @return BinaryFileResponse
     */
    public function sendFile(stdClass $file, $alias = null) {

        $url    = $this->fs->realpath($file->uri);
        $header = ['Content-Type' => $file->filemime];

        if ($alias) {

            $header['Link'] = '<'.$alias.'>; rel="canonical"';
        }

        return new BinaryFileResponse($url, 200, $header);
    }

    /**
     * Dispatch an incoming download request.
     * 
     * @param  String - a file name or null
     * @param  Request 
     */
    public function dispatch($fn, Request $request) {

        if (!$fn) {

            throw new NotFoundHttpException();
        }

        // Get the requested file

        $file = $this->getFileByFn($fn);

        if (empty($file)) {

            throw new NotFoundHttpException();
        }

        /**
         * @var File
         */
        $file = end($file);

        // Try guides.
        // 
        // If the requested files matches a node of type guide, serve it under the
        // node's canonical.
        
        if (strpos($fn, 'ratgeber_') === 0) {
            
            $nodes = $this->getNodesByAttachment($file->fid, 'guide');

            if (empty($nodes)) {

                return $this->sendFile($file);
            }

            // Get alias of the last found node 

            $node = end($nodes);
            $url  = Url::fromRoute('entity.node.canonical', ['node' => $node->entity_id]);

            return $this->sendFile($file, $url->setAbsolute()->toString());
        }

        // Try aliases.
        // 
        // If the requested files matches a node's alias, serve it under the
        // node's canonical.

        $slug = basename($fn, '.pdf');
        $nid  = $this->getNodeIdByAlias($slug);

        if ($nid) {

            $url = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
            return $this->sendFile($file, $url->setAbsolute()->toString());
        }

        return $this->sendFile($file);
    }
}
