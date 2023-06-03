<?php

namespace Drupal\quiz_truefalse\Plugin\quiz\QuizQuestion;

use Drupal\quiz\Entity\QuizQuestionResponse;
use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Util\QuizUtil;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Extension of QuizQuestionResponse.
 */
class TrueFalseResponse extends QuizResultAnswer {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function score(array $response): ?int {
    $tfQuestion = $this->getQuizQuestion();
    $this->set('truefalse_answer', $response['answer']);
    $this->setEvaluated();

    if ($response['answer'] == $tfQuestion->getCorrectAnswer()) {
      return $tfQuestion->getMaximumScore();
    }
    else {
      return 0;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    return $this->get('truefalse_answer')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues(): array {

    $answer = $this->getResponse();
    if (is_numeric($answer)) {
      $answer = intval($answer);
    }

    $correct_answer = intval($this->getQuizQuestion()->getCorrectAnswer());

    $data = [];
    $data[] = [
      'choice' => $this->t('True'),
      'attempt' => $answer === 1 ? QuizUtil::icon('selected') : '',
      'correct' => $answer === 1 ? QuizUtil::icon($correct_answer ? 'correct' : 'incorrect') : '',
      'score' => intval($correct_answer === 1 && $answer === 1),
      'answer_feedback' => '',
      'solution' => $correct_answer === 1 ? QuizUtil::icon('should') : '',
    ];

    $data[] = [
      'choice' => $this->t('False'),
      'attempt' => $answer === 0 ? QuizUtil::icon('selected') : '',
      'correct' => $answer === 0 ? (QuizUtil::icon(!$correct_answer ? 'correct' : 'incorrect')) : '',
      'score' => intval($correct_answer === 0 && $answer === 0),
      'answer_feedback' => '',
      'solution' => $correct_answer === 0 ? QuizUtil::icon('should') : '',
    ];

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
      $items[$qra->getQuizResultId()][] = [
        'answer' => $qra->getResponse() ? t('True') : t('False'),
      ];
    }
    return $items;
  }

}
