<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\DataParserPluginManager;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Stephane888\Debug\debugLog;
use Stephane888\Debug\ExceptionDebug as DebugCode;
use PhpParser\Node\Stmt\Static_;
use Drupal\Core\Logger\LoggerChannel;

class MigrationImportAutoBlock extends MigrationImportAutoBase {
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
    "visibility"
  ];
  private $unGetRelationships = [];
  private $SkypRunMigrate = false;
  
  function __construct(MigrationPluginManager $MigrationPluginManager, DataParserPluginManager $DataParserPluginManager, LoggerChannel $LoggerChannel, $entityTypeId) {
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->DataParserPluginManager = $DataParserPluginManager;
    $this->entityTypeId = $entityTypeId;
    $this->LoggerChannel = $LoggerChannel;
    $this->field_id_type = 'string';
    $this->field_id = 'drupal_internal__id';
  }
  
  public function runImport() {
    if (!$this->fieldData && !$this->url)
      throw new \ErrorException(' Vous devez definir fieldData ');
    $this->retrieveDatas();
    $this->clearDatas();
    
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
            'type' => 'string'
          ]
        ],
        'plugin' => 'embedded_data',
        'data_rows' => []
      ],
      'process' => []
    ];
    $results = $this->loopDatas($configuration);
    return $results;
  }
  
  /**
   * Actuelement, il ya des problemes de configurations des blocs.
   * Les données importer contiennent des informations invalide.
   * I.e on a des blocs qui ont des dependance de theme X mais qui demande un
   * domaine Y.
   * ( va etre supprimer une foix que les blocs seront ok.
   */
  protected function clearDatas() {
    $newDatas = [];
    
    if (!empty($this->rawDatas['data']) && !empty($this->rawDatas['data'][0])) {
      foreach ($this->rawDatas['data'] as $value) {
        $attributes = $value['attributes'];
        $theme = $attributes['theme'];
        if (empty($attributes['visibility'])) {
          $newDatas[] = $value;
        }
        elseif (in_array($theme, $attributes['visibility']['domain']['domains'])) {
          $newDatas[] = $value;
        }
      }
      $this->rawDatas['data'] = $newDatas;
    }
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\migrationwbh\Services\MigrationImportAutoBase::buildDataRows()
   */
  public function buildDataRows(array $row, array &$data_rows) {
    $k = 0;
    $data_rows[$k] = $row['attributes'];
    // On importe le paragraph à partir de son id.
    if (!empty($row['attributes']["plugin"]) && $row['attributes']["plugin"] == 'entity_block:paragraph') {
      // On a un soucis pour le moment, c'est qu'on ne sais pas comment accedé à
      // une entité avec bundle sans le bundle.
      // objectif etait de pouvoir importer du contenus à partir des
      // configurations.
      // ( en attendant, on importe tous les paragraphes ).
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
        if ($fieldName == 'drupal_internal__id') {
          $process['id'] = $fieldName;
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
    if (!empty($this->rawDatas['data'][0]) && !empty($this->rawDatas['data'][0]['attributes']['drupal_internal__id'])) {
      return true;
    }
    else {
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas
      ];
      throw DebugCode::exception('Block: format de donnée non valide ', $dbg);
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