<?php

namespace Drupal\quiz_matching\Plugin\quiz\QuizQuestion;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Util\QuizUtil;
use function _quiz_question_response_get_instance;
use function db_delete;
use function db_insert;
use function db_query;
use function node_load;

/**
 * Extension of QuizQuestionResponse
 */
class MatchingResponse extends QuizResultAnswer {

  /**
   * Implementation of score().
   *
   * @see QuizQuestionResponse::score()
   */
  public function score(array $values): ?int {
    // Reset whatever was here already.
    $this->get('matching_user_answer')->setValue(NULL);

    foreach ($this->getQuizQuestion()->get('quiz_matching')->referencedEntities() as $paragraph) {
      //foreach ($values['answer']['user_answer'] as $left_id => $right_id) {
      $left_paragraph = $paragraph;
      $right_paragraph = \Drupal::entityTypeManager()
        ->getStorage('paragraph')
        ->loadRevision($values['answer']['user_answer'][$paragraph->getRevisionId()]);
      $new_paragraph = Paragraph::create([
        'type' => 'quiz_matching_answer',
        'matching_user_question' => $left_paragraph->getRevisionId(),
        'matching_user_answer' => $right_paragraph ? $right_paragraph->getRevisionId() : '',
      ]);
      $this->get('matching_user_answer')->appendItem($new_paragraph);
    }


    $answers = $this->getQuizQuestion()->getCorrectAnswer();

    foreach ($this->get('matching_user_answer')->referencedEntities() as $pair) {
      if ($pair->get('matching_user_answer')->getValue()) {
        $response[$pair->get('matching_user_question')->value] = $pair->get('matching_user_answer')->value;
      }
      else {
        $response[$pair->get('matching_user_question')->value] = -1;
      }
    }

    $score = 0;

    foreach ($answers as $vid => $answer) {
      if ($response[$vid] == $vid) {
        $score++;
      }
      elseif ($response[$vid] != -1 && $this->getQuizQuestion()->get('choice_penalty')->getString()) {
        $score -= 1;
      }
    }

    return $score > 0 ? $score : 0;
  }

  /**
   * Implementation of getResponse().
   *
   * @see QuizQuestionResponse::getResponse()
   */
  public function getResponse() {
    return $this->get('matching_user_answer')->referencedEntities();
  }

  /**
   * Implementation of getFeedbackValues().
   *
   * @see QuizQuestionResponse::getFeedbackValues()
   */
  public function getFeedbackValues(): array {
    $data = [];
    $answers = $this->getQuizQuestion()->getCorrectAnswer();

    foreach ($this->get('matching_user_answer')->referencedEntities() as $pair) {
      if ($pair->get('matching_user_answer')->getValue()) {
        $response[$pair->get('matching_user_question')
                    ->getValue()[0]['value']] = $pair->get('matching_user_answer')
                                                  ->getValue()[0]['value'];
      }
    }

    $penalty = $this->getQuizQuestion()->get('choice_penalty')->value;
    foreach ($this->getQuizQuestion()
      ->get('quiz_matching')
      ->referencedEntities() as $paragraph) {
      $vid = $paragraph->getRevisionId();

      $match = ($response[$vid] ?? -1) == $vid;
      $score = 0;

      if ($match) {
        $score = 1;
      }
      elseif ($penalty) {
        $score = -1;
      }

      $attempt_index = $response[$paragraph->getRevisionId()];
      $data[] = [
        'choice' => $paragraph->get('matching_question')->getString(),
        'attempt' => isset($attempt_index) && $answers[$attempt_index] ? $answers[$attempt_index]->get('matching_answer')->getString() : '',
        'correct' => ($response[$vid] ?? -1) == $vid ? QuizUtil::icon('correct') : QuizUtil::icon('incorrect'),
        'score' => $score,
        'answer_feedback' => 'placeholder', // @todo: $answer->get('matching_feedback')->getString(),
        'solution' => $paragraph->get('matching_answer')->getString(),
      ];
    }

    return $data;
  }

  /**
   * Get answers for a question in a result.
   *
   * This static method assists in building views for the mass export of
   * question answers.
   *
   * @see views_handler_field_prerender_list for the expected return value.
   */
  public static function viewsGetAnswers(array $result_answer_ids = []): array {
    $items = [];

    foreach (QuizResultAnswer::loadMultiple($result_answer_ids) as $qra) {
      foreach ($qra->getResponse() as $paragraph) {
        /* @var $paragraph Drupal\paragraphs\Entity\Paragraph */
        $q_vid = $paragraph->get('matching_user_question')->value;
        $qp = \Drupal::entityTypeManager()
          ->getStorage('paragraph')
          ->loadRevision($q_vid);
        $a_vid = $paragraph->get('matching_user_answer')->value;
        if ($a_vid) {
          $qa = \Drupal::entityTypeManager()
            ->getStorage('paragraph')
            ->loadRevision($a_vid);
        }

        if ($qa) {
          $items[$qra->getQuizResultId()][] = array('answer' => $qp->get('matching_question')->value . ': ' . $qa->get('matching_answer')->value);
        }
      }
    }

    return $items;
  }

}
