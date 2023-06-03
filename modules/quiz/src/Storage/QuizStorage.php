<?php

namespace Drupal\quiz\Storage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

class QuizStorage extends SqlContentEntityStorage {

  function doPreSave(EntityInterface $entity) {
    $max_score = 0;

    if ($entity->get('randomization')->value < 2) {
      $entity->set('number_of_random_questions', 0);
    }

    if ($entity->get('randomization')->value == 2) {
      $max_score = $entity->get('number_of_random_questions')->value * $entity->get('max_score_for_random')->value;
      $entity->set('max_score', $max_score);
    }

    if ($entity->get('randomization')->value == 3) {
      $num_questions = 0;
      foreach ($entity->get('quiz_terms')->referencedEntities() as $ref) {
        $max_score += $ref->get('quiz_question_max_score')->value * $ref->get('quiz_question_number')->value;
        $num_questions += $ref->get('quiz_question_number')->value;
      }
      $entity->set('number_of_random_questions', $num_questions);
      $entity->set('max_score', $max_score);
    }

    return parent::doPreSave($entity);
  }

  protected function doPostSave(EntityInterface $entity, $update) {
    /* @var $entity \Drupal\quiz\Entity\Quiz */

    if (isset($entity->old_vid)) {
      // Duplicate of quiz.
      $old_vid = $entity->old_vid;
    }

    if (!$entity->isNew() && $entity->isNewRevision()) {
      // New revision of quiz.
      $old_vid = $entity->getLoadedRevisionId();
    }

    if (isset($old_vid)) {
      $original = \Drupal::entityTypeManager()
        ->getStorage('quiz')
        ->loadRevision($old_vid);
      $entity->copyFromRevision($original);
    }

    return parent::doPostSave($entity, $update);
  }

}
