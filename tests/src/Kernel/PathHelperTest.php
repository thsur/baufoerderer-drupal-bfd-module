<?php

namespace Drupal\Tests\bfd\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\bfd\PathHelper;
use Drupal\node\Entity\Node;

/**
 * Simple test to ensure that asserts pass.
 *
 * @group bfd
 */
class PathHelperTest extends KernelTestBase {
  
  /**
   * The service under test.
   *
   * @var \Drupal\bfd\PathHelper
   */
  protected $service;
  
  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  public static $modules = [
  
    'paragraphs',
    'node',
    'user',
    'system',
    'field',
    'taxonomy',
    'entity_reference_revisions',
    'text',
    'ctools', 
    'language',
    'bfd',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {

    parent::setUp();
    
    $this->installSchema('system', ['url_alias', 'sequences', 'router']);
    $this->installSchema('node', ['node_access']);
    
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('paragraph');

    $this->installConfig(['system', 'paragraphs', 'field', 'node']);


    $this->service = \Drupal::service('bfd.path_helper');
  }
  
  public function testGetNodeParent() {

    $this->assertEquals(0, $this->service->getNodeParent());
  }
  
  /**
   * Once test method has finished running, whether it succeeded or failed, tearDown() will be invoked.
   * Unset the $unit object.
   */
  public function tearDown() {
  
    unset($this->service);
  }
}