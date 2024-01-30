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
use GuzzleHttp\Client;

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
  
  function __construct(MigrationPluginManager $MigrationPluginManager, DataParserPluginManager $DataParserPluginManager, $entityTypeId) {
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->DataParserPluginManager = $DataParserPluginManager;
    $this->entityTypeId = $entityTypeId;
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
   *
   * {@inheritdoc}
   * @see \Drupal\migrationwbh\Services\MigrationImportAutoBase::buildDataRows()
   */
  public function buildDataRows(array $row, array &$data_rows) {
    // On recupere le fichier :
    $file = File::load($row['attributes']['drupal_internal__fid']);
    if (!$file) {
      /**
       *
       * @var \Drupal\Core\File\FileSystem $filesystem
       */
      $filesystem = \Drupal::service('file_system');
      $file_info = $this->getBasePathFromUri($row['attributes']['uri']['value']);
      if ($filesystem->prepareDirectory($file_info['base_path'], FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        // Save the file.
        $url = trim(static::$configImport['external_domain'], '/') . $row['attributes']['uri']['url'];
        $data = file_get_contents($url);
        if (!empty($data)) {
          $newUri = $filesystem->saveData($data, $row['attributes']['uri']['value']);
          $row['attributes']['uri']['value'] = $newUri;
          // On va creer l'entité.
          $data_rows[0] = $row['attributes'];
        }
      }
    }
  }
  
  /**
   * Le uri contient egalement le nom de l'image, l'idée est de separer les
   * deux.
   * RQ: dans row on a un filename, mais ce dernier est parfois different ce
   * celui au niveau de URI, et cela peut generer une erreur.
   */
  protected function getBasePathFromUri(string $uri) {
    $f1 = explode("://", $uri);
    $f2 = explode("/", $f1[1]);
    $basePath = $f1[0] . "://";
    $nbre = count($f2) - 1;
    if ($nbre) {
      for ($i = 0; $i < $nbre; $i++) {
        $basePath .= $f2[$i] . "/";
      }
      $filename = $f2[$nbre];
    }
    else {
      $basePath .= "migrations";
      $filename = $f2[0];
    }
    return [
      'base_path' => $basePath,
      'filename' => $filename
    ];
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