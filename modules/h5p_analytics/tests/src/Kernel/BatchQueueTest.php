<?php

namespace Drupal\Tests\h5p_analytics\Kernel;

use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\h5p_analytics\Exception\MissingConfigurationException;
use Drupal\h5p_analytics\LrsServiceInterface;
use Drupal\h5p_analytics\Plugin\QueueWorker\BatchQueue;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Test description.
 *
 * @group h5p_analytics
 */
class BatchQueueTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['h5p_analytics'];

  /**
   * @var BatchQueue
   */
  protected $batchQueue;

  /**
   * @var Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('h5p_analytics', [
      'h5p_analytics_statement_log',
    ]);

    $lrsService = $this->prophesize(LrsServiceInterface::class);
    $lrsService->sendToLrs([
      'code' => 200,
    ])->willReturn(new Response(200));
    $lrsService->sendToLrs([
      'error' => 'MissingConfigurationException',
    ])->willThrow(new MissingConfigurationException('MissingConfigurationException'));

    $request = new Request('POST', 'https://endpoint');

    foreach ([400, 401, 403, 404, 500, 502, 503, 418] as $code) {
      $lrsService->sendToLrs([
        'error' => 'RequestException',
        'code' => $code,
      ])->willThrow(new RequestException('RequestException', $request, new Response($code)));
    }

    $this->connection = $this->container->get('database');
    $this->batchQueue = new BatchQueue([], 'h5p_analytics_batches', [], $this->container->get('database'), $this->container->get('datetime.time'), $lrsService->reveal());
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->connection->delete('h5p_analytics_statement_log')->execute();
    parent::tearDown();
  }

  /**
   * Asserts that h5p_analytics_statement_log entries count equals to provided number.
   *
   * @param int $count
   */
  protected function assertAnalyticsStatementLogCount(int $count) {
    $this->assertEquals($count, $this->connection
      ->select('h5p_analytics_statement_log')
      ->countQuery()
      ->execute()
      ->fetchField()
    );
  }

  /**
   * Asserts that h5p_analytics_statement_log entry exists in the database.
   *
   * @param int $code
   * @param string $reason
   * @param int $count
   * @param string|null $data
   * @return void
   */
  protected function assertInAnalyticsStatementLog(int $code, string $reason, int $count, ?string $data) {
    $query = $this->connection
      ->select('h5p_analytics_statement_log')
      ->condition('code', $code, '=')
      ->condition('reason', $reason, '=')
      ->condition('count', $count, '=');

    if (is_null($data)) {
      $query->isNull('data');
    } else {
      $query->condition('data', $data, '=');
    }

    $this->assertEquals(1, $query
      ->countQuery()
      ->execute()
      ->fetchField()
    );
  }

  /**
   * Tests adding statement to the database log.
   * @throws \ReflectionException
   */
  public function testAddToStatementLog() {
    $reflectionMethod = new \ReflectionMethod($this->batchQueue, 'addToStatementLog');
    $reflectionMethod->setAccessible(true);
    $reflectionMethod->invoke($this->batchQueue, 200, 'OK', 5, '');
    $this->assertAnalyticsStatementLogCount(1);
    $this->assertInAnalyticsStatementLog(200, 'OK', 5, '');
  }

  /**
   * Tests item processing.
   * @throws \Exception
   */
  public function testProcessItem() {
    $this->batchQueue->processItem([
      'code' => 200,
    ]);
    $this->assertAnalyticsStatementLogCount(1);
    $this->assertInAnalyticsStatementLog(200, 'OK', 1, null);

    try {
      $this->batchQueue->processItem([
        'error' => 'MissingConfigurationException',
      ]);
    } catch (SuspendQueueException $e) {
      $this->assertEquals('MissingConfigurationException', $e->getMessage());
    }

    $this->batchQueue->processItem([
      'error' => 'RequestException',
      'code' => 400,
    ]);
    $this->assertAnalyticsStatementLogCount(2);
    $this->assertInAnalyticsStatementLog(400, 'Bad Request', 2, '{"error":"RequestException","code":400}');

    try {
      $this->batchQueue->processItem([
        'error' => 'RequestException',
        'code' => 401,
      ]);
    } catch (SuspendQueueException $e) {
      $this->assertEquals('RequestException', $e->getMessage());
    }

    foreach ([403, 404, 500, 502, 503] as $code) {
      try {
        $this->batchQueue->processItem([
          'error' => 'RequestException',
          'code' => $code,
        ]);
      } catch (RequestException $e) {
        $this->assertEquals('RequestException', $e->getMessage());
      }
    }

    $this->batchQueue->processItem([
      'error' => 'RequestException',
      'code' => 418,
    ]);
    $this->assertAnalyticsStatementLogCount(3);
    $this->assertInAnalyticsStatementLog(418, 'I\'m a teapot', 2, '{"error":"RequestException","code":418}');
  }

}
