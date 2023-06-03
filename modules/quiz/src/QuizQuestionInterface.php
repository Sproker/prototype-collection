<?php

namespace Drupal\quiz;

use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizResult;
use Drupal\quiz\Entity\QuizResultAnswer;

/**
 * Provides an interface for quiz questions.
 */
interface QuizQuestionInterface {

  /**
   * Get the form through which the user will answer the question.
   *
   * Question types should populate the form with selected values from the
   * current result if possible.
   *
   * @param FormStateInterface $form_state
   *   Form state.
   * @param QuizResultAnswer $answer
   *   The quiz result answer.
   *
   * @return array
   *   Form array.
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $answer): array;

  /**
   * Get the maximum possible score for this question.
   *
   * @return int
   */
  public function getMaximumScore(): int;

  /**
   * Is this question graded?
   *
   * Questions like Quiz Directions, Quiz Page, and Scale are not.
   *
   * By default, questions are expected to be gradeable
   *
   * @return bool
   */
  public function isGraded(): bool;

  /**
   * Does this question type give feedback?
   *
   * Questions like Quiz Directions and Quiz Pages do not.
   *
   * By default, questions give feedback
   *
   * @return bool
   */
  public function hasFeedback(): bool;

  /**
   * Is this "question" an actual question?
   *
   * For example, a Quiz Page is not a question, neither is a "quiz directions".
   *
   * Returning FALSE here means that the question will not be numbered, and
   * possibly other things.
   *
   * @return bool
   */
  public function isQuestion(): bool;

  /**
   * Get the response to this question in a quiz result.
   *
   * @param \Drupal\quiz\Entity\QuizResult $quiz_result
   *
   * @return QuizResultAnswer|NULL
   */
  public function getResponse(QuizResult $quiz_result): ?QuizResultAnswer;

}
