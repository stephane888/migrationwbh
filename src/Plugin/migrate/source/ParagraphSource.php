<?php
declare(strict_types = 1);

namespace Drupal\migrationwbh\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Component\Serialization\Json;

/**
 * Source plugin permettant de :
 * - Contruire de maniere automatique les champs (source.fields)
 *
 * @see \Drupal\migrate\Plugin\MigrateSourceInterface
 *
 * @MigrateSource(
 *   id = "paragraph_source"
 * )
 */
final class ParagraphSource extends BasicEntitySource {
  /**
   * Contient les données de la rerquetes
   *
   * @var array
   */
  private $jsonDatas;
  
  function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
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