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
   * Execute l'import via le batch API.
   *
   * @param array $config
   *        // un tabeleau à une seule dimantion.
   */
  protected function runBatch(array $config) {
    $external_domain = $config['external_domain'];
    $offset = 0;
    $limit = 5;
    $progress = 0;
    $batch = [
      'title' => "Debut de l'import sur $external_domain",
      'init_message' => "Import des données encours ...",
      'finished' => self::class . '::import_run_all',
      'operations' => [
        [
          self::class . '::_batch_import_paragraph',
          [
            $external_domain,
            $offset,
            $limit,
            $progress
          ]
        ],
        [
          self::class . '::_batch_import_blocks_contents',
          [
            $external_domain,
            $offset,
            $limit,
            $progress
          ]
        ],
        [
          self::class . '::_batch_import_block_content',
          [
            $external_domain,
            $offset,
            $limit,
            $progress
          ]
        ],
        [
          self::class . '::_batch_import_node',
          [
            $external_domain,
            $offset,
            $limit,
            $progress
          ]
        ],
        [
          self::class . '::_batch_import_commerce_product',
          [
            $external_domain,
            $offset,
            $limit,
            $progress
          ]
        ],
        [
          self::class . '::_batch_import_page_web',
          [
            $external_domain,
            $offset,
            $limit,
            $progress
          ]
        ],
        [
          self::class . '::_batch_import_config_theme_entity',
          [
            $external_domain,
            $offset,
            $limit,
            $progress
          ]
        ],
        [
          self::class . '::_batch_import_block',
          [
            $external_domain,
            $offset,
            $limit,
            $progress
          ]
        ]
      ]
    ];
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
  static public function _batch_import_blocks_contents($external_domain, $offset, $limit, $progress, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = $progress + 10;
      $context['sandbox']['current_id'] = 10;
      $context['sandbox']['max'] = $limit + 30;
    }
    $url = trim($external_domain, '/') . "/jsonapi/export-entities-wbhorizon/blocks_contents?page[offset]=$offset&page[limit]=$limit";
    
    /**
     *
     * @var \Drupal\migrationwbh\Services\MigrationImportAutoBlocksContents $MigrationImportEntities
     */
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.blocks_contents');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    $MigrationImportEntities->runImport();
    $MigrationImportEntities->activeIgnoreData();
    $numberImport = $MigrationImportEntities->getNumberItems();
    $context['message'] = 'Import blocks_contents, OK.';
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
    
    if ($numberImport == $limit) {
      $offset += $limit;
      $progress++;
      $batch = [
        'title' => "Import Blocks Contents from $external_domain",
        'init_message' => "Import de  $numberImport entites de type Blocks Contents; Etape : $progress",
        'percentage' => 35,
        'progress_message' => "Etape : $progress",
        'operations' => [
          [
            self::class . '::_batch_import_blocks_contents',
            [
              $external_domain,
              $offset,
              $limit,
              $progress
            ]
          ]
        ],
        'finished' => self::class . '::import_run_partiel'
      ]; // + $context
      batch_set($batch);
    }
  }
  
  /**
   *
   * @param string $external_domain
   * @param array $context
   */
  static public function _batch_import_page_web($external_domain, $offset, $limit, $progress, &$context) {
    // Import des pages web.
    $url = trim($external_domain, '/') . '/jsonapi/export-entities-wbhorizon/site_internet_entity';
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.site_internet_entity');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    $MigrationImportEntities->runImport();
    $numberImport = $MigrationImportEntities->getNumberItems();
    $context['message'] = 'Import des pages, OK.';
    $context['results'][] = "5 pages";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__SiteInternetEntity', true, "logs");
    }
    if ($numberImport == $limit) {
      $offset += $limit;
      $progress++;
      $batch = [
        'title' => "Import Pages from $external_domain",
        'init_message' => "Import de  $numberImport entites de type Pages(site_internet_entity); Etape : $progress",
        'percentage' => 35,
        'progress_message' => "Etape : $progress",
        'operations' => [
          [
            self::class . '::_batch_import_page_web',
            [
              $external_domain,
              $offset,
              $limit,
              $progress
            ]
          ]
        ],
        'finished' => self::class . '::import_run_partiel'
      ]; // + $context
      batch_set($batch);
    }
  }
  
  static public function _batch_import_block_content($external_domain, $offset, $limit, $progress, &$context) {
    /**
     * Import des block_content
     */
    $url = trim($external_domain, '/') . '/jsonapi/export-entities-wbhorizon/block_content';
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.block_content');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    $MigrationImportEntities->runImport();
    $MigrationImportEntities->activeIgnoreData();
    $numberImport = $MigrationImportEntities->getNumberItems();
    $context['message'] = 'Import block_content, OK.';
    $context['results'][] = "5 block_content";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__BlockContent', true, "logs");
    }
    if ($numberImport == $limit) {
      $offset += $limit;
      $progress++;
      $batch = [
        'title' => "Import block_content from $external_domain",
        'init_message' => "Import de  $numberImport entites de type block_content; Etape : $progress",
        'percentage' => 35,
        'progress_message' => "Etape : $progress",
        'operations' => [
          [
            self::class . '::_batch_import_block_content',
            [
              $external_domain,
              $offset,
              $limit,
              $progress
            ]
          ]
        ],
        'finished' => self::class . '::import_run_partiel'
      ]; // + $context
      batch_set($batch);
    }
  }
  
  static public function _batch_import_paragraph($external_domain, $offset, $limit, $progress, &$context) {
    /**
     * Import paragraph.
     */
    $url = trim($external_domain, '/') . '/jsonapi/export-entities-wbhorizon/paragraph';
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.paragraph');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    $MigrationImportEntities->activeIgnoreData();
    $MigrationImportEntities->runImport();
    $numberImport = $MigrationImportEntities->getNumberItems();
    $context['message'] = 'Import des paragraphes, OK.';
    $context['results'][] = "5 paragraph";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__Paragraph', true, "logs");
    }
    if ($numberImport == $limit) {
      $offset += $limit;
      $progress++;
      $batch = [
        'title' => "Import Paragraphes from $external_domain",
        'init_message' => "Import de  $numberImport entites de type Paragraphes; Etape : $progress",
        'percentage' => 35,
        'progress_message' => "Etape : $progress",
        'operations' => [
          [
            self::class . '::_batch_import_paragraph',
            [
              $external_domain,
              $offset,
              $limit,
              $progress
            ]
          ]
        ],
        'finished' => self::class . '::import_run_partiel'
      ]; // + $context
      batch_set($batch);
    }
  }
  
  static public function _batch_import_node($external_domain, $offset, $limit, $progress, &$context) {
    /**
     * Import paragraph.
     */
    $url = trim($external_domain, '/') . '/jsonapi/export-entities-wbhorizon/node';
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.node');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    $MigrationImportEntities->activeIgnoreData();
    $MigrationImportEntities->runImport();
    $numberImport = $MigrationImportEntities->getNumberItems();
    $context['message'] = 'Import nodes, OK.';
    $context['results'][] = "5 node";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__Paragraph', true, "logs");
    }
    if ($numberImport == $limit) {
      $offset += $limit;
      $progress++;
      $batch = [
        'title' => "Import node from $external_domain",
        'init_message' => "Import de  $numberImport entites de type node; Etape : $progress",
        'percentage' => 35,
        'progress_message' => "Etape : $progress",
        'operations' => [
          [
            self::class . '::_batch_import_node',
            [
              $external_domain,
              $offset,
              $limit,
              $progress
            ]
          ]
        ],
        'finished' => self::class . '::import_run_partiel'
      ]; // + $context
      batch_set($batch);
    }
  }
  
  static public function _batch_import_commerce_product($external_domain, $offset, $limit, $progress, &$context) {
    /**
     * Import des produits.
     */
    $url = trim($external_domain, '/') . '/jsonapi/export-entities-wbhorizon/commerce_product';
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.commerce_product');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    $MigrationImportEntities->runImport();
    $MigrationImportEntities->activeIgnoreData();
    $numberImport = $MigrationImportEntities->getNumberItems();
    $context['message'] = 'Import commerce_product, OK.';
    $context['results'][] = "5 commerce_product";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__commerce_product', true, "logs");
    }
    if ($numberImport == $limit) {
      $offset += $limit;
      $progress++;
      $batch = [
        'title' => "Import commerce_product from $external_domain",
        'init_message' => "Import de  $numberImport entites de type commerce_product; Etape : $progress",
        'percentage' => 35,
        'progress_message' => "Etape : $progress",
        'operations' => [
          [
            self::class . '::_batch_import_commerce_product',
            [
              $external_domain,
              $offset,
              $limit,
              $progress
            ]
          ]
        ],
        'finished' => self::class . '::import_run_partiel'
      ]; // + $context
      batch_set($batch);
    }
  }
  
  static public function _batch_import_config_theme_entity($external_domain, $offset, $limit, $progress, &$context) {
    /**
     * Import du theme.
     */
    $url = trim($external_domain, '/') . '/jsonapi/export-entities-wbhorizon/config_theme_entity';
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.config_theme_entity');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    $MigrationImportEntities->runImport();
    $context['message'] = "Import config_theme_entity, OK.";
    $context['results'][] = "5 config_theme_entity";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__ConfigThemeEntity', true, "logs");
    }
  }
  
  static public function _batch_import_block($external_domain, $offset, $limit, $progress, &$context) {
    /**
     * Import du theme.
     */
    $url = trim($external_domain, '/') . '/jsonapi/export-entities-wbhorizon/block';
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.block');
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    $MigrationImportEntities->runImport();
    $numberImport = $MigrationImportEntities->getNumberItems();
    $context['message'] = "Import block, OK.";
    $context['results'][] = "5 block";
    if (self::$debugMode) {
      $logs = $MigrationImportEntities->getLogs();
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__Blok', true, "logs");
    }
    if ($numberImport == $limit) {
      $offset += $limit;
      $progress++;
      $batch = [
        'title' => "Import block from $external_domain",
        'init_message' => "Import de  $numberImport entites de type block; Etape : $progress",
        'percentage' => 35,
        'progress_message' => "Etape : $progress",
        'operations' => [
          [
            self::class . '::_batch_import_block',
            [
              $external_domain,
              $offset,
              $limit,
              $progress
            ]
          ]
        ],
        'finished' => self::class . '::import_run_partiel'
      ]; // + $context
      batch_set($batch);
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
  
}