<?php
declare(strict_types = 1);

namespace Drupal\migrationwbh\Plugin\migrate\source;

use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Component\Serialization\Json;
use Stephane888\Debug\debugLog;

/**
 * Source plugin for beer comments.
 *
 * @see \Drupal\migrate\Plugin\MigrateSourceInterface
 *
 * @MigrateSource(
 *   id = "paragraph_source"
 * )
 */
final class ParagraphSource extends Url {
  /**
   * Contient les données de la rerquetes
   *
   * @var array
   */
  private $jsonDatas;

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
      $this->messenger()->addWarning(' Constants.url  not found ..');
      $configuration['urls'] = [
        ''
      ];
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  public function fields(): array {
    $fields = parent::fields();

    return $fields;
  }

  /**
   * On recupere les champs necessaire au niveau des données recus.
   *
   * @param array $fields
   */
  private function buildFields(array &$fields) {
    $this->getJsonDatas();
    // //
  }

  /**
   * recupere les données
   */
  private function getJsonDatas() {
    if (!$this->jsonDatas) {
      /**
       *
       * @var \Drupal\migrationwbh\Plugin\migrate_plus\data_parser\JsonApi $dataParser
       */
      $dataParser = $this->getDataParserPlugin();
      /**
       *
       * @var \Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http $dataFetcher
       */
      $dataFetcher = $dataParser->getDataFetcherPlugin();
      $url = reset($this->sourceUrls);
      if (!empty($url)) {
        $this->jsonDatas = Json::decode($dataFetcher->getResponseContent($url));
      }
    }
    return $this->jsonDatas;
  }

}