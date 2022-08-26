<?php
declare(strict_types = 1);

namespace Drupal\migrationwbh\Plugin\migrate\source;

use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Source plugin for beer comments.
 *
 * @see \Drupal\migrate\Plugin\MigrateSourceInterface
 *
 * @MigrateSource(
 *   id = "basic_entity_source"
 * )
 */
final class BasicEntitySource extends Url {

  function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    $conf = \Drupal::config('migrationwbh.import')->getRawData();
    if (empty($conf)) {
      throw new \Exception(' Config migrationwbh.import not found ');
    }
    if (!empty($configuration['constants']['url'])) {
      $configuration['urls'] = [
        trim($conf['external_domain'], "/") . $configuration['constants']['url']
      ];
      // Auth
      $configuration['authentication'] = [
        'plugin' => 'basic',
        'username' => $conf['username'],
        'password' => $conf['password']
      ];
      // dump($conf);
    }
    else {
      $this->messenger()->addWarning(' Constants.url  not found .');
      $configuration['urls'] = [
        ''
      ];
    }
    // if (!empty($configuration['constants']['url']))
    // dump($configuration);
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  public function prepareRow($row) {
    $status = parent::prepareRow($row);
    //
    return $status;
  }

}