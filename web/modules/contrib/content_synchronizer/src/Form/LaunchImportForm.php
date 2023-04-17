<?php

namespace Drupal\content_synchronizer\Form;

use Drupal\content_synchronizer\Entity\ImportEntity;
use Drupal\content_synchronizer\Processors\BatchImportProcessor;
use Drupal\content_synchronizer\Processors\ImportProcessor;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Launch Import Form.
 */
class LaunchImportForm extends FormBase {

  /**
   * The import entity.
   *
   * @var \Drupal\content_synchronizer\Entity\ImportEntity
   */
  protected $import;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_synchronizer.import.launch';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\content_synchronizer\Entity\ImportEntity $import */
    $this->import = $form_state->getBuildInfo()['import'];

    if ($this->import->getProcessingStatus() === ImportEntity::STATUS_NOT_STARTED) {
      // Settings.
      $form['settings'] = [
        '#type'  => 'fieldset',
        '#title' => $this->t('Settings'),
      ];

      $form['settings']['creationType'] = [
        '#type'          => 'radios',
        '#title'         => $this->t('Action on entity creation'),
        '#options'       => static::getCreateOptions(),
        '#default_value' => ImportProcessor::DEFAULT_PUBLICATION_TYPE,
      ];

      $form['settings']['updateType'] = [
        '#type'          => 'radios',
        '#title'         => $this->t('Action on entity update'),
        '#options'       => static::getUpdateOptions(),
        '#default_value' => ImportProcessor::DEFAULT_UPDATE_TYPE,
      ];
    }

    // Entity list.
    $this->initRootEntitiesList($form);
    if ($this->import->getProcessingStatus() === ImportEntity::STATUS_NOT_STARTED) {

      $form['launch'] = [
        '#type'        => 'submit',
        '#value'       => $this->t('Import selected entities'),
        '#button_type' => 'primary',
      ];
    }

    return $form;
  }

  /**
   * Return create Options.
   *
   * @return array
   *   The create options.
   */
  public static function getCreateOptions() {
    return [
      ImportProcessor::PUBLICATION_PUBLISH   => t('Publish created content'),
      ImportProcessor::PUBLICATION_UNPUBLISH => t('Do not publish created content'),
    ];
  }

  /**
   * Return update options.
   *
   * @return array
   *   The update options.
   */
  public static function getUpdateOptions() {
    return [
      ImportProcessor::UPDATE_SYSTEMATIC => t('Always update existing entity with importing content'),
      ImportProcessor::UPDATE_IF_RECENT  => t('Update existing entity only if the last change date of new content is more recent than the last change date of the corresponding existing entity'),
      ImportProcessor::UPDATE_NO_UPDATE  => t('Do not update existing content'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batchImport = new BatchImportProcessor();

    $batchImport->import(
      $this->import,
      array_intersect_key($this->import->getRootsEntities(), array_flip($form_state->getUserInput()['entities_to_import'])),
      [
        $this,
        'onBatchEnd',
      ],
      $form_state->getValue('creationType'), $form_state->getValue('updateType'));
  }

  /**
   * The callback after batch process.
   */
  public function onBatchEnd($data) {
    $this->import->removeArchive();
  }

  /**
   * Init the root entities list for display.
   */
  protected function initRootEntitiesList(array &$form) {
    $rootEntities = $this->import->getRootsEntities();
    $build = [
      '#theme'         => 'entities_list_table',
      '#entities'      => $rootEntities,
      '#status_or_bundle' => $this->t('status'),
      '#checkbox_name' => 'entities_to_import[]',
      '#title'         => $this->t('Entities to import'),
      '#attached'      => [
        'library' => ['content_synchronizer/list'],
      ],
    ];
    $form['entities_list'] = $build;
  }

}
