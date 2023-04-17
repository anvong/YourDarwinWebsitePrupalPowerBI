<?php

namespace Drupal\content_synchronizer\Controller;

use Drupal\content_synchronizer\Processors\ExportEntityWriter;
use Drupal\content_synchronizer\Service\ArchiveDownloader;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Core\File\FileSystem;

/**
 * Class ArchiveDownloaderController.
 *
 * @package Drupal\content_synchronizer\Controller
 */
class ArchiveDownloaderController extends ControllerBase {

  /**
   * File System.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * ArchiveDownloaderController constructor.
   *
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   The file system.
   */
  public function __construct(FileSystem $fileSystem) {
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system')
    );
  }

  /**
   * Download the tmp file.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response|null
   *   The download response.
   */
  public function downloadArchive(Request $request) {
    if ($request->query->has(ArchiveDownloader::ARCHIVE_PARAMS)) {
      $fileUri = ExportEntityWriter::getGeneratorDir() . $request->query->get(ArchiveDownloader::ARCHIVE_PARAMS);

      if (file_exists($fileUri)) {
        $response = new Response(file_get_contents($fileUri));

        $disposition = $response->headers->makeDisposition(
          ResponseHeaderBag::DISPOSITION_ATTACHMENT,
          basename($fileUri)
        );
        $response->headers->set('Content-Disposition', $disposition);

        // Delete temporary file.
        $repName = substr($fileUri, 0, strrpos($fileUri, '/'));
        $this->fileSystem->deleteRecursive($repName);

        return $response;
      }
    }

    return NULL;
  }

}
