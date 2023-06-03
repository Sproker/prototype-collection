<?php

namespace Drupal\quiz\Entity;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use function _quiz_get_quiz_name;
use function db_query;
use function db_query_range;
use function entity_load;
use function filter_default_format;

/**
 * A trait all Quiz question strongly typed entity bundles must use.
 */
trait QuizQuestionEntityTrait {

  /*
   * QUESTION IMPLEMENTATION FUNCTIONS
   *
   * This part acts as a contract(/interface) between the question-types and the
   * rest of the system.
   *
   * Question types are made by extending these generic methods and abstract
   * methods.
   */

  /**
   * Allow question types to override the body field title.
   *
   * @return string
   *   The title for the body field.
   */
  public function getBodyFieldTitle() {
    return t('Question');
  }

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $quizQuestionResultAnswer): array {
    $form = [];
    $form['#element_validate'] = [[static::class, 'getAnsweringFormValidate']];
    return $form;
  }

  /**
   * Finds out if a question has been answered or not.
   *
   * This function also returns TRUE if a quiz that this question belongs to
   * have been answered. Even if the question itself haven't been answered.
   * This is because the question might have been rendered and a user is about
   * to answer it...
   *
   * @return bool
   *   TRUE if question has been answered or is about to be answered...
   */
  public function hasBeenAnswered() {
    $result = \Drupal::entityQuery('quiz_result_answer')
      ->condition('question_vid', $this->getRevisionId())
      ->range(0, 1)
      ->execute();
    return !empty($result);
  }

  /**
   * Determines if the user can view the correct answers.
   *
   * @return true|null
   *   TRUE if the view may include the correct answers to the question.
   */
  public function viewCanRevealCorrect() {
    $user = \Drupal::currentUser();

    $reveal_correct[] = user_access_test_user_access('view any quiz question correct response');
    $reveal_correct[] = ($user->id() == $this->node->uid);
    if (array_filter($reveal_correct)) {
      return TRUE;
    }
  }

  /**
   * Utility function that returns the format of the node body.
   *
   * @return string|null
   *   The format of the node body
   */
  protected function getFormat() {
    $node = isset($this->node) ? $this->node : $this->question;
    $body = field_get_items('node', $node, 'body');
    return isset($body[0]['format']) ? $body[0]['format'] : NULL;
  }

  /**
   * This may be overridden in subclasses. If it returns true,
   * it means the max_score is updated for all occurrences of
   * this question in quizzes.
   *
   * @return bool
   */
  protected function autoUpdateMaxScore() {
    return FALSE;
  }

  /**
   * Validate a user's answer.
   *
   * @param array $element
   *   The form element of this question.
   * @param mixed $form_state
   *   Form state.
   */
  public static function getAnsweringFormValidate(array &$element, FormStateInterface $form_state) {
    $quiz = \Drupal::entityTypeManager()
      ->getStorage('quiz')
      ->loadRevision($form_state->getCompleteForm()['#quiz']->getRevisionId());

    $qqid = $element['#array_parents'][1];

    // There was an answer submitted.
    /* @var $qra QuizResultAnswer */
    $qra = $element['#quiz_result_answer'];

    // Temporarily score the answer.
    $score = $qra->score($form_state->getValue('question')[$qqid]);

    // @todo kinda hacky here, we have to scale it temporarily so isCorrect()
    // works
    $qra->set('points_awarded', $qra->getWeightedRatio() * $score);

    if ($quiz->get('repeat_until_correct')->getString() && !$qra->isCorrect() && $qra->isEvaluated()) {
      $form_state->setErrorByName('', t('The answer was incorrect. Please try again.'));

      // Show feedback after incorrect answer.
      $view_builder = Drupal::entityTypeManager()
        ->getViewBuilder('quiz_result_answer');
      $element['feedback'] = $view_builder->view($qra);
      $element['feedback']['#weight'] = 100;
      $element['feedback']['#parents'] = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isGraded(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasFeedback(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isQuestion(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(QuizResult $quiz_result): ?QuizResultAnswer {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('quiz_result_answer')
      ->loadByProperties([
        'result_id' => $quiz_result->id(),
        'question_id' => $this->id(),
        'question_vid' => $this->getRevisionId(),
      ]);
    return reset($entities);
  }

}
