<?php

namespace Drupal\quiz;

use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizQuestionRelationship;
use Drupal\quiz\Entity\QuizResult;

/**
 * Provides an interface for quiz answers.
 *
 * Each question type must store its own answer data and be able to calculate
 * a score for that data.
 */
interface QuizAnswerInterface {

  /**
   * Get the question of this question answer.
   *
   * @return \Drupal\quiz\Entity\QuizQuestion
   */
  public function getQuizQuestion(): QuizQuestion;

  /**
   * Get the result of this question response.
   *
   * @return \Drupal\quiz\Entity\QuizResult
   */
  public function getQuizResult(): QuizResult;

  /**
   * Get the result ID of this question response.
   *
   * @return int
   */
  public function getQuizResultId(): int;

  /**
   * Indicate whether the response has been evaluated (scored) yet.
   *
   * Questions that require human scoring (e.g. essays) may need to manually
   * toggle this.
   *
   * @return bool
   */
  public function isEvaluated(): bool;

  /**
   * Check to see if the answer is marked as correct.
   *
   * This default version returns TRUE if the score is equal to the maximum
   * possible score. Each question type can determine on its own if the question
   * response is "correct". For example a multiple choice question with 4
   * correct answers could be considered correct in different configurations.
   *
   * @return bool
   */
  public function isCorrect(): bool;

  /**
   * Get the scaled awarded points.
   *
   * @return int
   *   The user's scaled awarded points for this question.
   */
  public function getPoints(): int;

  /**
   * Get the related question relationship from this quiz result answer.
   *
   * @return \Drupal\quiz\Entity\QuizQuestionRelationship|NULL
   */
  public function getQuestionRelationship(): ?QuizQuestionRelationship;

  /**
   * Get the weighted max score of this question response. This is the score
   * that is entered on the manage questions screen. For example if a multiple
   * choice question is worth 4 points, but 8 points are entered on the manage
   * questions screen, 8 points is returned here.
   *
   * @return int
   *   The weighted max score of this question response.
   */
  public function getMaxScore(): int;

  /**
   * Creates the report form for the admin pages.
   *
   * @return array
   *   An renderable FAPI array
   */
  public function getReportForm(): array;

  /**
   * Get the response part of the report form.
   *
   * @return array
   *   Array of response data, with each item being an answer to a response. For
   *   an example, see MultichoiceResponse::getFeedbackValues(). The sub items
   *   are keyed by the feedback type. Providing a NULL option means that
   *   feedback will not be shown. See an example at
   *   LongAnswerResponse::getFeedbackValues().
   */
  public function getFeedbackValues(): array;

  /**
   * Calculate the unscaled score in points for this question response.
   *
   * @param array $values
   *   A part of form state values with the question input from the user.
   *
   * @return int|NULL
   *   The unscaled point value of the answer. If a point value is final,
   *   questions should make sure to run setEvaluated(). return NULL if the
   *   answer is not automatically scored.
   */
  public function score(array $values): ?int;

  /**
   * Get the user's response.
   *
   * @return mixed
   *   The answer given by the user
   */
  public function getResponse();

  /**
   * Can the quiz taker view the requested review?
   *
   * @param string $option
   *   An option key.
   *
   * @return bool
   */
  public function canReview(string $option): bool;

  /**
   * Get the weighted score ratio.
   *
   * This returns the ratio of the weighted score of this question versus the
   * question score. For example, if the question is worth 10 points in the
   * associated quiz, but it is a 3 point multichoice question, the weighted
   * ratio is 3.33.
   *
   * This is marked as final to make sure that no question overrides this and
   * causes reporting issues.
   *
   * @return float|int
   *   The weight of the question
   */
  public function getWeightedRatio();

  /**
   * Indicate whether the response has been evaluated (scored) yet.
   *
   * Questions that require human scoring (e.g. essays) may need to manually
   * toggle this.
   *
   * @return bool
   */
  public function isAnswered(): bool;

  /**
   * Indicate if the question was marked as skipped.
   *
   * @return bool
   */
  public function isSkipped(): bool;

  /**
   * Set the answer to evaluated, if the point value is final and does not
   * require instructor action.
   *
   * @param bool $evaluated
   *   If the answer is evaluated.
   *
   * @return $this
   */
  public function setEvaluated($evaluated = TRUE);

  /**
   * Get answers for a question in a result.
   *
   * This static method assists in building views for the mass export of
   * question answers.
   *
   * It is not as easy as instantiating all the question responses and returning
   * the answer. To do this in views at scale we have to gather the data
   * carefully.
   *
   * This base method provides a very poor way of gathering the data.
   *
   * @see views_handler_field_prerender_list for the expected return value.
   *
   * @see MultichoiceResponse::viewsGetAnswers() for a correct approach
   * @see TrueFalseResponse::viewsGetAnswers() for a correct approach
   */
  public static function viewsGetAnswers(array $result_answer_ids = []): array;

}
