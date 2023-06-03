<?php

namespace Drupal\quiz_multichoice\Plugin\quiz\QuizQuestion;

use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Util\QuizUtil;
use function _quiz_question_response_get_instance;
use function check_markup;
use function db_delete;
use function db_query;
use function db_select;
use function entity_load;
use function node_load_multiple;

/**
 * Extension of QuizQuestionResponse.
 */
class MultichoiceResponse extends QuizResultAnswer {

  /**
   * ID of the answers.
   */
  protected $user_answer_ids;

  protected $choice_order;

  /**
   * {@inheritdoc}
   */
  public function score(array $response): ?int {
    if (!is_array($response['answer']['user_answer'])) {
      $selected_vids = [$response['answer']['user_answer']];
    }
    else {
      $selected_vids = $response['answer']['user_answer'];
    }

    // Reset whatever was here already.
    $this->get('multichoice_answer')->setValue(NULL);

    // The answer ID is the revision ID of the Paragraph item of the MCQ.
    // Fun!
    foreach ($selected_vids as $vid) {
      // Loop through all selected answers and append them to the paragraph
      // revision reference.
      $this->get('multichoice_answer')->appendItem($vid);
    }

    $simple = $this->getQuizQuestion()->get('choice_boolean')->getString();
    $multi = $this->getQuizQuestion()->get('choice_multi')->getString();

    $score = 0;

    $alternatives = $this->getQuizQuestion()
      ->get('alternatives')
      ->referencedEntities();
    foreach ($alternatives as $alternative) {
      // Take action on each alternative being selected (or not).
      $vid = $alternative->getRevisionId();
      // If this alternative was selected.
      $selected = in_array($vid, $selected_vids);
      $correct = $alternative->get('multichoice_correct')->getString();

      if (!$selected && $simple && $correct) {
        // Selected this answer, simple scoring on, and the answer was incorrect.
        $score = 0;
        break;
      }

      if ($selected && $correct && !$multi) {
        // User selected a correct answer and this is not a multiple answer
        // question. User gets the point value of the question.
        $score = $alternative->get('multichoice_score_chosen')->getString();
        break;
      }

      if ($multi) {
        // In multiple answer questions we add (or subtract) some points.
        $score += $selected ? $alternative->get('multichoice_score_chosen')->getString() : $alternative->get('multichoice_score_not_chosen')->getString();
      }
    }

    // We have scored the question.
    $this->setEvaluated();

    // If simple scoring is on, return 1 point if score is more than 1.
    // Otherwise, return the score as-is.
    return $simple ? intval($score >= 1) : $score;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    $vids = [];
    foreach ($this->get('multichoice_answer')->getValue() as $alternative) {
      $vids[] = $alternative['value'];
    }
    return $vids;
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues(): array {
    // @todo d8
    //$this->orderAlternatives($this->question->alternatives);
    $simple_scoring = $this->getQuizQuestion()
      ->get('choice_boolean')
      ->getString();

    $user_answers = $this->getResponse();

    $data = [];
    $alternatives = $this->getQuizQuestion()
      ->get('alternatives')
      ->referencedEntities();
    foreach ($alternatives as $alternative) {
      $chosen = in_array($alternative->getRevisionId(), $user_answers);
      $not = $chosen ? '' : 'not_';
      $chosen_feedback = $alternative->{"multichoice_feedback_{$not}chosen"};

      $data[] = [
        'choice' => check_markup($alternative->multichoice_answer->value, $alternative->multichoice_answer->format),
        'attempt' => $chosen ? QuizUtil::icon('selected') : '',
        'correct' => $chosen ? $alternative->multichoice_score_chosen->value > 0 ? QuizUtil::icon('correct') : QuizUtil::icon('incorrect') : '',
        'score' => (int) $alternative->{"multichoice_score_{$not}chosen"}->value,
        'answer_feedback' => check_markup($chosen_feedback->value, $chosen_feedback->format),
        'question_feedback' => 'Question feedback',
        'solution' => $alternative->multichoice_score_chosen->value > 0 ? QuizUtil::icon('should') : ($simple_scoring ? QuizUtil::icon('should-not') : ''),
        'quiz_feedback' => "Quiz feedback",
      ];
    }

    return $data;
  }

  /**
   * Order the alternatives according to the choice order stored in the
   * database.
   *
   * @param array $alternatives
   *   The alternatives to be ordered.
   */
  protected function orderAlternatives(array &$alternatives) {
    if (!$this->question->choice_random) {
      return;
    }
    // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
    // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
    $result = \Drupal::database()
      ->query('SELECT choice_order FROM {quiz_multichoice_user_answers}
            WHERE result_answer_id = :raid', [':raid' => $this->result_answer_id])
      ->fetchField();
    if (!$result) {
      return;
    }
    $order = explode(',', $result);
    $newAlternatives = [];
    foreach ($order as $value) {
      foreach ($alternatives as $alternative) {
        if ($alternative['id'] == $value) {
          $newAlternatives[] = $alternative;
          break;
        }
      }
    }
    $alternatives = $newAlternatives;
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
      foreach ($qra->getResponse() as $vid) {
        if ($vid) {
          $paragraph = \Drupal::entityTypeManager()
            ->getStorage('paragraph')
            ->loadRevision($vid);
          $answer = trim(strip_tags($paragraph->get('multichoice_answer')->value));
          $items[$qra->getQuizResultId()][] = ['answer' => $answer];
        }
      }
    }

    return $items;
  }

}
