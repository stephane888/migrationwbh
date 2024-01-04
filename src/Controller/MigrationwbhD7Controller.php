<?php

namespace Drupal\migrationwbh\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrationwbh\Services\D7\ManageImport;

/**
 * Returns responses for migrationwbh routes.
 */
class MigrationwbhD7Controller extends ControllerBase {
  protected $ManageImport;

  function __construct(ManageImport $ManageImport) {
    $this->ManageImport = $ManageImport;
  }

  /**
   * Ceci est un test via l'environnement de Sogetie.
   */
  function buildImportDemoD7() {
    $this->ManageImport->ValidConfigEntities();
    return [];
  }

  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('migrationwbh.migrate_import_d7'));
  }

}