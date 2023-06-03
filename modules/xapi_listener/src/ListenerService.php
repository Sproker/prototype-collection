<?php

namespace Drupal\xapi_listener;

use Drupal;
use Drupal\Core\Database\Connection;

/**
 * Class ListenerService.
 */
class ListenerService
{

  /**
   * Database connection.
   *
   * @var Connection
   */
  protected $database;

  /**
   * Constructs a new ListenerService object.
   *
   * @param Connection $database
   *   The database connection object.
   */
  public function __construct(Connection $database)
  {
    $this->database = $database;
  }

  /**
   * Log statements to the database.
   *
   * @param array $statement
   *   The statements to log.
   */
  public function logStatements(array $statement)
  {
    $qtitle = $this->database->select('quiz', 'q')
      ->fields('q', ['title'])
      ->condition('qid', $statement['qid'])
      ->execute()
      ->fetchField();

    $existing_record = $this->database->select('xapi_listener_statements', 's')
      ->fields('s', ['attempts'])
      ->condition('qid', $statement['qid'])
      ->condition('qqid', $statement['qqid'])
      ->condition('uid', Drupal::currentUser()->id())
      ->execute()
      ->fetchAssoc();

    $attempts = $existing_record ? $existing_record['attempts'] + 1 : 1;
    preg_match('/\d+\.\d+S/', $statement['duration'], $matches);
    $duration_formatted = (float) rtrim($matches[0], 'S');

    $this->database->merge('xapi_listener_statements')
      ->key([
        'qid' => $statement['qid'],
        'qqid' => $statement['qqid'],
        'uid' => Drupal::currentUser()->id(),
      ])
      ->fields([
        'qtitle' => $qtitle,
        'score_raw' => $statement['score'],
        'score_max' => $statement['max'],
        'duration' => $duration_formatted,
        'attempts' => $attempts,
        'created' => Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }
}
