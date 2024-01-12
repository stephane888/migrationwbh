<?php

namespace Drupal\migrationwbh\Form;

trait HelperToImport {
  
  /**
   * On doit desactiver l'utilisation du domaine pour la configuration des
   * themes.
   */
  protected function disableUseDomainConfig() {
    $key = "generate_style_theme.settings";
    $config = \Drupal::config($key)->getRawData();
    if (!empty($config['tab1']['use_domain'])) {
      $configEit = \Drupal::service('config.factory')->getEditable($key);
      $configEit->set('tab1.use_domain', 0);
      $configEit->save();
    }
  }
  
  /**
   * Permet de creer un domaine si aucun n'existe.
   */
  protected function createDomain() {
    $domains = \Drupal::entityTypeManager()->getStorage('domain')->loadMultiple();
    if (empty($domains)) {
      $values = [
        'hostname' => $_SERVER['HTTP_HOST'],
        'name' => $_SERVER['HTTP_HOST'],
        'id' => preg_replace('/[^a-z0-9]/', "_", $_SERVER['HTTP_HOST']),
        'is_default' => true
      ];
      $domain = \Drupal::entityTypeManager()->getStorage('domain')->create($values);
      $domain->save();
    }
  }
  
  protected function assureThemeIsActive() {
    $config_theme_entities = \Drupal::entityTypeManager()->getStorage('config_theme_entity')->loadMultiple();
    // comment identifiez l'ancien theme ? à partir de %_wb_horizon_%
    if (!empty($config_theme_entities)) {
      $old_config_theme_entity = null;
      foreach ($config_theme_entities as $config_theme_entity) {
        /**
         *
         * @var \Drupal\generate_style_theme\Entity\ConfigThemeEntity $config_theme_entity
         */
        if (str_contains($config_theme_entity->getHostname(), '_wb_horizon_')) {
          $old_config_theme_entity = $config_theme_entity;
          break;
        }
      }
      $conf = \Drupal::config("system.theme")->getRawData();
      
      if ($conf['default'] != $old_config_theme_entity->getHostname()) {
        $old_config_theme_entity->save();
        $this->messenger()->addStatus("Le theme a été MAJ ");
      }
    }
  }
  
  protected function addLanguage() {
    $en = \Drupal\language\Entity\ConfigurableLanguage::load('en');
    if (empty($en)) {
      $language = \Drupal\language\Entity\ConfigurableLanguage::createFromLangcode('en');
      $language->save();
    }
  }
  
  protected function disabledPreprocessCss() {
    $key = "system.performance";
    $conf = \Drupal::config($key)->getRawData();
    if ($conf['css']['preprocess']) {
      $configEit = \Drupal::service('config.factory')->getEditable($key);
      $configEit->set('css.preprocess', false);
      $configEit->set('js.preprocess', false);
      $configEit->save();
    }
  }
  
  /**
   * On desactive certains blocs
   */
  protected function disabledBlocks() {
    $config_theme_entity = $this->getOldTheme();
    if ($config_theme_entity) {
      $themeName = $config_theme_entity->getHostname();
      //
      $block = \Drupal::entityTypeManager()->getStorage('block')->load($themeName . '_page_title');
      if ($block) {
        $block->set('status', false);
        $block->save();
      }
      //
      $block = \Drupal::entityTypeManager()->getStorage('block')->load($themeName . '_breamcrumb');
      if ($block) {
        $block->set('status', false);
        $block->save();
      }
    }
  }
  
  /**
   *
   * @return void|\Drupal\generate_style_theme\Entity\ConfigThemeEntity
   */
  protected function getOldTheme() {
    $config_theme_entities = \Drupal::entityTypeManager()->getStorage('config_theme_entity')->loadMultiple();
    // comment identifiez l'ancien theme ? à partir de %_wb_horizon_%
    if (!empty($config_theme_entities)) {
      foreach ($config_theme_entities as $config_theme_entity) {
        /**
         *
         * @var \Drupal\generate_style_theme\Entity\ConfigThemeEntity $config_theme_entity
         */
        if (str_contains($config_theme_entity->getHostname(), '_wb_horizon_')) {
          return $config_theme_entity;
        }
      }
    }
    return;
  }
  
}