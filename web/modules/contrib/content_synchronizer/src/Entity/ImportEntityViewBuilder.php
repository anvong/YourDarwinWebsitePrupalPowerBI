<?php

namespace Drupal\content_synchronizer\Entity;

use Drupal\content_synchronizer\Form\LaunchImportForm;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Theme\Registry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View for Import entity.
 */
class ImportEntityViewBuilder extends EntityViewBuilder {

  /**
   * Form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  private $formBuilder;

  /**
   * ExportEntityViewBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repo.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Theme\Registry|null $theme_registry
   *   The theme registry.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface|null $entity_display_repository
   *   The entity Display repo.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityRepositoryInterface $entity_repository, LanguageManagerInterface $language_manager, Registry $theme_registry = NULL, EntityDisplayRepositoryInterface $entity_display_repository = NULL, FormBuilderInterface $formBuilder = NULL) {
    parent::__construct($entity_type, $entity_repository, $language_manager, $theme_registry, $entity_display_repository);
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('theme.registry'),
      $container->get('entity_display.repository'),
      $container->get('form_builder')
    );
  }

  /**
   * Render the view of the entity.
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL) {

    $formState = new FormState();
    $formState->addBuildInfo('import', $entity);

    return $this->formBuilder->buildForm(LaunchImportForm::class, $formState);
  }

}
