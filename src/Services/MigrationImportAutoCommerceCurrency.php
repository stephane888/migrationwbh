<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\DataParserPluginManager;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Stephane888\Debug\Utility as UtilityError;
use Stephane888\Debug\debugLog;
use Stephane888\Debug\DebugCode;

class MigrationImportAutoCommerceCurrency extends MigrationImportAutoBase {

  /**
   * les champs qui serront ignorées dans le mapping.
   * * Les colonnes avec les dates posent probleme. ( il faudra pouvoir les
   * identifiers et appliqué une conversion ).
   *
   * @var array
   */
  private $unMappingFields = [];
  private $unGetRelationships = [];

  function __construct(MigrationPluginManager $MigrationPluginManager, DataParserPluginManager $DataParserPluginManager, $entityTypeId) {
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->DataParserPluginManager = $DataParserPluginManager;
    $this->entityTypeId = $entityTypeId;
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
        'plugin' => 'entity:' . $this->entityTypeId
      ],
      'source' => [
        'ids' => [
          'drupal_internal__currencyCode' => [
            'type' => 'string'
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
   * Permet de construire,
   *
   * @param array $configuration
   */
  protected function buildDataRows(array $row, array &$data_rows) {
    $k = 0;
    $data_rows[$k] = $row['attributes'];
  }

  /**
   *
   * @param
   *        $configuration
   * @param array $process
   */
  protected function buildMappingProcess($configuration, array &$process) {
    if (!empty($configuration['source']['data_rows'][0])) {
      foreach ($configuration['source']['data_rows'][0] as $fieldName => $value) {
        if ($fieldName == 'drupal_internal__currencyCode') {
          $process['currencyCode'] = $fieldName;
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
    if (!empty($this->rawDatas['data'][0]) && !empty($this->rawDatas['data'][0]['attributes']['drupal_internal__currencyCode'])) {
      return true;
    }
    else {
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas
      ];
      throw DebugCode::exception('validationDatas', $dbg);
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