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
      'site_internet_entity',
      'menu_link_content'
    ];
    $k = 0;
    foreach ($entities as $entity) {
      self::_batch_import_entities_count($batch, $entity, $external_domain, $limit, $k);
      $k++;
    }
  }
  
  /**
   * Permet de construire les etapes d'import et permet aussi de compter le
   * nombre de données par entités.
   *
   * @param array $batch
   * @param string $entity_id
   * @param string $external_domain
   * @param int $limit
   */
  static public function _batch_import_entities_count(array &$batch, string $entity_id, string $external_domain, int $limit, int $k = 0) {
    /**
     * Import paragraph.
     */
    $url = trim($external_domain, '/') . '/export-entities-wbhorizon/countentities/' . $entity_id;
    /**
     *
     * @var \Drupal\migrationwbh\Services\MigrationImportAutoBlocksContents $MigrationImportEntities
     */
    $MigrationImportEntities = self::loadPluginMigrate('migrationwbh.migrate_auto_import.' . $entity_id);
    $MigrationImportEntities->setDebugMode(self::$debugMode);
    $MigrationImportEntities->setUrl($url);
    $numberToImport = $MigrationImportEntities->CountAllData();
    if ($numberToImport > $limit) {
      $step = intdiv($numberToImport, $limit);
      $reste = $numberToImport % $limit;
      $offset = 0;
      for ($i = 1; $i <= $step; $i++) {
        $batch['operations'][] = [
          self::class . '::_batch_import_' . $entity_id,
          [
            $external_domain,
            $offset,
            $limit,
            $numberToImport
          ]
        ];
        $offset += $limit;
      }
      // On met à jour le message d'initialisation.
      if ($k == 0) {
        $batch['init_message'] = "Initialisation, import de $limit entités ($entity_id)";
      }
      if ($reste) {
        $batch['operations'][] = [
          self::class . '::_batch_import_' . $entity_id,
          [
            $external_domain,
            $offset,
            $limit,
            $numberToImport
          ]
        ];
      }
    }
  }
  
  /**
   * Permet de tester la connexion au serveur.
   *
   * @param string $external_domain
   * @return boolean
   */
  static public function checkConnexionFrom($external_domain) {
    try {
      $batch = [];
      $entity = 'paragraph';
      $limit = 1;
      self::_batch_import_entities_count($batch, $entity, $external_domain, $limit);
      return true;
    }
    catch (\Exception $e) {
      \Drupal::logger('migrationwbh')->alert($e->getMessage());
    }
    return false;
  }
  
}