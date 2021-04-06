<?php

/**
 * @see https://github.com/drush-ops/drush/tree/master/examples/Commands
 */

namespace Drupal\bfd\Commands;

use Drush\Commands\DrushCommands;
use Drupal\bfd\Utilities;
use Drupal\bfd\Content\PDF as Pdf;

class PdfCommands extends DrushCommands {

  /**
   * @var Pdf
   */
  protected $pdf;

  /**
   * @var Utilities
   */
  protected $utils;

  /**
   * Generate Pdf for the given node id.
   *
   * @param   $nid
   * @usage   bfd:generate-subsidy-pdf 123
   *
   * @command bfd:generate-subsidy-pdf
   */
  public function generateSubsidyPdf($nid) {

    $this->pdf->create($nid);
  }

  /**
   * Generate Pdfs for all subsidy nodes.
   *
   * @command bfd:generate-subsidy-pdfs
   */
  public function generateSubsidyPdfs() {

    $nodes = $this->utils->loadNodesByType('subsidy');

    foreach ($nodes as $node) {

      $this->pdf->create($node->id());
    }
  }

  /**
   * Dependencies are injected via drush.services.yml
   * 
   * @param ModuleHandler    
   * @param StorageInterface 
   */
  public function __construct(Utilities $utils, Pdf $pdf) {

    $this->pdf   = $pdf;
    $this->utils = $utils;
  }
}
