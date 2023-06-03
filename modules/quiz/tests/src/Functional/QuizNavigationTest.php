<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test question navigation.
 *
 * @group Quiz
 */
class QuizNavigationTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse'];

  /**
   * Test the question navigation and access.
   */
  public function testQuestionNavigationBasic() {
    $this->drupalLogin($this->admin);
    $quiz_node = $this->createQuiz();

    // 3 questions.
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
    $question3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question3, $quiz_node);

    // Testing basic navigation. Ensure next questions are not yet available.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->assertText("Page 1 of 3");
    $this->assertResponse(200);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertResponse(403);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/3");
    $this->assertResponse(403);

    // Answer a question, ensure next question is available.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertText("Page 2 of 3");
    $this->assertResponse(200);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/3");
    $this->assertResponse(403);
  }

  /**
   * Test that all questions are available when quiz jumping is on.
   */
  public function testQuestionNavigationJumping() {
    $this->drupalLogin($this->admin);
    $quiz_node = $this->createQuiz();

    // 5 questions.
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
    $question3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question3, $quiz_node);
    $question4 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question4, $quiz_node);
    $question5 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question5, $quiz_node);

    // Testing jumpable navigation.
    $this->drupalLogin($this->user);

    // We should not have a selectbox
    $quiz_node->set('allow_jumping', 0)->save();
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertNoFieldById('edit-question-number', NULL);

    // Now we should have a selectbox
    $quiz_node->set('allow_jumping', 1)->save();
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertFieldById('edit-question-number', NULL);

    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->assertResponse(200);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertResponse(200);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/3");
    $this->assertResponse(200);

    // We should have a selectbox right now.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertFieldById('edit-question-number', NULL);
    // Check that the "first" pager link does not appear.
    $this->assertNoLinkByHref("quiz/{$quiz_node->id()}/take/1");

    // Test the switch between select/pager.
    $config = \Drupal::configFactory()->getEditable('quiz.settings');
    // Set the threshold to 5 questions.
    $config->set('pager_start', 5);
    // One on each side.
    $config->set('pager_siblings', 2);
    $config->save();
    $this->drupalGet("quiz/{$quiz_node->id()}/take/3");
    $this->assertNoFieldById('edit-question-number');
    $this->assertNoLink('1');
    $this->assertLinkByHref("quiz/{$quiz_node->id()}/take/2");
    $this->assertNoLinkByHref("quiz/{$quiz_node->id()}/take/3");
    $this->assertLinkByHref("quiz/{$quiz_node->id()}/take/4");
    $this->assertNoLink('5');
  }

  /**
   * Test that a user can skip a question.
   */
  public function testQuestionNavigationSkipping() {
    $this->drupalLogin($this->admin);
    // Default behavior, anyway.
    $quiz_node = $this->createQuiz(['allow_skipping' => 1]);

    // 3 questions.
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
    $question3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question3, $quiz_node);

    // Ensure next questions are blocked until skipped.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->assertResponse(200);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertResponse(403);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/3");
    $this->assertResponse(403);

    // Leave a question blank.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([], $this->t('Leave blank'));
    // Now question 2 is accessible.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertResponse(200);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/3");
    $this->assertResponse(403);
  }

  /**
   * Test preventing backwards navigation of questions.
   */
  public function testQuestionNavigationBackwards() {
    $this->drupalLogin($this->admin);
    $quiz_node = $this->createQuiz([
      'backwards_navigation' => 0,
      'allow_skipping' => 0,
      'allow_jumping' => 0,
    ]);

    // 3 questions.
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
    $question3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question3, $quiz_node);

    // Testing basic navigation.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->assertResponse(200);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertResponse(403);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/3");
    $this->assertResponse(403);

    // Answer a question, ensure next question is available. Ensure previous
    // question is not.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertResponse(200);
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->assertResponse(403);
  }

}
