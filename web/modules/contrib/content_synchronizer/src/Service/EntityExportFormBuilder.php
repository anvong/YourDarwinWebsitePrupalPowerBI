<?php

namespace Drupal\content_synchronizer\Service;

use Drupal\content_synchronizer\Entity\ExportEntity;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\system\MenuInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The entity export form builder.
 */
class EntityExportFormBuilder {

  use StringTranslationTrait;

  const SERVICE_NAME = "content_synchronizer.entity_export_form_builder";

  const ARCHIVE_PARAMS = 'archive';

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
   * Export Manager.
   *
   * @var \Drupal\content_synchronizer\Service\ExportManager
   */
  protected $exportManager;

  /**
   * Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The current url.
   *
   * @var \Drupal\Core\Url
   */
  protected $currentUrl;

  /**
   * EntityExportFormBuilder constructor.
   *
   * @param \Drupal\content_synchronizer\Service\ExportManager $exportManager
   *   The export manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The requeststack.
   */
  public function __construct(ExportManager $exportManager, RequestStack $requestStack) {
    $this->exportManager = $exportManager;
    $this->request = $requestStack->getCurrentRequest();
  }

  /**
   * Add the export form in the entity edit form, if the entity is exportable.
   */
  public function addExportFields(array &$form, FormStateInterface $formState) {
    if ($this->isEntityEditForm($form, $formState)) {
      $this->addExportFieldsToEntityForm($form, $formState);
    }
  }

  /**
   * Return true if the form needs to have an export field.
   *
   * @param array $form
   *   The form build array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The formState array.
   *
   * @return bool
   *   The result.
   */
  protected function isEntityEditForm(array &$form, FormStateInterface $formState) {
    /** @var \Drupal\Core\Entity\EntityForm $formObject */
    $formObject = $formState->getFormObject();

    if ($formObject instanceof EntityForm) {
      if (in_array($formObject->getOperation(), ['edit', 'default'])) {
        $entity = $formObject->getEntity();
        if (strpos(get_class($entity), 'content_synchronizer') === FALSE) {
          if ($objectId = $entity->id()) {
            return $objectId !== NULL;
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * Add exports fields to the entity form.
   *
   * @param array $form
   *   The form build array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  protected function addExportFieldsToEntityForm(array &$form, FormStateInterface $formState) {
    $entity = $formState->getFormObject()->getEntity();
    $isBundle = $entity instanceof ConfigEntityBundleBase;
    if ($entity instanceof ContentEntityBase || $isBundle) {
      $this->initExportForm($entity, $form, $formState, $isBundle);
    }

    // Menu.
    if ($entity instanceof MenuInterface) {
      $this->addMenuExportForm($entity, $form, $formState);
    }
  }

  /**
   * Init the export form.
   */
  protected function initExportForm(EntityInterface $entity, array &$form, FormStateInterface $formState, $isBundle = FALSE) {
    /** @var ExportManager $exportManager */
    $exportManager = \Drupal::service(ExportManager::SERVICE_NAME);

    $form['content_synchronizer'] = [
      '#type'   => 'details',
      '#title'  => $isBundle ? $this->t('Export all entities of @bundle bundle', ['@bundle' => $entity->label()]) : $this->t('Export'),
      '#group'  => 'advanced',
      '#weight' => '100',
    ];

    // Init labels.
    $quickExportButton = $isBundle ? $this->t('Export entities') : $this->t('Export entity');
    $addToExportButton = $isBundle ? $this->t('Or add the entities to an existing export') : $this->t('Or add the entity to an existing export');

    $form['content_synchronizer']['quick_export'] = [
      '#markup' => '<a href="' . $this->getQuickExportUrl($entity) . '" class="button button--primary">' . $quickExportButton . '</a>',
    ];

    $exportsListOptions = $exportManager->getExportsListOptions();
    if (!empty($exportsListOptions)) {
      $form['content_synchronizer']['exports_list'] = [
        '#type'          => 'checkboxes',
        '#title'         => $addToExportButton,
        '#options'       => $exportsListOptions,
        '#default_value' => array_keys($exportManager->getEntitiesExport($entity)),
      ];

      $form['content_synchronizer']['add_to_export'] = [
        '#type'   => 'submit',
        '#value'  => $this->t('Add to the export'),
        '#submit' => [get_called_class() . '::onAddToExport'],
      ];
    }
  }

  /**
   * Get the batch URL.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   THe entity to download.
   *
   * @return string
   *   THe batch url.
   */
  protected function getQuickExportUrl(EntityInterface $entity) {
    $url = Url::fromRoute('content_synchronizer.quick_export');
    $parameters = [
      'destination'  => \Drupal::request()->getRequestUri(),
      'entityTypeId' => $entity->getEntityTypeId(),
      'entityId'     => $entity->id(),
    ];

    return $url->toString() . '?' . http_build_query($parameters);
  }

  /**
   * Add entity to an existing entity export.
   *
   * @param array $form
   *   The form build array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public static function onAddToExport(array &$form, FormStateInterface $formState) {
    $exportsList = ExportEntity::loadMultiple($formState->getValue('exports_list'));
    $entity = $formState->getFormObject()->getEntity();

    if ($entity instanceof ConfigEntityBundleBase) {
      if ($entitiesToExport = self::getEntitiesFromBundle($entity)) {
        /** @var \Drupal\content_synchronizer\Entity\ExportEntity $export */
        foreach (ExportEntity::loadMultiple() as $export) {
          foreach ($entitiesToExport as $entityToExport) {
            if (array_key_exists($export->id(), $exportsList)) {
              $export->addEntity($entityToExport);
            }
          }
        }
      }
    }
    else {
      /** @var \Drupal\content_synchronizer\Entity\ExportEntity $export */
      foreach (ExportEntity::loadMultiple() as $export) {
        if (array_key_exists($export->id(), $exportsList)) {
          $export->addEntity($entity);
        }
        else {
          $export->removeEntity($entity);
        }
      }
    }
  }

  /**
   * Get the list of entities from a bundle entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityBundleBase $entity
   *   The bundle entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|null
   *   The entities of the bundle.
   */
  public static function getEntitiesFromBundle(ConfigEntityBundleBase $entity) {
    $entityType = $entity->getEntityType()->getBundleOf();
    $bundleKey = \Drupal::entityTypeManager()
      ->getDefinitions()[$entityType]->getKeys()['bundle'];

    $query = \Drupal::entityQuery($entityType)
      ->condition($bundleKey, $entity->id());
    $entitiesIds = $query->execute();
    if (!empty($entitiesIds)) {
      return \Drupal::entityTypeManager()
        ->getStorage($entityType)
        ->loadMultiple($entitiesIds);
    }

    return NULL;
  }

  /**
   * Add form for menu items export.
   *
   * @param \Drupal\system\MenuInterface $entity
   *   The entity.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   THe formstate.
   */
  protected function addMenuExportForm(MenuInterface $entity, array &$form, FormStateInterface $formState) {
    /** @var ExportManager $exportManager */
    $exportManager = \Drupal::service(ExportManager::SERVICE_NAME);

    $form['content_synchronizer'] = [
      '#type'   => 'details',
      '#title'  => $this->t('Export'),
      '#group'  => 'advanced',
      '#weight' => '100',
    ];

    // Init labels.
    $quickExportButton = $this->t('Export entity');

    $form['content_synchronizer']['add_to_export'] = [
      '#type'   => 'submit',
      '#value'  => $quickExportButton,
      '#submit' => [get_called_class() . '::onAddMenuToExport'],
    ];
  }

  /**
   * Create an export and add Menu Items.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The formstate.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function onAddMenuToExport(array &$form, FormStateInterface $formState) {
    $menu = $formState->getFormObject()->getEntity();

    if ($menu instanceof Menu) {
      // Create a menu export.
      $exportEntity = ExportEntity::create([
        'name' => $menu->label(),
      ]);
      $exportEntity->save();

      // Add all menu items.
      $menuLinkTree = \Drupal::service('menu.link_tree');
      $tree = $menuLinkTree->load($menu->id(), new MenuTreeParameters());
      static::me()->addMenuElementsToExportEntity($tree, $exportEntity);

      $formState->setRedirectUrl(Url::fromRoute('entity.export_entity.canonical', ['export_entity' => $exportEntity->id()]));
    }
  }

  /**
   * Menu link content to export entity, recursively.
   *
   * @param array $tree
   *   The tree.
   * @param \Drupal\content_synchronizer\Entity\ExportEntity $exportEntity
   *   The export entity.
   */
  protected function addMenuElementsToExportEntity(array $tree, ExportEntity $exportEntity) {
    foreach ($tree as $item) {
      if (isset($item->link->getPluginDefinition()['metadata']['entity_id'])) {
        if ($menuItem = MenuLinkContent::load($item->link->getPluginDefinition()['metadata']['entity_id'])) {
          $exportEntity->addEntity($menuItem);
        }
      }

      if ($item->subtree && count($item->subtree)) {
        $this->addMenuElementsToExportEntity($item->subtree, $exportEntity);
      }
    }
  }

}
