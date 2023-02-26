<?php

namespace Drupal\migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\EmbeddedDataSource;

/**
 * Allows source data to be defined in the configuration of the source plugin.
 *
 * The embedded_data source plugin is used to inject source data from the plugin
 * configuration. One use case is when some small amount of fixed data is
 * imported, so that it can be referenced by other migrations. Another use case
 * is testing.
 *
 * Available configuration keys:
 * - data_rows: The source data array. Each source row should be an associative
 * array of values keyed by field names.
 * - ids: An associative array of fields uniquely identifying a source row.
 * See \Drupal\migrate\Plugin\MigrateSourceInterface::getIds() for more
 * information.
 *
 * Example:
 *
 * @code
 * source:
 *   plugin: embedded_data
 *   data_rows:
 *     -
 *       channel_machine_name: music
 *       channel_description: Music
 *     -
 *       channel_machine_name: movies
 *       channel_description: Movies
 *   ids:
 *     channel_machine_name:
 *       type: string
 * @endcode
 *
 * This example migrates a channel vocabulary specified in the source section.
 *
 * For additional configuration keys, refer to the parent class:
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 *
 * @MigrateSource(
 *   id = "url_embedded_dataSource"
 * )
 */
class UrlEmbeddedDataSource extends EmbeddedDataSource {
  
}