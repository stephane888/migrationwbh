<?php

namespace Drupal\migrationwbh\Plugin\migrate\process;

use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_file\Plugin\migrate\process\ImageImport;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\FileCopy;

/**
 * Source plugin for beer comments.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "image_entity_import"
 * )
 */
final class ImageEntityImport extends ImageImport {
  
  /**
   *
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!empty($value['uri']['url'])) {
      $value = "http://wb-horizon.kksa" . $value['uri']['url'];
      $this->configuration['alt'] = 'none';
      return parent::transform($value, $migrate_executable, $row, $destination_property);
    }
    return null;
  }
  
}