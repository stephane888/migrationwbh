<?php

namespace Drupal\migrationwbh\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\migrationwbh\Services\MigrationImport;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrationwbh\Services\MigrationAutoImport;

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
    return new static($container->get('migrationwbh.migrate_import'), $container->get('migrationwbh.migrate_auto_import'));
  }
  
  function __construct(MigrationImport $MigrationImport, MigrationAutoImport $MigrationAutoImport) {
    $this->MigrationImport = $MigrationImport;
    $this->MigrationAutoImport = $MigrationAutoImport;
  }
  
  /**
   * Builds the response.
   */
  public function build() {
    $this->MigrationAutoImport->testSiteInternetEntityImport('http://test62.wb-horizon.kksa/jsonapi/export/page-web');
    $build['content'] = [
      '#theme' => 'migrationwbh_debug_migrate',
      '#content' => $this->t(' It works! ')
    ];
    return $build;
  }
  
  //
  
  /**
   * Builds the response.
   */
  public function loadParagrph() {
    $this->MigrationAutoImport->testParagraphImport('/jsonapi/export/paragraph');
    $build['content'] = [
      '#theme' => 'migrationwbh_debug_migrate',
      '#content' => $this->t(' It works! ')
    ];
    return $build;
  }
  
  /**
   * Builds the response.
   */
  public function buildOld() {
    $datas = $this->MigrationAutoImport->testNodeImport('http://test62.wb-horizon.kksa/jsonapi/node/realisations_entreprise_generale');
    $build['content'] = [
      '#theme' => 'migrationwbh_debug_migrate',
      '#content' => $this->t(' It works! '),
      '#configuration' => $datas['conf'],
      '#data' => null,
      '#raw_data' => $datas['rawDatas'],
      '#error' => $datas['error']
    ];
    return $build;
  }
  
}
