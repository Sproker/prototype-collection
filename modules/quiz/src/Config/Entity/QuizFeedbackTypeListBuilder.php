<?php

namespace Drupal\quiz\Config\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines the list builder for quiz question types.
 */
class QuizFeedbackTypeListBuilder extends ConfigEntityListBuilder {

  public function render() {
    $build = parent::render();
    $build['table']['#caption'] = $this->t('Feedback types.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['type'] = $this->t('Feedback time');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['type'] = $entity->toLink(NULL, 'edit-form');
    return $row + parent::buildRow($entity);
  }


  /**
   * {@inheritdoc}
   */
  function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $operations['conditions'] = [
      'title' => $this->t('Conditions'),
      'url' => Url::fromRoute('entity.quiz_feedback_type.conditions', ['quiz_feedback_type' => $entity->id()]),
      'weight' => 50,
    ];
    return $operations;
  }

}
