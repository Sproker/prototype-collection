<?php

namespace Drupal\quiz_short_answer\Plugin\quiz\QuizQuestion;

use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Util\QuizUtil;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Extension of QuizResultAnswer.
 */
class ShortAnswerResponse extends QuizResultAnswer {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function score(array $values): ?int {
    $question = $this->getQuizQuestion();

    $correct = $question->get('short_answer_correct')->getString();

    $this->set('short_answer', $values['answer']);

    switch ($question->get('short_answer_evaluation')->getString()) {
      case ShortAnswerQuestion::ANSWER_MANUAL:
        $this->setEvaluated(FALSE);
        break;
      case ShortAnswerQuestion::ANSWER_MATCH:
        $this->setEvaluated();
        if ($values['answer'] == $correct) {
          return $question->getMaximumScore();
        }
        break;

      case ShortAnswerQuestion::ANSWER_INSENSITIVE_MATCH:
        $this->setEvaluated();
        if (strtolower($values['answer']) == strtolower($correct)) {
          return $question->getMaximumScore();
        }
        break;

      case ShortAnswerQuestion::ANSWER_REGEX:
        $this->setEvaluated();
        if (preg_match($correct, $values['answer']) > 0) {
          return $question->getMaximumScore();
        }
        break;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    return $this->get('short_answer')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues(): array {
    $data = [];
    $score = $this->getPoints();
    $max = $this->getMaxScore();

    if ($this->isEvaluated()) {
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

    $answer_feedback = $this->get('answer_feedback')->getValue()[0];
    $data[] = [
      // Hide this column. Does not make sense for short answer as there are no
      // choices.
      'choice' => NULL,
      'attempt' => $this->get('short_answer')->getString(),
      'correct' => $icon,
      'score' => !$this->isEvaluated() ? $this->t('This answer has not yet been scored.') : $this->getPoints(),
      'solution' => $this->getQuizQuestion()->get('short_answer_correct')->getString(),
      'answer_feedback' => check_markup($answer_feedback['value'], $answer_feedback['format']),
    ];

    return $data;
  }

}
