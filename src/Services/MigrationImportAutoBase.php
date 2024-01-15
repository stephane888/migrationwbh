<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Stephane888\Debug\ExceptionExtractMessage;
use Stephane888\Debug\ExceptionDebug as DebugCode;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\layout_builder\Section;

class MigrationImportAutoBase implements MigrationImportAutoBaseInterface {
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
  /**
   * Permet d'active ou pas le mode debocage, le code s'arrete encas d'erreurs.
   *
   * @var boolean
   */
  protected $debugMode = false;
  /**
   *
   * @var boolean
   */
  protected $rollback = false;
  /**
   *
   * @var boolean
   */
  protected $import = true;
  /**
   * Permet d'ignorer l'import d'une entite si son id existe deja.
   *
   * @var boolean
   */
  protected $ignoreExistantData = false;
  
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
  //
  protected static $configImport;
  
  /**
   * id du domaine encours.
   */
  protected $domaineId;
  /**
   * Permet de determiner le nombre de donnée dans la requette.
   *
   * @var integer
   */
  protected $numberItems = 0;
  
  public function setData(array $data) {
    if (empty($data['data']) || empty($data['links'])) {
      throw new \ErrorException('Données non valide');
      \Stephane888\Debug\debugLog::kintDebugDrupal($data, 'MigrationImportAutoBase-ERROR--setData--', true);
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
    // //
    if ($this->entityTypeId == 'block') {
      $db = [
        $this->rollback,
        $this->import,
        'conf' => $this->configuration
      ];
    }
    // //
    try {
      /**
       *
       * @var \Drupal\migrate\Plugin\Migration $migrate
       */
      $migrate = $this->MigrationPluginManager->createInstance($plugin_id, $configuration);
      if (empty($migrate)) {
        throw DebugCode::exception(" Le plugin n'existe pas : " . $plugin_id, $plugin_id);
      }
      $migrate->getIdMap()->prepareUpdate();
      if ($this->entityTypeId == 'block') {
        $db['getMessages'] = $migrate->getIdMap()->getMessages();
      }
      $executable = new MigrateExecutable($migrate, new MigrateMessage());
      //
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
      if ($this->entityTypeId == 'block') {
        \Stephane888\Debug\debugLog::kintDebugDrupal($db, 'runMigrate', true);
      }
      return true;
    }
    catch (DebugCode $e) {
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas,
        'errors' => ExceptionExtractMessage::errorAll($e),
        'error_value' => $e->getContentToDebug()
      ];
      \Drupal::logger('migrationwbh')->debug($e->getMessage(), $dbg);
      if ($this->debugMode) {
        $this->addDebugLogs($dbg, 'runMigrate');
        dd($e->getMessage(), $dbg);
      }
      return false;
    }
    catch (\Exception $e) {
      $migrate->setStatus(MigrationInterface::STATUS_IDLE);
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas,
        'configuration' => $configuration,
        'errors' => ExceptionExtractMessage::errorAll($e)
      ];
      \Drupal::logger('migrationwbh')->alert($e->getMessage(), $dbg);
      if ($this->debugMode) {
        $this->addDebugLogs($dbg, 'runMigrate');
        dd($e->getMessage(), $dbg);
      }
      return false;
    }
    catch (\Error $e) {
      $migrate->setStatus(MigrationInterface::STATUS_IDLE);
      $dbg = [
        'fieldData' => $this->fieldData,
        'rawData' => $this->rawDatas,
        'configuration' => $configuration,
        'errors' => ExceptionExtractMessage::errorAll($e)
      ];
      \Drupal::logger('migrationwbh')->error($e->getMessage(), $dbg);
      if ($this->debugMode) {
        $this->addDebugLogs($dbg, 'runMigrate');
        dd($e->getMessage(), $dbg);
      }
      return false;
    }
  }
  
  /**
   * Permet de determiner le nombre données.
   */
  public function CountAllData() {
    if (!$this->fieldData && !$this->url)
      throw new \ErrorException(' Vous devez definir fieldData ');
    return $this->retrieveCountDatas();
  }
  
  /**
   * Les resultats d'une requetes peuvent avoir des contenus de types
   * differents.
   */
  protected function loopDatas($configuration) {
    $confRow = [];
    $results = [];
    $this->validationDatas();
    
    if (!empty($this->rawDatas['data']))
      foreach ($this->rawDatas['data'] as $k => $row) {
        $confRow[$k] = $configuration;
        $entityId = $k;
        // Get id contenu.
        $idKey = array_key_first($configuration['source']['ids']);
        if (!empty($row['attributes'][$idKey]))
          $entityId = $row['attributes'][$idKey];
        // ignore existant datas.
        if ($this->ignoreExistantData) {
          $entity = \Drupal::entityTypeManager()->getStorage($this->entityTypeId)->load($entityId);
          if ($entity) {
            $results[$entityId] = true;
            continue;
          }
        }
        /**
         * Les paths posent un probleme sur commerce.
         * On opte dans un premier temps de les OFFs.
         * Mais pour la suite, il faudra en tenir compte.
         */
        if (!empty($row['attributes']['path'])) {
          $row['attributes']['path'] = [];
        }
        $this->buildDataRows($row, $confRow[$k]['source']['data_rows']);
        $this->buildMappingProcess($confRow[$k], $confRow[$k]['process']);
        $results[$entityId] = $this->runMigrate($confRow[$k]);
      }
    return $results;
  }
  
  /**
   * recupere la configuration encours.
   */
  public function getCurrentDomaine() {
    if (!$this->domaineId) {
      /**
       *
       * @var \Drupal\domain\DomainNegotiator $domainNegotiator
       */
      $domainNegotiator = \Drupal::service("domain.negotiator");
      $domain = $domainNegotiator->getActiveDomain();
      if ($domain) {
        $this->domaineId = $domain->id();
      }
    }
    return $this->domaineId;
  }
  
  /**
   * Permet de recuperer les données à partir de l'url;
   */
  protected function retrieveCountDatas() {
    $this->getConfigImport();
    if (!empty($this->fieldData))
      $url = $this->fieldData['links']['related']['href'];
    else
      $url = $this->url;
    $conf = [
      'data_fetcher_plugin' => 'http',
      'urls' => [
        $url
      ],
      'authentication' => [
        'plugin' => 'basic',
        'username' => static::$configImport['username'],
        'password' => static::$configImport['password']
      ],
      'request_options' => [
        'timeout' => 300
      ]
    ];
    
    /**
     *
     * @var \Drupal\migrationwbh\Plugin\migrate_plus\data_parser\JsonApi $json_api
     */
    $json_api = $this->DataParserPluginManager->createInstance('json_api', $conf);
    return (int) $json_api->getResourseBrute($url);
  }
  
  /**
   * Permet de recuperer les données à partir de l'url;
   */
  protected function retrieveDatas() {
    $this->getConfigImport();
    if (!empty($this->fieldData))
      $url = $this->fieldData['links']['related']['href'];
    else
      $url = $this->url;
    $conf = [
      'data_fetcher_plugin' => 'http',
      'urls' => [
        $url
      ],
      'authentication' => [
        'plugin' => 'basic',
        'username' => static::$configImport['username'],
        'password' => static::$configImport['password']
      ]
    ];
    
    /**
     *
     * @var \Drupal\migrationwbh\Plugin\migrate_plus\data_parser\JsonApi $json_api
     */
    $json_api = $this->DataParserPluginManager->createInstance('json_api', $conf);
    $this->rawDatas = $json_api->getDataByExternalApi($url);
    if (!empty($this->rawDatas['data'][0])) {
      $this->numberItems = count($this->rawDatas['data']);
    }
    else
      $this->numberItems = 1;
  }
  
  /**
   * Lorque jsonapi renvoit 1 donnée, il ne le met pas dans [0].
   * Notre Logique attent toujours [0]
   */
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
  
  /**
   * Pour importer les contenus en relation.
   */
  protected function getRelationShip(array &$data_rows, $k, $fieldName, $value) {
    try {
      // if ($fieldName == 'field_reference_menu') {
      // dump($data_rows, $value);
      // }
      $MigrationAutoImport = new MigrationAutoImport($this->MigrationPluginManager, $this->DataParserPluginManager);
      $MigrationAutoImport->setData($value);
      $MigrationAutoImport->rollback = $this->rollback;
      $MigrationAutoImport->ignoreExistantData = $this->ignoreExistantData;
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
        'errors' => ExceptionExtractMessage::errorAll($e, 7),
        'error_value' => $e->getContentToDebug()
      ];
      \Drupal::logger('migrationwbh')->alert($e->getMessage(), $dbg);
      if ($this->debugMode) {
        $this->addToLogs($dbg, $fieldName);
        dd($dbg, 'getRelationShip', true);
      }
    }
    catch (\Exception $e) {
      $dbg = [
        'value' => $value,
        'errors' => ExceptionExtractMessage::errorAll($e, 7)
      ];
      \Drupal::logger('migrationwbh')->alert($e->getMessage(), $dbg);
      if ($this->debugMode) {
        $this->addToLogs($dbg, $fieldName);
        dd($dbg, 'getRelationShip', true);
      }
    }
  }
  
  protected function getConfigImport() {
    if (!static::$configImport) {
      static::$configImport = \Drupal::config('migrationwbh.import')->getRawData();
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
  
  public function activeIgnoreData() {
    $this->setIgnoreDatas(true);
  }
  
  public function setIgnoreDatas($value) {
    $this->ignoreExistantData = $value;
  }
  
  /**
   * Drupal pour le moment a opter de ne pas exposer les données de layouts
   * builder, car ce dernier utilise le format json et un ya quelques probleme
   * de logique ou conception.
   * Pour remedier à cela, on opte de fournir le nessaire pour son import en
   * attendant la reponse de drupal.
   *
   * @see https://www.drupal.org/project/drupal/issues/2942975
   *
   * @param array $entity
   */
  protected function getLayoutBuilderField(array &$entity) {
    if (!empty($entity['layout_builder__layout'])) {
      foreach ($entity['layout_builder__layout'] as $i => $section) {
        /**
         *
         * @var \Drupal\layout_builder\Section $section
         */
        $entity['layout_builder__layout'][$i] = Section::fromArray($section);
      }
    }
  }
  
  /**
   *
   * @return number
   */
  public function getNumberItems() {
    return $this->numberItems;
  }
  
  public function setDebugMode(bool $value) {
    $this->debugMode = $value;
  }
  
  /**
   *
   * {@inheritdoc}
   * @see \Drupal\migrationwbh\Services\MigrationImportAutoBaseInterface::buildDataRows()
   */
  public function buildDataRows(array $row, array &$data_rows) {
  }
  
}