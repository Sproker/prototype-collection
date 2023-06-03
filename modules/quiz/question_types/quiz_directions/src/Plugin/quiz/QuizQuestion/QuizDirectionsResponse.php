<?php

namespace Drupal\quiz_directions\Plugin\quiz\QuizQuestion;

use Drupal\quiz\Entity\QuizResultAnswer;

/**
 * Extension of QuizQuestionResponse.
 */
class QuizDirectionsResponse extends QuizResultAnswer {

  /**
   * {@inheritdoc}
   */
  public function score(array $values): ?int {
    // We have scored the question.
    $this->setEvaluated();
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function isCorrect(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    return TRUE;
  }

}
