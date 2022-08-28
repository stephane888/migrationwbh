<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Stephane888\Debug\debugLog;
use Stephane888\Debug\Utility as UtilityError;
use Stephane888\Debug\DebugCode;
use Drupal\migrate\Plugin\MigrationInterface;

class MigrationImportAutoBase {
  private $SkypRunMigrate = false;
  private $configuration;
  /**
   * Permet de recuperer les données provenant de relation Ship.
   */
  protected $fieldData;

  /**
   * Permet de recuperer les données à partir d'une source.
   */
  protected $url;
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
   * @deprecated
   */
  protected $debugLog;
  protected $rollback = false;
  protected $import = true;

  /**
   * entityTypeId ( node, block_content ...
   * )
   */
  protected $entityTypeId = null;

  /**
   * Permet de suivre l'import et analysé son status.
   *
   * @var array
   */
  protected static $logs = [];

  public function setData(array $data) {
    if (empty($data['data']) || empty($data['links'])) {
      throw new \ErrorException('Données non valide');
      \Stephane888\Debug\debugLog::kintDebugDrupal($data, 'MigrationImportAutoNode-ERROR--setData--', true);
    }
    $this->fieldData = $data;
  }

  public function setUrl($url) {
    $this->url = $url;
  }

  protected function runMigrate(array $configuration) {
    $this->configuration = $configuration;
    if ($this->SkypRunMigrate)
      return true;
    $plugin_id = 'wbhorizon_entites_auto';
    if (!empty($this->entityTypeId))
      $plugin_id = $plugin_id . '_' . $this->entityTypeId;
    /**
     *
     * @var \Drupal\migrate\Plugin\Migration $migrate
     */
    $migrate = $this->MigrationPluginManager->createInstance($plugin_id, $configuration);
    $migrate->getIdMap()->prepareUpdate();
    $executable = new MigrateExecutable($migrate, new MigrateMessage());
    try {
      if ($this->rollback)
        $executable->rollback();
      // Run the migration.
      if ($this->import) {
        $status = $executable->import();
        if ($status !== 1) {
          $migrate->setStatus(MigrationInterface::STATUS_IDLE);
          throw DebugCode::exception('runMigrate error : ' . $status, $executable->message);
        }
      }
      return true;
    }
    catch (DebugCode $e) {
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas,
        'errors' => UtilityError::errorAll($e, 7),
        'error_value' => $e->getContentToDebug()
      ];
      // $this->debugLog['runMigrate'][] = $dbg;
      $this->addDebugLogs($dbg, 'runMigrate');
      return false;
    }
    catch (\Exception $e) {
      $migrate->setStatus(MigrationInterface::STATUS_IDLE);
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas,
        'configuration' => $configuration,
        'errors' => UtilityError::errorAll($e, 7)
      ];
      // $this->debugLog['runMigrate'][] = $dbg;
      $this->addDebugLogs($dbg, 'runMigrate');
      return false;
    }
  }

  /**
   * Les resultats d'une requetes peuvent avoir des contenus de types
   * differents.
   */
  protected function loopDatas($configuration) {
    $confRow = [];
    $results = [];
    $this->validationDatas();
    foreach ($this->rawDatas['data'] as $k => $row) {
      $confRow[$k] = $configuration;
      $this->buildDataRows($row, $confRow[$k]['source']['data_rows']);
      $this->buildMappingProcess($confRow[$k], $confRow[$k]['process']);
      $entityId = $k;
      // Get id contenu.
      $idKey = array_key_first($configuration['source']['ids']);
      if (!empty($row['attributes'][$idKey]))
        $entityId = $row['attributes'][$idKey];
      $results[$entityId] = $this->runMigrate($confRow[$k]);
      $dbg = [
        'conf' => $confRow[$k],
        'result' => $results[$entityId]
      ];
      $this->addToLogs($dbg, $entityId);
    }
    //
    return $results;
  }

  /**
   * Permet de recuper les données à partir de l'url;
   */
  protected function retrieveDatas() {
    if (!empty($this->fieldData))
      $url = $this->fieldData['links']['related']['href'];
    else
      $url = $this->url;
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

  protected function performRawDatas() {
    if (!empty($this->rawDatas['data']) && empty($this->rawDatas['data'][0])) {
      $temp = $this->rawDatas['data'];
      unset($this->rawDatas['data']);
      $this->rawDatas['data'][0] = $temp;
    }
  }

  /**
   * Base de validation.
   */
  protected function validationDatas() {
    //
  }

  protected function getRelationShip(array &$data_rows, $k, $fieldName, $value) {
    try {
      $MigrationAutoImport = new MigrationAutoImport($this->MigrationPluginManager, $this->DataParserPluginManager);
      $MigrationAutoImport->setData($value);
      $MigrationAutoImport->rollback = $this->rollback;
      if ($result = $MigrationAutoImport->runImport()) {
        // Si on une image, on essaye de recuperer le titre et l'alt.
        // if ($MigrationAutoImport->getEntityTypeId() == 'file') {
        if (!empty($value['data'][0])) {
          foreach ($value['data'] as $subValue) {
            if (!empty($result[$subValue['meta']["drupal_internal__target_id"]])) {
              $subValue['meta']["target_id"] = $subValue['meta']["drupal_internal__target_id"];
              unset($subValue['meta']["drupal_internal__target_id"]);
              $data_rows[$k][$fieldName][] = $subValue['meta'];
            }
          }
        }
        else {
          $value['data']['meta']["target_id"] = $value['data']['meta']["drupal_internal__target_id"];
          unset($value['data']['meta']["drupal_internal__target_id"]);
          $data_rows[$k][$fieldName] = $value['data']['meta'];
        }
        // }
        // La recuperation des informations par defaut
        // else {
        // if (!empty($value['data'][0])) {
        // foreach ($value['data'] as $subValue) {
        // if (!empty($result[$subValue['meta']["drupal_internal__target_id"]]))
        // $data_rows[$k][$fieldName][] =
        // $subValue['meta']["drupal_internal__target_id"];
        // }
        // }
        // else
        // $data_rows[$k][$fieldName] =
        // $value['data']['meta']["drupal_internal__target_id"];
        // }
      }
    }
    catch (DebugCode $e) {
      $dbg = [
        'fieldName' => $fieldName,
        'value' => $value,
        'errors' => UtilityError::errorAll($e, 7),
        'error_value' => $e->getContentToDebug()
      ];
      $this->addToLogs($dbg, $fieldName);
    }
    catch (\Exception $e) {
      $dbg = [
        'value' => $value,
        'errors' => UtilityError::errorAll($e, 7)
      ];
      $this->addToLogs($dbg, $fieldName);
    }
  }

  public function getDebugLog() {
    return $this->debugLog;
  }

  public function getRawDatas() {
    return $this->rawDatas;
  }

  public function getConfiguration() {
    return $this->configuration;
  }

  public function getFieldData() {
    return $this->fieldData;
  }

  /**
   * Permet de regenerer le rendu.
   *
   * @param boolean $val
   */
  public function setRollback($val = true) {
    $this->rollback = $val;
  }

  /**
   *
   * @param boolean $val
   */
  public function setImport($val = true) {
    $this->import = $val;
  }

  protected function addToLogs($data, $key = null) {
    if ($key)
      static::$logs[$key][] = $data;
    else
      static::$logs[] = $data;
  }

  protected function addDebugLogs($data, $key = null) {
    if ($key)
      static::$logs['debug'][$key][] = $data;
    else
      static::$logs['debug'][] = $data;
  }

  public function getLogs() {
    return static::$logs;
  }

  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

}