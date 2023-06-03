<?php
/**
 * @file
 * Contains \Drupal\h5p_analytics\Plugin\QueueWorker\BatchQueue.
 */
namespace Drupal\h5p_analytics\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\h5p_analytics\LrsServiceInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\h5p_analytics\Exception\MissingConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes statement batches.
 *
 * @QueueWorker(
 *   id = "h5p_analytics_batches",
 *   title = @Translation("Statement batch processing worker"),
 *   cron = {"time" = 1200}
 * )
 */
final class BatchQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * Database connection
   *
   * @var Connection
   */

  protected $connection;
  /**
   * Time service
   *
   * @var TimeInterface
   */
  protected $time;

  /**
   * LRS service
   *
   * @var LrsServiceInterface
   */
  protected $lrs;

  /**
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param Connection $connection
   * @param TimeInterface $time
   * @param LrsServiceInterface $lrs
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection, TimeInterface $time, LrsServiceInterface $lrs)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->connection = $connection;
    $this->time = $time;
    $this->lrs = $lrs;
  }

  /**
   * @param ContainerInterface $container
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   *
   * @return BatchQueue|static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('h5p_analytics.lrs')
    );
  }

  /**
   * Adds data to statement log. Mostly happens if request is successful or
   * @param int $code Status code
   * @param string $reason Response reason
   * @param int $count Number of statements in the batch
   * @param string $data JSON-encoded data or NULL
   * @throws \Exception
   */
  private function addToStatementLog($code, $reason, $count, $data) {
    $this->connection->insert('h5p_analytics_statement_log')
    ->fields([
      'code' => $code,
      'reason' => $reason,
      'count' => $count,
      'data' => $data,
      'created' => $this->time->getRequestTime(),
    ])
    ->execute();
  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function processItem($data) {

    try {
      $response = $this->lrs->sendToLrs($data);
      // TODO This could throw an error, needs to be handled
      $this->addToStatementLog($response->getStatusCode(), $response->getReasonPhrase(), sizeof($data), NULL);
    } catch (MissingConfigurationException $e) {
      throw new SuspendQueueException($e->getMessage());
    } catch (RequestException $e) {
      switch((int)$e->getCode()) {
        case 400:
          // TODO This could throw an error, needs to be handled
          $this->addToStatementLog($e->getCode(), $e->hasResponse() ? $e->getResponse()->getReasonPhrase() : '', sizeof($data), json_encode($data));
        break;
        case 401:
          throw new SuspendQueueException($e->getMessage());
        break;
        case 403:
        case 404:
        case 500:
        case 502:
        case 503:
          // These cases will allow data transfer to be retried
          throw $e;
        break;
        default:
          // TODO See if we could detect timeout case and make the try again logic the default one instead
          // The only concern is tha case of request timing out and data potentially being accepted by the server
          $this->addToStatementLog($e->getCode(), $e->hasResponse() ? $e->getResponse()->getReasonPhrase() : '', sizeof($data), json_encode($data));
      }
    }
  }
}
