<?php

namespace Drupal\migrationwbh\Form;

use Stephane888\Debug\debugLog;

trait BatchImport {
  /**
   * Contient les plugin charger
   *
   * @var array
   */
  protected static $CustomPluginMigrate = [];
  /**
   *
   * @var integer
   */
  protected static $limitPagination = 10;
  protected static $debugMode = false;
  
  /**
   * Permet de forcer les contenus à etre re-importer.
   *
   * @var boolean
   */
  protected static $IgnoreDataReImport = false;
  protected static $keySettings = 'migrationwbh.import';
  
  /**
   * Execute l'import via le batch API.
   * Pour que l'utilitair bash fonctionne bien, il faut connaitre toutes les
   * etapes à l'avance.
   *
   * @param array $config
   *        // un tabeleau à une seule dimantion.
   */
  protected function runBatch(array $config) {
    $external_domain = $config['external_domain'];
    $offset = 0;
    $numberToImport = 0;
    
    $limit = !empty($config['number_import']) ? (int) $config['number_import'] : 3;
    $batch = [
      'title' => "Debut de l'import sur $external_domain",
      'init_message' => "Import des données encours ...",
      'finished' => self::class . '::import_run_all',
      'operations' => []
    ];
    self::buildOperations($batch, $external_domain, $limit);
    $batch['operations'][] = [
      self::class . '::_batch_import_config_theme_entity',
      [
        $external_domain,
        $offset,
        $limit,
        $numberToImport
      ]
    ];
    $batch['operations'][] = [
      self::class . '::_batch_import_block',
      [
        $external_domain,
        $offset,
        $limit,
        $numberToImport
      ]
    ];
    $batch['operations'][] = [
      self::class . '::_batch_import_block',
      [
        $external_domain,
        $offset,
        $limit,
        $numberToImport
      ]
    ];
    // dd($batch);
    batch_set($batch);
  }
  
  static public function import_run_all($success, $results, $operations) {
    if ($success)
      \Drupal::messenger()->addStatus("Run import all datas is OK");
    else
      \Drupal::messenger()->addError("Run import all datas with error");
  }
  
  static public function import_run_partiel($success, $results, $operations) {
    if ($success)
      \Drupal::messenger()->addStatus("Run import partiel OK");
    else
      \Drupal::messenger()->addError("Run import partiel with error");
  }
  
  /**
   *
   * @param string $external_domain
   * @param array $context
   */
  static public function _batch_import_blocks_contents($external_domain, $offset, $limit, $numberToImport, &$context) {
    self::getConfig();
    $url = trim($external_domain, '/') . "/jsonapi/export-entities-wbhorizon/blocks_contents?page[offset]=$offset&page[limit]=$limit";
    
    /**
     *
     * @var \Drupal\migrationwbh\Services\MigrationImportAutoBlocksContents $MigrationImportEntities
     */
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.blocks_contents');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    if (self::$IgnoreDataReImport)
      $MigrationImportEntities->activeIgnoreData();
    $MigrationImportEntities->runImport();
    
    $context['message'] = 'Import blocks_contents, ' . $MigrationImportEntities->getNumberItems() + $offset . '/' . $numberToImport;
    $context['results'][] = "5 pages";
    // $numberImport||$limit";
    // $context['results'][] = "$numberImport blocks_contents";
    // $context['finished'] = "Cest fini ooohhh";
    // $context['percentage'] = 20;
    // $context["label"] = "My label osd";
    
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__BlocksContents', true, "logs");
    }
  }
  
  /**
   *
   * @param string $external_domain
   * @param array $context
   */
  static public function _batch_import_site_internet_entity($external_domain, $offset, $limit, $numberToImport, &$context) {
    self::getConfig();
    // Import des pages web.
    $url = trim($external_domain, '/') . "/jsonapi/export-entities-wbhorizon/site_internet_entity?page[offset]=$offset&page[limit]=$limit";
    /**
     *
     * @var \Drupal\migrationwbh\Services\MigrationImportAutoSiteInternetEntity $MigrationImportEntities
     */
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.site_internet_entity');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    if (self::$IgnoreDataReImport)
      $MigrationImportEntities->activeIgnoreData();
    $MigrationImportEntities->runImport();
    $context['message'] = 'Import des pages, ' . $MigrationImportEntities->getNumberItems() + $offset . '/' . $numberToImport;
    $context['results'][] = "5 pages";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      dump($logs);
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__SiteInternetEntity', true, "logs");
    }
  }
  
  static public function _batch_import_block_content($external_domain, $offset, $limit, $numberToImport, &$context) {
    self::getConfig();
    /**
     * Import des block_content
     */
    $url = trim($external_domain, '/') . "/jsonapi/export-entities-wbhorizon/block_content?page[offset]=$offset&page[limit]=$limit";
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.block_content');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    if (self::$IgnoreDataReImport)
      $MigrationImportEntities->activeIgnoreData();
    $MigrationImportEntities->runImport();
    
    $context['message'] = 'Import block_content, ' . $MigrationImportEntities->getNumberItems() + $offset . '/' . $numberToImport;
    $context['results'][] = "5 block_content";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__BlockContent', true, "logs");
    }
  }
  
  static public function _batch_import_paragraph($external_domain, $offset, $limit, $numberToImport, &$context) {
    self::getConfig();
    /**
     * Import paragraph.
     */
    $url = trim($external_domain, '/') . "/jsonapi/export-entities-wbhorizon/paragraph?page[offset]=$offset&page[limit]=$limit";
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.paragraph');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    if (self::$IgnoreDataReImport)
      $MigrationImportEntities->activeIgnoreData();
    $MigrationImportEntities->runImport();
    $context['message'] = 'Import des paragraphes, ' . $MigrationImportEntities->getNumberItems() + $offset . '/' . $numberToImport;
    $context['results'][] = "5 paragraph";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__Paragraph', true, "logs");
    }
  }
  
  static public function _batch_import_node($external_domain, $offset, $limit, $numberToImport, &$context) {
    self::getConfig();
    /**
     * Import paragraph.
     */
    $url = trim($external_domain, '/') . "/jsonapi/export-entities-wbhorizon/node?page[offset]=$offset&page[limit]=$limit";
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.node');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    if (self::$IgnoreDataReImport)
      $MigrationImportEntities->activeIgnoreData();
    $MigrationImportEntities->runImport();
    $context['message'] = 'Import nodes, ' . $MigrationImportEntities->getNumberItems() + $offset . '/' . $numberToImport;
    $context['results'][] = "5 node";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__Paragraph', true, "logs");
    }
  }
  
  static public function _batch_import_commerce_product($external_domain, $offset, $limit, $numberToImport, &$context) {
    self::getConfig();
    /**
     * Import des produits.
     */
    $url = trim($external_domain, '/') . "/jsonapi/export-entities-wbhorizon/commerce_product?page[offset]=$offset&page[limit]=$limit";
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.commerce_product');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    if (self::$IgnoreDataReImport)
      $MigrationImportEntities->activeIgnoreData();
    $MigrationImportEntities->runImport();
    $context['message'] = 'Import commerce_product, ' . $MigrationImportEntities->getNumberItems() + $offset . '/' . $numberToImport;
    $context['results'][] = "5 commerce_product";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__commerce_product', true, "logs");
    }
  }
  
  static public function _batch_import_config_theme_entity($external_domain, $offset, $limit, $numberToImport, &$context) {
    self::getConfig();
    /**
     * Import du theme.
     */
    $url = trim($external_domain, '/') . "/jsonapi/export-entities-wbhorizon/config_theme_entity?page[offset]=$offset&page[limit]=$limit";
    /**
     *
     * @var \Drupal\migrationwbh\Services\MigrationImportAutoConfigThemeEntity $MigrationImportEntities
     */
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.config_theme_entity');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    if (self::$IgnoreDataReImport)
      $MigrationImportEntities->activeIgnoreData();
    $MigrationImportEntities->runImport();
    $context['message'] = "Import config_theme_entity, " . $MigrationImportEntities->getNumberItems() + $offset . '/' . $numberToImport;
    $context['results'][] = "5 config_theme_entity";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__ConfigThemeEntity', true, "logs");
    }
  }
  
  static public function _batch_import_menu_link_content($external_domain, $offset, $limit, $numberToImport, &$context) {
    self::getConfig();
    /**
     * Import du theme.
     */
    $url = trim($external_domain, '/') . "/jsonapi/export-entities-wbhorizon/menu_link_content?page[offset]=$offset&page[limit]=$limit";
    /**
     *
     * @var \Drupal\migrationwbh\Services\MigrationImportAutoConfigThemeEntity $MigrationImportEntities
     */
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.menu_link_content');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    if (self::$IgnoreDataReImport)
      $MigrationImportEntities->activeIgnoreData();
    $MigrationImportEntities->runImport();
    $context['message'] = "Import config_theme_entity, " . $MigrationImportEntities->getNumberItems() + $offset . '/' . $numberToImport;
    $context['results'][] = "5 config_theme_entity";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__ConfigThemeEntity', true, "logs");
    }
  }
  
  static public function _batch_import_block($external_domain, $offset, $limit, $numberToImport, &$context) {
    self::getConfig();
    /**
     * Specifiquement pour ce les blocks, on les importes tous directement.
     * ( en regle generale, on aurra moins de 10 entites de blocs ).
     */
    $url = trim($external_domain, '/') . "/jsonapi/export-entities-wbhorizon/block";
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.block');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    if (self::$IgnoreDataReImport)
      $MigrationImportEntities->activeIgnoreData();
    $MigrationImportEntities->runImport();
    $context['message'] = "Import block, " . $MigrationImportEntities->getNumberItems() + $offset . '/' . $numberToImport;
    $context['results'][] = "5 block";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__Blok', true, "logs");
    }
  }
  
  /**
   *
   * @param string $service_id
   * @return \Drupal\migrationwbh\Services\MigrationImportAutoBase
   */
  static public function loadPluginMigrate($service_id) {
    if (empty(self::$CustomPluginMigrate[$service_id])) {
      self::$CustomPluginMigrate[$service_id] = \Drupal::service($service_id);
    }
    return self::$CustomPluginMigrate[$service_id];
  }
  
  /**
   * Permet de recuperer les informations de configurations.
   * (Chaque requete est unique).
   */
  static protected function getConfig() {
    $config = \Drupal::config(self::$keySettings)->getRawData();
    if (!empty($config)) {
      self::$IgnoreDataReImport = !empty($config['force_re_import']) ? FALSE : TRUE;
    }
  }
  
}
