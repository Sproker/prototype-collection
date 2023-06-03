<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test aspects of quiz creation.
 *
 * @group Quiz
 */
class QuizCreationTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse'];

  /**
   * Test basic quiz creation.
   */
  public function testQuizCreation() {
    $this->drupalLogin($this->admin);
    $this->drupalGet("quiz/add/quiz");

    // These are the basic system defaults.
    $this->assertFieldChecked('edit-allow-resume-value');
    $this->assertFieldChecked('edit-allow-skipping-value');
    $this->assertNoFieldChecked('edit-allow-jumping-value');
    $this->assertFieldChecked('edit-allow-change-value');
    $this->assertFieldChecked('edit-backwards-navigation-value');
    $this->assertNoFieldChecked('edit-repeat-until-correct-value');
    $this->assertNoFieldChecked('edit-mark-doubtful-value');
    $this->assertFieldChecked('edit-show-passed-value');
    $this->assertFieldChecked('edit-status-value');

    $this->submitForm([
      'title[0][value]' => 'Test quiz creation',
      'body[0][value]' => 'Test quiz description',
    ], $this->t('Save'));
    $this->assertText('Manage questions');
  }

  /**
   * Test cloning quizzes with questions.
   */
  public function testCloneQuiz() {
    $this->drupalLogin($this->admin);
    $question = $this->createQuestion([
      'title' => 'TF 1',
      'body' => 'TF 1',
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $quiz = $this->linkQuestionToQuiz($question);

    $quiz->save();
    $new_quiz = $quiz->createDuplicate();
    $new_quiz->save();
    $this->assertNotEquals($new_quiz->id(), $quiz->id());

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->assertText('TF 1');
    $this->drupalGet("quiz/{$new_quiz->id()}/take");
    $this->assertText('TF 1');
  }

}
