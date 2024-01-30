<?php

namespace Drupal\migrationwbh\Services;

interface MigrationImportAutoBaseInterface {
  
  /**
   * Permet de construire les données à sauvagarder à partir des information
   * brutes.
   *
   * @param array $row
   *        contient les données brute de l'entite provenant de la requete.
   * @param array $data_rows
   *        Contient les données qui doivent etre sauvagarder.
   */
  public function buildDataRows(array $row, array &$data_rows);
  
  /**
   * Permet de construire le mapping entre les champs du flux et les champs de
   * l'entité.
   *
   * @param array $configuration
   * @param array $process
   */
  public function buildMappingProcess(array $configuration, array &$process);
  
}