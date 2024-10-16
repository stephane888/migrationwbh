<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\DataParserPluginManager;
use Stephane888\Debug\ExceptionDebug as DebugCode;
use Drupal\Core\Logger\LoggerChannel;

class MigrationImportAutoConfigThemeEntity extends MigrationImportAutoBase {
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
   * les champs qui serront ignorées dans le mapping.
   *
   * @var array
   */
  private $unMappingFields = [
    'created',
    "changed"
  ];
  private $unGetRelationships = [];
  private $SkypRunMigrate = false;

  function __construct(MigrationPluginManager $MigrationPluginManager, DataParserPluginManager $DataParserPluginManager, LoggerChannel $LoggerChannel, $entityTypeId) {
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->DataParserPluginManager = $DataParserPluginManager;
    $this->entityTypeId = $entityTypeId;
    $this->LoggerChannel = $LoggerChannel;
  }

  public function runImport() {
    if (!$this->fieldData && !$this->url)
      throw new \ErrorException(' Vous devez definir fieldData ');
    $this->retrieveDatas();
    /**
     * --
     *
     * @var array $configuration
     */
    $configuration = [
      'destination' => [
        'plugin' => 'entity:' . $this->entityTypeId
      ],
      'source' => [
        'ids' => [
          'drupal_internal__id' => [
            'type' => 'integer'
          ]
        ],
        'plugin' => 'embedded_data',
        'data_rows' => []
      ],
      'process' => []
    ];
    /**
     * On ignore toujours l'existance des données pour le theme, afin de
     * sassurer que la page d'accuiel est definie.
     *
     * @var \Drupal\migrationwbh\Services\MigrationImportAutoConfigThemeEntity $ignoreExistantData
     */
    $this->ignoreExistantData = false;
    return $this->loopDatas($configuration);
  }

  /**
   *
   * {@inheritdoc}
   * @see \Drupal\migrationwbh\Services\MigrationImportAutoBase::buildDataRows()
   */
  public function buildDataRows(array $row, array &$data_rows) {
    $k = 0;
    $data_rows[$k] = $row['attributes'];
    // Get relationships datas
    foreach ($row['relationships'] as $fieldName => $value) {
      if (in_array($fieldName, $this->unGetRelationships) || empty($value['data']))
        continue;
      $this->getRelationShip($data_rows, $k, $fieldName, $value);
    }

    /**
     * On force ces champs à false pour éviter la regénération du theme
     */

    $source_default_langcode = \Drupal::config("wb_horizon_public.source_site_configs")->get("languages.default_langcode") ?? "fr";
    $config_factory = \Drupal::service('config.factory');
    $config = $config_factory->getEditable('system.site');
    $config->set('langcode', $source_default_langcode);
    $config->set('default_langcode', $source_default_langcode);
    $config->save();


    $data_rows[$k]["run_npm"] = false;
    $data_rows[$k]["force_regenerate_npm_files"] = false;
  }

  /**
   *
   * @param
   *        $configuration
   * @param array $process
   */
  public function buildMappingProcess($configuration, array &$process) {
    if (!empty($configuration['source']['data_rows'][0])) {
      foreach ($configuration['source']['data_rows'][0] as $fieldName => $value) {
        if ($fieldName == 'drupal_internal__id') {
          $process['id'] = $fieldName;
        } elseif (in_array($fieldName, $this->unMappingFields))
          continue;
        else
          $process[$fieldName] = $fieldName;
      }
    }
  }

  /**
   * Dans la mesure ou le contenu est renvoyé sur 1 ligne, (data.type au lieu de
   * data.0.type ).
   * On corrige cela.
   *
   * @return boolean
   */
  protected function validationDatas() {
    $this->performRawDatas();
    if (!empty($this->rawDatas['data'][0]) && !empty($this->rawDatas['data'][0]['attributes']['drupal_internal__id'])) {
      return true;
    } else {
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas
      ];
      throw DebugCode::exception(' AutoConfigThemeEntity: format de donnée non valide ', $dbg);
    }
  }

  protected function addToLogs($data, $key = null) {
    if ($this->entityTypeId)
      static::$logs[$this->entityTypeId][$key][] = $data;
  }

  protected function addDebugLogs($data, $key = null) {
    if ($this->entityTypeId)
      static::$logs['debug'][$this->entityTypeId][$key][] = $data;
  }
}
