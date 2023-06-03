<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test quiz resume functionality.
 *
 * @group Quiz
 */
class QuizResumeTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse'];

  /**
   * Test the quiz resuming from database.
   */
  public function testQuizResuming() {
    $this->drupalLogin($this->admin);
    // Resuming is default behavior.
    $quiz_node = $this->createQuiz(['allow_resume' => 1, 'takes' => 1]);

    // 2 questions.
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz_node);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question2, $quiz_node);

    // Answer a question. Ensure the question navigation restrictions are
    // maintained.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertResponse(403);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));

    // Login again.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertText('Resuming');

    // We should have been advanced to the next question.
    $this->assertUrl("quiz/{$quiz_node->id()}/take/2");

    // Assert 2nd question is accessible (indicating the answer to #1 was
    // saved.)
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertResponse(200);
  }

  /**
   * Test the quiz not resuming from database.
   */
  public function testQuizNoResuming() {
    $this->drupalLogin($this->admin);
    // Resuming is default behavior.
    $quiz_node = $this->createQuiz(['allow_resume' => 0, 'takes' => 1]);

    // 2 questions.
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz_node);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question2, $quiz_node);

    // Answer a question. Ensure the question navigation restrictions are
    // maintained.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertResponse(403);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));

    // Login again.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertNoText('Resuming');

    // Assert 2nd question is not accessible (indicating the answer to #1 was
    // not saved.)
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertResponse(403);
  }

}
