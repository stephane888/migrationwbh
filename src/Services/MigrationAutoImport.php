<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\DataParserPluginManager;
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
    // File type on data
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
        //
        elseif ($this->entityTypeId == 'menu_link_content__') {
          $MigrationImportAutoMenuLinkContent = new MigrationImportAutoMenuLinkContent($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoMenuLinkContent->setData($this->fieldData);
          $MigrationImportAutoMenuLinkContent->setRollback($this->rollback);
          $results = $MigrationImportAutoMenuLinkContent->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoMenuLinkContent->getLogs(),
            'errors' => $MigrationImportAutoMenuLinkContent->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoMenuLinkContent->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoMenuLinkContent->getRawDatas();
          return $results;
        }
        elseif ($this->entityTypeId == 'site_internet_entity') {
          $MigrationImportAutoSiteInternetEntity = new MigrationImportAutoSiteInternetEntity($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoSiteInternetEntity->setData($this->fieldData);
          $MigrationImportAutoSiteInternetEntity->setRollback($this->rollback);
          $results = $MigrationImportAutoSiteInternetEntity->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoSiteInternetEntity->getLogs(),
            'errors' => $MigrationImportAutoSiteInternetEntity->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoSiteInternetEntity->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoSiteInternetEntity->getRawDatas();
          return $results;
        }
        elseif ($this->entityTypeId == 'commerce_product') {
          $MigrationImportAutoCommerceProduct = new MigrationImportAutoCommerceProduct($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoCommerceProduct->setData($this->fieldData);
          $MigrationImportAutoCommerceProduct->setRollback($this->rollback);
          $results = $MigrationImportAutoCommerceProduct->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoCommerceProduct->getLogs(),
            'errors' => $MigrationImportAutoCommerceProduct->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoCommerceProduct->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoCommerceProduct->getRawDatas();
          return $results;
        }
        elseif ($this->entityTypeId == 'commerce_product_variation') {
          $MigrationImportAutoCommerceProductVariation = new MigrationImportAutoCommerceProductVariation($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoCommerceProductVariation->setData($this->fieldData);
          $MigrationImportAutoCommerceProductVariation->setRollback($this->rollback);
          $results = $MigrationImportAutoCommerceProductVariation->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoCommerceProductVariation->getLogs(),
            'errors' => $MigrationImportAutoCommerceProductVariation->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoCommerceProductVariation->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoCommerceProductVariation->getRawDatas();
          return $results;
        }
        elseif ($this->entityTypeId == 'commerce_store') {
          $MigrationImportAutoCommerceStore = new MigrationImportAutoCommerceStore($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoCommerceStore->setData($this->fieldData);
          $MigrationImportAutoCommerceStore->setRollback($this->rollback);
          $results = $MigrationImportAutoCommerceStore->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoCommerceStore->getLogs(),
            'errors' => $MigrationImportAutoCommerceStore->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoCommerceStore->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoCommerceStore->getRawDatas();
          return $results;
        }
      }
      // Ceci inclut egalements les configurations.
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
          case 'block':
            $MigrationImportAutoBlock = new MigrationImportAutoBlock($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId);
            $MigrationImportAutoBlock->setData($this->fieldData);
            $MigrationImportAutoBlock->setRollback($this->rollback);
            $results = $MigrationImportAutoBlock->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoBlock->getLogs();
            return $results;
          case 'commerce_currency':
            $MigrationImportAutoCommerceCurrency = new MigrationImportAutoCommerceCurrency($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId);
            $MigrationImportAutoCommerceCurrency->setData($this->fieldData);
            $MigrationImportAutoCommerceCurrency->setRollback($this->rollback);
            $results = $MigrationImportAutoCommerceCurrency->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoCommerceCurrency->getLogs();
            return $results;
          case 'config_theme_entity':
            $MigrationImportAutoConfigThemeEntity = new MigrationImportAutoConfigThemeEntity($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId);
            $MigrationImportAutoConfigThemeEntity->setData($this->fieldData);
            $MigrationImportAutoConfigThemeEntity->setRollback($this->rollback);
            $results = $MigrationImportAutoConfigThemeEntity->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoConfigThemeEntity->getLogs();
            return $results;
            break;
          default:
            //
            break;
        }
      }
      // On doit importer les pathutos
      // Les produits
    }
    // Entité sans bundle.
    else {
    }
    return false;
  }

  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

  /**
   * Utiliser pour model.
   *
   * @param string $url
   * @return NULL[]|array[][]|NULL[][]|boolean[][]
   */
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

  /**
   * Utiliser pour model.
   *
   * @param string $url
   * @return NULL[]|array[][]|NULL[][]|boolean[][]
   */
  function testSiteInternetEntityImport($url) {
    $this->entityTypeId = 'site_internet_entity';
    $MigrationImport = new MigrationImportAutoSiteInternetEntity($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
    $MigrationImport->setUrl($url);
    // $MigrationImportAutoNode->setRollback(true);
    // $MigrationImportAutoNode->setImport(false);
    $re = [
      'resul' => $MigrationImport->runImport(),
      'conf' => [
        $MigrationImport->getConfiguration(),
        'subConf' => static::$subConf
      ],
      'rawDatas' => [
        $MigrationImport->getRawDatas(),
        'SubRawDatas' => static::$SubRawDatas
      ],
      'error' => $MigrationImport->getLogs()
    ];
    debugLog::$max_depth = 15;
    debugLog::kintDebugDrupal($MigrationImport->getLogs(), 'testSiteInternetEntityImport', true);
    return [
      $re
    ];
  }

}