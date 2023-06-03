<?php

namespace Drupal\quiz\Config\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\quiz\Entity\QuizQuestionType;

/**
 * Defines the list builder for quiz question entities.
 */
class QuizQuestionListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#caption'] = $this->t('Questions.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Title');
    $header['type'] = $this->t('Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = $entity->toLink(NULL, 'edit-form');
    $row['type'] = QuizQuestionType::load($entity->bundle())->label();
    return $row + parent::buildRow($entity);
  }

}
