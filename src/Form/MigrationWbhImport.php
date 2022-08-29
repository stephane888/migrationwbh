<?php

namespace Drupal\migrationwbh\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrationwbh\Services\MigrationImport;
use Drupal\migrationwbh\Services\MigrationImportAutoSiteInternetEntity;
use Drupal\Core\Render\Renderer;
use Stephane888\Debug\debugLog;

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
  protected $maxStep = 3;
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
   * @param ConfigFactoryInterface $config_factory
   * @param MigrationImport $MigrationImport
   */
  public function __construct(ConfigFactoryInterface $config_factory, MigrationImport $MigrationImport, Renderer $Renderer, MigrationImportAutoSiteInternetEntity $MigrationImportAutoSiteInternetEntity) {
    parent::__construct($config_factory);
    $this->MigrationImport = $MigrationImport;
    $this->Renderer = $Renderer;
    $this->MigrationImportAutoSiteInternetEntity = $MigrationImportAutoSiteInternetEntity;
  }

  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('migrationwbh.migrate_import'), $container->get('renderer'), $container->get('migrationwbh.migrate_auto_import.site_internet_entity'));
  }

  /**
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::$keySettings)->getRawData();
    if ($form_state->has('step')) {
      switch ($form_state->get('step')) {
        case 1:
          $this->formState1($form, $form_state, $config);
          break;
        case 2:
          $this->formState2($form, $form_state, $config);
          break;
        case 3:
          $this->formState3($form, $form_state, $config);
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
    $this->actionButtons($form, $form_state, "Importer et passer à l'etape suivante", 'ImportNextSubmit');
  }

  protected function formState3(array &$form, FormStateInterface $form_state, $config) {
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
    if ($form_state->get('step') > 1)
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
    if ($form_state->get('step') < $this->maxStep)
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
    if ($form_state->get('step') >= $this->maxStep) {
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
    parent::submitForm($form, $form_state);
    // reconstruction du theme.
  }

  /**
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function selectNextFieldSubmit(array &$form, FormStateInterface $form_state) {
    $nextStep = $form_state->get('step') + 1;
    if ($nextStep > $this->maxStep)
      $nextStep = $this->maxStep;
    $form_state->set('step', $nextStep);
    $form_state->setRebuild();
  }

  public function saveConfigNext(array &$form, FormStateInterface $form_state) {
    $nextStep = $form_state->get('step') + 1;
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
    $form_state->setRebuild();
  }

  /**
   * --
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function ImportNextSubmit(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::$keySettings)->getRawData();
    $nextStep = $form_state->get('step') + 1;
    if ($nextStep > $this->maxStep)
      $nextStep = $this->maxStep;
    $form_state->set('step', $nextStep);
    // Import des pages web.
    $urlPageWeb = trim($config['external_domain'], '/jsonapi/export/page-web');
    $this->MigrationImportAutoSiteInternetEntity->setUrl($urlPageWeb);
    $this->MigrationImportAutoSiteInternetEntity->runImport();
    debugLog::$max_depth = 15;
    debugLog::kintDebugDrupal($this->MigrationImportAutoSiteInternetEntity->getLogs(), 'ImportNextSubmit__SiteInternetEntity', true);
    // Import des block_contents.
    // ***
    // Import du theme.
    // ***
    // Import des blocks.
    // ***
    // Import des nodes.
    // ***
    $form_state->setRebuild();
  }

  public function selectPreviewsFieldSubmit(array &$form, FormStateInterface $form_state) {
    $pvStep = $form_state->get('step') - 1;
    if ($pvStep <= 0)
      $pvStep = 1;
    $form_state->set('step', $pvStep);
    $form_state->setRebuild();
  }

}


