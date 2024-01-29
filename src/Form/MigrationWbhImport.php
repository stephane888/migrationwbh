<?php

namespace Drupal\migrationwbh\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrationwbh\Services\MigrationImport;
use Drupal\migrationwbh\Services\MigrationImportAutoBlock;
use Drupal\Core\Render\Renderer;
use Stephane888\Debug\debugLog;
use Stephane888\Debug\ExceptionExtractMessage;
use Drupal\Component\Serialization\Json;
use Drupal\migrate\MigrateException;

/**
 * Configure migrationwbh settings for this site.
 */
class MigrationWbhImport extends ConfigFormBase {
  use BatchImport;
  use HelperToImport;
  use BatchImportConfig;
  
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
  protected $maxStep = 5;
  /**
   * elle permet juste afficher, le processus d'import n'est pas liée
   * à ce dernier, mais voir => ImportNextSubmit.
   *
   * @deprecated ( On doit trouver une autre approche ).
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
   * @var \Drupal\migrationwbh\Services\MigrationImportAutoBlock
   */
  protected $MigrationImportAutoBlock;
  
  /**
   *
   * @var LayoutgenentitystylesServices
   */
  // protected $LayoutgenentitystylesServices;
  
  /**
   *
   * @param ConfigFactoryInterface $config_factory
   * @param MigrationImport $MigrationImport
   */
  public function __construct(ConfigFactoryInterface $config_factory, MigrationImport $MigrationImport, Renderer $Renderer, MigrationImportAutoBlock $MigrationImportAutoBlock) {
    parent::__construct($config_factory);
    $this->MigrationImport = $MigrationImport;
    $this->Renderer = $Renderer;
    $this->MigrationImportAutoBlock = $MigrationImportAutoBlock;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('migrationwbh.migrate_import'), $container->get('renderer'), $container->get('migrationwbh.migrate_auto_import.block'));
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
        case 4:
          $this->formState4($form, $form_state, $config);
          break;
        case 5:
          $this->formState5($form, $form_state, $config);
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
      '#description' => 'Votre domaine au format complet: example => http://mark-business.wh-hozizon.com ',
      '#element_validate' => [
        '::text_identification'
      ]
    ];
    $form['force_re_import'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Forcer à re-importer'),
      '#description' => "Vous pouvez forcer les contenus a etre re-importer. ( Cela augmente le temps d'import )",
      '#default_value' => isset($config['force_re_import']) ? $config['force_re_import'] : false
    ];
    $form['number_import'] = [
      '#type' => 'number',
      '#title' => $this->t('Nombre de contenu a importer par etape'),
      '#description' => "Si vous le temps d'execution sur votre serveur est elevé, vous pouvez augemnter la valeur",
      '#default_value' => isset($config['number_import']) ? $config['number_import'] : 3
    ];
    $this->addLanguage();
    //
    $this->actionButtons($form, $form_state, "Suivant", "saveConfigNext");
  }
  
  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  protected function formState2(array &$form, FormStateInterface $form_state) {
    try {
      // $this->testImport();
      // $this->getMigrationList($form);
      $this->createDomain();
      $this->disableUseDomainConfig();
      $this->actionButtons($form, $form_state, "Importer les contenus et passer à l'etape suivante", 'ImportNextSubmit');
    }
    catch (MigrateException $e) {
      $this->messenger()->addError("Une erreur s'est produite, vieillez contactez l'administrateur");
      $this->messenger()->addError($e->getMessage());
      $this->logger('migrationwbh')->alert($e->getMessage(), ExceptionExtractMessage::errorAll($e));
    }
    catch (\Error $e) {
      $this->messenger()->addError("Une erreur s'est produite, vieillez contactez l'administrateur");
      $this->messenger()->addError($e->getMessage());
      $this->logger('migrationwbh')->alert($e->getMessage(), ExceptionExtractMessage::errorAll($e));
    }
  }
  
  /**
   * Permet de faire de tests d'import.
   */
  protected function testImport() {
    $config = $this->config(static::$keySettings)->getRawData();
    $external_domain = $config['external_domain'];
    $context = [];
    $offset = 0;
    $limit = 1;
    $progress = 0;
    // self::$debugMode = true;
    self::_batch_import_block($external_domain, $offset, $limit, $progress, $context);
    // $this->runBatch($config);
  }
  
  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  protected function formState3(array &$form, FormStateInterface $form_state) {
    $this->assureThemeIsActive();
    $this->actionButtons($form, $form_state, "Importer les blocks", 'ImportNextSubmit2');
  }
  
  /**
   * Limport du block entete ne marche pas à tous les couts, d'ou l'ajout de
   * cette etape afin de forcer l'import de l'ente.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  protected function formState4(array &$form, FormStateInterface $form_state) {
    $this->assureThemeIsActive();
    $this->actionButtons($form, $form_state, "Importer les configurations et passer à l'etape finale", 'ImportNextSubmit2');
  }
  
  protected function formState5(array &$form, FormStateInterface $form_state, $config) {
    $form['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => 'Regenerer votre theme'
    ];
    $this->disabledPreprocessCss();
    $this->disabledBlocks();
    $this->actionButtons($form, $form_state);
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state) {
    //
  }
  
  public function text_identification($element, FormStateInterface $form_state) {
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
    // dump($instances);
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
    // On ne genere pas les fichiers pour le moment.
    // $this->LayoutgenentitystylesServices->generateAllFilesStyles();
    $this->rebuildTheme();
    parent::submitForm($form, $form_state);
    $this->messenger()->addMessage('Theme regenerer avec succes');
    // On redirige l'utilisateur sur la langue par defaut.
    $defaultLanguage = \Drupal::languageManager()->getDefaultLanguage();
    $form_state->setRedirect('<front>', [], [
      'language' => $defaultLanguage
    ]);
  }
  
  /**
   * Cette function permet d'appliquer les paramettres du themes, notament le
   * logo.
   */
  protected function rebuildTheme() {
    $defaultThemeName = \Drupal::config('system.theme')->get('default');
    if ($defaultThemeName) {
      $config_theme_entities = \Drupal::entityTypeManager()->getStorage('config_theme_entity')->loadByProperties([
        'hostname' => $defaultThemeName
      ]);
    }
    //
    if (!empty($config_theme_entities)) {
      /**
       *
       * @var \Drupal\generate_style_theme\Entity\ConfigThemeEntity $config_theme_entity
       */
      $config_theme_entity = reset($config_theme_entities);
      $config_theme_entity->set('run_npm', false);
      $config_theme_entity->getLogo();
      $config_theme_entity->save();
    }
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
    $config->set('force_re_import', $form_state->getValue('force_re_import'));
    $config->set('number_import', $form_state->getValue('number_import'));
    if (!empty($form_state->getValue('password')))
      $config->set('password', $form_state->getValue('password'));
    $config->save();
    //
    try {
      // Permet de tester la connexion au serveur.
      if (self::checkConnexionFrom($form_state->getValue('external_domain'))) {
        // Mise à jour des configurations.
        $externalConf = Json::decode(file_get_contents(trim($config->get('external_domain'), '/') . '/export-entities-wbhorizon/show-site-config'));
        $editConfig = \Drupal::configFactory()->getEditable('system.site');
        if (!empty($externalConf)) {
          if (!empty($externalConf['system.site']['langcode']))
            $editConfig->set('langcode', $externalConf['system.site']['langcode']);
          if (!empty($externalConf['system.site']['default_langcode']))
            $editConfig->set('default_langcode', $externalConf['system.site']['default_langcode']);
          $editConfig->save();
        }
        $form_state->setRedirect('migrationwbh.runimportform', [], [
          'query' => [
            'step' => $nextStep
          ]
        ]);
      }
      else
        $this->messenger()->addError("Echec de connexion au serveur distant.");
    }
    catch (\Error $e) {
      $this->messenger()->addWarning($e->getMessage());
    }
  }
  
  /**
   * Importation des données.
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
    
    debugLog::$max_depth = 15;
    $this->runBatch($config);
    // Import des nodes.
    // ***
    $form_state->setRedirect('migrationwbh.runimportform', [], [
      'query' => [
        'step' => $nextStep
      ]
    ]);
  }
  
  /**
   * --
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function ImportNextSubmit2(array &$form, FormStateInterface $form_state) {
    // $config = $this->config(static::$keySettings)->getRawData();
    // $nextStep = $form_state->get('step') + 1;
    $nextStep = !empty($_GET['step']) ? $_GET['step'] + 1 : 1;
    if ($nextStep > $this->maxStep)
      $nextStep = $this->maxStep;
    $form_state->set('step', $nextStep);
    
    // Import des blocks.
    // $urlBlock = trim($config['external_domain'], '/') .
    // '/jsonapi/export/block';
    // $this->MigrationImportAutoBlock->setUrl($urlBlock);
    // $this->MigrationImportAutoBlock->runImport();
    // debugLog::$max_depth = 15;
    // debugLog::kintDebugDrupal($this->MigrationImportAutoBlock->getLogs(),
    // 'ImportNextSubmit__Block', true, "logs");
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
  
}


