<?php

namespace Drupal\quiz\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\quiz\Annotation\QuizQuestion;
use Traversable;

/**
 * Provides the Quiz Question plugin manager.
 */
class QuizQuestionPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new QuizQuestionPluginManager object.
   *
   * @param Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/quiz', $namespaces, $module_handler, QuizQuestionInterface::class, QuizQuestion::class);

    $this->alterInfo('quiz_question_info');
    $this->setCacheBackend($cache_backend, 'quiz_question_plugins');
  }

}
