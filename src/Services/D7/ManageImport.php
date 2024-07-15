<?php

namespace Drupal\migrationwbh\Services\D7;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Serialization\Json;

/**
 * Permet de faire l'import de D7 vers D9 ou D10.
 *
 * @author stephane
 *
 */
class ManageImport extends ControllerBase {
  /**
   * Contient les entites et leurs configurations.
   *
   * @var array
   */
  protected $entitiesConfig = [];

  /**
   * Contient les entites (données).
   *
   * @var array
   */
  protected $entities = [];

  /**
   * Contruit des route permettant l'import.
   */
  public function buildRouteForImport(string $entity_type_id, string $bundle) {
    $entities = $this->loadEntities($entity_type_id, $bundle);
    foreach ($entities as $entity) {
      //
    }
  }

  /**
   * --
   *
   * @param string $entity_type_id
   * @param string $bundle
   */
  protected function loadEntities(string $entity_type_id, string $bundle) {
    if (!$this->entities) {
      $config = $this->defaultconfig();
      $this->entities = Json::decode(\file_get_contents($config['domaine'] . '/migrate-export-entities/' . $entity_type_id . '/' . $bundle));
    }
    return $this->entities;
  }

  /**
   * Permet de verifier que la configuration est ok.
   */
  public function ValidConfigEntities() {
    $configEntities = $this->getConfigEntitties();
    foreach ($configEntities as $entity_type_id => $configEntity) {
      if (is_array($configEntity)) {
        $this->checkConfigEntity($entity_type_id, $configEntity);
      }
    }
  }

  /**
   * Permet de verifier la configuration d'une entité.
   *
   * @param string $entity_type
   * @param array $configEntity
   */
  protected function checkConfigEntity(string $entity_type_id, array $configEntities) {
    $storageEntityType = $this->entityTypeManager()->getStorage($entity_type_id);
    if ($storageEntityType) {
      // On recupere l'entité du bundle s"il existe.
      $EntityTypebundle = $storageEntityType->getEntityType()->getBundleEntityType();
      if ($EntityTypebundle) {
        // dump($configEntities);
        foreach ($configEntities as $bundle => $configEntity) {
          // On valide les configuration par bundle.
          if (!empty($configEntity['fields']))
            foreach ($configEntity['fields'] as $fieldName => $setting) {
              $id = $entity_type_id . '.' . $bundle . '.' . $fieldName;
              if (!$this->checkIfFieldExist($id)) {
                $this->messenger()->addWarning("Le champs $fieldName n'existe pas");
              }
            }
        }
      }
      else {
        if (!empty($configEntities[$entity_type_id]['fields']))
          foreach ($configEntities[$entity_type_id]['fields'] as $fieldName => $setting) {
            $id = $entity_type_id . '.' . $entity_type_id . '.' . $fieldName;
            if (!$this->checkIfFieldExist($id)) {
              $this->messenger()->addWarning("Le champs $fieldName n'existe pas");
            }
          }
      }
    }
    else
      $this->messenger()->addWarning("L'entité type : " . $entity_type_id . " n'existe pas");
  }

  protected function checkIfFieldExist($id) {
    if ($this->entityTypeManager()->getStorage('field_config')->load($id))
      return TRUE;
    return FALSE;
  }

  /**
   *
   * @return array|mixed
   */
  protected function getConfigEntitties() {
    if (!$this->entitiesConfig) {
      $config = $this->defaultconfig();
      $this->entitiesConfig = Json::decode(\file_get_contents($config['domaine'] . $config['url_base']));
    }
    return $this->entitiesConfig;
  }

  /**
   * Retourne la configuration.
   */
  protected function defaultconfig() {
    return [
      'domaine' => 'http://itietogo7.kksa',
      'url_base' => '/migrateexport'
    ];
  }

}
