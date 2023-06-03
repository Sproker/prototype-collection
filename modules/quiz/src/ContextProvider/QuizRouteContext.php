<?php

namespace Drupal\quiz\ContextProvider;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Sets the current quiz as a context on quiz routes.
 */
class QuizRouteContext implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * The route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new QuizRouteContext.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   */
  public function __construct(RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager) {
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $result = [];
    $context_definition = EntityContextDefinition::create('quiz')->setRequired(FALSE);
    $value = NULL;
    if ($route_object = $this->routeMatch->getRouteObject()) {
      $route_parameters = $route_object->getOption('parameters');

      if (isset($route_parameters['quiz']) && $quiz = $this->routeMatch->getParameter('quiz')) {
        $value = $quiz;
      }
      elseif ($this->routeMatch->getParameters()->has('quiz')) {
        // Try to load quiz from entity route that contains a 'quiz' parameter,
        // like on /quiz/{quiz}/result/{quiz_result}.
        $quiz = $this->entityTypeManager->getStorage('quiz')->load($this->routeMatch->getParameters()->get('quiz'));
        $value = !empty($quiz) ? $quiz : NULL;
      }
    }

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route']);

    $context = new Context($context_definition, $value);
    $context->addCacheableDependency($cacheability);
    $result['quiz'] = $context;

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $context = EntityContext::fromEntityTypeId('quiz', $this->t('Quiz from URL'));
    return ['quiz' => $context];
  }

}
