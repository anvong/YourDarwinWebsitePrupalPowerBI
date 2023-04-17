<?php

namespace Drupal\content_synchronizer\Processors\Type;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\TypedData\TypedData;

/**
 * Class DefaultTypeProcessor.
 *
 * @package Drupal\content_synchronizer\Processors\Type
 */
class DefaultTypeProcessor implements TypeProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return 'type_processor_default';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getExportedData(TypedData $propertyData) {
    $data = [];
    foreach ($propertyData as $value) {
      $data[] = $value->getValue();
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function initImportedEntity(EntityInterface $entityToImport, $propertyId, array $data) {
    $entityToImport->set($propertyId, $data[$propertyId]);
  }

}
