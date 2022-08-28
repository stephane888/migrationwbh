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
   * @deprecated
   */
  protected $fieldData;
  protected static $configImport;

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
   * @deprecated
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
  /**
   *
   * @deprecated
   * @var array
   */
  protected $EntityValide = [
    'block_content',
    'paragraph',
    'node',
    'taxonomy_term'
  ];

  /**
   * les champs qui serront ignorées dans le mapping.
   *
   * @var array
   * @deprecated
   */
  protected $unMappingFields = [
    'block_content_type',
    'revision_user',
    'content_translation_uid',
    'paragraph_type',
    "node_type",
    "user",
    "revision_uid",
    "uid",
    "taxonomy_vocabulary",
    "vid",
    "field_localisation" //
  ];
  /**
   *
   * @deprecated
   */
  protected $SkypRun;

  /**
   * Pour pouvoir recuperer la configuration.
   *
   * @var array
   * @deprecated
   */
  protected $constructConf = [];
  private static $debugInfo = [];
  private static $subConf = [];
  private static $SubRawDatas = [];
  public $rollback = false;

  function __construct(MigrationPluginManager $MigrationPluginManager, DataParserPluginManager $DataParserPluginManager) {
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->DataParserPluginManager = $DataParserPluginManager;
  }

  /**
   *
   * @deprecated
   * @param array $data
   * @throws \ErrorException
   */
  public function setData(array $data) {
    if (empty($data['data']) || empty($data['links'])) {
      throw new \ErrorException('Données non valide');
      \Stephane888\Debug\debugLog::kintDebugDrupal($data, 'MigrationAutoImport-data-error-', true);
    }
    $this->getConfigImport();
    $this->SkypRun = false;
    $this->fieldData = $data;
  }

  /**
   *
   * @deprecated
   */
  protected function getConfigImport() {
    if (!static::$configImport) {
      static::$configImport = \Drupal::config('migrationwbh.import')->getRawData();
    }
  }

  /**
   * Le constructeur determine et initialise la class chargé de migrer l'entité.
   */
  public function runImport() {
    if (!$this->fieldData)
      throw new \ErrorException(' Vous devez definir fieldData ');
    if (!empty($this->fieldData['data']) && empty($this->fieldData['data'][0]))
      $this->fieldData['data'][0] = $this->fieldData['data'];
    // file type on data
    if (!empty($this->fieldData['data'][0])) {
      $row = $this->fieldData['data'][0];
      $type = explode("--", $row['type']);
      $this->entityTypeId = $type[0];
      // Entité avec bundle.
      if ($type[0] != $type[1]) {
        $this->bundle = $type[1];
        if ($this->entityTypeId == 'node') {
          $MigrationImportAutoNode = new MigrationImportAutoNode($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoNode->setData($this->fieldData);
          $MigrationImportAutoNode->setRollback($this->rollback);
          $results = $MigrationImportAutoNode->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoNode->getLogs(),
            'errors' => $MigrationImportAutoNode->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoNode->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoNode->getRawDatas();
          return $results;
        }
        elseif ($this->entityTypeId == 'taxonomy_term') {
          $MigrationImportAutoTaxoTerm = new MigrationImportAutoTaxoTerm($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoTaxoTerm->setData($this->fieldData);
          $MigrationImportAutoTaxoTerm->setRollback($this->rollback);
          $results = $MigrationImportAutoTaxoTerm->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoTaxoTerm->getLogs(),
            'errors' => $MigrationImportAutoTaxoTerm->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoTaxoTerm->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoTaxoTerm->getRawDatas();
          return $results;
        }
        elseif ($this->entityTypeId == 'paragraph') {
          $MigrationImportAutoParagraph = new MigrationImportAutoParagraph($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoParagraph->setData($this->fieldData);
          $MigrationImportAutoParagraph->setRollback($this->rollback);
          $results = $MigrationImportAutoParagraph->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoParagraph->getLogs(),
            'errors' => $MigrationImportAutoParagraph->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoParagraph->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoParagraph->getRawDatas();
          return $results;
        }
        elseif ($this->entityTypeId == 'block_content') {
          $MigrationImportAutoBlockContent = new MigrationImportAutoBlockContent($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoBlockContent->setData($this->fieldData);
          $MigrationImportAutoBlockContent->setRollback($this->rollback);
          $results = $MigrationImportAutoBlockContent->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoBlockContent->getLogs(),
            'errors' => $MigrationImportAutoBlockContent->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoBlockContent->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoBlockContent->getRawDatas();
          return $results;
        }
      }
      else {
        switch ($this->entityTypeId) {
          case 'file':
            $MigrationImportAutoFile = new MigrationImportAutoFile($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId);
            $MigrationImportAutoFile->setData($this->fieldData);
            $MigrationImportAutoFile->setRollback($this->rollback);
            $results = $MigrationImportAutoFile->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoFile->getLogs();
            return $results;
            break;
          case 'menu':
            $MigrationImportAutoMenu = new MigrationImportAutoMenu($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId);
            $MigrationImportAutoMenu->setData($this->fieldData);
            $MigrationImportAutoMenu->setRollback($this->rollback);
            $results = $MigrationImportAutoMenu->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoMenu->getLogs();
            return $results;
            break;
          default:
            ;
            break;
        }
      }
      // Menu--menu => qui doit importer les menu_link_content.
      // On doit importer les pathotos
      // Les produits
      // Les blocks
    }
    // Entité sans bundle.
    else {
    }
    return false;
  }

  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

  function testNodeImport($url) {
    $this->entityTypeId = 'node';
    $MigrationImportAutoNode = new MigrationImportAutoNode($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
    $MigrationImportAutoNode->setUrl($url);
    // $MigrationImportAutoNode->setRollback(true);
    // $MigrationImportAutoNode->setImport(false);
    $re = [
      'resul' => $MigrationImportAutoNode->runImport(),
      'conf' => [
        $MigrationImportAutoNode->getConfiguration(),
        'subConf' => static::$subConf
      ],
      'rawDatas' => [
        $MigrationImportAutoNode->getRawDatas(),
        'SubRawDatas' => static::$SubRawDatas
      ],
      'error' => $MigrationImportAutoNode->getLogs()
    ];
    debugLog::$max_depth = 15;
    debugLog::kintDebugDrupal($MigrationImportAutoNode->getLogs(), 'testNodeImport', true);
    return $re;
  }

  /**
   * On construit la configuration du plugin migration.
   * Le constructeur determine et initialise la class chargé de construire la
   * configuration.
   *
   * @deprecated
   */
  protected function constructPluginOLD() {
    $configuration = [];
    $this->retrieveDatas();
    //
    $this->getDatasInformation($configuration);
    $this->constructConf = $configuration;
    return $configuration;
  }

  protected function runMigrate(array $configuration) {
    if ($this->SkypRun)
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
      $migrateParagraph->setStatus(MigrationInterface::STATUS_IDLE);
      $fileName = (!empty($this->fieldData['data'][0])) ? $this->fieldData['data'][0]['type'] : $this->fieldData['data']['type'];
      $fileName .= '-----';
      $fileName .= (!empty($this->rawDatas['data'][0])) ? $this->rawDatas['data'][0]['type'] : $this->rawDatas['data']['type'];
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas,
        'configuration' => $configuration,
        'errors' => UtilityError::errorAll($e, 7),
        'Ids' => $migrateParagraph->getSourcePlugin()->getIds(),
        'Definition' => $migrateParagraph->getSourcePlugin()->getPluginDefinition()
      ];
      debugLog::kintDebugDrupal($dbg, $fileName . '--ERRORS--', true);
      return false;
    }
  }

  /**
   * Permet de recuperer et de remplir les informations de base telsque :
   * - entityTypeId ( node, block_content ...
   * ).
   * - bundle ( s'il existe ).
   * -
   *
   * @deprecated
   */
  protected function getDatasInformation(array &$configuration) {
    $rawDatas = $this->rawDatas['data'];
    // import 1 image
    if (!empty($rawDatas['type']) && $rawDatas['type'] == 'file--file') {
      $this->SkypRun = true;
      $this->entityTypeId = "file";
      $oldFile = File::load($rawDatas['attributes']['drupal_internal__fid']);
      if (!empty($oldFile)) {
        return;
      }
      $filesystem = \Drupal::service('file_system');
      $icon_file_destination = $rawDatas['attributes']['uri']['value'];
      $icon_upload_path = explode($rawDatas['attributes']['filename'], $rawDatas['attributes']['uri']['value']);
      $filesystem->prepareDirectory($icon_upload_path[0], FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      // Save the default icon file.
      $url = trim(static::$configImport['external_domain'], '/') . $rawDatas['attributes']['uri']['url'];
      $icon_file_uri = $filesystem->saveData(file_get_contents($url), $icon_file_destination);
      // Create the icon file entity.
      $icon_entity_values = [
        'uri' => $icon_file_uri,
        'uid' => \Drupal::currentUser()->id(),
        'uuid' => $rawDatas['id'],
        'status' => FILE_STATUS_PERMANENT,
        'fid' => $rawDatas['attributes']['drupal_internal__fid']
      ];
      $new_icon = File::create($icon_entity_values);
      $new_icon->save();
      // file usage
      if ($new_icon->id()) {
        /** @var \Drupal\file\FileUsage\DatabaseFileUsageBackend $file_usage */
        $file_usage = \Drupal::service('file.usage');
        // Add usage of the new icon file.
        $file_usage->add($new_icon, 'migrationwbh', 'migration_auto_import', $rawDatas['attributes']['drupal_internal__fid']);
      }
      return;
    }
    // Import multiple image;
    elseif (!empty($rawDatas[0]['type']) && $rawDatas[0]['type'] == 'file--file') {
      $this->SkypRun = true;
      $this->entityTypeId = "file";
      foreach ($rawDatas as $value) {
        $oldFile = File::load($value['attributes']['drupal_internal__fid']);
        if (!empty($oldFile)) {
          continue;
        }
        $filesystem = \Drupal::service('file_system');
        $icon_file_destination = $value['attributes']['uri']['value'];
        $icon_upload_path = explode($value['attributes']['filename'], $value['attributes']['uri']['value']);
        $filesystem->prepareDirectory($icon_upload_path[0], FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        // Save the default icon file.
        $url = trim(static::$configImport['external_domain'], '/') . $value['attributes']['uri']['url'];
        $icon_file_uri = $filesystem->saveData(file_get_contents($url), $icon_file_destination);
        // Create the icon file entity.
        $icon_entity_values = [
          'uri' => $icon_file_uri,
          'uid' => \Drupal::currentUser()->id(),
          'uuid' => $value['id'],
          'status' => FILE_STATUS_PERMANENT,
          'fid' => $value['attributes']['drupal_internal__fid']
        ];
        $new_icon = File::create($icon_entity_values);
        $new_icon->save();
        // file usage
        if ($new_icon->id()) {
          /** @var \Drupal\file\FileUsage\DatabaseFileUsageBackend $file_usage */
          $file_usage = \Drupal::service('file.usage');
          // Add usage of the new icon file.
          $file_usage->add($new_icon, 'migrationwbh', 'migration_auto_import', $value['attributes']['drupal_internal__fid']);
        }
        return;
      }
    }
    // cas des données simple:
    elseif (!empty($rawDatas['type'])) {
      $type = explode("--", $rawDatas['type']);
      if ($this->checkEntityValide($type[0])) {
        $this->entityTypeId = $type[0];
        $configuration['destination']['plugin'] = 'entity:' . $this->entityTypeId;
        if ($type[0] != $type[1]) {
          $this->bundle = $type[1];
          $configuration['destination']['default_bundle'] = $this->bundle;
        }
        $this->constructConf = $configuration;
        $this->setIdentificatorRow($configuration);
        $this->constructConf = $configuration;
        $this->getSourceFieldsAndProcessMappingSimple($configuration);
        $this->constructConf = $configuration;
      }
      else {
        $dbg = [
          'fieldData' => $this->fieldData,
          'rawData' => $this->rawDatas
        ];
        \Stephane888\Debug\debugLog::$max_depth = 10;
        \Stephane888\Debug\debugLog::kintDebugDrupal($dbg, 'MigrationAutoImport-entity-non-valide---', true);
      }
    }
    elseif (empty($rawDatas[0])) {
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
      $this->constructConf = $configuration;
      $this->setIdentificatorRow($configuration);
      $this->constructConf = $configuration;
      $this->getSourceFieldsAndProcessMapping($configuration);
      $this->constructConf = $configuration;
      if (!empty($this->fieldData['data'][0])) {
        $dbg = [
          'fieldData' => $this->fieldData,
          'rawData' => $this->rawDatas,
          'configuration' => $configuration
        ];
        \Stephane888\Debug\debugLog::$max_depth = 10;
        \Stephane888\Debug\debugLog::kintDebugDrupal($dbg, 'MigrationAutoImport-6--' . $this->entityTypeId . '--', true);
      }
    }
    else {
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas
      ];
      \Stephane888\Debug\debugLog::$max_depth = 10;
      \Stephane888\Debug\debugLog::kintDebugDrupal($dbg, 'MigrationAutoImport-entity-non-valide-', true);
    }
  }

  /**
   * Recupere les données (data_rows) et le mapping;
   *
   * @param array $firstRow
   * @param array $configuration
   * @deprecated
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
      //
      if (!empty($rawData['relationships'])) {
        foreach ($rawData['relationships'] as $fieldName => $val) {
          if (in_array($fieldName, $this->unMappingFields) || empty($val['data']))
            continue;

          try {
            // à la fin du process, on doit obtenir des données valide.
            // \Stephane888\Debug\debugLog::kintDebugDrupal($val,
            // 'MigrationAutoImport---' . $fieldName . '---', true);
            $relationship = new MigrationAutoImport($this->MigrationPluginManager, $this->DataParserPluginManager);
            $relationship->setData($val);
            /**
             * Apres traitement, on mettra à jour $this->rawDatas, fields
             */
            if ($relationship->runImport()) {
              if (!empty($val['data'][0]))
                foreach ($val['data'] as $valRelation) {
                  $data_rows[$k][$fieldName][] = $valRelation['meta']['drupal_internal__target_id'];
                }
              else {
                $data_rows[$k][$fieldName] = $val['data']['meta']['drupal_internal__target_id'];
              }
            }
          }
          catch (\Exception $e) {
            $dbg = [
              $e->getMessage(),
              'conf' => $relationship->getCurrentConf(),
              'datas' => $relationship->getCurrentData(),
              $e->getTrace()
            ];
            \Stephane888\Debug\debugLog::$max_depth = 10;
            \Stephane888\Debug\debugLog::kintDebugDrupal($dbg, 'sub-import-error-' . $this->entityTypeId . '--', true);
          }
        }
      }
    }
    // mapping;
    $this->getProcessMapping($data_rows[0], $processMapping);
    // add datas to config plugin.
    $configuration['source']['plugin'] = 'embedded_data';
    $configuration['source']['data_rows'] = $data_rows;
    $configuration['process'] = $processMapping;
  }

  /**
   * --
   *
   * @deprecated
   */
  public function getSourceFieldsAndProcessMappingSimple(array &$configuration) {
    $processMapping = [];
    $data_rows = [];
    // add type, ceci fonctionne pour la majorité des entités.
    if ($this->bundle)
      $this->rawDatas['data']['attributes']['type'] = $this->bundle;
    $data_rows = $this->rawDatas['data']['attributes'];
    //
    $this->getProcessMapping($data_rows, $processMapping);
    // add datas to config plugin.
    $configuration['source']['plugin'] = 'embedded_data';
    $configuration['source']['data_rows'] = [
      $data_rows
    ];
    $configuration['process'] = $processMapping;
  }

  /**
   *
   * @deprecated
   * @param array $data_rows
   * @param array $processMapping
   */
  public function getProcessMapping($data_rows, array &$processMapping) {
    $processMapping = [];
    // ;mapping;
    foreach ($data_rows as $fieldName => $v) {
      if ($fieldName == 'drupal_internal__nid') {
        $processMapping['nid'] = $fieldName;
      }
      elseif ($fieldName == 'drupal_internal__tid') {
        $processMapping['tid'] = $fieldName;
      }
      elseif ($fieldName == 'drupal_internal__id') {
        $processMapping['id'] = $fieldName;
      }
      elseif ($fieldName == 'drupal_internal__revision_id' || $fieldName == 'drupal_internal__vid')
        continue;
      else
        $processMapping[$fieldName] = $fieldName;
    }
  }

  /**
   * Champs permettant d'identifier une ligne.
   *
   * @param array $configuration
   * @deprecated
   */
  protected function setIdentificatorRow(array &$configuration) {
    if ($this->entityTypeId == 'node') {
      $configuration['source']['ids']['drupal_internal__nid'] = [
        'type' => 'integer'
      ];
    }
    elseif ($this->entityTypeId == 'taxonomy_term') {
      $configuration['source']['ids']['drupal_internal__tid'] = [
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
   * @deprecated
   */
  protected function checkEntityValide($entityTypeId) {
    return (in_array($entityTypeId, $this->EntityValide)) ? true : false;
  }

  /**
   * Permet de recuper les données à partir de l'url;
   *
   * @deprecated
   */
  protected function retrieveDatas() {
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
   *
   * @return array
   */
  public function getCurrentData() {
    return [
      'fieldData' => $this->fieldData,
      'rawDatas' => $this->rawDatas
    ];
  }

  /**
   *
   * @deprecated
   * @return array
   */
  public function getCurrentConf() {
    return $this->constructConf;
  }

  /**
   *
   * @deprecated
   * @throws \ErrorException
   * @return boolean
   */
  public function runImportOLD() {
    if (!$this->fieldData)
      throw new \ErrorException(' Vous devez definir fieldData ');
    $configuration = $this->constructPlugin();
    $dbg = [
      'fieldData' => $this->fieldData,
      'rawData' => $this->rawDatas,
      'configuration' => $configuration
    ];

    \Stephane888\Debug\debugLog::$max_depth = 10;
    $fileName = (!empty($this->fieldData['data'][0])) ? $this->fieldData['data'][0]['type'] : $this->fieldData['data']['type'];
    $fileName .= '-----';
    if ((!empty($this->rawDatas['data'][0]))) {
      $fileName .= $this->rawDatas['data'][0]['type'];
      if (!empty($this->rawDatas['data'][0]['attributes']['drupal_internal__fid']))
        $fileName .= '___' . $this->rawDatas['data'][0]['attributes']['drupal_internal__fid'] . '___';
    }
    else {
      $fileName .= $this->rawDatas['data']['type'];
      if (!empty($this->rawDatas['data']['attributes']['drupal_internal__fid']))
        $fileName .= '___' . $this->rawDatas['data']['attributes']['drupal_internal__fid'] . '___';
    }

    \Stephane888\Debug\debugLog::kintDebugDrupal($dbg, $fileName, true);
    return $this->runMigrate($configuration);
  }

}