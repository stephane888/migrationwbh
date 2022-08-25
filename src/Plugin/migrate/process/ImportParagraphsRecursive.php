<?php

namespace Drupal\migrationwbh\Plugin\migrate\process;

use Drupal\migrate\Row;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Stephane888\Debug\debugLog;
use Drupal\Component\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Stephane888\Debug\Utility as UtilityError;
use Drupal\migrationwbh\Services\MigrationAutoImport;

/**
 * Ce plugin permet d'importer un paragraphe, y compris les types de contenus
 * ci-dessous qui lui sont attachés.
 * Liste de type de contenu chargé de maniere auto.
 * -
 * On doit commencer par verifier si l'id de l'element qu'on souhaite importer
 * existe,
 * - Si c'est le cas, on recupere son id.
 * - Sinon, on le crée.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "import_paragraphs_recursive"
 * )
 */
final class ImportParagraphsRecursive extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   *
   * @var \Drupal\migrationwbh\Services\MigrationAutoImport
   */
  protected $MigrationAutoImport;

  /**
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $MigrationPluginManager;

  function __construct($configuration, $plugin_id, $plugin_definition, MigrationPluginManager $MigrationPluginManager, MigrationAutoImport $MigrationAutoImport) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->MigrationAutoImport = $MigrationAutoImport;
  }

  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('plugin.manager.migration'), $container->get('migrationwbh.migrate_auto_import'));
  }

  /**
   *
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    //
    if (!empty($value)) {
      // \Stephane888\Debug\debugLog::$max_depth = 10;
      // \Stephane888\Debug\debugLog::kintDebugDrupal($value,
      // 'import_paragraphs_recursive--value--', true);
      $val = $this->importParagraph($value);
      return $val;
    }
    return null;
  }

  protected function importParagraph($value) {
    if (!empty($value['relationships'])) {
      foreach ($value['relationships'] as $fieldName => $val) {
        // on ignore certains
        if ($fieldName == 'paragraph_type' || empty($val['data']))
          continue;
        //
      }
    }
    $row = $this->getParagraphRow($value);
    // debugLog::kintDebugDrupal($row, 'getParagraphRow', true);
    if ($row) {
      $configurations = [
        'source' => [
          'data_rows' => [
            $row['attributes']
          ]
        ],
        'process' => $row['proccess_field']
      ];
      $key = 'wbhorizon_paragraph_embed';
      /**
       *
       * @var \Drupal\migrate\Plugin\Migration $migrateParagraph
       */
      $migrateParagraph = $this->MigrationPluginManager->createInstance($key, $configurations);
      if ($this->runParagraphImport($migrateParagraph)) {
        return $row['attributes']['drupal_internal__id'];
      }
    }
    return null;
  }

  protected function getParagraphRow(array $value) {
    if ($value['attributes']) {
      $type = explode("paragraph--", $value['type']);
      $value['attributes']['type'] = $type[1];
      $attributes = $value['attributes'];
      // à ces données basique, on ajoute les données relationShips.
      if (!empty($value['relationships']))
        foreach ($value['relationships'] as $fieldName => $val) {
          if ($fieldName == 'paragraph_type' || empty($val['data']))
            continue;
          $this->MigrationAutoImport->setData($val);
          // debugLog::kintDebugDrupal($val, $fieldName . '---', true);
          // si l'import des elements enfants s'effectuent bien ? on garde ses
          // id.
          if ($this->MigrationAutoImport->runImport()) {
            foreach ($val['data'] as $valRelation) {
              $attributes[$fieldName][] = $valRelation['meta']['drupal_internal__target_id'];
            }
          }
        }
      // process
      $proccess_field = [];
      foreach ($attributes as $k => $field) {
        if ($k == 'drupal_internal__id')
          $proccess_field['id'] = $k;
        elseif ($k == 'drupal_internal__revision_id')
          continue;
        else
          $proccess_field[$k] = $k;
      }

      return [
        'attributes' => $attributes,
        'proccess_field' => $proccess_field
      ];
    }
    return null;
  }

  /**
   * --
   *
   * @param Migration $migrateParagraph
   */
  protected function runParagraphImport(Migration $migrateParagraph) {
    $migrateParagraph->getIdMap()->prepareUpdate();
    $executable = new MigrateExecutable($migrateParagraph, new MigrateMessage());
    try {
      // Run the migration.
      $executable->import();
      return true;
    }
    catch (\Exception $e) {
      $migrateParagraph->setStatus(MigrationInterface::STATUS_IDLE);
      debugLog::kintDebugDrupal(UtilityError::errorAll($e), 'runParagraphImport--error--', true);
      return false;
    }
  }

}