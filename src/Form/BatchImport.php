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
   * Execute l'import via le batch API.
   *
   * @param array $config
   *        // un tabeleau à une seule dimantion.
   */
  protected function runBatch(array $config) {
    $external_domain = $config['external_domain'];
    $batch = [
      'title' => t('Import datas from wb-horizon.com'),
      'operations' => [
        [
          self::class . '::_test_import',
          [
            $external_domain
          ]
        ],
        [
          self::class . '::_batch_import_page_web',
          [
            $external_domain
          ]
        ],
        [
          self::class . '::_batch_import_block_content',
          [
            $external_domain
          ]
        ],
        [
          self::class . '::_batch_import_paragraph',
          [
            $external_domain
          ]
        ],
        [
          self::class . '::_batch_import_commerce_product',
          [
            $external_domain
          ]
        ],
        [
          self::class . '::_batch_import_config_theme_entity',
          [
            $external_domain
          ]
        ]
      ],
      'finished' => self::class . '::import_run_all'
      // 'file' => $this->cl
    ];
    batch_set($batch);
  }
  
  static public function import_run_all($success, $results, $operations) {
    if ($success)
      \Drupal::messenger()->addStatus("Run import all datas is OK");
    else
      \Drupal::messenger()->addError("Run import all datas with error");
  }
  
  static public function _test_import($external_domain, &$context) {
    \Drupal::messenger()->addStatus("Run import all datas is OK");
    $context['message'] = 'Run import all datas is OK';
    $context['results'][] = "result 125";
  }
  
  /**
   *
   * @param string $external_domain
   * @param array $context
   */
  static public function _batch_import_page_web($external_domain, &$context) {
    // Import des pages web.
    $urlPageWeb = trim($external_domain, '/') . '/jsonapi/export/page-web';
    $MigrationImportAutoSiteInternetEntity = self::loadPluginMigrate('migrationwbh.migrate_auto_import.site_internet_entity');
    $MigrationImportAutoSiteInternetEntity->setUrl($urlPageWeb);
    $MigrationImportAutoSiteInternetEntity->runImport();
    $logs = $MigrationImportAutoSiteInternetEntity->getLogs();
    $context['message'] = 'Import des pages, OK.';
    $context['results'][] = "5 pages";
    if ($logs)
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__SiteInternetEntity', true, "logs");
  }
  
  static public function _batch_import_block_content($external_domain, &$context) {
    /**
     * Import des block_content
     */
    $urlBlockContents = trim($external_domain, '/') . '/jsonapi/export/block_content';
    $MigrationImportAutoBlockContent = self::loadPluginMigrate('migrationwbh.migrate_auto_import.block_content');
    $MigrationImportAutoBlockContent->setUrl($urlBlockContents);
    $MigrationImportAutoBlockContent->runImport();
    $logs = $MigrationImportAutoBlockContent->getLogs();
    $context['message'] = 'Import block_content, OK.';
    $context['results'][] = "5 block_content";
    if ($logs)
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__BlockContent', true, "logs");
  }
  
  static public function _batch_import_paragraph($external_domain, &$context) {
    /**
     * Import paragraph.
     */
    $urlParagraph = trim($external_domain, '/') . '/jsonapi/export/paragraph';
    $MigrationImportAutoParagraph = self::loadPluginMigrate('migrationwbh.migrate_auto_import.paragraph');
    $MigrationImportAutoParagraph->setUrl($urlParagraph);
    $MigrationImportAutoParagraph->activeIgnoreData();
    $MigrationImportAutoParagraph->runImport();
    $logs = $MigrationImportAutoParagraph->getLogs();
    $context['message'] = 'Import paragraph, OK.';
    $context['results'][] = "5 paragraph";
    if ($logs)
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__Paragraph', true, "logs");
  }
  
  static public function _batch_import_commerce_product($external_domain, &$context) {
    /**
     * Import des produits.
     */
    $urlCommerceProduct = trim($external_domain, '/') . '/jsonapi/export/commerce_product';
    $MigrationImportAutoParagraph = self::loadPluginMigrate('migrationwbh.migrate_auto_import.commerce_product');
    $MigrationImportAutoParagraph->setUrl($urlCommerceProduct);
    $MigrationImportAutoParagraph->runImport();
    $MigrationImportAutoParagraph->activeIgnoreData();
    $logs = $MigrationImportAutoParagraph->getLogs();
    $context['message'] = 'Import commerce_product, OK.';
    $context['results'][] = "5 commerce_product";
    if ($logs)
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__commerce_product', true, "logs");
  }
  
  static public function _batch_import_config_theme_entity($external_domain, &$context) {
    /**
     * Import du theme.
     */
    $urlConfigThemeEntity = trim($external_domain, '/') . '/jsonapi/export/template-theme';
    $MigrationImportAutoConfigThemeEntity = self::loadPluginMigrate('migrationwbh.migrate_auto_import.config_theme_entity');
    $MigrationImportAutoConfigThemeEntity->setUrl($urlConfigThemeEntity);
    $MigrationImportAutoConfigThemeEntity->runImport();
    $logs = $MigrationImportAutoConfigThemeEntity->getLogs();
    $context['message'] = 'Import config_theme_entity, OK.';
    $context['results'][] = "5 config_theme_entity";
    if ($logs)
      debugLog::kintDebugDrupal($logs, 'ImportNextSubmit__ConfigThemeEntity', true, "logs");
  }
  
  /**
   *
   * @param string $service_id
   * @return \Drupal\migrationwbh\Services\MigrationAutoImport
   */
  static public function loadPluginMigrate($service_id) {
    if (empty(self::$CustomPluginMigrate[$service_id])) {
      self::$CustomPluginMigrate[$service_id] = \Drupal::service($service_id);
    }
    return self::$CustomPluginMigrate[$service_id];
  }
  
}