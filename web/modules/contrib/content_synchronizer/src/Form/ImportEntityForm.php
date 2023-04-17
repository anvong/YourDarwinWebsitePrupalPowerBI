<?php

namespace Drupal\content_synchronizer\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Import edit forms.
 *
 * @ingroup content_synchronizer
 */
class ImportEntityForm extends ContentEntityForm {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, DateFormatterInterface $dateFormatter = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /* @var $entity \Drupal\content_synchronizer\Entity\ImportEntity */
    $entity = $form_state->getFormObject()->getEntity();

    $defaultName = $entity ? $entity->label() : $this->t('Import - %date', [
      '%date' => $this->dateFormatter->format(time()),
    ]);
    $form['name']['widget'][0]['value']['#default_value'] = $defaultName;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\content_synchronizer\Entity\ImportEntity $entity */
    $entity = &$this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Import.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Import.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.import_entity.canonical', ['import_entity' => $entity->id()]);
    return $status;
  }

}
