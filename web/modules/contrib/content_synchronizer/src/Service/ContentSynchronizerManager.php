<?php

namespace Drupal\content_synchronizer\Service;

use Drupal\content_synchronizer\Entity\ExportEntity;
use Drupal\content_synchronizer\Entity\ImportEntity;
use Drupal\content_synchronizer\Entity\ImportEntityInterface;
use Drupal\content_synchronizer\Form\LaunchImportForm;
use Drupal\content_synchronizer\Processors\ExportEntityWriter;
use Drupal\content_synchronizer\Processors\ExportProcessor;
use Drupal\content_synchronizer\Processors\ImportProcessor;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;

/**
 * ContentSynchronizerManager service.
 */
class ContentSynchronizerManager {

  use StringTranslationTrait;

  /**
   * Nom du service.
   *
   * @const string
   */
  const SERVICE_NAME = 'content_synchronizer.manager';

  /**
   * Retourne le singleton.
   *
   * @return static
   *   Le singleton.
   */
  public static function me() {
    return \Drupal::service(static::SERVICE_NAME);
  }

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * App root.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * Constructs a ContentSynchronizerManager object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type.
   * @param string $appRoot
   *   The app root.
   */
  public function __construct(DateFormatterInterface $date_formatter, FileSystemInterface $file_system, EntityTypeManagerInterface $entityTypeManager, $appRoot) {
    $this->dateFormatter = $date_formatter;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entityTypeManager;
    $this->appRoot = $appRoot;
  }

  /**
   * Create an import entity from a tar.gz file.
   *
   * @param string $tarGzFilePath
   *   The tar.gz file path.
   *
   * @return \Drupal\content_synchronizer\Entity\ImportEntityInterface|null
   *   The import entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createImportFromTarGzFilePath($tarGzFilePath): ImportEntityInterface {
    $importEntity = NULL;
    if (file_exists($tarGzFilePath)) {
      $extensionData = explode('.', $tarGzFilePath);
      if (end($extensionData) == 'gz') {
        $uri = $this->fileSystem->saveData(file_get_contents($tarGzFilePath), ImportEntity::ARCHIVE_DESTINATION . '/' . basename($tarGzFilePath));
        $this->fileSystem->chmod($uri, 775);
        $file = File::create([
          'uri'    => $uri,
          'status' => FILE_STATUS_PERMANENT,
        ]);

        if ($file) {
          $name = strip_tags($this->t('Drush import - %date', [
            '%date' => $this->dateFormatter->format(time()),
          ]));
          $importEntity = ImportEntity::create(
            [
              'name'                      => $name,
              ImportEntity::FIELD_ARCHIVE => $file,
            ]
          );
          $importEntity->save();
        }
      }
      else {
        throw new \Error('The file is not a .tar.gz archive');
      }
    }
    else {
      throw new \Error('No file found');
    }

    return $importEntity;
  }

  /**
   * Clean temporary files.
   *
   * @return array
   *   The list of deleted files.
   */
  public function cleanTemporaryFiles() {
    $deletedFiles = [];
    $path = $this->fileSystem->realpath(ExportEntityWriter::getGeneratorDir());
    foreach (glob($path . '/*') as $file) {
      if (is_dir($file)) {
        $this->fileSystem->deleteRecursive($file);
        $deletedFiles[] = $file;
      }
    }

    return $deletedFiles;
  }

  /**
   * Launch the specified export.
   *
   * @param int $exportId
   *   The id of the export to launch.
   * @param string $destination
   *   The path of the created file.
   *
   * @return array
   *   The destination data.
   */
  public function launchExport($exportId, $destination = '') {
    $export = ExportEntity::load($exportId);
    if (!$export) {
      throw new \Exception('No export with the ID ' . $exportId . ' found');
    }

    return $this->createExportFile($export->getEntitiesList(), $export->label(), $destination);
  }

  /**
   * Export a single entity.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param int $id
   *   The entity id.
   * @param string $destination
   *   The entity id.
   *
   * @return array
   *   The destination data.
   */
  public function exportEntity($entityTypeId, $id, $destination = '') {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $entityTypeManager */
    $entityTypeManager = $this->entityTypeManager->getStorage($entityTypeId);
    if (!$entityTypeManager) {
      throw new \Exception('No entity type "' . $entityTypeId . '" found');
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $entityTypeManager->load($id);
    if (!$entity) {
      throw new \Exception('No entity found [type:' . $entityTypeId . ', id:' . $id . ']');
    }

    return $this->createExportFile([$entity], NULL, $destination);
  }

  /**
   * Create a tar.gz file.
   *
   * @param array $entitiesToExport
   *   The entities list.
   * @param string|bool $label
   *   The id of the export.
   * @param string $destination
   *   The destination.
   *
   * @return array
   *   The data of the export.
   */
  public function createExportFile(array $entitiesToExport = [], $label = FALSE, $destination = '') {
    $writer = new ExportEntityWriter();
    $writer->initFromId(($label ?: time()));
    $processor = new ExportProcessor($writer);

    // Loop for log.
    $count = count($entitiesToExport);
    $data = [
      'destination' => '',
      'entities'    => [],
    ];
    foreach (array_values($entitiesToExport) as $key => $entity) {
      try {
        $processor->exportEntity($entity);
        $status = $this->t('Exported');
      }
      catch (\Exception $error) {
        $status = $this->t('Error');
      }

      $data['entities'][] = [
        '@key'    => $key + 1,
        '@count'  => $count,
        '@label'  => ExportEntityWriter::getEntityLabel($entity),
        '@status' => $status,
      ];
    }

    $tempArchive = $this->fileSystem->realpath($processor->closeProcess());
    // Deplace archive.
    $data['destination'] = $this->setDestination($destination, $tempArchive);

    return $data;
  }

  /**
   * Launch import from import id.
   */
  public function launchImport($importId, $publishType = ImportProcessor::DEFAULT_PUBLICATION_TYPE, $updateType = ImportProcessor::DEFAULT_UPDATE_TYPE) {
    // Check import id.
    $import = ImportEntity::load($importId);
    if (!$import) {
      throw new \Exception('No import entity found with id ' . $importId);
    }

    // Check publish type.
    $allowedCreateTypes = LaunchImportForm::getCreateOptions();
    if (!in_array($publishType, array_keys($allowedCreateTypes))) {
      throw new \Exception("Publish option must be in : " . implode('|', $allowedCreateTypes));
    }

    // Check update type.
    $allowedUpdateTypes = LaunchImportForm::getUpdateOptions();
    if (!in_array($updateType, array_keys($allowedUpdateTypes))) {
      throw new \Exception("Update option must be in : " . implode('|', $allowedUpdateTypes));
    }

    // Create import process.
    $importProcessor = new ImportProcessor($import);
    $importProcessor->setCreationType($publishType);
    $importProcessor->setUpdateType($updateType);

    // Loop into root entities.
    $rootEntities = $import->getRootsEntities();
    $count = count($rootEntities);
    $importData = [
      'entities' => [],
    ];
    foreach ($rootEntities as $key => $rootEntityData) {
      try {
        $entity = $importProcessor->importEntityFromRootData($rootEntityData);
        $status = array_key_exists('edit_url', $rootEntityData) ? $this->t('Updated') : $this->t('Created');
      }
      catch (\Exception $error) {
        $errorMessage = $error->getMessage();
        $status = $this->t('Error');
      }

      $importData['entities'][] = [
        '@key'          => $key + 1,
        '@count'        => $count,
        '@status'       => $status,
        '@label'        => $rootEntityData['label'],
        '@url'          => $entity ? $entity->toUrl()
          ->setAbsolute(TRUE)
          ->toString() : '',
        '@errorMessage' => $errorMessage ?: '',
      ];
    }

    // Close process.
    $import->removeArchive();

    return $importData;
  }

  /**
   * Update destination.
   *
   * @param string $destination
   *   The destination.
   * @param string $tempArchive
   *   The real path archive.
   *
   * @return string
   *   The final destination.
   */
  protected function setDestination($destination, $tempArchive) {
    $absolutePath = NULL;
    $resultDestination = NULL;

    if ($destination !== '') {
      $baseName = basename($destination);
      $path = str_replace($baseName, '', $destination);

      // Relative.
      if ($destination[0] !== '/') {
        $path = $destination[0] === '.' ? substr($path, 1) : $path;

        // Get options.
        $root = getopt('r:')['r'] ?: getopt('', ['root:'])['root'];
        if ($root) {
          $absolutePath = $root . '/' . $path;
        }
        elseif ($_SERVER && array_key_exists('PWD', $_SERVER)) {
          $absolutePath = $_SERVER['PWD'] . '/' . $path;
        }
      }
      // Absolute.
      else {
        $absolutePath = $destination;
      }

      if ($absolutePath) {
        if (!is_dir($absolutePath)) {
          $this->fileSystem->prepareDirectory($absolutePath, FileSystemInterface::CREATE_DIRECTORY);
        }

        $resultDestination = $absolutePath . '/' . $baseName;
      }
    }
    // Try destination.
    copy($tempArchive, $resultDestination);
    if (!file_exists($resultDestination)) {
      // Try root.
      $resultDestination = $this->appRoot . '/' . ($baseName ?: basename($tempArchive));
      copy($tempArchive, $resultDestination);
      if (!file_exists($resultDestination)) {
        // Destination is tmp.
        $resultDestination = $tempArchive;
      }
    }

    return $resultDestination;
  }

  /**
   * Return true if export id exists.
   *
   * @param string|int $id
   *   The export id.
   *
   * @return string|int
   *   The id if exists.
   *
   * @throws \Exception
   */
  public function exportIdExists($id) {
    if (is_null(ExportEntity::load($id))) {
      throw new \Exception('Export id [' . $id . '] does not exists');
    }
    return $id;
  }

  /**
   * Check if entity type exists.
   *
   * @param string $value
   *   The value.
   *
   * @return string
   *   The value.
   *
   * @throws \Exception
   */
  public function entityTypeExists($value) {
    $this->entityTypeManager->getStorage($value);
    return $value;
  }

  /**
   * Check if entity exists.
   *
   * @param string $value
   *   The value.
   * @param string $entityTypeId
   *   The entity type id.
   *
   * @return string
   *   The value.
   *
   * @throws \Exception
   */
  public function entityExists($value, $entityTypeId) {
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    if (is_null($storage->load($value))) {
      throw new \Exception('Entity does not exist');
    }
    return $value;
  }

  /**
   * Test if tar.gz exists.
   *
   * @param string $value
   *   The path.
   *
   * @return string
   *   The path
   */
  public function tarGzExists($value) {
    if (!file_exists($value)) {
      throw new \Exception($value . ' does not exist');
    }
    if (strpos($value, '.tar.gz') === FALSE) {
      throw new \Exception($value . ' is not a .tar.gz file');
    }

    return $value;
  }

}
