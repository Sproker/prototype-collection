<?php

namespace Drupal\quiz_directions\Plugin\quiz\QuizQuestion;

use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizResultAnswer;

/**
 * @file
 * Quiz_directions classes.
 *
 * This module uses the question interface to define something which is
 * actually not a question.
 *
 * A Quiz Directions node is a placeholder for adding directions to a quiz. It
 * can be inserted any number of times into a quiz. Example uses may include:
 *   - Initial quiz-wide directions;
 *   - Section directions, e.g. "The next five questions are multiple choice,
 *     please..." (Won't work if the question order is randomized);
 *   - Final confirmation, e.g. "You have answered all questions. Click submit
 *     to submit this quiz.";
 */

/**
 * @QuizQuestion (
 *   id = "directions",
 *   label = @Translation("Directions question"),
 *   handlers = {
 *     "response" = "\Drupal\quiz_directions\Plugin\quiz\QuizQuestion\QuizDirectionsResponse"
 *   }
 * )
 */
class QuizDirectionsQuestion extends QuizQuestion {

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $quizQuestionResultAnswer): array {
    $form = parent::getAnsweringForm($form_state, $quizQuestionResultAnswer);
    $form['directions'] = [
      '#type' => 'hidden',
      '#value' => '1',
    ];
    return $form;
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
