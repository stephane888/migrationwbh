<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\DataParserPluginManager;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Stephane888\Debug\Utility as UtilityError;
use Stephane888\Debug\debugLog;

/**
 * NB: pour une URL donnée les données sont du meme type.
 *
 * @author stephane
 *
 */
class MigrationAutoImport {
  /**
   * Donnée provenant du champs de type entité.
   * ( d'une source migrate ).
   *
   * @var array
   */
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
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $MigrationPluginManager;

  /**
   *
   * @var DataParserPluginManager
   */
  protected $DataParserPluginManager;

  /**
   * entityTypeId ( node, block_content ...
   * )
   */
  protected $entityTypeId = null;

  /**
   * disponible pour des entités avec bundles.
   */
  protected $bundle = null;
  protected $EntityValide = [
    'block_content',
    'paragraph'
  ];

  /**
   * les champs qui serront ignorées dans le mapping.
   *
   * @var array
   */
  protected $unMappingFields = [
    'block_content_type',
    'revision_user',
    'content_translation_uid',
    'paragraph_type'
  ];

  function __construct(MigrationPluginManager $MigrationPluginManager, DataParserPluginManager $DataParserPluginManager) {
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->DataParserPluginManager = $DataParserPluginManager;
  }

  public function setData(array $data) {
    if (empty($data['data']) || empty($data['links'])) {
      throw new \ErrorException('Données non valide');
      \Stephane888\Debug\debugLog::kintDebugDrupal($data, 'MigrationAutoImport-data-error-', true);
    }

    $this->fieldData = $data;
  }

  public function runImport() {
    if (!$this->fieldData)
      throw new \ErrorException(' Vous devez definir fieldData ');
    $configuration = $this->constructPlugin();
    // $dbg = [
    // 'fieldData' => $this->fieldData,
    // 'configuration' => $configuration,
    // 'rawData' => $this->rawDatas
    // ];
    // //
    // \Stephane888\Debug\debugLog::$max_depth = 10;
    // \Stephane888\Debug\debugLog::kintDebugDrupal($dbg, 'MigrationAutoImport',
    // true);
    return $this->runMigrate($configuration);
  }

  /**
   * On construit la configuration du plugin migration
   */
  protected function constructPlugin() {
    $configuration = [];
    if (empty($this->rawDatas))
      $this->retrieveDatas();
    //
    $this->getDatasInformation($configuration);
    return $configuration;
  }

  protected function runMigrate(array $configuration) {
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
      $migrateParagraph->setStatus(MigrationInterface::STATUS_IDLE);
      debugLog::kintDebugDrupal(UtilityError::errorAll($e), 'runParagraphImport--error--', true);
      return false;
    }
  }

  /**
   * Permet de recuperer et de remplir les informations de base telsque :
   * - entityTypeId ( node, block_content ...
   * ).
   * - bundle ( s'il existe ).
   * -
   */
  protected function getDatasInformation(array &$configuration) {
    $rawDatas = $this->rawDatas['data'];
    if (empty($rawDatas[0])) {
      \Stephane888\Debug\debugLog::$max_depth = 10;
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas
      ];
      \Stephane888\Debug\debugLog::kintDebugDrupal($dbg, 'MigrationAutoImport-data-error-', true);
      throw new \ErrorException(" Structure des données non prise en charge ");
    }

    //
    $firstRow = $rawDatas[0];
    $type = explode("--", $firstRow['type']);

    if ($this->checkEntityValide($type[0])) {
      $this->entityTypeId = $type[0];
      $configuration['destination']['plugin'] = 'entity:' . $this->entityTypeId;
      if ($type[0] != $type[1]) {
        $this->bundle = $type[1];
        $configuration['destination']['default_bundle'] = $this->bundle;
      }
      $this->setIdentificatorRow($configuration);
      $this->getSourceFieldsAndProcessMapping($configuration);
    }
    else {
      \Stephane888\Debug\debugLog::$max_depth = 10;
      \Stephane888\Debug\debugLog::kintDebugDrupal($this->rawDatas, 'MigrationAutoImport-entity-non-valide-', true);
    }
  }

  /**
   * Recupere les données (data_rows) et le mapping;
   *
   * @param array $firstRow
   * @param array $configuration
   */
  protected function getSourceFieldsAndProcessMapping(array &$configuration) {
    $processMapping = [];
    $data_rows = [];
    /**
     * Pour les champs avec relations, on va relancer le processus afin
     * d'obtenir la valeur et de l'ajouter dans le processus.
     */
    foreach ($this->rawDatas['data'] as $k => $rawData) {
      // add type, ceci fonctionne pour la majorité des entités.
      if ($this->bundle)
        $this->rawDatas['data'][$k]['attributes']['type'] = $this->bundle;
      $data_rows[$k] = $this->rawDatas['data'][$k]['attributes'];
      if (!empty($rawData['relationships'])) {
        foreach ($rawData['relationships'] as $fieldName => $val) {
          if (in_array($fieldName, $this->unMappingFields) || empty($val['data']))
            continue;
          // à la fin du process, on doit obtenir des données valide.
          $relationship = new MigrationAutoImport($this->MigrationPluginManager, $this->DataParserPluginManager);
        /**
         * Apres traitement, on mettra à jour $this->rawDatas, fields
         */
        }
      }
    }
    // mapping;
    foreach ($data_rows[0] as $fieldName => $v) {
      if ($fieldName == 'drupal_internal__nid') {
        $processMapping['nid'] = $fieldName;
      }
      elseif ($fieldName == 'drupal_internal__id') {
        $processMapping['id'] = $fieldName;
      }
      elseif ($fieldName == 'drupal_internal__revision_id' || $fieldName == 'drupal_internal__vid')
        continue;
      else
        $processMapping[$fieldName] = $fieldName;
    }
    // add datas to config plugin.
    $configuration['source']['plugin'] = 'embedded_data';
    $configuration['source']['data_rows'] = $data_rows;
    $configuration['process'] = $processMapping;
  }

  /**
   * Champs permettant d'identifier une ligne.
   *
   * @param array $configuration
   */
  protected function setIdentificatorRow(array &$configuration) {
    if ($this->entityTypeId == 'node') {
      $configuration['source']['ids']['drupal_internal__nid'] = [
        'type' => 'integer'
      ];
    }
    else {
      $configuration['source']['ids']['drupal_internal__id'] = [
        'type' => 'integer'
      ];
    }
  }

  /**
   *
   * @param string $entityTypeId
   * @return boolean
   */
  protected function checkEntityValide($entityTypeId) {
    return in_array($entityTypeId, $this->EntityValide) ? true : false;
  }

  /**
   * Permet de recuper les données à partir de l'url;
   */
  protected function retrieveDatas() {
    $conf = [
      'data_fetcher_plugin' => 'http'
    ];
    /**
     *
     * @var \Drupal\migrationwbh\Plugin\migrate_plus\data_parser\JsonApi $json_api
     */
    $json_api = $this->DataParserPluginManager->createInstance('json_api', $conf);
    $url = $this->fieldData['links']['related']['href'];
    $this->rawDatas = $json_api->getDataByExternalApi($url);
  }

}