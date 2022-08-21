<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;

class MigrationImport {
  protected $definitions;
  protected $instanceMigrate;
  /**
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $MigrationPluginManager;

  function __construct(MigrationPluginManager $MigrationPluginManager) {
    $this->MigrationPluginManager = $MigrationPluginManager;
  }

  /**
   * --
   */
  public function listMigrate() {
    if (!$this->definitions)
      $this->definitions = $this->MigrationPluginManager->getDefinitions();
    return $this->definitions;
  }

  protected function createSpecificMigration() {
    $key = 'wbhorizon_paragraph_base';
    /**
     * Cela utilise le plugin par defaut.( ie la classe par defaut ).
     *
     * @var \Drupal\migrate\Plugin\Migration $migrateParagraph
     */
    $migrateParagraph = $this->MigrationPluginManager->createInstance($key);
    // à ce niveau on pourrait le surcharger.
    // dump($migrateParagraph->getSourcePlugin());
    // il faut une autre autres source et proccess.
    // Drupal\migrationwbh\Plugin\migrate\source\ParagraphSource
    /**
     *
     * @var \Drupal\migrationwbh\Plugin\migrate\source\ParagraphSource $sourcePlugin
     */
    $sourcePlugin = $migrateParagraph->getSourcePlugin();
    $sourcePlugin->fields();
    /**
     *
     * @var \Drupal\migrationwbh\Plugin\migrate_plus\data_parser\JsonApi $dataParse
     */
    $dataParse = $sourcePlugin->getDataParserPlugin();
    /**
     *
     * @var \Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http $DataFetcher
     */
    $DataFetcher = $dataParse->getDataFetcherPlugin();
    // $datas =
    // $DataFetcher->getResponseContent('http://test62.wb-horizon.kksa/jsonapi/user/1/content');

    // dump($datas);
  }

  public function listMigrateInstance() {
    $this->listMigrate();
    $this->createSpecificMigration();
    if (!$this->instanceMigrate)
      foreach ($this->definitions as $key => $value) {
        $migration = $this->MigrationPluginManager->createInstance($key);
        // update existing entity imported.
        $migration->getIdMap()->prepareUpdate();
        $executable = new MigrateExecutable($migration, new MigrateMessage());
        $this->instanceMigrate[$key] = $executable;
      }
    return $this->instanceMigrate;
  }

  function runImport() {
    $this->listMigrate();
    foreach ($this->definitions as $migration_id => $value) {
      $migration = $this->MigrationPluginManager->createInstance($migration_id);
      // update existing entity imported.
      $migration->getIdMap()->prepareUpdate();
      $executable = new MigrateExecutable($migration, new MigrateMessage());
      try {
        // Run the migration.
        $resul = $executable->import();
        dump($resul);
      }
      catch (\Exception $e) {
        $migration->setStatus(MigrationInterface::STATUS_IDLE);
      }
    }
  }

}