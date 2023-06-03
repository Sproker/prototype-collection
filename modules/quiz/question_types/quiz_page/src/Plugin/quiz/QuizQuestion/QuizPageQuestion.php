<?php

namespace Drupal\quiz_page\Plugin\quiz\QuizQuestion;

use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizResultAnswer;

/**
 * @file
 * Quiz page classes.
 *
 * This module uses the question interface to define something which is
 * actually not a question.
 *
 * A Quiz page node is a placeholder for presenting multiple questions
 * on the same page.
 */

/**
 * @QuizQuestion (
 *   id = "page",
 *   label = @Translation("Quiz page"),
 *   handlers = {
 *     "response" = "\Drupal\quiz_page\Plugin\quiz\QuizQuestion\QuizPageResponse"
 *   }
 * )
 */
class QuizPageQuestion extends QuizQuestion {

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $quizQuestionResultAnswer): array {
    $element = [
      '#type' => 'hidden',
    ];
    return $element;
  }

  /**
   * Implementation of getCreationForm().
   *
   * @see QuizQuestion::getCreationForm()
   */
  public function getCreationForm(array &$form_state = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMaximumScore(): int {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function isGraded(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasFeedback(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isQuestion(): bool {
    return FALSE;
  }

}
