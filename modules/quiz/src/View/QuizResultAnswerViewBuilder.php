<?php

namespace Drupal\quiz\View;

use Drupal;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\quiz\Entity\QuizResultAnswer;

use function check_markup;
use function quiz_access_to_score;
use function render;

class QuizResultAnswerViewBuilder extends EntityViewBuilder {

  /**
   * Build the response content with feedback.
   *
   * @todo d8 putting this here, but needs to be somewhere else.
   */
  public function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    assert($entity instanceof QuizResultAnswer);
    // Add the question display if configured.
    $view_modes = \Drupal::service('entity_display.repository')
      ->getViewModes('quiz_question');
    $view_builder = Drupal::entityTypeManager()
      ->getViewBuilder('quiz_question');
    if ($entity->canReview("quiz_question_view_full")) {
      // Default view mode.
      $build["quiz_question_view_full"] = $view_builder->view($entity->getQuizQuestion());
    }
    foreach (array_keys($view_modes) as $view_mode) {
      // Custom view modes.
      if ($entity->canReview("quiz_question_view_" . $view_mode)) {
        $build["quiz_question_view_" . $view_mode] = $view_builder->view($entity->getQuizQuestion(), $view_mode);
      }
    }

    $rows = [];

    $labels = [
      'attempt' => $this->t('Your answer'),
      'choice' => $this->t('Choice'),
      'correct' => $this->t('Correct?'),
      'score' => $this->t('Score'),
      'answer_feedback' => $this->t('Feedback'),
      'solution' => $this->t('Correct answer'),
    ];
    Drupal::moduleHandler()->alter('quiz_feedback_labels', $labels);

    foreach ($entity->getFeedbackValues() as $idx => $row) {
      foreach ($labels as $reviewType => $label) {
        if ((isset($row[$reviewType]) && $entity->canReview($reviewType))) {
          // Add to table.
          if (!is_null($row[$reviewType])) {
            $rows[$idx][$reviewType]['data'] = $row[$reviewType];
            // Add to render.
            if ($display->getComponent($reviewType)) {
              $build[$reviewType] = [
                '#title' => $label,
                '#type' => 'item',
                '#markup' => render($row[$reviewType]),
              ];
            }
          }
        }
      }
    }

    if ($entity->isEvaluated()) {
      $score = $entity->getPoints();
      if ($entity->isCorrect()) {
        $class = 'q-correct';
      }
      else {
        $class = 'q-wrong';
      }
    }
    else {
      $score = $this->t('?');
      $class = 'q-waiting';
    }

    $quiz_result = $entity->getQuizResult();

    if ($entity->canReview('score') || $quiz_result->access('update')) {
      $build['score']['#theme'] = 'quiz_question_score';
      $build['score']['#score'] = $score;
      $build['score']['#max_score'] = $entity->getMaxScore();
      $build['score']['#class'] = $class;
    }

    if ($rows) {
      $headers = array_intersect_key($labels, $rows[0]);
      $build['table'] = [
        '#type' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
      ];
    }

    if ($entity->canReview('question_feedback')) {
      $account = $quiz_result->getOwner();
      $token_data = [
        'global' => NULL,
        'quiz_question' => $entity->getQuizQuestion(),
        'user' => $account,
      ];
      $feedback = Drupal::token()->replace($entity->getQuizQuestion()->get('feedback')->first()->getValue()['value'], $token_data);
      $build['question_feedback']['#markup'] = check_markup($feedback, $entity->getQuizQuestion()->get('feedback')->first()->getValue()['format']);
    }

    // Question feedback is dynamic.
    $build['#cache']['max-age'] = 0;

    return $build;
  }

}
