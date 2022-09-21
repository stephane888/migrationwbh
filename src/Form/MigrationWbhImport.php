<?php

namespace Drupal\migrationwbh\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrationwbh\Services\MigrationImport;
use Drupal\migrationwbh\Services\MigrationImportAutoSiteInternetEntity;
use Drupal\migrationwbh\Services\MigrationImportAutoBlockContent;
use Drupal\migrationwbh\Services\MigrationImportAutoConfigThemeEntity;
use Drupal\migrationwbh\Services\MigrationImportAutoBlock;
use Drupal\layoutgenentitystyles\Services\LayoutgenentitystylesServices;
use Drupal\Core\Render\Renderer;
use Stephane888\Debug\debugLog;
use Drupal\generate_style_theme\Services\GenerateStyleTheme;
use Drupal\Core\Url;

/**
 * Configure migrationwbh settings for this site.
 */
class MigrationWbhImport extends ConfigFormBase {
  protected static $keySettings = 'migrationwbh.import';
  /**
   *
   * @var \Drupal\migrationwbh\Services\MigrationImport
   */
  protected $MigrationImport;

  /**
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $Renderer;

  /**
   *
   * @var integer
   */
  protected $maxStep = 4;
  /**
   *
   * @deprecated
   * @var array
   */
  protected static $pluginIds = [
    'wbhorizon_site_internet_entity_architecte' => 'wbhorizon_site_internet_entity_architecte',
    'wbhorizon_config_theme_entity' => 'wbhorizon_config_theme_entity',
    'wbhorizon_block_content_f_h' => 'wbhorizon_block_content_f_h',
    'wbhorizon_menu_link_content' => 'wbhorizon_menu_link_content',
    'wbhorizon_block' => 'wbhorizon_block',
    'wbhorizon_block_content_menu' => 'wbhorizon_block_content_menu',
    'wbhorizon_block_content_bp' => 'wbhorizon_block_content_bp'
  ];
  /**
   *
   * @var MigrationImportAutoSiteInternetEntity
   */
  protected $MigrationImportAutoSiteInternetEntity;

  /**
   *
   * @var MigrationImportAutoBlockContent
   */
  protected $MigrationImportAutoBlockContent;

  /**
   *
   * @var MigrationImportAutoConfigThemeEntity
   */
  protected $MigrationImportAutoConfigThemeEntity;

  /**
   *
   * @var MigrationImportAutoBlock
   */
  protected $MigrationImportAutoBlock;

  /**
   *
   * @var LayoutgenentitystylesServices
   */
  protected $LayoutgenentitystylesServices;

  /**
   *
   * @param ConfigFactoryInterface $config_factory
   * @param MigrationImport $MigrationImport
   */
  public function __construct(ConfigFactoryInterface $config_factory, MigrationImport $MigrationImport, Renderer $Renderer, MigrationImportAutoSiteInternetEntity $MigrationImportAutoSiteInternetEntity, MigrationImportAutoBlockContent $MigrationImportAutoBlockContent, MigrationImportAutoConfigThemeEntity $MigrationImportAutoConfigThemeEntity, MigrationImportAutoBlock $MigrationImportAutoBlock, LayoutgenentitystylesServices $LayoutgenentitystylesServices) {
    parent::__construct($config_factory);
    $this->MigrationImport = $MigrationImport;
    $this->Renderer = $Renderer;
    $this->MigrationImportAutoSiteInternetEntity = $MigrationImportAutoSiteInternetEntity;
    $this->MigrationImportAutoBlockContent = $MigrationImportAutoBlockContent;
    $this->MigrationImportAutoConfigThemeEntity = $MigrationImportAutoConfigThemeEntity;
    $this->MigrationImportAutoBlock = $MigrationImportAutoBlock;
    $this->LayoutgenentitystylesServices = $LayoutgenentitystylesServices;
  }

  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('migrationwbh.migrate_import'), $container->get('renderer'), $container->get('migrationwbh.migrate_auto_import.site_internet_entity'), $container->get('migrationwbh.migrate_auto_import.block_content'), $container->get('migrationwbh.migrate_auto_import.config_theme_entity'), $container->get('migrationwbh.migrate_auto_import.block'), $container->get('layoutgenentitystyles.add.style.theme'));
  }

  /**
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::$keySettings)->getRawData();
    // if ($form_state->has('step')) {
    if (!empty($_GET['step'])) {
      switch ($_GET['step']) {
        case 1:
          $this->formState1($form, $form_state, $config);
          break;
        case 2:
          $this->formState2($form, $form_state, $config);
          break;
        case 3:
          $this->formState3($form, $form_state, $config);
          break;
        case 4:
          $this->formState4($form, $form_state, $config);
          break;
        default:
          ;
          break;
      }
    }
    else {
      $form_state->set('step', 1);
      $this->formState1($form, $form_state, $config);
    }
    return $form;
  }

  protected function formState1(array &$form, FormStateInterface $form_state, $config) {
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Name'),
      '#default_value' => $config['username']
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('password'),
      '#default_value' => $config['password']
    ];
    $form['external_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Votre domaine sur wh-horizon.com'),
      '#default_value' => $config['external_domain'],
      '#description' => 'Votre domaine au format complet: example => http://mark-business.wh-hozizon.com '
    ];
    //
    $this->actionButtons($form, $form_state, "Suivant", "saveConfigNext");
  }

  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  protected function formState2(array &$form, FormStateInterface $form_state) {
    $this->getMigrationList($form);
    // $form = parent::buildForm($form, $form_state);
    // if (!empty($form['actions']['submit'])) {
    // $form['actions']['submit']['#value'] = 'save config';
    // }
    $this->disableUseDomainConfig();
    $this->actionButtons($form, $form_state, "Importer les contenus et passer à l'etape suivante", 'ImportNextSubmit');
  }

  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  protected function formState3(array &$form, FormStateInterface $form_state) {
    $this->createDomain();
    $this->assureThemeIsActive();
    $this->actionButtons($form, $form_state, "Importer les blocks et passer à l'etape finale", 'ImportNextSubmit2');
  }

  protected function formState4(array &$form, FormStateInterface $form_state, $config) {
    $form['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => 'Regenerer votre theme'
    ];
    //
    $this->actionButtons($form, $form_state);
  }

  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  protected function actionButtons(array &$form, FormStateInterface $form_state, $title_next = "Suivant", $submit_next = 'selectNextFieldSubmit', $title_preview = "Precedent") {
    $Step = !empty($_GET['step']) ? $_GET['step'] : 1;
    if ($Step > 1)
      $form['preview'] = [
        '#type' => 'submit',
        '#value' => $title_preview,
        '#button_type' => 'secondary',
        '#submit' => [
          [
            $this,
            'selectPreviewsFieldSubmit'
          ]
        ]
      ];
    if ($Step < $this->maxStep)
      $form['next'] = [
        '#type' => 'submit',
        '#value' => $title_next,
        '#button_type' => 'secondary',
        '#submit' => [
          [
            $this,
            $submit_next
          ]
        ]
      ];
    if ($Step >= $this->maxStep) {
      $form = parent::buildForm($form, $form_state);
      if (!empty($form['actions']['submit'])) {
        $form['actions']['submit']['#value'] = 'Terminer le processus';
      }
    }
  }

  /**
   *
   * @param array $form
   */
  protected function getMigrationList(&$form) {
    $form['list-migrations'] = [
      '#type' => 'fieldset',
      '#title' => 'Liste de données à importer'
    ];
    $form['list-migrations']['migrations'] = array(
      '#type' => 'table',
      '#header' => [
        'group' => 'Group',
        'idmig' => 'Migration ID',
        'status' => 'Status',
        'total' => 'Total',
        'imported' => 'Imported',
        'unprocessed' => 'Unprocessed',
        'last-imported' => 'Last Imported'
      ]
    );
    $pluginIds = static::$pluginIds;
    //
    $instances = $this->MigrationImport->listMigrateInstance($pluginIds);
    foreach ($this->MigrationImport->listMigrate($pluginIds) as $key => $migrateDefinition) {
      /** @var \Drupal\migrate\Plugin\Migration $Migration */
      $Migration = $instances[$key];
      $source = $Migration->getSourcePlugin();
      $idmig = [
        [
          '#type' => 'html_tag',
          '#tag' => 'strong',
          '#value' => $migrateDefinition['label']
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'div',
          [
            '#type' => 'html_tag',
            '#tag' => 'small',
            '#value' => $key
          ]
        ]
      ];
      $form['list-migrations']['migrations']['#rows'][] = [
        'group' => $migrateDefinition['migration_group'],
        'idmig' => $this->Renderer->renderRoot($idmig),
        'status' => $Migration->getStatusLabel(),
        'total' => $source->count(),
        'imported' => '',
        'unprocessed' => 'Unprocessed',
        'last-imported' => 'Last Imported'
      ];
    }
  }

  /**
   *
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrationwbh_import';
  }

  /**
   *
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::$keySettings
    ];
  }

  /**
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Generer les fichiers du themes.
    $this->LayoutgenentitystylesServices->generateAllFilesStyles();
    $defaultThemeName = \Drupal::config('system.theme')->get('default');

    if ($defaultThemeName) {
      $config_theme_entity = \Drupal::entityTypeManager()->getStorage('config_theme_entity')->loadByProperties([
        'hostname' => $defaultThemeName
      ]);
    }
    //
    if (!empty($config_theme_entity)) {
      $config_theme_entity = reset($config_theme_entity);
      $GenerateStyleTheme = new GenerateStyleTheme($config_theme_entity);
      $GenerateStyleTheme->buildSubTheme(false, true);
    }

    parent::submitForm($form, $form_state);
    $this->messenger()->addMessage('Theme regenerer avec succes');
    // $response = Url::fromUserInput('internal:/node');
    // $form_state->setRedirect($response);
    $form_state->setRedirect('<front>');
  }

  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function selectNextFieldSubmit(array &$form, FormStateInterface $form_state) {
    // $nextStep = $form_state->get('step') + 1;
    $nextStep = !empty($_GET['step']) ? $_GET['step'] + 1 : 2;
    if ($nextStep > $this->maxStep)
      $nextStep = $this->maxStep;
    $form_state->set('step', $nextStep);
    $form_state->setRebuild();
  }

  public function saveConfigNext(array &$form, FormStateInterface $form_state) {
    // $nextStep = $form_state->get('step') + 1;
    $nextStep = !empty($_GET['step']) ? $_GET['step'] + 1 : 2;
    if ($nextStep > $this->maxStep)
      $nextStep = $this->maxStep;
    $form_state->set('step', $nextStep);
    //
    $config = $this->config(static::$keySettings);
    $config->set('username', $form_state->getValue('username'));
    $config->set('external_domain', $form_state->getValue('external_domain'));
    if (!empty($form_state->getValue('password')))
      $config->set('password', $form_state->getValue('password'));
    $config->save();
    //
    $form_state->setRedirect('migrationwbh.runimportform', [], [
      'query' => [
        'step' => $nextStep
      ]
    ]);
    // $form_state->setRebuild();
  }

  /**
   * --
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function ImportNextSubmit(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::$keySettings)->getRawData();
    // $nextStep = $form_state->get('step') + 1;
    $nextStep = !empty($_GET['step']) ? $_GET['step'] + 1 : 1;
    if ($nextStep > $this->maxStep)
      $nextStep = $this->maxStep;
    $form_state->set('step', $nextStep);
    // Import des pages web.
    $urlPageWeb = trim($config['external_domain'], '/') . '/jsonapi/export/page-web';
    $this->MigrationImportAutoSiteInternetEntity->setUrl($urlPageWeb);
    $this->MigrationImportAutoSiteInternetEntity->runImport();
    debugLog::$max_depth = 15;
    debugLog::kintDebugDrupal($this->MigrationImportAutoSiteInternetEntity->getLogs(), 'ImportNextSubmit__SiteInternetEntity', true);
    // Import des block_contents.
    $urlBlockContents = trim($config['external_domain'], '/') . '/jsonapi/export/block_content';
    $this->MigrationImportAutoBlockContent->setUrl($urlBlockContents);
    $this->MigrationImportAutoBlockContent->runImport();
    debugLog::$max_depth = 15;
    debugLog::kintDebugDrupal($this->MigrationImportAutoBlockContent->getLogs(), 'ImportNextSubmit__BlockContent', true);
    // Import du theme.
    $urlConfigThemeEntity = trim($config['external_domain'], '/') . '/jsonapi/export/template-theme';
    $this->MigrationImportAutoConfigThemeEntity->setUrl($urlConfigThemeEntity);
    $this->MigrationImportAutoConfigThemeEntity->runImport();
    debugLog::$max_depth = 15;
    debugLog::kintDebugDrupal($this->MigrationImportAutoConfigThemeEntity->getLogs(), 'ImportNextSubmit__ConfigThemeEntity', true);

    // Import des nodes.
    // ***
    $form_state->setRedirect('migrationwbh.runimportform', [], [
      'query' => [
        'step' => $nextStep
      ]
    ]);
    // $form_state->setRebuild();
  }

  /**
   * --
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function ImportNextSubmit2(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::$keySettings)->getRawData();
    // $nextStep = $form_state->get('step') + 1;
    $nextStep = !empty($_GET['step']) ? $_GET['step'] + 1 : 1;
    if ($nextStep > $this->maxStep)
      $nextStep = $this->maxStep;
    $form_state->set('step', $nextStep);

    // Import des blocks.
    $urlBlock = trim($config['external_domain'], '/') . '/jsonapi/export/block';
    $this->MigrationImportAutoBlock->setUrl($urlBlock);
    $this->MigrationImportAutoBlock->runImport();
    debugLog::$max_depth = 15;
    debugLog::kintDebugDrupal($this->MigrationImportAutoBlock->getLogs(), 'ImportNextSubmit__Block', true);
    //
    // ***
    $form_state->setRedirect('migrationwbh.runimportform', [], [
      'query' => [
        'step' => $nextStep
      ]
    ]);
  }

  public function selectPreviewsFieldSubmit(array &$form, FormStateInterface $form_state) {
    $pvStep = !empty($_GET['step']) ? $_GET['step'] - 1 : 0;
    if ($pvStep <= 0)
      $pvStep = 1;
    $form_state->set('step', $pvStep);
    // $form_state->setRebuild();
    $form_state->setRedirect('migrationwbh.runimportform', [], [
      'query' => [
        'step' => $pvStep
      ]
    ]);
  }

  /**
   * On doit desactiver l'utilisation du domaine pour la configuration des
   * themes.
   */
  protected function disableUseDomainConfig() {
    $key = "generate_style_theme.settings";
    $config = \Drupal::config($key)->getRawData();
    if (!empty($config['tab1']['use_domain'])) {
      $configEit = \Drupal::service('config.factory')->getEditable($key);
      $configEit->set('tab1.use_domain', 0);
      $configEit->save();
    }
  }

  /**
   * Permet de creer un domaine si aucun n'existe.
   */
  protected function createDomain() {
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    if (empty($domains)) {
      $values = [
        'hostname' => $_SERVER['HTTP_HOST'],
        'name' => $_SERVER['HTTP_HOST'],
        'id' => preg_replace('/[^a-z0-9]/', "_", $_SERVER['HTTP_HOST']),
        'is_default' => true
      ];
      $domain = \Drupal::entityTypeManager()->getStorage('domain')->create($values);
      $domain->save();
    }
  }

  protected function assureThemeIsActive() {
    $config_theme_entities = \Drupal::entityTypeManager()->getStorage('config_theme_entity')->loadMultiple();
    // comment identifiez l'ancien theme ? à partir de %_wb_horizon_%
    if (!empty($config_theme_entities)) {
      $old_config_theme_entity = null;
      foreach ($config_theme_entities as $config_theme_entity) {
        /**
         *
         * @var \Drupal\generate_style_theme\Entity\ConfigThemeEntity $config_theme_entity
         */
        if (str_contains($config_theme_entity->getHostname(), '_wb_horizon_')) {
          $old_config_theme_entity = $config_theme_entity;
          // dump($config_theme_entity);
        }
      }
      $conf = \Drupal::config("system.theme")->getRawData();
      if ($conf['default'] != $old_config_theme_entity->getHostname()) {
        $old_config_theme_entity->save();
        $this->messenger()->addStatus("Le theme a été MAJ ");
      }
    }
  }

  /**
   * Permet de creer/maj un theme en function du nouveau domaine et des
   * informations de configuration de l'ancien domaine.
   *
   * @deprecated pas necessaire
   */
  protected function createNewTheme() {
    $config_theme_entities = \Drupal::entityTypeManager()->getStorage('config_theme_entity')->loadMultiple();
    // comment identifiez l'ancien theme ? à partir de %_wb_horizon_%
    if (!empty($config_theme_entities)) {
      $old_config_theme_entities = null;
      foreach ($config_theme_entities as $config_theme_entity) {
        /**
         *
         * @var \Drupal\generate_style_theme\Entity\ConfigThemeEntity $config_theme_entity
         */
        if (str_contains($config_theme_entity->getHostname(), '_wb_horizon_')) {
          $old_config_theme_entities = $config_theme_entity;
          // dump($config_theme_entity);
        }
      }
      // On charge le theme.
      $id = preg_replace('/[^a-z0-9]/', "_", $_SERVER['HTTP_HOST']);
      $domain = \Drupal::entityTypeManager()->getStorage('domain')->load($id);
      if ($domain && $old_config_theme_entities) {
        // Si le nouveau theme n'existe pas on le cree :
        $newTHemeExit = \Drupal::entityTypeManager()->getStorage('config_theme_entity')->loadByProperties([
          'hostname' => $id
        ]);
        if (empty($newTHemeExit)) {
          $domain = $domain->toArray();
          $newTheme = $old_config_theme_entities->createDuplicate();
          $newTheme->set('hostname', $id);
          $newTheme->save();
          // On met à jours les id du theme au niveau des blocs.
          // $this->updateBlock($id);
        }
        else {
          // $newTheme = reset($newTHemeExit);
          // $newTheme->save();
          // $this->updateBlock($id);
        }
      }
      else {
        throw new \Exception(" Une erreur s'est produite ... ");
      }
    }
  }

  /**
   * Met à jour les configurations de blocs.
   *
   * @deprecated pas necessaire
   */
  protected function updateBlock($id) {
    $blocks = \Drupal::entityTypeManager()->getStorage('block')->loadMultiple();
    foreach ($blocks as $block) {
      // dump($block->toArray());
      if (str_contains($block->get('theme'), '_wb_horizon_')) {
        // $block->set('theme', $id);
        // $block->save();
        dump($block->toArray());
      }
    }
  }

}


