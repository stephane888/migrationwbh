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
use Stephane888\Debug\DebugCode;

class MigrationImportAutoFile extends MigrationImportAutoBase {
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
    'changed'
  ];
  private $unGetRelationships = [];
  private $SkypRunMigrate = false;
  protected static $configImport;

  function __construct(MigrationPluginManager $MigrationPluginManager, DataParserPluginManager $DataParserPluginManager, $entityTypeId) {
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->DataParserPluginManager = $DataParserPluginManager;
    $this->entityTypeId = $entityTypeId;
  }

  public function runImport() {
    if (!$this->fieldData)
      throw DebugCode::exception(' Vous devez definir fieldData ', $this->fieldData);
    $this->retrieveDatas();
    $this->getConfigImport();
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
          'drupal_internal__fid' => [
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
   * Permet de construire,
   *
   * @param array $configuration
   */
  protected function buildDataRows(array $row, array &$data_rows) {
    // On recupere le fichier :
    $file = File::load($row['attributes']['drupal_internal__fid']);
    if (!$file) {
      /**
       *
       * @var \Drupal\Core\File\FileSystem $filesystem
       */
      $filesystem = \Drupal::service('file_system');
      $icon_file_destination = $row['attributes']['uri']['value'];
      $icon_upload_path = explode($row['attributes']['filename'], $row['attributes']['uri']['value']);
      $filesystem->prepareDirectory($icon_upload_path[0], FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      // Save the file.
      $url = trim(static::$configImport['external_domain'], '/') . $row['attributes']['uri']['url'];
      $filesystem->saveData(file_get_contents($url), $icon_file_destination);
    }
    // on va creer l'entité
    $data_rows[0] = $row['attributes'];
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
        if ($fieldName == 'drupal_internal__fid') {
          $process['fid'] = $fieldName;
        }
        elseif (in_array($fieldName, $this->unMappingFields))
          continue;
        else
          $process[$fieldName] = $fieldName;
      }
    }
  }

  protected function getConfigImport() {
    if (!static::$configImport) {
      static::$configImport = \Drupal::config('migrationwbh.import')->getRawData();
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
    if (!empty($this->rawDatas['data'][0]) && !empty($this->rawDatas['data'][0]['attributes']['drupal_internal__fid'])) {
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