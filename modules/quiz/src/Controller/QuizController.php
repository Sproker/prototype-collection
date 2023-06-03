<?php

namespace Drupal\quiz\Controller;

use Drupal;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;
use Drupal\quiz\Form\QuizQuestionsForm;
use Drupal\quiz\Util\QuizUtil;
use Drupal\views\Views;

class QuizController extends EntityController {

  /**
   * Take the quiz.
   *
   * @return type
   */
  function take(Quiz $quiz) {
    $page = [];
    /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
    $quiz_session = \Drupal::service('quiz.session');
    /* @var $result AccessResultReasonInterface */
    $result = $quiz->access('take', NULL, TRUE);

    $message = '';
    if (is_subclass_of($result, AccessResultReasonInterface::class)) {
      $message = $result->getReason();
    }
    $success = !$result->isForbidden();

    if (!$success) {
      // Not allowed.
      $page['body']['#markup'] = $message;
      return $page;
    }
    elseif ($message) {
      // Allowed, but we have a message.
      \Drupal::messenger()->addMessage($message);
    }

    if ($quiz_result = $this->resume($quiz)) {
      // Resuming attempt.
      if (!empty($quiz_result->resume)) {
        // Show a message if this was reloaded from the database and not just
        // from the session.
        \Drupal::messenger()->addStatus($this->t('Resuming a previous @quiz in-progress.', array('@quiz' => QuizUtil::getQuizName())));
      }
      return $this->redirect('quiz.question.take', [
        'quiz' => $quiz->id(),
        'question_number' => $quiz_session->getCurrentQuestion($quiz),
      ]);
    }
    else {
      // Create new result.
      if ($success) {
        // Test a build of questions.
        $questions = $quiz->buildLayout();
        if (empty($questions)) {
          \Drupal::messenger()->addError($this->t('Not enough questions were found. Please add more questions before trying to take this @quiz.', array('@quiz' => QuizUtil::getQuizName())));
          return $this->redirect('entity.quiz.canonical', ['quiz' => $quiz->id()]);
        }

        // Creat a new Quiz result.
        $quiz_result = QuizResult::create([
          'qid' => $quiz->id(),
          'vid' => $quiz->getRevisionId(),
          'uid' => \Drupal::currentUser()->id(),
          'type' => $quiz->get('result_type')->getString(),
        ]);


        $build_on_last = $quiz->get('build_on_last')->getString() != 'fresh' && $quiz_result->findOldResult();
        $instances = Drupal::service('entity_field.manager')
          ->getFieldDefinitions('quiz_result', $quiz->get('result_type')->getString());
        foreach ($instances as $field_name => $field) {
          if ($build_on_last || (is_a($field, FieldConfig::class) && $field->getThirdPartySetting('quiz', 'show_field'))) {
            // We found a field to be filled out.
            $redirect_url = Url::fromRoute('entity.quiz.take', [
              'quiz' => $quiz_result->getQuiz()->id(),
            ]);
            $form = \Drupal::service('entity.form_builder')
              ->getForm($quiz_result, 'default', ['redirect' => $redirect_url]);
            return $form;
          }
        }
      }
      else {
        $page['body']['#markup'] = $result['message'];
        return $page;
      }
    }


    // New attempt.
    $quiz_result->save();
    $quiz_session->startQuiz($quiz_result);
    return $this->redirect('quiz.question.take', [
      'quiz' => $quiz->id(),
      'question_number' => 1,
    ]);
  }

  /**
   * Creates a form for quiz questions.
   *
   * Handles the manage questions tab.
   *
   * @param $node
   *   The quiz node we are managing questions for.
   *
   * @return ???
   *   String containing the form.
   */
  function manageQuestions(Quiz $quiz) {
    if ($quiz->get('randomization')->getString() < 3) {
      $manage_questions = Drupal::formBuilder()
        ->getForm(QuizQuestionsForm::class, $quiz);
      return $manage_questions;

      $question_bank = Views::getView('quiz_question_bank')->preview();

      // Insert into vert tabs.
      $form['vert_tabs'] = [
        '#type' => 'x', // @todo wtf?
        '#weight' => 0,
        '#default_tab' => 'edit-questions',
      ];
      $form['vert_tabs']['questions'] = [
        '#type' => 'details',
        '#title' => $this->t('Manage questions'),
        '#group' => 'vert_tabs',
        'questions' => $manage_questions,
      ];
      $form['vert_tabs']['bank'] = [
        '#type' => 'details',
        '#title' => $this->t('Question bank'),
        '#group' => 'vert_tabs',
        'bank' => $question_bank,
      ];
      return $manage_questions;
    }
    else {
      $form = \Drupal::service('entity_type.manager')
        ->getFormObject('quiz', 'default')
        ->setEntity($quiz);
      $form = \Drupal::formBuilder()->getForm($form);
    }

    foreach (Element::children($form) as $key) {
      if (in_array($key, array_keys($quiz->getFieldDefinitions())) || $form[$key]['#type'] == 'details') {
        if (!in_array($key, ['quiz_terms', 'random', 'quiz'])) {
          $form[$key]['#access'] = FALSE;
        }
      }
    }
    return $form;
  }

  /**
   * Resume a quiz.
   *
   * Search the database for an in progress attempt, and put it back into the
   * session if allowed.
   *
   * @return QuizResult
   */
  function resume($quiz) {
    $user = \Drupal::currentUser();
    /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
    $quiz_session = \Drupal::service('quiz.session');

    // Make sure we use the same revision of the quiz throughout the quiz taking
    // session.
    $quiz_result = $quiz_session->getResult($quiz);
    if ($quiz_result) {
      return $quiz_result;
    }
    else {
      // User doesn't have attempt in session. If we allow resuming we can load it
      // from the database.
      if ($quiz->get('allow_resume')->getString() && $user->isAuthenticated()) {
        if ($quiz_result = $quiz->getResumeableResult($user)) {
          // Put the result in the user's session.
          $quiz_session->startQuiz($quiz_result);

          // Now advance the user to after the last answered question.
          $prev = NULL;
          foreach ($quiz_result->getLayout() as $qra) {
            if ($prev) {
              if ($qra->get('answer_timestamp')->isEmpty() && !$prev->get('answer_timestamp')->isEmpty()) {
                // This question has not been answered, but the previous
                // question has.
                $quiz_session->setCurrentQuestion($quiz, $qra->get('number')->getString());
              }
            }

            $prev = clone $qra;
          }

          // Mark this quiz as being resumed from the database.
          $quiz_result->resume = TRUE;
          return $quiz_result;
        }
      }
    }

    return FALSE;
  }

}
