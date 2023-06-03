<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz\Entity\QuizResult;

/**
 * Test quiz evaluation.
 *
 * @group Quiz
 */
class QuizEvaluationTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_page', 'quiz_directions'];

  /**
   * Test that a quiz result is marked as evaluated.
   */
  public function testQuizEvaluation() {
    $this->drupalLogin($this->admin);

    $quiz_node = $this->createQuiz();

    $question_node1 = $this->createQuestion([
      'type' => 'directions',
      'body' => 'These are the quiz directions.',
    ]);
    $this->linkQuestionToQuiz($question_node1, $quiz_node); // QNR ID 1

    $page_node1 = $this->createQuestion(['type' => 'page']);
    $this->linkQuestionToQuiz($page_node1, $quiz_node); // QNR ID 2

    $this->drupalGet("quiz/{$quiz_node->id()}/questions");
    $post = [
      "question_list[{$question_node1->getRevisionId()}][qqr_pid]" => 2,
    ];
    $this->submitForm($post, $this->t('Submit'));

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
    ], $this->t('Finish'));

    $quiz_result = QuizResult::load(1);
    $this->assertEquals(true, $quiz_result->isEvaluated());
  }
}
