<?php
declare(strict_types = 1);

namespace Drupal\migrationwbh\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;

/**
 * Obtain JSON data for migration.
 * Ce fichier permet de recuperer les données du blocs "included" et de les
 * mettres dans le champs correspondants.
 * il permet de recuperer les types :
 * - 'file--file' ( images et fichiers ).
 * - 'paragraphs_type--paragraphs_type' ( les paragraphs ).
 *
 * @DataParser(
 *   id = "json_api",
 *   title = @Translation("JSON API"),
 *   data_fetcher_plugin= "http"
 * )
 */
class JsonApi extends Json {

  /**
   * Recupere les données de /included et les ajoutées dans /data/relationships.
   * les données recuperer de /includesd => "links","attributes","relationships"
   * - example
   * /included/[]/attributes => /data/[]/relationships/data
   *
   * @param string $url
   *        URL of a JSON feed.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  protected function getSourceData(string $url): array {
    $response = $this->getResourseBrute($url);
    $source_data = parent::getSourceData($url);
    if (!empty($response['included'])) {
      foreach ($source_data as $k => $value) {
        // On parcourt les champs: relationships
        if (!empty($value['relationships']))
          foreach ($value['relationships'] as $fieldName => $relation) {
            // On a deux cas de figure, le cas avec des champs uniques et le
            // cas des champs multiple.
            // Cas unique.
            if (!empty($relation['data']['type'])) {
              // On identity le type de donnée(file)
              if ($relation['data']['type'] == 'file--file') {
                $fid = $relation['data']['meta']['drupal_internal__target_id'];
                $attributes = $this->getImageURL($response['included'], $fid);
                if ($attributes) {
                  // On choisie de supprimer les autres entrées.
                  $source_data[$k]['relationships'][$fieldName] = [
                    'attributes' => $attributes
                  ];
                }
              }
              elseif (str_contains($relation['data']['type'], "paragraph--")) {
                $paragraph = $this->getParagraphs($response['included'], $relation['data']['meta']['drupal_internal__target_id']);
                // On choisie de supprimer les autres entrées.
                $source_data[$k]['relationships'][$fieldName] = [
                  'attributes' => $paragraph['attributes'],
                  'links' => $paragraph['links'],
                  'relationships' => $paragraph['relationships']
                ];
              }
            }
            // Cas multiple.
            if (!empty($relation['data'][0])) {
              foreach ($relation['data'] as $id_value => $data) {
                // identification du type.
                if (!empty($data['type'])) {
                  if (str_contains($data['type'], "paragraph--")) {
                    $paragraph = $this->getParagraphs($response['included'], $data['meta']['drupal_internal__target_id']);
                    $source_data[$k]['relationships'][$fieldName]['data'][$id_value]['attributes'] = $paragraph['attributes'];
                    $source_data[$k]['relationships'][$fieldName]['data'][$id_value]['links'] = $paragraph['links'];
                    $source_data[$k]['relationships'][$fieldName]['data'][$id_value]['relationships'] = $paragraph['relationships'];
                  }
                }
              }
            }
          }
      }
    }
    // \Stephane888\Debug\debugLog::$max_depth = 7;
    // $debug = [
    // $response,
    // $source_data
    // ];
    // \Stephane888\Debug\debugLog::$max_depth = 8;
    // \Stephane888\Debug\debugLog::kintDebugDrupal($source_data,
    // 'getSourceData-includes', true);
    return $source_data;
  }

  /**
   * Objectif recuperer les urls des images de "included" et les ajouter au
   * champs
   * correspondant;
   *
   * @param array $included
   *        Tableau des included.
   * @param string $fid
   *        id du champs.
   * @return array|NULL
   */
  private function getImageURL(array $included, $fid) {
    foreach ($included as $value) {
      // On identifie le type de donnée(file)
      if ($value['type'] == 'file--file' && $value['attributes']['drupal_internal__fid'] == $fid) {
        return $value['attributes'];
      }
    }
    return null;
  }

  /**
   * Pour les paragraphs il est important de savoir que les paragraphs peuvent
   * contenir des images, d'autres paragraphes.
   * Donc on a besoin d'une boucle.
   *
   * @param array $included
   * @param string $pid
   * @return array|NULL
   */
  private function getParagraphs(array $included, $pid) {
    foreach ($included as $value) {
      // On identifie le type de donnée(file)
      if (str_contains($value['type'], "paragraph--") && $value['attributes']['drupal_internal__id'] == $pid) {
        return $value;
      }
    }
    return null;
  }

  private function getResourseBrute($url) {
    $response = $this->getDataFetcherPlugin()->getResponseContent($url);
    // Convert objects to associative arrays.
    $source_data = json_decode($response, TRUE, 512, JSON_THROW_ON_ERROR);

    // If json_decode() has returned NULL, it might be that the data isn't
    // valid utf8 - see http://php.net/manual/en/function.json-decode.php#86997.
    if (is_null($source_data)) {
      $utf8response = utf8_encode($response);
      $source_data = json_decode($utf8response, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    return $source_data;
  }

  public function getDataByExternalApi(string $url) {
    return $this->getSourceData($url);
  }

}