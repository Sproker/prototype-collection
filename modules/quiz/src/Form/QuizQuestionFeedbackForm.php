<?php

namespace Drupal\quiz\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\quiz\Util\QuizUtil;

/**
 * Post-question feedback form.
 */
class QuizQuestionFeedbackForm extends FormBase {

  /**
   * Show feedback for a question response.
   */
  function buildForm(array $form, FormStateInterface $form_state) {

    $quiz = $form_state->getBuildInfo()['args'][0];
    $question_number = $form_state->getBuildInfo()['args'][1];
    $quiz_result = QuizUtil::resultOrTemp($quiz);
    $form = [];


    $form['actions']['#type'] = 'actions';

    if (!$quiz_result->get('time_end')->isEmpty()) {
      // Quiz is done.
      $form['actions']['finish'] = [
        '#type' => 'submit',
        '#submit' => ['::submitEnd'],
        '#value' => $this->t('Finish'),
      ];
    }
    else {
      $form['actions']['next'] = Link::createFromRoute($this->t('Next question'), 'quiz.question.take', [
        'quiz' => $quiz->id(),
        'question_number' => $question_number + 1,
      ], ['attributes' => ['class' => ['button']]])->toRenderable();
    }

    $view_builder = Drupal::entityTypeManager()
      ->getViewBuilder('quiz_result_answer');


    // Add feedback.
    $out = [];

    foreach ($quiz_result->getLayout() as $question) {
      if ($question->get('number')->getString() == $question_number &&
        $question->qqr_pid) {
        // Question is in a page.
        foreach ($quiz_result->getLayout() as $qra) {
          if ($qra->qqr_pid == $question->qqr_pid) {
            $out[] = [
              '#title' => $this->t('Question @num', [
                '@num' => $qra->get('display_number')->getString(),
              ]),
              '#type' => 'fieldset',
              'feedback' => $view_builder->view($qra),
            ];
          }
        }
      }
    }

    // Single question.
    if (empty($out)) {
      $qra = $quiz_result->getLayout()[$question_number];

      $feedback = $view_builder->view($qra);

      $out[] = [
        '#title' => $this->t('Question @num', [
          '@num' => $quiz_result->getLayout()[$question_number]->get('display_number')->getString(),
        ]),
        '#type' => 'fieldset',
        'feedback' => $feedback,
      ];
    }

    $form['feedback'] = $out;

    return $form;
  }

  public function getFormId() {
    return 'quiz_take_question_feedback_form';
  }

  /**
   * Submit handler to go to the next question from the question feedback.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $quiz = $form_state->getBuildInfo()['args'][0];
    /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
    $quiz_session = \Drupal::service('quiz.session');
    $form_state->setRedirect('quiz.question.take', [
      'quiz' => $quiz->id(),
      'question_number' => $quiz_session->getCurrentQuestion($quiz),
    ]);
  }

  /**
   * Submit handler to go to the quiz results from the last question's feedback.
   */
  function submitEnd($form, &$form_state) {
    /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
    $quiz_session = \Drupal::service('quiz.session');
    $quiz_result = $quiz_session->getTemporaryResult();
    $quiz = $form_state->getBuildInfo()['args'][0];
    $form_state->setRedirect('entity.quiz_result.canonical', [
      'quiz' => $quiz->id(),
      'quiz_result' => $quiz_result->id(),
    ]);
  }

}
