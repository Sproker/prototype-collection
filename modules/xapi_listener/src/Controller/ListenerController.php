<?php

namespace Drupal\xapi_listener\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\xapi_listener\ListenerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ListenerController.
 */
final class ListenerController extends ControllerBase
{

  /**
   * Listener service.
   *
   * @var ListenerService
   */
  protected $listenerService;

  /**
   * Constructs a new ListenerController object.
   *
   * @param ListenerService $listenerService
   *   The listener service object.
   */
  public function __construct(ListenerService $listenerService)
  {
    $this->listenerService = $listenerService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('xapi_listener.service')
    );
  }

  /**
   * Callback for the xAPI endpoint.
   *
   * @param Request $request
   *   The incoming request.
   *
   * @return JsonResponse
   *   The response.
   */

  public function xapi(Request $request)
  {
    $statements = json_decode($request->getContent(), TRUE);
    Drupal::logger('xapi_listener')->debug('Incoming statements: @statements', ['@statements' => print_r($statements, TRUE)]);
    $this->listenerService->logStatements($statements);
    return new JsonResponse(['success' => TRUE]);
  }

}
