<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines the list builder for quiz results.
 */
class QuizResultListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#caption'] = t('Quiz results.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['quiz'] = $this->t('Quiz');
    $header['user'] = $this->t('User');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    assert($entity instanceof QuizResult);
    $row['quiz'] = $entity->getOwner()->toLink();
    $row['user']['data'] = [
      '#theme' => 'username',
      '#account' => $entity->getOwner(),
    ];
    return $row + parent::buildRow($entity);
  }

}
