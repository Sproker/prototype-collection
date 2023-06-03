<?php

namespace Drupal\quiz\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizResult;

/**
 * The form used to deliver questions to users and capture their responses.
 */
class QuizQuestionAnsweringForm extends FormBase {

  /**
   * Get the form to show to the quiz taker.
   *
   * @param array $form
   * @param array $form_state
   * @param array $questions
   *   A list of question nodes to get answers from.
   * @param int $result_id
   *   The result ID for this attempt.
   *
   * @return array
   *   An renderable FAPI array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    /* @var $questions \Drupal\quiz\Entity\QuizQuestion[] */
    $questions = $form_state->getBuildInfo()['args'][0];
    /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
    $quiz_session = \Drupal::service('quiz.session');

    /* @var $quiz_result \Drupal\quiz\Entity\QuizResult */
    $quiz_result = $form_state->getBuildInfo()['args'][1];
    $quiz = \Drupal::entityTypeManager()
      ->getStorage('quiz')
      ->loadRevision($quiz_result->get('vid')->getString());

    // Store quiz and result in the form.
    $form['#quiz'] = $quiz;
    $form['#quiz_result'] = $quiz_result;

    if (!is_array($questions)) {
      // One single question (or page?)
      if ($questions->bundle() == 'page') {
        foreach ($quiz_result->getLayout() as $qra) {
          if ($qra->get('question_id')->getString() == $questions->id()) {
            // Found the page in the layout.
            // Add the page as the first "question".
            $questions = [$questions];
            foreach ($quiz_result->getLayout() as $qra2) {
              // Get all page children.
              if ($qra2->qqr_pid == $qra->qqr_id) {
                // This question belongs in the requested page.
                $questions[] = $qra2->getQuizQuestion();
              }
            }
            break;
          }
        }
        unset($qra, $qra2);
      }
      else {
        // Make this a page of 1 question.
        $questions = [$questions->id() => $questions];
      }
    }

    $form['#attributes']['class'] = ['answering-form'];
    $form['#tree'] = TRUE;

    // Mark this as the current question.
    $quiz_result->setQuestion(reset($questions)
      ->getResponse($quiz_result)
      ->get('number')->value);

    foreach ($questions as $question) {
      $class = Html::getClass('quiz-question-' . $question->bundle());
      // Element for a single question.
      // @todo Instead of doing this load I think we can refer back to the layout.
      $qra = $question->getResponse($quiz_result);

      $element = $question->getAnsweringForm($form_state, $qra);

      // Render the question using the "question" view mode.
      $build = \Drupal::entityTypeManager()
        ->getViewBuilder('quiz_question')
        ->view($question, 'question');

      $header_markup = NULL;
      if (!$qra->get('display_number')->isEmpty()) {
        $header_markup = ['#markup' => "<h2>" . $this->t("Question @question", ['@question' => $qra->get('display_number')->getString()]) . "</h2>"];
      }

      $form['question'][$question->id()] = [
        '#attributes' => ['class' => [$class]],
        '#type' => 'container',
        'header' => $header_markup,
        'question' => $build,
        'answer' => $element,
      ];
      $form['question'][$question->id()]['answer']['#quiz_result_answer'] = $qra;

      $blank_and_change = $qra->get('is_skipped')->getString() && $quiz->get('allow_change_blank')->getString();
      if (!$quiz->get('allow_change')->getString() && !$qra->get('answer_timestamp')->isEmpty()) {
        if ($blank_and_change) {
          // Allow it.
        }
        else {
          // This question was already answered, or answering blank question is
          // disabled.
          $form['question'][$question->id()]['#disabled'] = TRUE;
          if (empty($form_state->getUserInput())) {
            // Only show message if not submitting.
            \Drupal::messenger()->addWarning($this->t('Changing answers is disabled.'));
          }
        }
      }

      if ($quiz->get('mark_doubtful')->getString() && $question->isQuestion()) {
        $form['question'][$question->id()]['is_doubtful'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Doubtful?'),
          '#default_value' => $qra->get('is_doubtful')->getString(),
        ];
      }
    }

    $is_last = $quiz->isLastQuestion();

    $form['navigation']['#type'] = 'actions';

    $form['navigation']['submit_hidden'] = [
      '#weight' => -9999,
      '#type' => 'submit',
      '#value' => $is_last ? $this->t('Finish') : $this->t('Next'),
      '#attributes' => ['style' => 'display: none'],
    ];

    if ($quiz->get('backwards_navigation')->getString() && ($quiz_session->getCurrentQuestion($quiz) != 1)) {
      // Backwards navigation enabled, and we are looking at not the first
      // question. @todo detect when on the first page.
      $form['navigation']['back'] = [
        '#weight' => 10,
        '#type' => 'submit',
        '#value' => $this->t('Back'),
        '#submit' => ['::submitBack'],
        '#limit_validation_errors' => [],
      ];
      if ($is_last) {
        $form['navigation']['#last'] = TRUE;
        $form['navigation']['last_text'] = [
          '#weight' => 0,
          '#markup' => '<p><em>' . $this->t('This is the last question. Press Finish to deliver your answers') . '</em></p>',
        ];
      }
    }

    $form['navigation']['submit'] = [
      '#weight' => 30,
      '#type' => 'submit',
      '#value' => $is_last ? $this->t('Finish') : $this->t('Next'),
      '#ajax' => [],
    ];

    if ($is_last && $quiz->get('backwards_navigation')->getString() && !$quiz->get('repeat_until_correct')->getString()) {
      // Display a confirmation dialogue if this is the last question and a user
      // is able to navigate backwards but not forced to answer correctly.
      $form['#attributes']['class'][] = 'quiz-answer-confirm';
      $form['#attributes']['data-confirm-message'] = $this->t("By proceeding you won't be able to go back and edit your answers.");
      $form['#attached']['library'][] = 'quiz/confirm';
    }
    if ($quiz->get('allow_skipping')->getString()) {
      $form['navigation']['skip'] = [
        '#weight' => 20,
        '#type' => 'submit',
        '#value' => $is_last ? $this->t('Leave blank and finish') : $this->t('Leave blank'),
        '#access' => $question->isGraded(),
        '#submit' => ['::submitBlank'],
        '#limit_validation_errors' => [],
      ];
    }

    return $form;
  }

  public function getFormId() {
    return 'quiz_question_answering_form';
  }

  /**
   * Submit handler for the question answering form.
   *
   * There is no validation code here, but there may be feedback code for
   * correct feedback.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('quiz.settings');
    /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
    $quiz_session = \Drupal::service('quiz.session');

    /* @var $quiz_result QuizResult */
    $quiz_result = $form_state->getBuildInfo()['args'][1];
    $feedback_count = 0;
    $quiz = \Drupal::entityTypeManager()
      ->getStorage('quiz')
      ->loadRevision($quiz_result->get('vid')->getString());

    $time_reached = $quiz_result->isTimeReached();
    $layout = $quiz_result->getLayout();

    if ($time_reached) {
      // Too late.
      // @todo move to quiz_question_answering_form_validate(), and then put all
      // the "quiz end" logic in a sharable place. We just need to not fire the
      // logic that saves all the users answers.
      \Drupal::messenger()->addError($this->t('The last answer was not submitted, as the time ran out.'));
    }
    else {
      $submitted = $form_state->getValue('question');

      foreach ($layout as $qra) {
        if (isset($submitted[$qra->get('question_id')->getString()])) {
          // User submitted a response to this question.
          $qqid = $qra->get('question_id')->getString();
          $qra->set('answer_timestamp', \Drupal::time()->getRequestTime());

          // Get the unscaled points awarded from the question response
          // implementation and then apply the weighted ratio in this quiz. For
          // example a MCQ question itself may be worth 4 points but worth 10
          // points in this quiz. A score of 2 would mean 5 points being
          // recorded.
          $qra->set('points_awarded', $qra->score($form_state->getValues()['question'][$qqid]) * $qra->getWeightedRatio());

          // Mark question as not skipped, in case it was skipped previously.
          $qra->set('is_skipped', FALSE);

          // Mark as doubtful.
          $qra->set('is_doubtful',  !empty($form_state->getValues()['question'][$qqid]['is_doubtful']) ? 1 : 0);

          // Mark correctness.
          $qra->set('is_correct', $qra->isCorrect());

          $qra->save();

          // Does this question type have feedback? We need to track it across pages.
          $feedback_count += $qra->getQuizQuestion()->hasFeedback();

          // Increment the question position.
          $quiz_result->setQuestion($qra->get('number')->getString() + 1);
        }
      }
    }

    // Wat do?
    $next_number = $quiz_session->getCurrentQuestion($quiz);

    if ($time_reached || !isset($layout[$next_number])) {
      // If this is the last question, finalize the quiz.
      $this->submitFinalize($form, $form_state);
    }
    else {
      // No question feedback. Go to next question.
      $form_state->setRedirect('quiz.question.take', [
        'quiz' => $quiz->id(),
        'question_number' => $next_number,
      ]);
    }

    $review_options = $quiz->get('review_options')->get(0);
    if ($review_options && !empty($review_options->getValue()['question']) && array_filter($review_options->getValue()['question']) && $feedback_count) {
      // This page contains questions that can provide feedback, and question
      // feedback is enabled on the quiz.
      $form_state->setRedirect('quiz.question.feedback', [
        'quiz' => $quiz->id(),
        'question_number' => $next_number - 1,
      ]);
      // For ajax_quiz.
      $form_state->set('feedback', TRUE);
    }
  }

  /**
   * Submit action for "leave blank".
   */
  public function submitBlank(array $form, FormStateInterface $form_state) {
    $quiz_result = $form_state->getBuildInfo()['args'][1];
    /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
    $quiz_session = \Drupal::service('quiz.session');
    $quiz = \Drupal::entityTypeManager()
      ->getStorage('quiz')
      ->loadRevision($quiz_result->get('vid')->getString());

    if (!empty($form_state->getUserInput()['question'])) {
      // Loop over all question inputs provided, and record them as skipped.
      foreach (array_keys($form_state->getUserInput()['question']) as $qqid) {

        foreach ($quiz_result->getLayout() as $idx => $qra) {
          // Find the blank submitted question in the current layout.
          if ($qra->get('question_id')->getString() == $qqid) {
            // Reset the question and mark it as taken, so restrictions like
            // backwards navigation function correctly.
            $qra->set('is_skipped', TRUE);
            $qra->set('is_correct', NULL);
            $qra->set('points_awarded', FALSE);
            $qra->set('answer_timestamp', \Drupal::time()->getRequestTime());
            $qra->save();
          }
        }
        $quiz_result->setQuestion($quiz_session->getCurrentQuestion($quiz) + 1);
      }
    }
    else {
      // Advance to next question, no input here.
      $quiz_result->setQuestion($quiz, $quiz_session->getCurrentQuestion($quiz) + 1);
    }

    // Advance to next question.
    $form_state->setRedirect('quiz.question.take', [
      'quiz' => $quiz->id(),
      'question_number' => $quiz_session->getCurrentQuestion($quiz),
    ]);

    $layout = $quiz_result->getLayout();
    if (!isset($layout[$quiz_session->getCurrentQuestion($quiz)])) {
      // If this is the last question, finalize the quiz.
      $this->submitFinalize($form, $form_state);
    }
  }

  /**
   * Helper function to finalize a quiz attempt.
   *
   * @see quiz_question_answering_form_submit()
   * @see quiz_question_answering_form_submit_blank()
   */
  function submitFinalize(array $form, FormStateInterface $form_state) {
    $quiz_result = $form_state->getBuildInfo()['args'][1];
    $quiz = \Drupal::entityTypeManager()
      ->getStorage('quiz')
      ->loadRevision($quiz_result->get('vid')->getString());
    /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
    $quiz_session = \Drupal::service('quiz.session');

    // No more questions. Score quiz and finalize result.
    $quiz_result->finalize();

    if (empty($quiz->review_options['question']) || !array_filter($quiz->review_options['question']) || empty($form_state['feedback'])) {
      // Only redirect to question results if there is not question feedback.
      // /** @todo D8*/
      $form_state->setRedirect('entity.quiz_result.canonical', [
        'quiz' => $quiz->id(),
        'quiz_result' => $quiz_result->id(),
      ]);
    }

    // Remove all information about this quiz from the session.
    // @todo but for anon, we might have to keep some so they could access
    // results
    // When quiz is completed we need to make sure that even though the quiz has
    // been removed from the session, that the user can still access the
    // feedback for the last question, THEN go to the results page.
    $quiz_session->setTemporaryResult($quiz_result);
    $quiz_session->removeQuiz($quiz);
  }

  /**
   * Submit handler for "back".
   */
  function submitBack(&$form, FormStateInterface $form_state) {
    // Back a question.
    /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
    $quiz_session = \Drupal::service('quiz.session');
    $quiz = $form['#quiz'];
    $quiz_result = $quiz_session->getResult($quiz);
    $quiz_result->setQuestion($quiz_session->getCurrentQuestion($quiz) - 1);

    // Handle going back to a question which is in a previous page.
    $layout = $quiz_result->getLayout();
    $question = $layout[$quiz_session->getCurrentQuestion($quiz)];
    if (!empty($question->qqr_pid)) {
      foreach ($layout as $question2) {
        if ($question2->qqr_id == $question->qqr_pid) {
          $quiz_result->setQuestion($question2->get('number')->value);
        }
      }
    }

    $form_state->setRedirect('quiz.question.take', [
      'quiz' => $quiz->id(),
      'question_number' => $quiz_session->getCurrentQuestion($quiz),
    ]);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $quiz_result = $form_state->getBuildInfo()['args'][1];
    $time_reached = $quiz_result->isTimeReached();

    if ($time_reached) {
      // Let's not validate anything, because the input won't get saved in submit
      // either.
      return;
    }
  }

}
