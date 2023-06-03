<?php

/**
 * @file
 * Administrator interface for Quiz module.
 */
// QUIZ ADMIN.
// Quiz Admin Settings.
// QUIZ RESULTS ADMIN.
/**
 * Quiz result report page for the quiz admin section.
 *
 * @param $quiz   The quiz node.
 * @param $result_id   The result id.
 */
function quiz_admin_results($quiz, $quiz_result) {
  // Preserve "Results" tab.
  $item = menu_get_item("quiz/$quiz->nid/quiz");
  menu_set_item(NULL, $item);

  return entity_ui_get_form('quiz_result', $quiz_result);
}

// HELPER FUNCTIONS.

/**
 * Adds inline js to automatically set the question's node title.
 */
function quiz_set_auto_title() {
  $max_length = \Drupal::config('quiz.settings')->get('autotitle_length', 50);
  drupal_add_js(['quiz_max_length' => $max_length], ['type' => 'setting']);
  drupal_add_js(drupal_get_path('module', 'quiz') . '/js/quiz.auto-title.js');
}
