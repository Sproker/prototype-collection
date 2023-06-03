<?php

namespace Drupal\Tests\h5p_analytics\Kernel;

use Drupal\Core\Config\Config;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueInterface;
use Drupal\h5p_analytics\Exception\MissingConfigurationException;
use Drupal\h5p_analytics\LrsService;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use ReflectionClass;

/**
 * Test description.
 *
 * @group h5p_analytics
 */
class TestLrsService extends KernelTestBase {

  /**
   * Sample LRS statement
   *
   * @var string
   */
  const STATEMENT = <<<EOT
{
  "actor": {
    "name": "Jim Beam",
    "mbox": "mailto:jim.beam@example.com"
  },
  "verb": {
    "id": "https://adlnet.gov/expapi/verbs/experienced",
    "display": { "en-US": "experienced" }
  },
  "object": {
    "id": "https://example.com/activities/watching-stars",
    "definition": {
      "name": { "en-US": "Watching stars" }
    }
  }
}
EOT;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['h5p_analytics', 'dblog'];

  /**
   * LRS service
   *
   * @var LrsService
   */
  protected $lrsService;

  /**
   * Database connection
   *
   * @var Connection
   */
  protected $connection;

  /**
   * Queue
   *
   * @var QueueInterface
   */
  protected $queue;

  /**
   * LRS statements data
   *
   * @var array
   */
  protected $statementsData = [];

  /**
   * Config
   *
   * @var Config
   */
  protected $config;

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('h5p_analytics', [
      'h5p_analytics_statement_log',
      'h5p_analytics_request_log',
    ]);
    $this->installSchema('dblog', [
      'watchdog',
    ]);

    $this->lrsService = $this->container->get('h5p_analytics.lrs');
    $this->connection = $this->container->get('database');
    $this->queue = $this->container->get('queue');
    $this->config = $this->config($this->lrsService::SETTINGS);

    $statementData = json_encode(self::STATEMENT, true);
    $this->statementsData[] = $statementData;
    $this->statementsData[] = $statementData;

    foreach ([
               [
                 'code' => 200,
                 'reason' => 'OK',
                 'count' => 10,
                 'data' => '',
                 'created' => 0,
               ],
               [
                 'code' => 400,
                 'reason' => 'Bad request',
                 'count' => 5,
                 'data' => '',
                 'created' => 0,
               ],
               [
                 'code' => 200,
                 'reason' => 'OK',
                 'count' => 15,
                 'data' => '',
                 'created' => 0,
               ],
             ] as $fields) {
      $this->connection->insert('h5p_analytics_statement_log')
        ->fields($fields)
        ->execute();
    }

    foreach ([
               [
                 'code' => 200,
                 'reason' => 'OK',
                 'error' => '',
                 'count' => 15,
                 'data' => '',
                 'created' => 0,
               ],
               [
                 'code' => 400,
                 'reason' => 'Bad request',
                 'error' => 'Bad request',
                 'count' => 10,
                 'data' => '',
                 'created' => 0,
               ],
               [
                 'code' => 200,
                 'reason' => 'OK',
                 'error' => '',
                 'count' => 15,
                 'data' => '',
                 'created' => 0,
               ],
             ] as $fields) {
      $this->connection->insert('h5p_analytics_request_log')
        ->fields($fields)
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->connection
      ->delete('h5p_analytics_statement_log')
      ->execute();
    $this->connection
      ->delete('h5p_analytics_request_log')
      ->execute();
    parent::tearDown();
  }

  /**
   * Tests LRS service class constants.
   */
  public function testConstants() {
    $reflectionClass = new ReflectionClass(LrsService::class);
    $this->assertTrue($reflectionClass->hasConstant('SETTINGS'));
    $this->assertTrue($reflectionClass->hasConstant('DEFAULT_BATCH_SIZE'));
    $this->assertTrue($reflectionClass->hasConstant('TIMEOUT'));
    $this->assertEquals('h5p_analytics.settings', LrsService::SETTINGS);
    $this->assertEquals(100, LrsService::DEFAULT_BATCH_SIZE);
    $this->assertEquals(45.0, LrsService::TIMEOUT);
  }

  /**
   * Tests batch size method.
   */
  public function testGetBatchSize() {
    $this->assertNull($this->config->get('batch_size'));
    $this->assertEquals(100, $this->lrsService->getBatchSize());
    $this->config->set('batch_size', 5)->save();
    $this->assertEquals($this->config->get('batch_size'), $this->lrsService->getBatchSize());
  }

  /**
   * Tests batch size method.
   */
  public function testGetTimeout() {
    $this->assertNull($this->config->get('timeout'));
    $this->assertEquals(45.0, $this->lrsService->getTimeout());
    $this->config->set('timeout', 30.0)->save();
    $this->assertEquals($this->config->get('timeout'), $this->lrsService->getTimeout());
  }

  /**
   * Tests statement processing into batches.
   */
  public function testProcessStatementsCron() {
    $statementData = json_encode(self::STATEMENT, true);
    $statements = $this->queue->get('h5p_analytics_statements');
    $statements->createItem($statementData);
    $statements->createItem($statementData);
    $statements->createItem($statementData);
    $statements->createItem($statementData);
    $statements->createItem($statementData);
    $this->assertEquals(5, $statements->numberOfItems());

    $config = $this->config('h5p_analytics.settings');
    $config->set('batch_size', 3)->save();
    $this->lrsService->processStatementsCron();
    $this->assertEquals(0, $statements->numberOfItems());

    $batches = $this->queue->get('h5p_analytics_batches');
    $this->assertEquals(2, $batches->numberOfItems());
    $batch = $batches->claimItem();
    $this->assertEquals(3, count($batch->data));
    $batches->deleteItem($batch);
    $this->assertEquals(1, $batches->numberOfItems());
    $batch = $batches->claimItem();
    $this->assertEquals(2, count($batch->data));
    $batches->deleteItem($batch);
    $this->assertEquals(0, $batches->numberOfItems());
  }

  /**
   * Tests making statements HTTP request.
   */
  public function testMakeStatementsHttpRequest() {
    $httpClient = $this->prophesize(Client::class);
    $httpClient->post('https://endpoint/statements', [
      'json' => $this->statementsData,
      'auth' => ['key', 'secret'],
      'headers' => [
        'X-Experience-API-Version' => '1.0.1',
      ],
      'timeout' => 45.0,
    ])->shouldBeCalledTimes(1);
    $this->container->set('http_client', $httpClient->reveal());
    $reflectionClass = new ReflectionClass(LrsService::class);
    $reflectionProperty = $reflectionClass->getProperty('httpClient');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($this->lrsService, $this->container->get('http_client'));
    $this->lrsService->makeStatementsHttpRequest('https://endpoint', 'key', 'secret', $this->statementsData);
  }

  /**
   * Tests sending data to LRS.
   *
   * @throws \Exception
   */
  public function testSendToLrs() {
    $missingConfigurationExceptionCallback = function ($e) {
      $this->assertInstanceOf(MissingConfigurationException::class, $e);
      $this->assertEquals('At least one of the required LRS configuration settings is missing!', $e->getMessage());
    };

    $requestExceptionCallback = function($e, $watchdogCount, $logCount) {
      $this->assertInstanceOf(RequestException::class, $e);
      $this->assertEquals('cURL error 6: Could not resolve host: endpoint (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)', $e->getMessage());
      $this->assertEquals($watchdogCount, $this->connection->select('watchdog')->countQuery()->execute()->fetchField());
      $this->assertEquals($logCount, $this->connection->select('h5p_analytics_request_log')->countQuery()->execute()->fetchField());
    };

    try {
      $this->lrsService->sendToLrs($this->statementsData, false);
    } catch (MissingConfigurationException $e) {
      call_user_func($missingConfigurationExceptionCallback, $e);
    }

    $this->config->set('xapi_endpoint', 'https://endpoint')->save();
    try {
      $this->lrsService->sendToLrs($this->statementsData, false);
    } catch (MissingConfigurationException $e) {
      call_user_func($missingConfigurationExceptionCallback, $e);
    }

    $this->config->set('key', 'key')->save();
    try {
      $this->lrsService->sendToLrs($this->statementsData, false);
    } catch (MissingConfigurationException $e) {
      call_user_func($missingConfigurationExceptionCallback, $e);
    }

    $this->config->set('secret', 'secret')->save();
    try {
      $this->lrsService->sendToLrs($this->statementsData, false);
    } catch (RequestException $e) {
      call_user_func($requestExceptionCallback, $e, 1, 4);
    }

    try {
      $this->lrsService->sendToLrs($this->statementsData, true);
    } catch (RequestException $e) {
      call_user_func($requestExceptionCallback, $e, 2, 4);
    }

    $httpClient = $this->prophesize(Client::class);
    $httpClient->post('https://endpoint/statements', [
      'json' => $this->statementsData,
      'auth' => ['key', 'secret'],
      'headers' => [
        'X-Experience-API-Version' => '1.0.1',
      ],
      'timeout' => 45.0,
    ])->willThrow(new \Exception('Fake exception'));
    $this->container->set('http_client', $httpClient->reveal());
    $reflectionClass = new ReflectionClass(LrsService::class);
    $reflectionProperty = $reflectionClass->getProperty('httpClient');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($this->lrsService, $this->container->get('http_client'));
    try {
      $this->lrsService->sendToLrs($this->statementsData, false);
    } catch (\Exception $e) {
      $this->assertEquals('Fake exception', $e->getMessage());
      $this->assertEquals(3, $this->connection->select('watchdog')->countQuery()->execute()->fetchField());
    }
  }

  /**
   * Tests statements statistics with predefined data.
   */
  public function testGetStatementStatistics() {
    $this->assertEquals([
      (object)[
        "code" => "200",
        "reason" => null,
        "total" => "25",
      ],
      (object)[
        "code" => "400",
        "reason" => null,
        "total" => "5",
      ],
    ], $this->lrsService->getStatementStatistics());
  }

  /**
   * Tests request statistics with predefined data.
   */
  public function testGetRequestStatistics() {
    $this->assertEquals([
      (object)[
        "code" => "200",
        "reason" => null,
        "error" => null,
        "total" => "2",
      ],
      (object)[
        "code" => "400",
        "reason" => null,
        "error" => null,
        "total" => "1",
      ],
    ], $this->lrsService->getRequestStatistics());
  }

}
