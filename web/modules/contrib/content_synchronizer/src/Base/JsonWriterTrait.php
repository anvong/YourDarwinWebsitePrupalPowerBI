<?php

namespace Drupal\content_synchronizer\Base;

use Drupal\Core\File\FileSystem;
use Drupal\file\Entity\File;

/**
 * Json writer tool.
 */
trait JsonWriterTrait {

  /**
   * File System.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Save json in the destination file.
   */
  protected function writeJson($data, $destination) {

    // Create dir :
    $dir = explode('/', $destination);
    array_pop($dir);
    $dir = implode('/', $dir);
    $this->createDirectory($dir);

    $this->fileSystem()->prepareDirectory($dir, FileSystem::CHMOD_DIRECTORY);
    $uri = $this->fileSystem()->saveData(json_encode($data), $destination, FileSystem::EXISTS_REPLACE);

    File::create([
      'uri' => $uri,
      'status' => FILE_STATUS_PERMANENT,
    ])->save();
  }

  /**
   * Get json decode data from a file.
   */
  protected function getDataFromFile($path) {
    if (file_exists($path)) {
      return json_decode(file_get_contents($path), TRUE);
    }
    return NULL;
  }

  /**
   * Create a directory if not exists.
   */
  protected function createDirectory($dir) {
    if (!is_dir($dir)) {
      $this->fileSystem()->prepareDirectory($dir, FileSystem::CREATE_DIRECTORY);
    }
  }

  /**
   * Create a directory tree.
   */
  protected function createDirTreeForFileDest($destination, $root = '/') {
    $destinationItems = explode('/', $destination);
    $fileName = array_pop($destinationItems);

    // Create destination tree.
    foreach ($destinationItems as $dirItem) {
      $root .= '/' . $dirItem;
      $this->createDirectory($root);
    }

    return $root . '/' . $fileName;
  }

  /**
   * Return file system.
   *
   * @return \Drupal\Core\File\FileSystem|mixed
   *   The file system.
   */
  public function fileSystem() {
    return \Drupal::service('file_system');
  }

}
