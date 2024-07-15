<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\DataParserPluginManager;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Stephane888\Debug\debugLog;
use Stephane888\Debug\ExceptionDebug as DebugCode;
use Drupal\Core\Logger\LoggerChannel;

class MigrationImportAutoCommerceStore extends MigrationImportAutoBase {
  
  /**
   * disponible pour des entités avec bundles.
   */
  protected $bundle = null;
  
  /**
   * les champs qui seront ignorées dans le mapping.
   * * Les colonnes avec les dates posent probleme. ( il faudra pouvoir les
   * identifiers et appliqué une conversion ).
   *
   * @var array
   */
  private $unMappingFields = [
    'revision_timestamp',
    'revision_log',
    'created',
    'changed'
  ];
  private $unGetRelationships = [
    "commerce_store_type",
    'revision_uid',
    'uid'
  ];
  
  function __construct(MigrationPluginManager $MigrationPluginManager, DataParserPluginManager $DataParserPluginManager, LoggerChannel $LoggerChannel, $entityTypeId, $bundle) {
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->DataParserPluginManager = $DataParserPluginManager;
    $this->entityTypeId = $entityTypeId;
    $this->bundle = $bundle;
    $this->LoggerChannel = $LoggerChannel;
  }
  
  public function runImport() {
    if (!$this->fieldData && !$this->url)
      throw new \ErrorException(' Vous devez definir fieldData ou une url ');
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
          'drupal_internal__store_id' => [
            'type' => 'integer'
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
    // Set type
    $data_rows[$k]['type'] = $row['relationships']['commerce_store_type']['data']['meta']["drupal_internal__target_id"];
    $this->bundle = $data_rows[$k]['type'];
    // Get relationships datas
    foreach ($row['relationships'] as $fieldName => $value) {
      if (in_array($fieldName, $this->unGetRelationships) || empty($value['data']))
        continue;
      $this->getRelationShip($data_rows, $k, $fieldName, $value);
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
        if ($fieldName == 'drupal_internal__store_id') {
          $process['store_id'] = $fieldName;
        }
        elseif (in_array($fieldName, $this->unMappingFields))
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
    if (empty($this->rawDatas['data']))
      return true;
    if (!empty($this->rawDatas['data'][0]) && !empty($this->rawDatas['data'][0]['attributes']['drupal_internal__store_id'])) {
      return true;
    }
    else {
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas
      ];
      throw DebugCode::exception(' CommerceStore: format de donnée non valide ', $dbg);
    }
  }
  
  protected function addToLogs($data, $key = null) {
    if ($this->entityTypeId && $this->bundle)
      static::$logs[$this->entityTypeId][$this->bundle][$key][] = $data;
    elseif ($this->entityTypeId)
      static::$logs[$this->entityTypeId][$key][] = $data;
  }
  
  protected function addDebugLogs($data, $key = null) {
    if ($this->entityTypeId && $this->bundle)
      static::$logs['debug'][$this->entityTypeId][$this->bundle][$key][] = $data;
    elseif ($this->entityTypeId)
      static::$logs['debug'][$this->entityTypeId][$key][] = $data;
  }
  
}