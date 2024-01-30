<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\DataParserPluginManager;
use Stephane888\Debug\ExceptionDebug as DebugCode;

class MigrationImportAutoTaxoTerm extends MigrationImportAutoBase {
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
   * disponible pour des entités avec bundles.
   */
  protected $bundle = null;
  
  /**
   * les champs qui serront ignorées dans le mapping.
   *
   * @var array
   */
  private $unMappingFields = [
    'drupal_internal__revision_id',
    'revision_created',
    'revision_log_message',
    'changed',
    'content_translation_created'
  ];
  private $unGetRelationships = [
    'vid',
    'revision_user',
    'content_translation_uid'
  ];
  private $SkypRunMigrate = false;
  
  function __construct(MigrationPluginManager $MigrationPluginManager, DataParserPluginManager $DataParserPluginManager, $entityTypeId, $bundle) {
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->DataParserPluginManager = $DataParserPluginManager;
    $this->entityTypeId = $entityTypeId;
    $this->bundle = $bundle;
  }
  
  public function runImport() {
    if (!$this->fieldData && !$this->url)
      throw DebugCode::exception(' Vous devez definir fieldData ', $this->fieldData);
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
          'drupal_internal__tid' => [
            'type' => 'integer'
          ]
        ],
        'plugin' => 'embedded_data',
        'data_rows' => []
      ],
      'process' => []
    ];
    return $this->loopDatas($configuration);
    //
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\migrationwbh\Services\MigrationImportAutoBase::buildDataRows()
   */
  public function buildDataRows(array $row, array &$data_rows) {
    $k = 0;
    $data_rows[$k] = $row['attributes'];
    $this->getLayoutBuilderField($data_rows[$k]);
    // Set vid
    $data_rows[$k]['vid'] = $row['relationships']['vid']['data']['meta']["drupal_internal__target_id"];
    $this->bundle = $data_rows[$k]['vid'];
    // Get relationships datas
    foreach ($row['relationships'] as $fieldName => $value) {
      if (in_array($fieldName, $this->unGetRelationships) || empty($value['data']))
        continue;
      // Les termes de premier niveau possede un parent donc l'id est "virtual".
      // On ne doit pas l'importer
      if ($fieldName == 'parent' && ((!empty($value['data'][0]['id']) && $value['data'][0]['id'] == "virtual") || (!empty($value['data']['id']) && $value['data']['id'] == "virtual")))
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
        if ($fieldName == 'drupal_internal__tid') {
          $process['tid'] = $fieldName;
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
    $this->performRawDatas();
    if (empty($this->rawDatas['data']))
      return true;
    if (!empty($this->rawDatas['data'][0]) && !empty($this->rawDatas['data'][0]['attributes']['drupal_internal__tid'])) {
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