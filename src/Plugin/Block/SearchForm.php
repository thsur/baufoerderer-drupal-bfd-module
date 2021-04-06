<?php

namespace Drupal\bfd\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\views\Views;

/**
 * @Block(
 *
 *  id          = "bfd_search_form",
 *  admin_label = @Translation("Bfd Search Form"),
 *  category    = @Translation("Search"),
 * )
 */
class SearchForm extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    
    return [

      '#theme' => 'bfd-search-form',
      '#data'  => Views::getView('site_search_db')->getPath()
    ];
  }
}
