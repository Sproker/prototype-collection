<?php

namespace Drupal\quiz\Entity;

use Drupal;
use Drupal\quiz_multichoice\Plugin\quiz\QuizQuestion\MultichoiceResponse;
use Drupal\quiz_truefalse\Plugin\quiz\QuizQuestion\TrueFalseResponse;
use function _quiz_question_response_get_instance;
use function drupal_static;
use function node_load;

/**
 *
 *
 * Each question type must store its own response data and be able to calculate
 * a score for that data.
 */
trait QuizResultAnswerEntityTrait {

  /**
   * {@inheritdoc}
   */
  public function getQuizQuestion(): QuizQuestion {
    return Drupal::entityTypeManager()
      ->getStorage('quiz_question')
      ->loadRevision($this->get('question_vid')->getString());
  }

  /**
   * {@inheritdoc}
   */
  public function getQuizResult(): QuizResult {
    return $this->get('result_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuizResultId(): int {
      return $this->get('result_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function isEvaluated(): bool {
    return (bool) $this->get('is_evaluated')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function isCorrect(): bool {
    return ($this->getMaxScore() == $this->getPoints());
  }

  /**
   * {@inheritdoc}
   *
   * This is marked as final to make sure that no question overrides this and
   * causes reporting issues.
   */
  public final function getPoints(): int {
    return (int) $this->get('points_awarded')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestionRelationship(): ?QuizQuestionRelationship {
    $quiz_result = $this->getQuizResult();
    $relationships = Drupal::entityTypeManager()
      ->getStorage('quiz_question_relationship')
      ->loadByProperties([
        'quiz_id' => $quiz_result->get('qid')->getString(),
        'quiz_vid' => $quiz_result->get('vid')->getString(),
        'question_id' => $this->get('question_id')->getString(),
        'question_vid' => $this->get('question_vid')->getString(),
      ]);
    if ($relationships) {
      return reset($relationships);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxScore(): int {
    $quiz = $this->getQuizResult()->getQuiz();

    if ($quiz->get('randomization')->getString() == 2) {
      return (int) $quiz->get('max_score_for_random')->getString();
    }

    if ($quiz->get('randomization')->getString() == 3) {
      /* @var $terms Drupal\paragraphs\Entity\Paragraph[] */
      $terms = $quiz->get('quiz_terms')->referencedEntities();
      $total_questions = [];
      foreach ($terms as $term) {
        if ($term->get('quiz_question_tid')->getString() == $this->get('tid')->getString()) {
          return $term->get('quiz_question_max_score')->getString();
        }
      }
    }

    if ($relationship = $this->getQuestionRelationship()) {
      return (int) $relationship->get('max_score')->getString();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReportForm(): array {
    // Add general data, and data from the question type implementation.
    $form = [];

    $form['display_number'] = [
      '#type' => 'value',
      '#value' => $this->display_number,
    ];

    $form['score'] = $this->getReportFormScore();
    $form['answer_feedback'] = $this->getReportFormAnswerFeedback();
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues(): array {
    $data = [];

    $data[] = [
      'choice' => 'True',
      'attempt' => 'Did the user choose this?',
      'correct' => 'Was their answer correct?',
      'score' => 'Points earned for this answer',
      'answer_feedback' => 'Feedback specific to the answer',
      'question_feedback' => 'General question feedback for any answer',
      'solution' => 'Is this choice the correct solution?',
      'quiz_feedback' => 'Quiz feedback at this time',
    ];

    return $data;
  }

  /**
   * Get the feedback form for the reportForm.
   *
   * @return array|false
   *   An renderable FAPI array, or FALSE if no answer form.
   */
  public function getReportFormAnswerFeedback() {
    $feedback = $this->get('answer_feedback')->getValue()[0];
    return [
      '#title' => t('Enter feedback'),
      '#type' => 'text_format',
      '#default_value' => $feedback['value'] ?: '',
      '#format' => $feedback['format'] ?: filter_default_format(),
      '#attributes' => ['class' => ['quiz-report-score']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function canReview(string $option): bool {
    $can_review = &drupal_static(__METHOD__, []);

    if (!isset($can_review[$option])) {
      $quiz_result = $this->getQuizResult();
      $can_review[$option] = $quiz_result->canReview($option);
    }

    return $can_review[$option];
  }

  /**
   * Implementation of getReportFormScore().
   *
   * @see QuizQuestionResponse::getReportFormScore()
   */
  public function getReportFormScore() {
    $score = ($this->isEvaluated()) ? $this->getPoints() : '';
    return [
      '#title' => 'Enter score',
      '#type' => 'number',
      '#default_value' => $score,
      '#min' => 0,
      '#max' => $this->getMaxScore(),
      '#attributes' => ['class' => ['quiz-report-score']],
      '#required' => TRUE,
      '#field_suffix' => '/ ' . $this->getMaxScore(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function viewsGetAnswers(array $result_answer_ids = []): array {
    $items = [];
    $qras = QuizResultAnswer::loadMultiple($result_answer_ids);
    foreach ($qras as $qra) {
      $items[$qra->getQuizResult()->id()][] = ['answer' => $qra->getResponse()];
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeightedRatio() {
    if ($this->getMaxScore() == 0) {
      return 0;
    }

    // getMaxScore() will get the relationship max score.
    // getMaximumScore() gets the unscaled question max score.
    return $this->getMaxScore() / $this->getQuizQuestion()->getMaximumScore();
  }

  /**
   * {@inheritdoc}
   */
  public function isAnswered(): bool {
    return (bool) !$this->get('answer_timestamp')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function isSkipped(): bool {
    return (bool) $this->get('is_skipped')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public final function setEvaluated($evaluated = TRUE) {
    return $this->set('is_evaluated', $evaluated);
  }

}
