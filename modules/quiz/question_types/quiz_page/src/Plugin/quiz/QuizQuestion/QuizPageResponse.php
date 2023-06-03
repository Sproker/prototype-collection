<?php

namespace Drupal\quiz_page\Plugin\quiz\QuizQuestion;

use Drupal\quiz\Entity\QuizResultAnswer;

/**
 * Extension of QuizQuestionResponse.
 */
class QuizPageResponse extends QuizResultAnswer {

  /**
   * {@inheritdoc}
   */
  public function score(array $values): ?int {
    // We have scored the question.
    $this->setEvaluated();
    return NULL;
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
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getReportForm(): array {
    return [
      '#no_report' => TRUE,
    ];
  }

}
