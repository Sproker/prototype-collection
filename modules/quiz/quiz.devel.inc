<?php

/**
 * Form callback for devel_generate support.
 */
function quiz_generate_form($form, $form_state) {
  $quiz_options['quiz'] = 'Quiz';

  $form['quiz_types'] = [
    '#type' => 'checkboxes',
    '#title' => t('Quiz types'),
    '#options' => $quiz_options,
    '#default_value' => ['quiz' => 'quiz'],
    '#required' => TRUE,
    '#access' => FALSE,
  ];

  $question_options = [];
  foreach (quiz_get_question_types() as $name => $question_type) {
    $question_options[$name] = $question_type['name'];
  }

  $form['quiz_question_types'] = [
    '#type' => 'checkboxes',
    '#title' => t('Question types'),
    '#options' => $question_options,
    '#default_value' => array_keys($question_options),
    '#required' => TRUE,
  ];

  $form['quiz_limit'] = [
    '#type' => 'textfield',
    '#title' => t('Number of quizzes to generate'),
    '#default_value' => 50,
    '#size' => 10,
    '#required' => TRUE,
    '#description' => t('Quiz authors will be randomly assigned.'),
  ];

  $form['quiz_question_limit'] = [
    '#type' => 'textfield',
    '#title' => t('Number of questions per quiz'),
    '#default_value' => 10,
    '#size' => 10,
    '#required' => TRUE,
    '#description' => t('Question authors will be randomly assigned.'),
  ];

  $form['quiz_results'] = [
    '#type' => 'textfield',
    '#title' => t('Number of results per quiz'),
    '#default_value' => 50,
    '#size' => 10,
    '#required' => TRUE,
    '#description' => t('Results will be randomly assigned to users already in the system.'),
  ];

  $form['submit'] = [
    '#type' => 'submit',
    '#value' => t('Generate'),
  ];

  return $form;
}

/**
 * Submit callback for devel_generate support.
 */
function quiz_generate_form_submit($form, &$form_state) {
  module_load_include('inc', 'devel_generate', 'devel_generate.fields');
  module_load_include('inc', 'devel_generate', 'devel_generate');

  $quiz_types = array_filter($form_state['values']['quiz_types']);
  $quiz_limit = $form_state['values']['quiz_limit'];
  $question_types = array_filter($form_state['values']['quiz_question_types']);
  $question_limit = $form_state['values']['quiz_question_limit'];
  $quiz_results = $form_state['values']['quiz_results'];

  for ($i = 1; $i <= $quiz_limit; $i++) {
    quiz_generate($quiz_type = array_rand($quiz_types), $question_types, $question_limit, $quiz_results);
  }
}

/**
 * Generate random quiz data.
 *
 * @param type $quiz_type
 * @param type $question_types
 * @param type $question_limit
 * @param type $quiz_results
 *
 * @todo associate results with random users
 *
 */
function quiz_generate($quiz_type, $question_types, $question_limit, $quiz_results) {
  $users = array_filter(devel_get_users());
  $quiz = (object) [
    'type' => $quiz_type,
    'title' => devel_create_greeking(rand(5, 10), TRUE),
    'uid' => $users[array_rand($users)],
    'created' => \Drupal::time()->getRequestTime(),
    'changed' => \Drupal::time()->getRequestTime(),
  ];

  // Populate all core fields on behalf of field.module.
  module_load_include('inc', 'devel_generate', 'devel_generate.fields');
  devel_generate_fields($quiz, 'node', $quiz->type);
  node_save($quiz);

  for ($i = 1; $i <= $question_limit; $i++) {
    quiz_generate_questions($quiz, array_rand($question_types));
  }

  for ($i = 1; $i <= $quiz_results; $i++) {
    $quiz_result = \Drupal::service('entity_type.manager')
      ->getStorage('quiz_result')
      ->create([
        'type' => 'quiz_result',
        'nid' => $quiz->nid,
        'vid' => $quiz->vid,
        'uid' => $users[array_rand($users)],
      ]);
    $quiz_result->save();
    // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
    // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
    \Drupal::database()->update('quiz_node_results_answers')
      ->expression('points_awarded', 'result_answer_id % 5')
      ->expression('is_correct', 'result_answer_id % 2')
      ->condition('result_id', $quiz_result->result_id)
      ->execute();
    // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
    // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
    \Drupal::database()->update('quiz_node_results')
      ->expression('score', rand(1, 100))
      ->expression('is_evaluated', 1)
      ->expression('time_end', rand(\Drupal::time()->getRequestTime() - 525600 * 60, \Drupal::time()->getRequestTime()))
      ->condition('result_id', $quiz_result->result_id)
      ->execute();
  }

  \Drupal::messenger()->addMessage('Generated quiz: ' . l($quiz->title, 'quiz/' . $quiz->nid));
}

/**
 * Generate Quiz questions.
 *
 * @param type $quiz
 * @param type $question_type
 */
function quiz_generate_questions($quiz, $question_type) {
  $users = array_filter(devel_get_users());
  $question_array = [
    'type' => $question_type,
    'changed' => \Drupal::time()->getRequestTime(),
    'moderate' => 0,
    'promote' => 0,
    'log' => '',
    'status' => 1,
    'sticky' => 0,
    'language' => LANGUAGE_NONE,
    'title' => devel_create_greeking(rand(5, 20), TRUE),
    'body' => [
      LANGUAGE_NONE => [
        [
          'value' => devel_create_para(rand(20, 50), 1),
        ],
      ],
    ],
    'uid' => $users[array_rand($users)],
  ];

  switch ($question_type) {
    case 'truefalse':
      $question_array += ['correct_answer' => rand(0, 1)];
      break;

    case 'short_answer':
      $question_array += [
        'correct_answer_evaluation' => rand(ShortAnswerQuestion::ANSWER_MATCH, ShortAnswerQuestion::ANSWER_MANUAL),
        'correct_answer' => devel_create_greeking(rand(10, 20)),
      ];
      break;

    case 'long_answer':
      $question_array += [
        'rubric' => devel_create_greeking(rand(10, 20)),
      ];
      break;

    case 'multichoice':
      $question_array += quiz_generate_dummy_multichoice_question_info();
      break;

    case 'quiz_directions':
      break;

    default:
      \Drupal::messenger()->addMessage('Unsupported question: ' . $question_type, 'error');
  }

  // Create question node.
  $question = (object) $question_array;
  // Populate all core fields on behalf of field.module.
  module_load_include('inc', 'devel_generate', 'devel_generate.fields');
  devel_generate_fields($question, 'node', $question->type);
  node_save($question);

  _quiz_question_get_instance($question)->saveRelationships($quiz->nid, $quiz->vid);
  quiz_update_max_score_properties([$quiz->vid]);
}

/**
 * Template for multichoice questions.
 *
 * @return array
 */
function quiz_generate_dummy_multichoice_question_info() {
  $array = [
    'choice_multi' => array_rand([0, 1]),
    'choice_random' => array_rand([0, 1]),
    'choice_boolean' => array_rand([0, 1]),
  ];

  $rand = $array['choice_multi'] ? 1 : rand(2, 10);
  for ($i = 0; $i < $rand; ++$i) {
    if (!$array['choice_multi']) {
      // Not multi answer - we can only do 1 correct answer.
      if ($i == 0) {
        $correct = 1;
      }
      else {
        $correct = 0;
      }
    }
    else {
      // Multi answer.
      $correct = rand(0, 1);
    }
    $array['alternatives'][] = [
      'correct' => $correct,
      'answer' => [
        'value' => devel_create_greeking(rand(2, 10)),
        'format' => filter_default_format(),
      ],
      'feedback_if_chosen' => [
        'value' => devel_create_greeking(rand(5, 10)),
        'format' => filter_default_format(),
      ],
      'feedback_if_not_chosen' => [
        'value' => devel_create_greeking(rand(5, 10)),
        'format' => filter_default_format(),
      ],
      'score_if_chosen' => 1,
      'score_if_not_chosen' => 0,
    ];
  }

  return $array;
}
