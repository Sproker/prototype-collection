<?php

/**
 * @file
 * Administration file for Quiz stats Module.
 */
/**
 * Page for selecting between several quiz revisions.
 *
 * @param $nid  Quiz node id.
 *
 * @return
 *  Themed html.
 */
function quiz_stats_revision_selector_page($quiz) {
  // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
  // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
  $res = \Drupal::database()
    ->query('SELECT vid FROM {node_revision} WHERE nid = :nid ORDER BY vid', [':nid' => $quiz->nid]);
  // Uses this variable to slightly increase performance.
  $count = 0;
  $vids = [];
  while ($res_o = $res->fetch()) {
    $vids[] = $res_o->vid;
    $count++;
  }
  if ($count < 1) {
    return t('Something went wrong. Please try again');
  }
  elseif ($count == 1) {
    // If there is only one revision we jump directly to that revision.
    drupal_goto("quiz/{$quiz->nid}/quiz/statistics/{$vids[0]}");
  }
  else {
    $content = [];
    $content['explanation'] = t('There are !num revisions of this quiz that have been taken.
    Different revisions may have different scoring, difficulity and other differences affecting its statistics.
    Because of this you have to choose the revision you want to see statistics from.', ['!num' => $count]);
    $content['links'] = [];
    foreach ($vids as $key => $value) {
      $content['links'][] = 'quiz/' . $quiz->nid . '/quiz/statistics/' . $value;
    }
    return theme('quiz_stats_revision_selector', ['content' => $content]);
  }
}

/**
 * Returns statistics for all available quizzes.
 *
 * @param $uid
 *   User id.
 *
 * @return
 *   HTML table.
 */
function quiz_stats_get_basic_stats($uid = 0) {
  $results = _quiz_get_quizzes($uid);
  return empty($results) ? t('No Statistics found.') : theme('quiz_stats_get_basic_stats', array('results' => $results));
}

/**
 * Get stats for a single quiz. Maybe also for a single user.
 *
 * @param $vid
 *   quiz revision id.
 * @param $uid
 *   User id.
 *
 * @return
 *   HTML page with charts/graphs.
 */
function quiz_stats_get_adv_stats($vid, $uid = 0) {
  $charts = [];
  drupal_add_css(drupal_get_path('module', 'quiz_stats') . '/quiz_stats.css', ['type' => 'file', 'weight' => CSS_THEME]);
  $charts['takeup'] = _get_date_vs_takeup_count_chart($vid, $uid);
  // Line chart/graph showing quiz takeup date along x-axis and count along
  // y-axis.
  $charts['status'] = _get_quiz_status_chart($vid, $uid);
  // 3D pie chart showing percentage of pass, fail, incomplete quiz status.
  $charts['top_scorers'] = _get_quiz_top_scorers_chart($vid, $uid);
  // Bar chart displaying top scorers.
  $charts['grade_range'] = _get_quiz_grade_range_chart($vid, $uid);
  return theme('quiz_stats_charts', ['charts' => $charts]);
}

/**
 * Generates grade range chart.
 *
 * @param int $vid
 *   Revision id of quiz node.
 * @param int $uid
 *   An optional user ID. If supplied, only quizzes created by that user will be
 *   returned.
 *
 * @return array|false
 *   Array with chart and metadata, FALSE if no quiz was found.
 */
function _get_quiz_grade_range_chart($vid, $uid = 0) {
  // @todo: make the ranges configurable
  // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
  // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
  $query = \Drupal::database()->select('quiz_node_results', 'qnr');
  $query->addExpression('SUM(score >= 0 && score < 20)', 'zero_to_twenty');
  $query->addExpression('SUM(score >= 20 && score < 40)', 'twenty_to_fourty');
  $query->addExpression('SUM(score >= 40 && score < 60)', 'fourty_to_sixty');
  $query->addExpression('SUM(score >= 60 && score < 80)', 'sixty_to_eighty');
  $query->addExpression('SUM(score >= 80 && score <= 100)', 'eighty_to_hundred');
  $query->condition('qnr.vid', $vid);
  $query->condition('qnr.is_invalid', 0);
  if ($uid != 0) {
    $query->condition('qnr.uid', $uid);
  }
  $range = $query->execute()->fetch();
  $count = $range->zero_to_twenty + $range->twenty_to_fourty + $range->fourty_to_sixty + $range->sixty_to_eighty + $range->eighty_to_hundred;
  if ($count < 2) {
    return FALSE;
  }

  // Return the chart with some meta data.
  $chart = '<div id="quiz_top_scorers" class="quiz-stats-chart-space">';
  $chart .= theme('quiz_grade_range', ['range' => $range]);
  $chart .= '</div>';
  return [
    'chart' => $chart,
    'title' => t('Distribution'),
    'explanation' => t('This chart shows the distribution of the scores on this quiz.'),
  ];
}

/**
 * Generates the top scorers chart.
 *
 * @param int $vid
 *   Revision id of quiz node.
 * @param int $uid
 *   An optional user ID. If supplied, only quizzes created by that user will be
 *   returned.
 *
 * @return array|false
 *   Array with chart and metadata, FALSE if no quiz was found.
 */
function _get_quiz_top_scorers_chart($vid, $uid = 0) {
  $top_scorers = [];

  // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
  // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
  $query = \Drupal::database()->select('quiz_node_results', 'qnr');
  $query->leftJoin('users', 'u', 'u.uid = qnr.uid');
  $query->fields('qnr', ['score']);
  $query->fields('u', ['name']);
  $query->condition('qnr.vid', $vid);
  $query->condition('qnr.is_invalid', 0);
  if ($uid != 0) {
    $query->condition('qnr.uid', $uid);
  }
  $query->orderBy('score', 'DESC');
  $query->range(0, 10);
  $results = $query->execute();
  while ($result = $results->fetchAssoc()) {
    $key = $result['name'] . '-' . $result['score'];
    $top_scorers[$key] = $result;
  }
  if (count($top_scorers) == 0) {
    return FALSE;
  }

  // Return the chart with some meta data.
  $chart = '<div id="quiz_top_scorers" class="quiz-stats-chart-space">';
  $chart .= theme('quiz_top_scorers', ['scorer' => $top_scorers]);
  $chart .= '</div>';
  return [
    'chart' => $chart,
    'title' => t('Top scorers'),
    'explanation' => t('This chart shows which quiz takers have the highest scores.'),
  ];
}

/**
 * Generates a chart showing the status for all registered responses to a quiz.
 *
 * @param int $vid
 *   Revision id of quiz node.
 * @param int $uid
 *   An optional user ID. If supplied, only quizzes created by that user will be
 *   returned.
 *
 * @return array|false
 *   Array with chart and metadata, FALSE if no quiz was found.
 */
function _get_quiz_status_chart($vid, $uid = 0) {
  // Get the pass rate of the given quiz by vid.
  // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
  // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
  $pass_rate = \Drupal::database()->select('quiz_node_properties', 'qnp')
    ->fields('qnp', ['pass_rate'])
    ->condition('vid', $vid)
    ->execute()
    ->fetchField();
  if (!$pass_rate) {
    return FALSE;
  }

  // Get the count value of results row above and below pass rate.
  // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
  // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
  $query = \Drupal::database()->select('quiz_node_results', 'qnr');
  $query->addExpression("SUM(score >= $pass_rate)", 'no_pass');
  $query->addExpression("SUM(score < $pass_rate)", 'no_fail');
  $query->addExpression('SUM(is_evaluated = 0)', 'no_incomplete');
  $query->condition('vid', $vid);
  $query->condition('is_invalid', 0);
  $quiz = $query->execute()->fetchAssoc();
  if (($quiz['no_pass'] + $quiz['no_fail'] + $quiz['no_incomplete']) < 1) {
    // No sufficient data.
    return FALSE;
  }
  // Generates quiz status chart 3D pie chart.
  $chart = '<div id="get_quiz_status_chart" class="quiz-stats-chart-space">';
  $chart .= theme('get_quiz_status_chart', ['quiz' => $quiz]);
  $chart .= '</div>';
  return [
    'chart' => $chart,
    'title' => t('Status'),
    'explanation' => t('This chart shows the status for all attempts made to answer this revision of the quiz.'),
  ];
}

/**
 * Generates chart showing how often the quiz has been taken the last days.
 *
 * @param $uid
 *   user id.
 * @param $vid
 *   revision id of quiz.
 *
 * @return
 *   HTML to display chart.
 */
function _get_date_vs_takeup_count_chart($vid, $uid = 0) {
  $start = 0;
  $end = 30;
  $takeup = [];
  $sql = "SELECT COUNT(result_id) AS count,
            DATE_FORMAT(FROM_UNIXTIME(time_start), '%Y-%m-%e') AS date
            FROM {quiz_node_results}
            WHERE vid = :vid AND time_start > (UNIX_TIMESTAMP(NOW()) - (86400*$end))";
  $sql_args[':vid'] = $vid;
  if ($uid != 0) {
    $sql .= " AND uid = :uid";
    $sql_args[':uid'] = $uid;
  }
  $sql .= " GROUP BY date ORDER BY time_start ASC";
  // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
  // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
  $results = \Drupal::database()->query($sql, $sql_args);
  $res_o = $results->fetchAll();
  if ($res_o) {
    $chart = '<div id="date_vs_takeup_count" class="quiz-stats-chart-space">';
    // Wrapping the chart output with div for custom theming.
    $chart .= theme('date_vs_takeup_count', ['takeup' => $res_o]);
    // Generate date vs takeup count line chart.
    $chart .= '</div>';
    return [
      'chart' => $chart,
      'title' => t('Activity'),
      'explanation' => t('This chart shows how many times the quiz has been taken each day over the last !days days.', ['!days' => $end]),
    ];
  }
}

/**
 * Computes quiz attempt count.
 *
 * @param $vid
 *   quiz node id.
 * @param $uid
 *   user id for filtering.
 *
 * @return
 *   Integer containing number of times that a quiz has
 *   been attended, can be filtered by uid.
 */
function _quiz_get_attempt_count($nid, $uid = 0) {
  $sql = 'SELECT COUNT(result_id) FROM {quiz_node_results} WHERE nid = :nid';
  $arg[':nid'] = $nid;
  if ($uid != 0) {
    $sql .= ' AND uid = :uid';
    $arg[':uid'] = $uid;
  }
  // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
  // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
  return \Drupal::database()->query($sql, $arg)->fetchField();
}

// Theme functions.

/**
 * Theme function for quiz grade range chart.
 *
 * Generates Chart using CHART API function.
 *
 * @param $range
 *   array containg quiz results data structure.
 *
 * @return
 *   HTML to render/display chart.
 */
function theme_quiz_grade_range($variables) {
  $range = $variables['range'];

  $max = max((array) $range);
  $count = array_sum((array) $range);

  $chart = [
    '#type' => 'chart',
    '#chart_type' => 'column',
    '#title' => t('Score distribution'),
  ];
  $chart['range'] = [
    '#type' => 'chart_data',
    '#title' => t('% of quiz takers'),
    '#data' => [
      round(($range->zero_to_twenty / $count) * 100),
      round(($range->twenty_to_fourty / $count) * 100),
      round(($range->fourty_to_sixty / $count) * 100),
      round(($range->sixty_to_eighty / $count) * 100),
      round(($range->eighty_to_hundred / $count) * 100),
    ],
  ];
  $chart['xaxis'] = [
    '#title' => t('Score'),
    '#type' => 'chart_xaxis',
    '#labels' => [
      '0-20%',
      '20-40%',
      '40-60%',
      '60-80%',
      '80-100%',
    ],
  ];
  $chart['yaxis'] = [
    '#title' => t('Grade istribution'),
    '#type' => 'chart_yaxis',
    '#max' => 100,
  ];
  return \Drupal::service('renderer')->render($chart);
}

/**
 * Generates a chart of quiz top scorers.
 *
 * @param $attendees
 *   array containing quiz data structure.
 *
 * @return
 *   HTML to render quiz top scorers chart.
 */
function theme_quiz_top_scorers($variables) {
  $attendees = array_reverse($variables['scorer']);
  $chart = [
    '#type' => 'chart',
    '#title' => t('Quiz Top Scorers'),
    '#chart_type' => 'column',
  ];
  $chart['bar_data'] = [
    '#type' => 'chart_data',
    '#title' => 'Result',
  ];
  foreach ($attendees as $attendee) {
    $chart['bar_data']['#data'][] = (float) $attendee['score'];
    $labels[] = $attendee['name'] . ' ' . $attendee['score'] . '%';

  }
  $chart['yaxis'] = [
    '#title' => t('Score') . ' (%)',
    '#type' => 'chart_yaxis',
    '#max' => 100,
  ];
  $chart['xaxis'] = [
    '#title' => 'Quiz Attendees',
    '#type' => 'chart_xaxis',
    '#labels' => $labels,

  ];
  return \Drupal::service('renderer')->render($chart);
}

/**
 * Generates quiz status chart number of pass, fail, incomplete.
 *
 * @param $quiz
 *   array of quiz data structure.
 *
 * @return
 *   HTML to render quiz status chart.
 */
function theme_get_quiz_status_chart($variables) {
  $quiz = $variables['quiz'];
  $quiz['total'] = $quiz['no_pass'] + $quiz['no_fail'] + $quiz['no_incomplete'];

  $chart = [
    '#type' => 'chart',
    '#title' => t('Quiz results'),
    '#chart_type' => 'pie',
  ];

  $chart['pie_data'] = [
    '#type' => 'chart_data',
    '#title' => t('Result'),
    '#labels' => ['Pass', 'Incomplete', 'Fail'],
    '#data' => [
      (int) $quiz['no_pass'],
      (int) $quiz['no_incomplete'],
      (int) $quiz['no_fail'],
    ],
  ];
  return \Drupal::service('renderer')->render($chart);
}

/**
 * Generates date vs takeup count chart.
 *
 * @param $takeup
 *   Array quiz data structure.
 *
 * @return
 *   HTML to render/display chart.
 */
function theme_date_vs_takeup_count($variables) {
  $chart = [
    '#type' => 'chart',
    '#title' => t('Activity by date'),
    '#chart_type' => 'line',
  ];

  $chart['line_data'] = [
    '#type' => 'chart_data',
    '#title' => t('Result'),
    '#labels' => ['Something'],
  ];

  $chart['line_data']['#data'] = [];
  $labels = [];
  foreach ($variables['takeup'] as $point) {
    $chart['line_data']['#data'][] = (int) $point->count;
    $labels[] = $point->date;
  }

  $chart['xaxis'] = [
    '#type' => 'chart_xaxis',
    '#labels' => $labels,
  ];
  $chart['yaxis'] = [
    '#title' => 'Participants',
    '#type' => 'chart_yaxis',
  ];
  return \Drupal::service('renderer')->render($chart);
}

/**
 * Generates table of results from quiz data structure.
 *
 * @param @results
 *   Array containing quiz results data structure.
 *
 * @return
 *   HTML table containing quiz title, author, questions count, attempt count.
 */
function theme_quiz_stats_get_basic_stats($variables) {
  $results = $variables['results'];
  if (empty($results)) {
    return '<p>' . t('No questions were found.') . '</p>';
  }

  $header = [
    'title' => t('Quiz'),
    'creator' => t('Author'),
    'created' => t('Created'),
  ];
  if (user_access('access author stats')) {
    $header['questions_count'] = t('Questions');
    $header['attempt_count'] = t('Attempts');
  }

  $p = drupal_get_path('module', 'quiz_stats');
  $chart_icon = theme('image', [
    'path' => "$p/chart.png",
    'width' => t('Charts'),
    'height' => t('See charts'),
  ]);

  foreach ($results as $result) {
    $title_link = user_access('access author stats') ? 'quiz/' . $result['nid'] . '/quiz/statistics' : 'user/' . arg(1) . '/stats/';
    $row = [
      'title' => l($chart_icon . ' ' . $result['title'], $title_link, ['html' => TRUE]),
      'creator' => l($result['name'], 'user/' . $result['uid']),
      'created' => \Drupal::service('date.formatter')->format($result['created'], 'short'),
    ];
    if (user_access('access author stats')) {
      $row['questions_count'] = quiz_get_number_of_questions($result['vid'], $result['nid']);
      $row['attempt_count'] = _quiz_get_attempt_count($result['nid']);
    }
    $rows[] = $row;
  }

  module_load_include('inc', 'quiz', 'quiz.pages');
  $cc = '<em>' . t('Chart icon from !url', ['!url' => 'pinvoke.com']) . '</em>';

  return theme('table', [
      'header' => $header,
      'rows' => $rows,
      'attributes' => ['id' => 'tablesorter'],
    ]) . $cc;
}
