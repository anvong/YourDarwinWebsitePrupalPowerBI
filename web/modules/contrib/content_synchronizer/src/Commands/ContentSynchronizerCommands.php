<?php

namespace Drupal\content_synchronizer\Commands;

use Drupal\content_synchronizer\Form\LaunchImportForm;
use Drupal\content_synchronizer\Processors\ImportProcessor;
use Drupal\content_synchronizer\Service\ContentSynchronizerManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
class ContentSynchronizerCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * The Content Synchronizer manager.
   *
   * @var \Drupal\content_synchronizer\Service\ContentSynchronizerManager
   */
  protected $contentSynchronizerManager;

  /**
   * ContentSynchronizerCommands constructor.
   *
   * @param \Drupal\content_synchronizer\Service\ContentSynchronizerManager $contentSynchronizerManager
   *   The content synchronizer manager.
   */
  public function __construct(ContentSynchronizerManager $contentSynchronizerManager) {
    $this->contentSynchronizerManager = $contentSynchronizerManager;
  }

  /**
   * Force temporary files cleaning.
   *
   * @usage content:synchronizer-clean-temporary-files
   *   Usage description
   *
   * @command content:synchronizer-clean-temporary-files
   * @aliases csctf
   */
  public function cleanTemporaryFiles() {
    $deletedFiles = $this->contentSynchronizerManager->cleanTemporaryFiles();
    foreach ($deletedFiles as $file) {
      $this->logger->notice($this->t('@file has been deleted', ['@file' => $file]));
    }
  }

  /**
   * Launch the export of the passed ID.
   *
   * @param int|bool $exportId
   *   The export id.
   * @param string|bool $destination
   *   File to create.
   *
   * @command content:synchronizer-launch-export
   * @aliases cslex
   */
  public function launchExport($exportId = FALSE, $destination = FALSE) {
    // Init user choice.
    $exportId = $exportId ?: $this->io()->ask(
      $this->t('Export Id ?'),
      NULL,
      [$this->contentSynchronizerManager, 'exportIdExists']
    );
    $destination = $destination ?: $this->io()->ask(
      $this->t('Generated tar.gz path'),
      '', [$this, 'canBeNull']);
    $this->logExportData(
      $this->contentSynchronizerManager->launchExport($exportId, $destination)
    );
  }

  /**
   * Export an entity into a tar.gz.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param int $id
   *   The id of the entity.
   * @param string $destination
   *   The destination.
   *
   * @command content:synchronizer-export-entity
   * @aliases cseex
   */
  public function exportEntity($entityTypeId = NULL, $id = NULL, $destination = '') {
    // Init user choice.
    $entityTypeId = $entityTypeId ?: $this->io()->ask(
      $this->t('Entity type Id ?'),
      'node',
      [$this->contentSynchronizerManager, 'entityTypeExists']
    );
    $id = $id ?: $this->io()->ask(
      $this->t('Entity Id ?'),
      NULL,
      function ($value) use ($entityTypeId) {
        return $this->contentSynchronizerManager->entityExists($value, $entityTypeId);
      }
    );
    $destination = $destination ?: $this->io()->ask(
      $this->t('Generated tar.gz path'),
      '',
      [$this, 'canBeNull']);

    $this->logExportData(
      $this->contentSynchronizerManager->exportEntity($entityTypeId, $id, $destination)
    );
  }

  /**
   * Log the result of an export.
   *
   * @param array $exportData
   *   Export data.
   */
  protected function logExportData(array $exportData) {
    foreach ($exportData['entities'] as $exportedEntity) {
      $this->logger->notice($this->t('[@key/@count] - "@label" - @status', $exportedEntity));
    }

    $this->logger->notice($this->t('@destination has been created', ['@destination' => $exportData['destination']]));
  }

  /**
   * Create an Import Entity from the tar.gz file absolute path.
   *
   * @param string $absolutePath
   *   Argument description.
   *
   * @usage content:synchronizer-create-import absolute path
   *   Usage description
   *
   * @command content:synchronizer-create-import
   * @aliases csci
   *
   * @return \Drupal\content_synchronizer\Entity\ImportEntityInterface|int|string|null
   *   The ImportEntity generated.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createImport($absolutePath = NULL) {
    $absolutePath = $absolutePath ?: $this->io()->ask(
      $this->t('File to import ?'),
      '',
      [$this->contentSynchronizerManager, 'tarGzExists']
    );
    $ie = $this->contentSynchronizerManager->createImportFromTarGzFilePath($absolutePath);
    $this->logger->notice($this->t('Import Entity has been created (ID : @ieId)', ['@ieId' => $ie->id()]));
    return $ie;
  }

  /**
   * Launch the import of the import entity ID.
   *
   * @param int $importId
   *   The import id.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option publish
   *   Autopublish imported content :  publish|unpublish
   * @option update
   *   Update stategy :  systematic|if_recent|no_update
   *
   * @command content:synchronizer-launch-import
   * @aliases cslim,content-synchronizer-launch-import
   *
   * @throws \Exception
   */
  public function launchImport($importId, array $options = [
    'publish' => FALSE,
    'update'  => FALSE,
  ]) {

    // Publish option.
    if (!$options['publish']) {
      $options['publish'] = $this->choice(
        $this->t('Action on entity creation'),
        LaunchImportForm::getCreateOptions(),
        ImportProcessor::DEFAULT_PUBLICATION_TYPE);
    }
    // Update option.
    if (!$options['update']) {
      $options['update'] = $this->choice(
        $this->t('Action on entity update'),
        LaunchImportForm::getUpdateOptions(),
        ImportProcessor::DEFAULT_UPDATE_TYPE);
    }

    $this->logImportData(
      $this->contentSynchronizerManager->launchImport($importId, $options['publish'], $options['update'])
    );
  }

  /**
   * Launch the import of the import entity ID.
   *
   * @param string $absolutePath
   *   The import id.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option publish
   *   Autopublish imported content :  publish|unpublish
   * @option update
   *   Update stategy :  systematic|if_recent|no_update
   *
   * @command content:synchronizer-launch-import
   * @aliases cscli
   *
   * @throws \Exception
   */
  public function createAndLaunchImport($absolutePath = NULL, array $options = [
    'publish' => FALSE,
    'update'  => FALSE,
  ]) {
    if ($import = $this->createImport($absolutePath)) {
      $this->launchImport($import->id(), $options);
    }
  }

  /**
   * Log import data.
   *
   * @param array $importData
   *   The import data.
   */
  protected function logImportData(array $importData) {
    foreach ($importData['entities'] as $datum) {
      $this->logger->notice($this->t('[@key/@count] - "@label" - @status (@url)', $datum));
    }
  }

  /**
   * Ask and return user choice.
   *
   * @param string $question
   *   The question.
   * @param array $options
   *   THe options.
   * @param string $defaultValue
   *   The default value.
   *
   * @return string
   *   The user selection.
   *
   * @throws \Drush\Exceptions\UserAbortException
   */
  protected function choice($question, array $options, $defaultValue): string {
    return $this->io()->choice(
      $question,
      $options,
      array_search($defaultValue, array_keys($options)) + 1
    );
  }

  /**
   * Validator can be null.
   *
   * @param string $value
   *   The value.
   *
   * @return string
   *   The value.
   */
  public function canBeNull($value) {
    return $value;
  }

}
