<?php

namespace Drupal\quiz_long_answer\Plugin\quiz\QuizQuestion;

use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Util\QuizUtil;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use function check_markup;

/**
 * Extension of QuizQuestionResponse.
 */
class LongAnswerResponse extends QuizResultAnswer {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function score(array $values): ?int {
    $this->set('long_answer', $values['answer']);
    $this->setEvaluated(FALSE);
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    return $this->get('long_answer')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues(): array {
    $data = [];
    $score = $this->getPoints();
    $max = $this->getMaxScore();

    if ($this->evaluated) {
      // Question has been graded.
      if ($score == 0) {
        $icon = QuizUtil::icon('incorrect');
      }
      if ($score > 0) {
        $icon = QuizUtil::icon('almost');
      }
      if ($score == $max) {
        $icon = QuizUtil::icon('correct');
      }
    }
    else {
      $icon = QuizUtil::icon('unknown');
    }

    $long_answer_rubric = $this->getQuizQuestion()->get('long_answer_rubric');
    $rubric = !$long_answer_rubric->isEmpty() ? $long_answer_rubric->get(0)->getValue() : NULL;
    $answer_feedback = $this->get('answer_feedback')->get(0)->getValue();
    $user_answer = !$this->get('long_answer')->isEmpty() ? $this->get('long_answer')->get(0)->getValue() : NULL;

    $data[] = [
      // Hide this column. Does not make sense for long answer as there are no
      // choices.
      'choice' => NULL,
      'attempt' => is_array($user_answer) ? check_markup($user_answer['value'], $user_answer['format']) : $user_answer,
      'correct' => $icon,
      'score' => !$this->isEvaluated() ? $this->t('This answer has not yet been scored.') : $score,
      'answer_feedback' => $answer_feedback ? check_markup($answer_feedback['value'], $answer_feedback['format']) : '',
      'solution' => $rubric ? check_markup($rubric['value'], $rubric['format']) : '',
    ];

    return $data;
  }

}
