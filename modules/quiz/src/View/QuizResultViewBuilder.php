<?php

namespace Drupal\quiz\View;

use Drupal;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\Element;
use Drupal\Core\Template\Attribute;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;
use Drupal\quiz\Util\QuizUtil;
use Drupal\user\Entity\User;
use function check_markup;

class QuizResultViewBuilder extends EntityViewBuilder {

  use MessengerTrait;

  public function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    /* @var $entity QuizResult */
    $render_controller = Drupal::entityTypeManager()
      ->getViewBuilder('quiz_result_answer');

    if ($entity->get('is_invalid')->value && (\Drupal::currentUser()->id() == $entity->get('uid')->getString())) {
      \Drupal::messenger()->addWarning($this->t('Your previous score on this @quiz was equal or better. This result will not be saved.', ['@quiz' => QuizUtil::getQuizName()]));
    }

    if (!$entity->is_evaluated && empty($_POST)) {
      $msg = $this->t('Parts of this @quiz have not been evaluated yet. The score below is not final.', ['@quiz' => QuizUtil::getQuizName()]);
      $this->messenger()->addWarning($msg);
    }

    $account = User::load($entity->get('uid')->getString());

    if ($display->getComponent('questions')) {
      $questions = [];
      foreach ($entity->getLayout() as $qra) {
        // Loop through all the questions and get their feedback.
        $question = Drupal::entityTypeManager()
          ->getStorage('quiz_question')
          ->loadRevision($qra->get('question_vid')->getString());

        if (!$question) {
          // Question went missing...
          continue;
        }

        if ($question->hasFeedback() && $entity->hasReview()) {
          $feedback = $render_controller->view($qra);
          $feedback_rendered = \Drupal::service('renderer')->renderRoot($feedback);
          if ($feedback_rendered) {
            $questions[$question->id()] = [
              '#title' => $this->t('Question @num', ['@num' => $qra->get('display_number')->getString()]),
              '#type' => 'fieldset',
              'feedback' => ['#markup' => $feedback_rendered],
              '#weight' => $qra->get('number')->getString(),
            ];
          }
        }
      }
      if ($questions) {
        $build['questions'] = $questions;
      }
    }


    if ($display->getComponent('summary') && $entity->canReview('quiz_feedback')) {
      $summary = $this->getSummaryText($entity);
      $build['summary'] = [
        '#theme' => 'quiz_result_summary',
        '#quiz_result' => $entity,
        '#summary_passfail' => !empty($summary['passfail']) ? $summary['passfail'] : NULL,
        '#summary_range' => !empty($summary['result']) ? $summary['result'] : NULL,
        '#attributes' => new Attribute([
          'id' => 'quiz-summary',
        ]),
      ];
    }

    if (!Element::children($build)) {
      $data = $entity->getXAPIListenerStatements($account->id());
      $header = [
        'attempts' => t('Number of attempt'),
        'score' => t('Score'),
        'percentage' => t('Percentage'),
        'duration' => t('Duration')
      ];
      if (!empty($data)) {
        $rows = [];
        foreach ($data as $statement) {
          $percentage = number_format($statement['sum_score_raw'] / $statement['sum_score_max'] * 100, 2) . '%';
          $rows[] = [
            $statement['attempts'],
            $statement['sum_score_raw'],
            $percentage,
            number_format($statement['sum_duration'], 2) . ' seconds',
          ];
        }

        $build['table'] = [
          '#theme' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#empty' => t('No data available'),
        ];
      } else {
        $build['no_feedback_text']['#markup'] = $this->t('Error, no quiz data available!');
      }
    }
    // The visibility of feedback may change based on time or other conditions.
    $build['#cache']['max-age'] = 0;

    return $build;
  }

  /**
   * Get the summary message for a completed quiz result.
   *
   * Summary is determined by the pass/fail configurations on the quiz.
   *
   * @param QuizResult $quiz_result
   *   The quiz result.
   *
   * @return
   *   Render array.
   */
  function getSummaryText(QuizResult $quiz_result) {
    $config = Drupal::config('quiz.settings');
    $quiz = Drupal::entityTypeManager()
      ->getStorage('quiz')
      ->loadRevision($quiz_result->get('vid')->getString());
    $token = Drupal::token();

    $account = $quiz_result->getOwner();
    $token_types = [
      'global' => NULL,
      'node' => $quiz,
      'user' => $account,
      'quiz_result' => $quiz_result,
    ];
    $summary = [];

    if ($paragraph = $this->getRangeFeedback($quiz, $quiz_result->get('score')->getString())) {
      // Found quiz feedback based on a grade range.
      $token = Drupal::token();
      $paragraph_text = $paragraph->get('quiz_feedback')->get(0)->getValue();
      $summary['result'] = check_markup($token->replace($paragraph_text['value'], $token_types), $paragraph_text['format']);
    }

    $pass_text = $quiz->get('summary_pass')->getValue()[0];
    $default_text = $quiz->get('summary_default')->getValue()[0];

    if ($config->get('use_passfail', 1) && $quiz->get('pass_rate')->getString() > 0) {
      if ($quiz_result->get('score')->getString() >= $quiz->get('pass_rate')->getString()) {
        // Pass/fail is enabled and user passed.
        $summary['passfail'] = check_markup($token->replace($pass_text['value'], $token_types), $pass_text['format']);
      }
      else {
        // User failed.
        $summary['passfail'] = check_markup($token->replace($default_text['value'], $token_types), $default_text['format']);
      }
    }
    else {
      // Pass/fail is not being used so display the default.
      $summary['passfail'] = check_markup($token->replace($default_text['value'], $token_types), $default_text['format']);
    }

    return $summary;
  }

  /**
   * Get summary text for a particular score from a set of result options.
   *
   * @param Quiz $quiz
   *   The quiz.
   * @param int $score
   *   The percentage score.
   *
   * @return Paragraph
   */
  function getRangeFeedback($quiz, $score) {
    foreach ($quiz->get('result_options')->referencedEntities() as $paragraph) {
      $range = $paragraph->get('quiz_feedback_range')->get(0)->getValue();
      if ($score >= $range['from'] && $score <= $range['to']) {
        return $paragraph;
      }
    }
  }

}
