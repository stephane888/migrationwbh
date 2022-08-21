<?php

namespace Drupal\migrationwbh\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrationwbh\Services\MigrationImport;
use Drupal\Core\Render\Renderer;

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
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::$keySettings);
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Name'),
      '#default_value' => $config->get('username')
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('password'),
      '#default_value' => $config->get('password')
    ];
    $form['external_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Votre domaine sur wh-horizon.com'),
      '#default_value' => $config->get('external_domain'),
      '#description' => 'Votre domaine au format complet: example => http://mark-business.wh-hozizon.com '
    ];
    $this->getMigrationList($form);
    $form = parent::buildForm($form, $form_state);
    if (!empty($form['actions']['submit'])) {
      $form['actions']['submit']['#value'] = 'save config';
    }
    return $form;
  }

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
    $instances = $this->MigrationImport->listMigrateInstance();
    foreach ($this->MigrationImport->listMigrate() as $key => $migrateDefinition) {
      /** @var \Drupal\migrate\MigrateExecutable $excutable */
      $excutable = $instances[$key];

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
        'status' => 'Status',
        'total' => 'Total',
        'imported' => 'Imported',
        'unprocessed' => 'Unprocessed',
        'last-imported' => 'Last Imported'
      ];
    }
  }

  /**
   *
   * @param ConfigFactoryInterface $config_factory
   * @param MigrationImport $MigrationImport
   */
  public function __construct(ConfigFactoryInterface $config_factory, MigrationImport $MigrationImport, Renderer $Renderer) {
    parent::__construct($config_factory);
    $this->MigrationImport = $MigrationImport;
    $this->Renderer = $Renderer;
  }

  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('migrationwbh.migrate_import'), $container->get('renderer'));
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
    $config = $this->config(static::$keySettings);
    $config->set('username', $form_state->getValue('username'));
    $config->set('external_domain', $form_state->getValue('external_domain'));
    if (!empty($form_state->getValue('password')))
      $config->set('password', $form_state->getValue('password'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}