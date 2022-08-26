<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\DataParserPluginManager;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Stephane888\Debug\Utility as UtilityError;
use Stephane888\Debug\debugLog;

class MigrationImportAutoNode {
  protected $fieldData;
  /**
   * Données brute provenant du site distant.
   * Structure :
   * un array donc chaque ligne represente une donnée.
   * $rawDatas[data]
   * $rawDatas[data][0--n][attributes]
   * $rawDatas[data][0--n][relationships]
   * $rawDatas[data][0--n][links]
   * $rawDatas[data][0--n][type]
   * $rawDatas[links]
   *
   * @var array
   */
  protected array $rawDatas = [];
  /**
   * entityTypeId ( node, block_content ...
   * )
   */
  protected $entityTypeId = null;

  /**
   * disponible pour des entités avec bundles.
   */
  protected $bundle = null;

  /**
   * les champs qui serront ignorées dans le mapping.
   *
   * @var array
   */
  private $unMappingFields = [
    'drupal_internal__vid',
    'revision_timestamp',
    'revision_log'
  ];
  private $SkypRunMigrate = false;

  function __construct(MigrationPluginManager $MigrationPluginManager, DataParserPluginManager $DataParserPluginManager, $entityTypeId, $bundle) {
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->DataParserPluginManager = $DataParserPluginManager;
    $this->entityTypeId = $entityTypeId;
    $this->bundle = $bundle;
  }

  public function setData(array $data) {
    if (empty($data['data']) || empty($data['links'])) {
      throw new \ErrorException('Données non valide');
      \Stephane888\Debug\debugLog::kintDebugDrupal($data, 'MigrationImportAutoNode-ERROR--setData--', true);
    }
    $this->fieldData = $data;
  }

  public function runImport() {
    if (!$this->fieldData)
      throw new \ErrorException(' Vous devez definir fieldData ');
    $this->retrieveDatas();
    /**
     * --
     *
     * @var array $configuration
     */
    $configuration = [
      'destination' => [
        'plugin' => 'entity:' . $this->entityTypeId,
        'default_bundle' => $this->bundle
      ],
      'source' => [
        'ids' => [
          'plugin' => 'embedded_data'
        ],
        'data_rows' => []
      ],
      'process' => []
    ];
    $this->buildDataRows($configuration['source']['data_rows']);
    $this->buildMappingProcess($configuration, $configuration['process']);
    //
    return $this->runMigrate($configuration);
  }

  private function runMigrate(array $configuration) {
    if ($this->SkypRunMigrate)
      return true;
    $plugin_id = 'wbhorizon_entites_auto';
    /**
     *
     * @var \Drupal\migrate\Plugin\Migration $migrateParagraph
     */
    $migrateParagraph = $this->MigrationPluginManager->createInstance($plugin_id, $configuration);
    $migrateParagraph->getIdMap()->prepareUpdate();
    $executable = new MigrateExecutable($migrateParagraph, new MigrateMessage());
    try {
      // Run the migration.
      $executable->import();
      return true;
    }
    catch (\Exception $e) {
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas,
        'configuration' => $configuration
      ];
      debugLog::kintDebugDrupal($dbg, 'MigrationImportAutoNode-ERROR--runMigrate--', true);
      return false;
    }
  }

  /**
   * Permet de construire,
   *
   * @param array $configuration
   */
  private function buildDataRows(array &$data_rows) {
    $this->validationDatas();
    $data = $this->rawDatas['data'];
    foreach ($data as $k => $row) {
      $data_rows[$k] = $row['attributes'];
    }
  }

  /**
   *
   * @param
   *        $configuration
   * @param array $process
   */
  private function buildMappingProcess($configuration, array &$process) {
    if (!empty($configuration['source']['data_rows'][0])) {
      foreach ($configuration['source']['data_rows'][0] as $fieldName => $value) {
        if ($fieldName == 'drupal_internal__nid') {
          $process['nid'] = $fieldName;
        }
        elseif (in_array($fieldName, $this->unMappingFields))
          continue;
        else
          $process[$fieldName] = $fieldName;
      }
    }
  }

  /**
   * Permet de recuper les données à partir de l'url;
   */
  private function retrieveDatas() {
    $url = $this->fieldData['links']['related']['href'];
    $conf = [
      'data_fetcher_plugin' => 'http',
      'urls' => [
        $url
      ]
    ];

    /**
     *
     * @var \Drupal\migrationwbh\Plugin\migrate_plus\data_parser\JsonApi $json_api
     */
    $json_api = $this->DataParserPluginManager->createInstance('json_api', $conf);
    $this->rawDatas = $json_api->getDataByExternalApi($url);
  }

  /**
   * Dans la mesure ou le contenu est renvoyé sur 1 ligne, (data.type au lieu de
   * data.0.type ).
   * On corrige cela.
   *
   * @return boolean
   */
  private function validationDatas() {
    if (!empty($this->rawDatas['data'][0]) && !empty($this->rawDatas['data'][0]['attributes']['drupal_internal__nid'])) {
      return true;
    }
    else {
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas
      ];
      debugLog::kintDebugDrupal($dbg, 'MigrationImportAutoNode-ERROR--validationDatas--', true);
    }
  }

}