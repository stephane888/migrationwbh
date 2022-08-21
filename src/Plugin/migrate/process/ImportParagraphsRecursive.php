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
use Drupal\migrate\Plugin\migrate\process\Get;

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

  function __construct($configuration, $plugin_id, $plugin_definition, MigrationPluginManager $MigrationPluginManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->MigrationPluginManager = $MigrationPluginManager;
  }

  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('plugin.manager.migration'));
  }

  /**
   *
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    //
    if (!empty($value)) {
      \Stephane888\Debug\debugLog::kintDebugDrupal($value, 'import_paragraphs_recursive--value--', true);
      $val = $this->importParagraph($value);
      return $val;
    }
    return null;
  }

  protected function importParagraph($value) {
    $row = $this->getParagraphRow($value);
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
      $proccess_field = [];
      foreach ($value['attributes'] as $k => $field) {
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
   * @deprecated
   */
  protected function importParagraphs($value) {
    // $k = rand(99, 999);
    $targets = [];
    foreach ($this->getParagraphRows($value) as $row) {
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
      /**
       *
       * @var \Drupal\migrationwbh\Plugin\migrate\source\ParagraphSource $sourcePlugin
       */
      $sourcePlugin = $migrateParagraph->getSourcePlugin();

      $ProcessPlugins = $migrateParagraph->getProcessPlugins();
      // $debug = [
      // $row,
      // $sourcePlugin->fields(),
      // $ProcessPlugins
      // ];
      // debugLog::$max_depth = 5;
      // debugLog::kintDebugDrupal($debug, 'paragraphs-to-imports-fields--' . $k
      // . '--', true);
      //
      if ($this->runParagraphImport($migrateParagraph)) {
        $targets[] = $row['attributes']['drupal_internal__id'];
      }
    }
    return $targets;
  }

  /**
   * On doit recuperer les données de champs et le type de paragraph.
   * On a un probleme dans la creation des champs. ( fields() ).
   * les fields() du plugin source sont construits à partir de la premiere
   * ligne, ce qui entraine une invalidation des champs pour certains loayouts.
   * On a deux options pour ressoudre le probleme :
   * - on ajoute ligne par ligne. ( on applique la solution 1 ).
   * - on regroupe les lignes par type.
   *
   * @deprecated
   */
  protected function getParagraphRows(array $value) {
    return $this->getParagraphRowsRowByRow($value);
  }

  /**
   * ...
   * on ajoute ligne par ligne. ( on applique la solution 1 ).
   *
   * @param array $value
   * @deprecated
   */
  protected function getParagraphRowsRowByRow(array $value) {
    $paragraphs = [];
    foreach ($value as $row) {
      $type = explode("paragraph--", $row['type']);
      if ($type[1]) {
        $row['attributes']['type'] = $type[1];
        $attributes = $row['attributes'];
        $proccess_field = [];
        foreach ($row['attributes'] as $k => $field) {
          if ($k == 'drupal_internal__id')
            $proccess_field['id'] = $k;
          elseif ($k == 'drupal_internal__revision_id')
            continue;
          else
            $proccess_field[$k] = $k;
        }
        $paragraphs[] = [
          'attributes' => $attributes,
          'proccess_field' => $proccess_field
        ];
      }
    }
    return $paragraphs;
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