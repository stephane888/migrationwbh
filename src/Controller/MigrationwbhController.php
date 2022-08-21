<?php

namespace Drupal\migrationwbh\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\migrationwbh\Services\MigrationImport;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for migrationwbh routes.
 */
class MigrationwbhController extends ControllerBase {
  protected $MigrationImport;
  
  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('migrationwbh.migrate_import'));
  }
  
  function __construct(MigrationImport $MigrationImport) {
    $this->MigrationImport = $MigrationImport;
  }
  
  /**
   * Builds the response.
   */
  public function build() {
    $this->MigrationImport->runImport();
    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t(' It works! ')
    ];
    
    return $build;
  }
  
}
