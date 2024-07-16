<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\DataParserPluginManager;
use Stephane888\Debug\ExceptionDebug as DebugCode;
use Drupal\Core\Logger\LoggerChannel;

/**
 * IL n'est pas definit comme service, car il est appeller automatiquement par
 * d'autres services.
 *
 * @author stephane
 *        
 */
class MigrationImportAutoEntities extends MigrationImportAutoBase {
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
   * Les champs qui serront ignorées dans le mapping.
   *
   * @var array
   */
  private $unMappingFields = [
    'created',
    "changed"
  ];
  private $unGetRelationships = [];
  private $SkypRunMigrate = false;

  function __construct(
    MigrationPluginManager $MigrationPluginManager,
    DataParserPluginManager $DataParserPluginManager,
    LoggerChannel $LoggerChannel,
    $entityTypeId
  ) {
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
          $this->getFieldId() => [
            'type' => $this->getFieldIdType()
          ]
        ],
        'plugin' => 'embedded_data',
        'data_rows' => []
      ],
      'process' => []
    ];
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
    if (!empty($row['relationships']))
      foreach ($row['relationships'] as $fieldName => $value) {
        if (in_array($fieldName, $this->unGetRelationships) || empty($value['data']))
          continue;
        $this->getRelationShip($data_rows, $k, $fieldName, $value);
      }
    // On formatte certains champs.
    if ($this->entityTypeId == 'commerce_promotion') {
      if ($data_rows[$k]["start_date"])
        $data_rows[$k]["start_date"] = $this->getValidDateString($data_rows[$k]["start_date"]);
      if ($data_rows[$k]["end_date"])
        $data_rows[$k]["end_date"] = $this->getValidDateString($data_rows[$k]["end_date"]);
    }
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
        if ($fieldName == $this->getFieldId()) {
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
    if (!empty($this->rawDatas['data'][0]) && !empty($this->rawDatas['data'][0]['attributes'][$this->getFieldId()])) {
      return true;
    } else {
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas
      ];
      \Stephane888\Debug\debugLog::symfonyDebug($dbg, 'validationDatas___' . $this->entityTypeId . '___', true);
      throw DebugCode::exception($this->entityTypeId . ' : format de donnée non valide ', $dbg);
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
