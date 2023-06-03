<?php

namespace Drupal\Tests\quiz\Kernel\ContextProvider;

use Drupal\Core\Routing\RouteMatch;
use Drupal\KernelTests\KernelTestBase;
use Drupal\quiz\ContextProvider\QuizRouteContext;
use Drupal\Tests\quiz\Traits\QuizTestTrait;

/**
 * @coversDefaultClass \Drupal\quiz\ContextProvider\QuizRouteContext
 *
 * @group quiz
 */
class QuizContextTest extends KernelTestBase {

  use QuizTestTrait;

  /**
   * Ensure strict config schema checking.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'file',
    'text',
    'user',
    'options',
    'range',
    'paragraphs',
    'quiz',
    'quiz_truefalse',
    'datetime_range',
    'entity_reference_revisions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('quiz');
    $this->installEntitySchema('quiz_question');
    $this->installEntitySchema('quiz_question_relationship');

    $this->installConfig(['filter', 'file', 'range', 'paragraphs', 'quiz', 'quiz_truefalse']);
  }

  /**
   * @covers ::getAvailableContexts
   */
  public function testGetAvailableContexts() {
    $context_repository = $this->container->get('context.repository');

    // Test quiz.quiz_route_context:quiz exists.
    $contexts = $context_repository->getAvailableContexts();
    $this->assertArrayHasKey('@quiz.quiz_route_context:quiz', $contexts);
    $this->assertSame('entity:quiz', $contexts['@quiz.quiz_route_context:quiz']->getContextDefinition()
      ->getDataType());
  }

  /**
   * @covers ::getRuntimeContexts
   */
  public function testGetRuntimeContexts() {
    // Create quiz.
    $quiz = $this->createQuiz();
    $question = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question, $quiz);

    // Create RouteMatch for quiz take route.
    $route_provider = \Drupal::service('router.route_provider');
    $route = $route_provider->getRouteByName('quiz.question.take');
    $route_match = new RouteMatch('quiz.question.take', $route, [
      'quiz' => $quiz,
    ]);

    // Initiate QuizRouteContext with RouteMatch.
    $provider = new QuizRouteContext($route_match, $this->container->get('entity_type.manager'));

    $runtime_contexts = $provider->getRuntimeContexts([]);
    $this->assertArrayHasKey('quiz', $runtime_contexts);
    $this->assertTrue($runtime_contexts['quiz']->hasContextValue());
  }

}
