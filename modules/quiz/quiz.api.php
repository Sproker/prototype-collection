<?php

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * @file quiz.api.php
 * Hooks provided by Quiz module.
 *
 * These entity types provided by Quiz also have entity API hooks. There are a
 * few additional Quiz specific hooks defined in this file.
 *
 * quiz (settings for quiz nodes)
 * quiz_result (quiz attempt/result)
 * quiz_result_answer (answer to a specific question in a quiz result)
 * quiz_question
 * quiz_question_relationship (relationship from quiz to question)
 *
 * So for example
 *
 * hook_quiz_result_presave(QuizResult $quiz_result)
 *   - Runs before a result is saved to the DB.
 * hook_quiz_question_relationship_insert(QuizQuestionRelationship $quiz_question_relationship)
 *   - Runs when a new question is added to a quiz.
 *
 * You can also use Rules to build conditional actions based off of these
 * events.
 *
 * Enjoy :)
 */

/**
 * Expose a feedback option to Quiz so that Quiz administrators can choose when
 * to show it to Quiz takers.
 *
 * @return array
 *   An array of feedback options keyed by machine name.
 */
function hook_quiz_feedback_options() {
  return [
    'percentile' => t('Percentile'),
  ];
}

/**
 * Allow modules to alter the quiz feedback options.
 *
 * @param array $review_options
 *   An array of review options keyed by a machine name.
 */
function hook_quiz_feedback_options_alter(&$review_options) {
  // Change label.
  $review_options['quiz_feedback'] = t('General feedback from the Quiz.');

  // Disable showing correct answer.
  unset($review_options['solution']);
}

/**
 * Allow modules to alter the feedback labels.
 *
 * These are the labels that are displayed to the user, so instead of
 * "Answer feedback" you may want to display something more learner-friendly.
 *
 * @param $feedback_labels
 *   An array keyed by the feedback option. Default keys are the keys from
 *   quiz_get_feedback_options().
 */
function hook_quiz_feedback_labels_alter(&$feedback_labels) {
  $feedback_labels['solution'] = t('The answer you should have chosen.');
}

/**
 * Implements hook_entity_access().
 *
 * Control access to Quizzes.
 *
 * @see quiz_quiz_access() for default access implementations.
 *
 * @see hook_entity_access() for usage.
 *
 * The behavior and return values are the same. Quiz introduces another
 * operation: "take"
 */
function hook_quiz_access(EntityInterface $entity, $operation, AccountInterface $account) {
  if ($operation == 'take') {

  }
}
