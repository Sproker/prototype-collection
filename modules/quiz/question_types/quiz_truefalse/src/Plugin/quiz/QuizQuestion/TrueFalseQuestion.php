<?php

namespace Drupal\quiz_truefalse\Plugin\quiz\QuizQuestion;

use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * @QuizQuestion (
 *   id = "truefalse",
 *   label = @Translation("True/false question"),
 *   handlers = {
 *     "response" = "\Drupal\quiz_truefalse\Plugin\quiz\QuizQuestion\TrueFalseResponse"
 *   }
 * )
 */
class TrueFalseQuestion extends QuizQuestion {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getNodeView() {
    $content = parent::getNodeView();
    if ($this->viewCanRevealCorrect()) {
      $answer = ($this->node->correct_answer) ? $this->t('True') : $this->t('False');
      $content['answers']['#markup'] = '<div class="quiz-solution">' . $answer . '</div>';
      $content['answers']['#weight'] = 2;
    }
    else {
      $content['answers'] = [
        '#markup' => '<div class="quiz-answer-hidden">' . $this->t('Answer hidden') . '</div>',
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
      '#type' => 'radios',
      '#title' => $this->t('Choose one'),
      '#options' => [
        1 => $this->t('True'),
        0 => $this->t('False'),
      ],
    ];

    if ($quizQuestionResultAnswer->isAnswered()) {
      if ($quizQuestionResultAnswer->getResponse() != '') {
        $element['#default_value'] = $quizQuestionResultAnswer->getResponse();
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function getAnsweringFormValidate(array &$element, FormStateInterface $form_state) {
    parent::getAnsweringFormValidate($element, $form_state);

    if (is_null($element['#value'])) {
      $form_state->setError($element, t('You must provide an answer.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCreationForm(array &$form_state = NULL) {
    $form['correct_answer'] = [
      '#type' => 'radios',
      '#title' => $this->t('Correct answer'),
      '#options' => [
        1 => $this->t('True'),
        0 => $this->t('False'),
      ],
      '#default_value' => isset($this->node->correct_answer) ? $this->node->correct_answer : 1,
      '#required' => TRUE,
      '#weight' => -4,
      '#description' => $this->t('Choose if the correct answer for this question is "true" or "false".'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * The maximum points for a true/false question is always 1.
   */
  public function getMaximumScore(): int {
    return 1;
  }

  /**
   * Get the correct answer to this question.
   *
   * This is a utility function. It is not defined in the interface.
   *
   * @return bool
   *   Boolean indicating if the correct answer is TRUE or FALSE
   */
  public function getCorrectAnswer(): bool {
    return $this->get('truefalse_correct')->getString();
  }

}
