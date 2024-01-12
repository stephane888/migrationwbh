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
  
}