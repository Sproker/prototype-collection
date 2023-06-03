<?php

namespace Drupal\quiz\Config\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Defines the list builder for Quiz entities.
 */
class QuizListBuilder extends EntityListBuilder {

  public function render() {
    $build = parent::render();
    $build['table']['#caption'] = $this->t('Quiz.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Quiz');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->toLink(NULL, 'edit-form');
    return $row + parent::buildRow($entity);
  }

  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    $operations['questions'] = [
      'title' => $this->t('Questions'),
      'weight' => 101,
      'url' => Url::fromRoute('quiz.questions', ['quiz' => $entity->id()]),
    ];

    $operations['results'] = [
      'title' => $this->t('Results'),
      'weight' => 102,
      'url' => Url::fromRoute('view.quiz_results.list', ['quiz' => $entity->id()]),
    ];
    return $operations;
  }

}
