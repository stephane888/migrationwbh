<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\DataParserPluginManager;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Stephane888\Debug\Utility as UtilityError;
use Stephane888\Debug\debugLog;

/**
 * NB: pour une URL donnée les données sont du meme type.
 *
 * @author stephane
 *
 */
class MigrationAutoImport {
  /**
   * Donnée provenant du champs de type entité.
   * ( d'une source migrate ).
   *
   * @var array
   */
  protected $fieldData;
  protected static $configImport;

  /**
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $MigrationPluginManager;

  /**
   *
   * @var DataParserPluginManager
   */
  protected $DataParserPluginManager;

  /**
   * entityTypeId ( node, block_content ...
   * )
   */
  protected $entityTypeId = null;

  /**
   * disponible pour des entités avec bundles.
   */
  protected $bundle = null;
  private static $debugInfo = [];
  private static $subConf = [];
  private static $SubRawDatas = [];
  public $rollback = false;

  function __construct(MigrationPluginManager $MigrationPluginManager, DataParserPluginManager $DataParserPluginManager) {
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->DataParserPluginManager = $DataParserPluginManager;
  }

  /**
   *
   * @param array $data
   * @throws \ErrorException
   */
  public function setData(array $data) {
    if (empty($data['data']) || empty($data['links'])) {
      throw new \ErrorException('Données non valide');
    }
    $this->fieldData = $data;
  }

  /**
   * Le constructeur determine et initialise la class chargé de migrer l'entité.
   */
  public function runImport() {
    if (!$this->fieldData)
      throw new \ErrorException(' Vous devez definir fieldData ');
    if (!empty($this->fieldData['data']) && empty($this->fieldData['data'][0]))
      $this->fieldData['data'][0] = $this->fieldData['data'];
    // file type on data
    if (!empty($this->fieldData['data'][0])) {
      $row = $this->fieldData['data'][0];
      $type = explode("--", $row['type']);
      $this->entityTypeId = $type[0];
      // Entité avec bundle.
      if ($type[0] != $type[1]) {
        $this->bundle = $type[1];
        if ($this->entityTypeId == 'node') {
          $MigrationImportAutoNode = new MigrationImportAutoNode($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoNode->setData($this->fieldData);
          $MigrationImportAutoNode->setRollback($this->rollback);
          $results = $MigrationImportAutoNode->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoNode->getLogs(),
            'errors' => $MigrationImportAutoNode->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoNode->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoNode->getRawDatas();
          return $results;
        }
        elseif ($this->entityTypeId == 'taxonomy_term') {
          $MigrationImportAutoTaxoTerm = new MigrationImportAutoTaxoTerm($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoTaxoTerm->setData($this->fieldData);
          $MigrationImportAutoTaxoTerm->setRollback($this->rollback);
          $results = $MigrationImportAutoTaxoTerm->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoTaxoTerm->getLogs(),
            'errors' => $MigrationImportAutoTaxoTerm->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoTaxoTerm->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoTaxoTerm->getRawDatas();
          return $results;
        }
        elseif ($this->entityTypeId == 'paragraph') {
          $MigrationImportAutoParagraph = new MigrationImportAutoParagraph($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoParagraph->setData($this->fieldData);
          $MigrationImportAutoParagraph->setRollback($this->rollback);
          $results = $MigrationImportAutoParagraph->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoParagraph->getLogs(),
            'errors' => $MigrationImportAutoParagraph->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoParagraph->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoParagraph->getRawDatas();
          return $results;
        }
        elseif ($this->entityTypeId == 'block_content') {
          $MigrationImportAutoBlockContent = new MigrationImportAutoBlockContent($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoBlockContent->setData($this->fieldData);
          $MigrationImportAutoBlockContent->setRollback($this->rollback);
          $results = $MigrationImportAutoBlockContent->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoBlockContent->getLogs(),
            'errors' => $MigrationImportAutoBlockContent->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoBlockContent->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoBlockContent->getRawDatas();
          return $results;
        }
      }
      else {
        switch ($this->entityTypeId) {
          case 'file':
            $MigrationImportAutoFile = new MigrationImportAutoFile($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId);
            $MigrationImportAutoFile->setData($this->fieldData);
            $MigrationImportAutoFile->setRollback($this->rollback);
            $results = $MigrationImportAutoFile->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoFile->getLogs();
            return $results;
            break;
          case 'menu':
            $MigrationImportAutoMenu = new MigrationImportAutoMenu($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId);
            $MigrationImportAutoMenu->setData($this->fieldData);
            $MigrationImportAutoMenu->setRollback($this->rollback);
            $results = $MigrationImportAutoMenu->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoMenu->getLogs();
            return $results;
            break;
          default:
            ;
            break;
        }
      }
      // Menu--menu => qui doit importer les menu_link_content.
      // On doit importer les pathotos
      // Les produits
      // Les blocks
    }
    // Entité sans bundle.
    else {
    }
    return false;
  }

  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

  function testNodeImport($url) {
    $this->entityTypeId = 'node';
    $MigrationImportAutoNode = new MigrationImportAutoNode($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
    $MigrationImportAutoNode->setUrl($url);
    // $MigrationImportAutoNode->setRollback(true);
    // $MigrationImportAutoNode->setImport(false);
    $re = [
      'resul' => $MigrationImportAutoNode->runImport(),
      'conf' => [
        $MigrationImportAutoNode->getConfiguration(),
        'subConf' => static::$subConf
      ],
      'rawDatas' => [
        $MigrationImportAutoNode->getRawDatas(),
        'SubRawDatas' => static::$SubRawDatas
      ],
      'error' => $MigrationImportAutoNode->getLogs()
    ];
    debugLog::$max_depth = 15;
    debugLog::kintDebugDrupal($MigrationImportAutoNode->getLogs(), 'testNodeImport', true);
    return $re;
  }

}