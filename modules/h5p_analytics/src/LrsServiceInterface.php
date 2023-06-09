<?php

namespace Drupal\h5p_analytics;

/**
 * Interface LrsServiceInterface.
 */
interface LrsServiceInterface {

  /**
   * Returns batch size value or a default one if value is less than 1.
   *
   * @return int
   *   Batch size
   */
  public function getBatchSize(): int;

  /**
   * Returns HTTP request timeout value.
   *
   * @return float
   *   Request timeout
   */
  public function getTimeout(): float;

  /**
   * Processes statements into batches
   */
  public function processStatementsCron();

  /**
   * Low level method that sends statements to LRS xAPI endpoint.
   * Throws errors in case request does not succeed.
   *
   * @param  string $endpoint
   *   Endpoint URL
   * @param  string $key
   *   Client key/user
   * @param  string $secret
   *   Client secret/password
   * @param  array  $data
   *   An array of statements
   *
   * @return mixed
   *    Response object of HTTP request
   */
  public function makeStatementsHttpRequest(string $endpoint, string $key, string $secret, array $data);

  /**
   * Sends statements to the LRS endpoint.
   * Throws exceptions in case request is not successful.
   * Loads configuration data and logs results.
   *
   * @param  array  $data
   *   Array of statements
   * @param bool    $bypass_request_log
   *   Do not store the failed request data in the database
   *
   * @return mixed
   *   Response object of HTTP request
   */
  public function sendToLrs(array $data, bool $bypass_request_log = FALSE);

  /**
   * Returns an array of statement statistics objects that have parameters:
   * code, reason and total.
   * Please note that reason will have any value that is frist in the grouped
   * result set as there could possibly be different reasons for the same code.
   *
   * @return array
   *   Array of statement statistics objects
   */
  public function getStatementStatistics(): array;

  /**
   * Returns an array of http request statistics objects that have parameters:
   * code, reason, error, total.
   * Please note that both reason and error will have any value that is first in
   * the grouped result set as there could possibly be different reasons and
   * errors for the same code.
   *
   * @return array
   *   An array of http request statistics objects
   */
  public function getRequestStatistics(): array;

}
