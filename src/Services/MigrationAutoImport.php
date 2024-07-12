<?php

namespace Drupal\migrationwbh\Services;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\DataParserPluginManager;
use Stephane888\Debug\ExceptionExtractMessage;
use Stephane888\Debug\debugLog;
use Drupal\Core\Logger\LoggerChannel;

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

  /**
   * Domaine externe, ( Example : https://hakeuk.wb-horizon.com )
   *
   * @var string
   */
  protected $externalDomain = null;

  /**
   * The logger channel factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;
  /**
   * Liste d'entites qui vont etre ignorer si elles ne sont pas traiter.
   *
   * @var array
   */
  protected $ignoreContifEntities = [
    'domain',
    'user'
  ];

  /**
   * Liste d'entites qui vont etre ignorer si elles ne sont pas traiter.
   *
   * @var array
   */
  protected $ignoreContentEntities = [
    'user'
  ];

  /**
   * Permet d'ignorer l'import d'une entite si son id existe deja.
   *
   * @var boolean
   */
  public $ignoreExistantData = false;

  /**
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $LoggerChannel;

  /**
   *
   * @param MigrationPluginManager $MigrationPluginManager
   * @param DataParserPluginManager $DataParserPluginManager
   */
  function __construct(MigrationPluginManager $MigrationPluginManager, DataParserPluginManager $DataParserPluginManager, LoggerChannel $LoggerChannel) {
    $this->MigrationPluginManager = $MigrationPluginManager;
    $this->DataParserPluginManager = $DataParserPluginManager;
    $this->LoggerChannel = $LoggerChannel;
  }

  /**
   *
   * @param array $data
   * @throws \ErrorException
   */
  public function setData(array $data) {
    if (empty($data['data']) || empty($data['links'])) {
      throw new \ErrorException('Données non valide; EntityTypeId : ' . $this->entityTypeId . '; Bundle : ' . $this->bundle);
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

        /**
         * recentrage car du code se répète.
         * cela facilitera la maintenance du code
         */
        $migrationImportAutoEntity = match ($this->entityTypeId) {
          'node' => new MigrationImportAutoNode($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle),
          'paragraph' => new MigrationImportAutoParagraph($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle),
          'taxonomy_term' => new MigrationImportAutoTaxoTerm($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle),
          'block_content' => new MigrationImportAutoBlockContent($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle),
          'blocks_contents' => new MigrationImportAutoBlocksContents($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle),
          'menu_link_content' => new MigrationImportAutoMenuLinkContent($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle),
          'site_internet_entity' => new MigrationImportAutoSiteInternetEntity($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle),
          'commerce_product' => new MigrationImportAutoCommerceProduct($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle),
          'commerce_product_variation' => new MigrationImportAutoCommerceProductVariation($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle),
          'commerce_store' => new MigrationImportAutoCommerceStore($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle),
        };
        if ($this->entityTypeId == 'node') {
          $MigrationImportAutoNode = new MigrationImportAutoNode($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoNode->setIgnoreDatas($this->ignoreExistantData);
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
        } elseif ($this->entityTypeId == 'taxonomy_term') {
          $MigrationImportAutoTaxoTerm = new MigrationImportAutoTaxoTerm($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoTaxoTerm->setIgnoreDatas($this->ignoreExistantData);
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
        } elseif ($this->entityTypeId == 'paragraph') {
          $MigrationImportAutoParagraph = new MigrationImportAutoParagraph($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoParagraph->setIgnoreDatas($this->ignoreExistantData);
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
        } elseif ($this->entityTypeId == 'block_content') {
          $MigrationImportAutoBlockContent = new MigrationImportAutoBlockContent($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoBlockContent->setIgnoreDatas($this->ignoreExistantData);
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
        } elseif ($this->entityTypeId == 'blocks_contents') {
          $MigrationImportAutoBlocksContents = new MigrationImportAutoBlocksContents($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoBlocksContents->setIgnoreDatas($this->ignoreExistantData);
          $MigrationImportAutoBlocksContents->setData($this->fieldData);
          $MigrationImportAutoBlocksContents->setRollback($this->rollback);
          $results = $MigrationImportAutoBlocksContents->runImport();
          static::$debugInfo[$this->entityTypeId][] = [
            'logs' => $MigrationImportAutoBlocksContents->getLogs(),
            'errors' => $MigrationImportAutoBlocksContents->getDebugLog()
          ];
          static::$subConf[$this->entityTypeId][] = $MigrationImportAutoBlocksContents->getConfiguration();
          static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoBlocksContents->getRawDatas();
          return $results;
        }
        //
        elseif ($this->entityTypeId == 'menu_link_content') {
          $MigrationImportAutoMenuLinkContent = new MigrationImportAutoMenuLinkContent($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoMenuLinkContent->setIgnoreDatas($this->ignoreExistantData);
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
        } elseif ($this->entityTypeId == 'site_internet_entity') {
          $MigrationImportAutoSiteInternetEntity = new MigrationImportAutoSiteInternetEntity($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoSiteInternetEntity->setIgnoreDatas($this->ignoreExistantData);
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
        } elseif ($this->entityTypeId == 'commerce_product') {
          $MigrationImportAutoCommerceProduct = new MigrationImportAutoCommerceProduct($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoCommerceProduct->setIgnoreDatas($this->ignoreExistantData);
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
        } elseif ($this->entityTypeId == 'commerce_product_variation') {
          $MigrationImportAutoCommerceProductVariation = new MigrationImportAutoCommerceProductVariation($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoCommerceProductVariation->setIgnoreDatas($this->ignoreExistantData);
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
        } elseif ($this->entityTypeId == 'commerce_store') {
          $MigrationImportAutoCommerceStore = new MigrationImportAutoCommerceStore($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle);
          $MigrationImportAutoCommerceStore->setIgnoreDatas($this->ignoreExistantData);
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
        } else {
          /**
           * à ce stade, on va mettre en place un executant dynamique.
           * Mais en gardant un certains controls.
           */
          $entities = [
            'commerce_product_attribute_value' => [
              'id' => 'drupal_internal__attribute_value_id',
              'type' => 'integer'
            ],
            'booking_equipes' => [
              'id' => 'drupal_internal__id',
              'type' => 'integer'
            ]
          ];
          if (!empty($entities[$this->entityTypeId])) {
            $MigrationImportAutoEntitiesBundle = new MigrationImportAutoEntitiesBundle($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId, $this->bundle);
            $MigrationImportAutoEntitiesBundle->setFieldId($entities[$this->entityTypeId]['id']);
            $MigrationImportAutoEntitiesBundle->setFieldIdType($entities[$this->entityTypeId]['type']);
            $MigrationImportAutoEntitiesBundle->setIgnoreDatas($this->ignoreExistantData);
            $MigrationImportAutoEntitiesBundle->setData($this->fieldData);
            $MigrationImportAutoEntitiesBundle->setRollback($this->rollback);
            $results = $MigrationImportAutoEntitiesBundle->runImport();
            static::$debugInfo[$this->entityTypeId][] = [
              'logs' => $MigrationImportAutoEntitiesBundle->getLogs(),
              'errors' => $MigrationImportAutoEntitiesBundle->getDebugLog()
            ];
            static::$subConf[$this->entityTypeId][] = $MigrationImportAutoEntitiesBundle->getConfiguration();
            static::$SubRawDatas[$this->entityTypeId][] = $MigrationImportAutoEntitiesBundle->getRawDatas();
            return $results;
          } elseif (!in_array($this->entityTypeId, $this->ignoreContentEntities))
            $this->getLogger('migrationwbh')->warning(" Le type contentEntity (with bundle) : `" . $this->entityTypeId . "` n'est pas encore pris en compte. <br> " . json_encode($this->fieldData));
        }
      }
      // Ceci inclut egalements les configurations.
      else {
        switch ($this->entityTypeId) {
          case 'file':
            $MigrationImportAutoFile = new MigrationImportAutoFile($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId);
            $MigrationImportAutoFile->setIgnoreDatas($this->ignoreExistantData);
            $MigrationImportAutoFile->setData($this->fieldData);
            $MigrationImportAutoFile->setRollback($this->rollback);
            $results = $MigrationImportAutoFile->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoFile->getLogs();
            return $results;
            break;
          case 'menu':
            $MigrationImportAutoMenu = new MigrationImportAutoMenu($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId);
            $MigrationImportAutoMenu->setIgnoreDatas($this->ignoreExistantData);
            $MigrationImportAutoMenu->setData($this->fieldData);
            $MigrationImportAutoMenu->setRollback($this->rollback);
            $results = $MigrationImportAutoMenu->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoMenu->getLogs();
            return $results;
            break;
          case 'block':
            $MigrationImportAutoBlock = new MigrationImportAutoBlock($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId);
            $MigrationImportAutoBlock->setIgnoreDatas($this->ignoreExistantData);
            $MigrationImportAutoBlock->setData($this->fieldData);
            $MigrationImportAutoBlock->setRollback($this->rollback);
            $results = $MigrationImportAutoBlock->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoBlock->getLogs();
            return $results;
          case 'webform':
            $MigrationImportAutoWebform = new MigrationImportAutoWebform($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId);
            $MigrationImportAutoWebform->setIgnoreDatas($this->ignoreExistantData);
            $MigrationImportAutoWebform->setData($this->fieldData);
            $MigrationImportAutoWebform->setRollback($this->rollback);
            $results = $MigrationImportAutoWebform->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoWebform->getLogs();
            return $results;
          case 'commerce_currency':
            $MigrationImportAutoCommerceCurrency = new MigrationImportAutoCommerceCurrency($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId);
            $MigrationImportAutoCommerceCurrency->setIgnoreDatas($this->ignoreExistantData);
            $MigrationImportAutoCommerceCurrency->setData($this->fieldData);
            $MigrationImportAutoCommerceCurrency->setRollback($this->rollback);
            $results = $MigrationImportAutoCommerceCurrency->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoCommerceCurrency->getLogs();
            return $results;
          case 'config_theme_entity':
            $MigrationImportAutoConfigThemeEntity = new MigrationImportAutoConfigThemeEntity($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId);
            $MigrationImportAutoConfigThemeEntity->setIgnoreDatas($this->ignoreExistantData);
            $MigrationImportAutoConfigThemeEntity->setData($this->fieldData);
            $MigrationImportAutoConfigThemeEntity->setRollback($this->rollback);
            $results = $MigrationImportAutoConfigThemeEntity->runImport();
            static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoConfigThemeEntity->getLogs();
            return $results;
            break;
            /**
             * à ce stade, on va mettre en place un executant dynamique.
             * Mais en gardant un certains controls.
             */
          default:
            $entities = [
              'hbk_collection' => [
                'id' => 'drupal_internal__id',
                'type' => 'integer'
              ],
              'commerce_product_attribute' => [
                'id' => 'drupal_internal__id',
                'type' => 'integer'
              ],
              'commerce_promotion' => [
                'id' => 'drupal_internal__promotion_id',
                'type' => 'integer'
              ],
              'commerce_order_item' => [
                'id' => 'drupal_internal__order_item_id',
                'type' => 'integer'
              ],
              'commerce_order_type' => [
                'id' => 'drupal_internal__id',
                'type' => 'string'
              ]
            ];
            if (!empty($entities[$this->entityTypeId])) {
              $MigrationImportAutoEntities = new MigrationImportAutoEntities($this->MigrationPluginManager, $this->DataParserPluginManager, $this->LoggerChannel, $this->entityTypeId);
              $MigrationImportAutoEntities->setFieldId($entities[$this->entityTypeId]['id']);
              $MigrationImportAutoEntities->setFieldIdType($entities[$this->entityTypeId]['type']);
              $MigrationImportAutoEntities->setIgnoreDatas($this->ignoreExistantData);
              $MigrationImportAutoEntities->setData($this->fieldData);
              $MigrationImportAutoEntities->setRollback($this->rollback);
              $results = $MigrationImportAutoEntities->runImport();
              static::$debugInfo[$this->entityTypeId][] = $MigrationImportAutoEntities->getLogs();
              return $results;
            } elseif (!in_array($this->entityTypeId, $this->ignoreContifEntities)) {
              $this->getLogger('migrationwbh')->warning(" Le type configEntity ou contentEntity (without bundle) : `" . $this->entityTypeId . "` n'est pas encore pris en compte. <br> " . json_encode($this->fieldData));
            }
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

  /**
   * Utiliser pour model.
   *
   * @param string $url
   * @return NULL[]|array[][]|NULL[][]|boolean[][]
   */
  function testParagraphImport($url) {
    $this->entityTypeId = 'paragraph';
    $re = [];
    if ($this->getExternalDomain()) {
      $MigrationImport = new MigrationImportAutoParagraph($this->MigrationPluginManager, $this->DataParserPluginManager, $this->entityTypeId, $this->bundle);
      $MigrationImport->setUrl($this->externalDomain . '/' . trim($url, "/"));
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
      debugLog::kintDebugDrupal($MigrationImport->getLogs(), 'testParagraphImport', true);
    }
    return [
      $re
    ];
  }

  protected function getExternalDomain() {
    if (!$this->externalDomain) {
      $conf = \Drupal::config('migrationwbh.import')->getRawData();
      if (!empty($conf['external_domain'])) {
        $this->externalDomain = trim($conf['external_domain'], "/");
      } else {
        $this->messenger()->addWarning(' constants.url  not found .');
      }
    }
    return $this->externalDomain;
  }

  /**
   *
   * @param string $channel
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected function getLogger($channel) {
    if (!$this->loggerFactory) {
      $this->loggerFactory = \Drupal::service('logger.factory');
    }
    return $this->loggerFactory->get($channel);
  }
}
