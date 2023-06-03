<?php

namespace Drupal\quiz\Controller;

use Drupal;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Link;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Form\QuizQuestionAnsweringForm;
use Drupal\quiz\Form\QuizQuestionFeedbackForm;
use Drupal\quiz\Util\QuizUtil;
use Symfony\Component\HttpFoundation\RedirectResponse;

class QuizQuestionController extends EntityController {

  /**
   * Show feedback for a question or page.
   *
   * @param Quiz $quiz
   * @param type $question_number
   *
   * @return type
   */
  function feedback(Quiz $quiz, $question_number) {
    $form = Drupal::formBuilder()
      ->getForm(QuizQuestionFeedbackForm::class, $quiz, $question_number);
    $page['body']['question'] = $form;
    return $page;
  }

  /**
   * Take a quiz questions.
   *
   * @param Quiz $quiz
   *   A quiz.
   * @param int $question_number
   *   A question number, starting at 1. Pages do not have question numbers.
   *   Quiz directions are considered part of the numbering.
   */
  function take(Quiz $quiz, $question_number) {
    /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
    $quiz_session = \Drupal::service('quiz.session');

    if ($quiz_session->isTakingQuiz($quiz)) {
      // Attempt to resume a quiz in progress.
      $quiz_result = $quiz_session->getResult($quiz);
      $layout = $quiz_result->getLayout();
      /* @var $question QuizResultAnswer */
      $question_relationship = $layout[$question_number];

      if (!empty($question_relationship->qqr_pid)) {
        // Find the parent.
        foreach ($layout as $pquestion) {
          if ($pquestion->qqr_id == $question_relationship->qqr_pid) {
            // Load the page that the requested question belongs to.
            $question = Drupal::entityTypeManager()
              ->getStorage('quiz_question')
              ->loadRevision($pquestion->get('question_vid')->getString());
          }
        }
      }
      else {
        // Load the question.
        $question = Drupal::entityTypeManager()
          ->getStorage('quiz_question')
          ->loadRevision($question_relationship->get('question_vid')->getString());
      }
    }

    if (!isset($question)) {
      // Question disappeared or invalid session. Start over.
      $quiz_session->removeQuiz($quiz);
      return $this->redirect('entity.quiz.canonical', ['quiz' => $quiz->id()]);
    }

    // Added the progress info to the view.
    $quiz_result = $quiz_session->getResult($quiz);
    $questions = [];
    $i = 0;
    $found_pages = 0;
    foreach ($quiz_result->getLayout() as $idx => $question_relationship) {
      if (empty($question_relationship->qqr_pid)) {
        // Question has no parent. Show it in the jumper.
        $questions[$idx] = ++$i;
        $found_pages++;
      }
      if ($question->id() == $question_relationship->get('question_id')->getString()) {
        // Found our question.
        $current_page = $found_pages;
      }
    }

    $content = [];

    $content['progress'] = [
      '#theme' => 'quiz_progress',
      '#total' => count($questions),
      '#current' => $current_page,
      '#weight' => -50,
    ];

    if ($quiz->get('allow_jumping')->value) {
      // Jumping is allowed.
      if (count($questions) < \Drupal::config('quiz.settings')->get('pager_start', 100)) {
        // We are still under the threshold, so we can show the select box.
        $selectbox = \Drupal::formBuilder()
          ->getForm('\Drupal\quiz\Form\QuizJumperForm', $quiz_result, $current_page, count($questions));
        $content['jumper'] = [
          '#theme' => 'quiz_jumper',
          '#form' => $selectbox,
          '#total' => count($questions),
        ];
      }
      else {
        // Show a pager because a large select box is not fun.
        $siblings = \Drupal::config('quiz.settings')->get('pager_siblings');
        $items[] = [
          '#wrapper_attributes' => ['class' => ['pager__item', 'pager-first']],
          'data' => Link::createFromRoute($this->t('first'), 'quiz.question.take', [
            'quiz' => $quiz->id(),
            'question_number' => 1,
          ])->toRenderable(),
        ];
        foreach (_quiz_pagination_helper(count($questions), 1, $current_page, $siblings) as $i) {
          if ($i == $current_page) {
            $items[] = [
              '#wrapper_attributes' => [
                'class' => [
                  'pager__item',
                  'pager-current',
                ],
              ],
              'data' => ['#markup' => $current_page],
            ];
          }
          else {
            $items[] = [
              '#wrapper_attributes' => [
                'class' => [
                  'pager__item',
                  'pager-item'
                ]
              ],
              'data' => Link::createFromRoute($i, 'quiz.question.take', [
                'quiz' => $quiz->id(),
                'question_number' => $i,
              ])->toRenderable(),
            ];
          }
        }
        $items[] = [
          '#wrapper_attributes' => ['class' => ['pager__item', 'pager-last']],
          'data' => Link::createFromRoute($this->t('last'), 'quiz.question.take', [
            'quiz' => $quiz->id(),
            'question_number' => count($questions),
          ])->toRenderable(),
        ];
        $content['pager'] = [
          '#type' => 'html_tag',
          '#tag' => 'nav',
          '#attributes' => ['class' => ['pager'], 'role' => 'navigation'],
        ];
        $content['pager']['links'] = [
          '#theme' => 'item_list',
          '#items' => $items,
          '#attributes' => ['class' => ['pager__items']],
        ];
      }
    }

    if (function_exists('jquery_countdown_add') && \Drupal::config('quiz.settings')->get('has_timer', 0) && $quiz->time_limit) {
      jquery_countdown_add('.countdown', [
        'until' => ($quiz_result->time_start + $quiz->time_limit - \Drupal::time()->getRequestTime()),
        'onExpiry' => 'quiz_finished',
        'compact' => FALSE,
        'layout' => $this->t('Time left') . ': {hnn}{sep}{mnn}{sep}{snn}',
      ]);
      // These are the two button op values that are accepted for answering
      // questions.
      $button_op1 = drupal_json_encode($this->t('Finish'));
      $button_op2 = drupal_json_encode($this->t('Next'));
      $js = "
            function quiz_finished() {
              // Find all buttons with a name of 'op'.
              var buttons = jQuery('input[type=submit][name=op], button[type=submit][name=op]');
              // Filter out the ones that don't have the right op value.
              buttons = buttons.filter(function() {
                return this.value == $button_op1 || this.value == $button_op2;
              });
              if (buttons.length == 1) {
                // Since only one button was found, this must be it.
                buttons.click();
              }
              else {
                // Zero, or more than one buttons were found; fall back on a page refresh.
                window.location = window.location.href;
              }
            }
          ";
      drupal_add_js($js, 'inline');

      // Add div to be used by jQuery countdown.
      $content['body']['countdown']['#markup'] = '<div class="countdown"></div>';
    }

    $form = Drupal::formBuilder()
      ->getForm(QuizQuestionAnsweringForm::class, $question, $quiz_session->getResult($quiz));
    $content['body']['question'] = $form;

    return $content;
  }

  /**
   * Translate the numeric question index to a question result answer, and run
   * the "take" entity access check on it.
   *
   * @param Quiz $quiz
   * @param int $question_number
   */
  function checkAccess(Quiz $quiz, $question_number) {
    return $this->checkEntityAccess('take', $quiz, $question_number);
  }

  /**
   * Translate the numeric question index to a question result answer, and run
   * the "feedback" entity access check on it.
   *
   * @param Quiz $quiz
   * @param int $question_number
   */
  function checkFeedbackAccess(Quiz $quiz, $question_number) {
    return $this->checkEntityAccess('feedback', $quiz, $question_number);
  }

  /**
   * Translate the numeric question index to a question result answer, and run
   * the default entity access check on it.
   *
   * @param string $op
   *   An entity operation to check.
   * @param Quiz $quiz
   *   The quiz.
   * @param int $question_number
   *   The question number in the current result.
   */
  function checkEntityAccess($op, Quiz $quiz, $question_number) {
    $qra = $this->numberToQuestionResultAnswer($quiz, $question_number);
    return ($qra && $qra->access($op)) ? AccessResultAllowed::allowed() : AccessResultForbidden::forbidden();
  }

  /**
   * Translate the numeric question index to a question result answer.
   *
   * @param Quiz $quiz
   * @param int $question_number
   *
   * @return QuizResultAnswer
   *   The question result answer, or NULL if the current result does not exist
   * or does not contain this question.
   */
  function numberToQuestionResultAnswer(Quiz $quiz, $question_number) {
    if ($quiz_result = QuizUtil::resultOrTemp($quiz)) {
      return $quiz_result->getLayout()[$question_number];
    }

    return NULL;
  }

  public static function getTitle(Quiz $quiz, $question_number) {
    return $quiz->label();
  }

}
