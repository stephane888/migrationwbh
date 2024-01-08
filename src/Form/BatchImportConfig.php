<?php

namespace Drupal\migrationwbh\Form;

use Stephane888\Debug\debugLog;

trait BatchImportConfig {
  
  /**
   * Permet de construire toutes les operations.
   *
   * @param array $batch
   */
  static function buildOperations(array &$batch, $external_domain, $limit) {
    $entities = [
      'paragraph',
      'blocks_contents',
      'block_content',
      'node',
      'commerce_product',
      'site_internet_entity'
    ];
    foreach ($entities as $entity) {
      self::_batch_import_entities_count($batch, $entity, $external_domain, $limit);
    }
  }
  
  static public function _batch_import_entities_count(&$batch, $entity, string $external_domain, int $limit) {
    /**
     * Import paragraph.
     */
    $url = trim($external_domain, '/') . '/export-entities-wbhorizon/countentities/' . $entity;
    /**
     *
     * @var \Drupal\migrationwbh\Services\MigrationImportAutoBlocksContents $MigrationImportEntities
     */
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.' . $entity);
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    $numbre = $MigrationImportEntities->CountAllData();
    if ($numbre > $limit) {
      $step = intdiv($numbre, $limit);
      $reste = $numbre % $limit;
      $offset = 0;
      for ($i = 1; $i <= $step; $i++) {
        $batch['operations'][] = [
          self::class . '::_batch_import_' . $entity,
          [
            $external_domain,
            $offset,
            $limit,
            $i
          ]
        ];
        $offset += $limit;
      }
      if ($reste) {
        $batch['operations'][] = [
          self::class . '::_batch_import_' . $entity,
          [
            $external_domain,
            $offset,
            $limit,
            $i++
          ]
        ];
        $offset += $limit;
      }
    }
  }
  
}