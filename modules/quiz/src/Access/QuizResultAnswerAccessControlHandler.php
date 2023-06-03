<?php

namespace Drupal\quiz\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\UncacheableEntityAccessControlHandler;
use Drupal\quiz\Entity\QuizResultAnswer;

class QuizResultAnswerAccessControlHandler extends UncacheableEntityAccessControlHandler {

  /**
   * Control access to taking a question or viewing feedback within a quiz.
   *
   * {@inheritdoc}
   */
  function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation == 'take') {
      /* @var $entity QuizResultAnswer */
      $quiz = $entity->getQuizResult()->getQuiz();
      /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
      $quiz_session = \Drupal::service('quiz.session');
      if (!$quiz_session->isTakingQuiz($quiz)) {
        // No access if the user isn't taking this quiz.
        return AccessResultForbidden::forbidden();
      }

      if ($quiz->get('allow_jumping')->getString()) {
        // Access to go to any question. Yay.
        return AccessResultAllowed::allowed();
      }

      $qra_last = $entity->getPrevious();
      $qra_next = $entity->getNext();

      if (!$quiz->get('backwards_navigation')->getString()) {
        // No backwards navigation.
        if ($entity->isAnswered()) {
          // This question was answered already.
          return AccessResultForbidden::forbidden();
        }
      }

      // Enforce normal navigation.
      if (!$qra_last || $qra_last->isAnswered()) {
        // Previous answer was submitted or this is the first question.
        return AccessResultAllowed::allowed();
      }

      return AccessResultForbidden::forbidden();
    }

    if ($operation == 'feedback') {
      if ($entity->isAnswered()) {
        // The user has answered this question, so they can see the feedback.
        return AccessResultAllowed::allowed();
      }

      // If they haven't answered the question, we want to make sure feedback is
      // blocked as it could be exposing correct answers.
      // @todo We may also want to check if they are viewing feedback for the
      // current question.
      return AccessResultForbidden::forbidden();
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
