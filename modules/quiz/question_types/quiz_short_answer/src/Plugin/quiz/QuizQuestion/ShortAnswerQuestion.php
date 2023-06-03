<?php

namespace Drupal\quiz_short_answer\Plugin\quiz\QuizQuestion;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * @QuizQuestion (
 *   id = "short_answer",
 *   label = @Translation("Short answer question"),
 *   handlers = {
 *     "response" = "\Drupal\quiz_short_answer\Plugin\quiz\QuizQuestion\ShortAnswerResponse"
 *   }
 * )
 */
class ShortAnswerQuestion extends QuizQuestion {

  use StringTranslationTrait;

  // Constants for answer matching options.
  const ANSWER_MATCH = 0;

  const ANSWER_INSENSITIVE_MATCH = 1;

  const ANSWER_REGEX = 2;

  const ANSWER_MANUAL = 3;

  /**
   * Implementation of validateNode().
   *
   * @see QuizQuestion::validateNode()
   */
  public function validateNode(array &$form) {
    if ($this->node->correct_answer_evaluation != self::ANSWER_MANUAL && empty($this->node->correct_answer)) {
      form_set_error('correct_answer', $this->t('An answer must be specified for any evaluation type other than manual scoring.'));
    }
  }

  /**
   * Implementation of getNodeView().
   *
   * @see QuizQuestion::getNodeView()
   */
  public function getNodeView() {
    $content = parent::getNodeView();
    if ($this->viewCanRevealCorrect()) {
      $content['answers'] = [
        '#markup' => '<div class="quiz-solution">' . check_plain($this->node->correct_answer) . '</div>',
        '#weight' => 2,
      ];
    }
    else {
      $content['answers'] = [
        '#markup' => '<div class="quiz-answer-hidden">Answer hidden</div>',
        '#weight' => 2,
      ];
    }
    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $quizQuestionResultAnswer): array {
    $element = parent::getAnsweringForm($form_state, $quizQuestionResultAnswer);

    $element += [
      '#type' => 'textfield',
      '#title' => $this->t('Answer'),
      '#description' => $this->t('Enter your answer here'),
      '#default_value' => '',
      '#size' => 60,
      '#maxlength' => 256,
      '#required' => FALSE,
      '#attributes' => ['autocomplete' => 'off'],
    ];

    if ($quizQuestionResultAnswer->isAnswered()) {
      $element['#default_value'] = $quizQuestionResultAnswer->getResponse();
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function getAnsweringFormValidate(array &$element, FormStateInterface $form_state) {
    if ($element['#value'] == '') {
      $form_state->setError($element, t('You must provide an answer.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMaximumScore(): int {
    return Drupal::config('quiz_short_answer.settings')
      ->get('default_max_score');
  }

}
