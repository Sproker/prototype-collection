<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test aspects of quiz deletion.
 *
 * @group Quiz
 */
class QuizDeletionTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse'];

  /**
   * Test basic quiz creation.
   */
  public function testQuizDeletion() {
    $this->drupalLogin($this->user);
    $quiz_node = $this->createQuiz();

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

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
      ], $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 1,
      ], $this->t('Finish'));

    // Delete user
    $this->user->delete();

    // Delete the quiz
    $quiz_node->delete();
  }

}
