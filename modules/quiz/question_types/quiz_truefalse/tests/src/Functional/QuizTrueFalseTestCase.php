<?php

namespace Drupal\Tests\quiz_truefalse\Functional;

use Drupal\quiz\Entity\QuizQuestion;
use Drupal\Tests\quiz\Functional\QuizQuestionTestBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test class for true false questions.
 *
 * @group QuizQuestion
 */
class QuizTrueFalseTestCase extends QuizQuestionTestBase {

  use StringTranslationTrait;

  public function getQuestionType() {
    return 'truefalse';
  }

  public static $modules = ['quiz_truefalse'];

  /**
   * Test adding a truefalse question.
   */
  public function testCreateQuizQuestion($settings = []) {
    // Login as our privileged user.
    $this->drupalLogin($this->admin);

    $question = QuizQuestion::create([
        'type' => 'truefalse',
        'title' => 'TF 1 title',
        'truefalse_correct' => ['value' => 1],
        'body' => 'TF 1 body text',
      ] + $settings);

    $question->save();

    return $question;
  }

  public function testTakeQuestion() {
    $question = $this->testCreateQuizQuestion();

    // Link the question.
    $quiz = $this->linkQuestionToQuiz($question);

    // Test that question appears in lists.
    $this->drupalGet("quiz/{$quiz->id()}/questions");
    $this->assertText('TF 1 title');

    // Login as non-admin.
    $this->drupalLogin($this->user);

    // Take the quiz.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->assertNoText('TF 1 title');
    $this->assertText('TF 1 body text');
    $this->assertText('True');
    $this->assertText('False');

    // Test validation.
    $this->submitForm([], $this->t('Finish'));
    $this->assertText('You must provide an answer.');

    // Test correct question.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer]" => 1,
    ], $this->t('Finish'));
    $this->assertText('You got 1 of 1 possible points.');

    // Test incorrect question.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer]" => 0,
    ], $this->t('Finish'));
    $this->assertText('You got 0 of 1 possible points.');
  }

  /**
   * Test incorrect question with all feedbacks on.
   */
  public function testQuestionFeedback() {
    // Login as our privileged user.
    $this->drupalLogin($this->admin);

    // Create the quiz and question.
    $question = $this->testCreateQuizQuestion();

    // Link the question.
    $quiz = $this->linkQuestionToQuiz($question);

    // Login as non-admin.
    $this->drupalLogin($this->user);
    // Take the quiz.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer]" => 1,
    ], $this->t('Finish'));
    $this->assertRaw('quiz-score-icon correct');
    $this->assertRaw('quiz-score-icon should');
    // Take the quiz.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer]" => 0,
    ], $this->t('Finish'));
    $this->assertRaw('quiz-score-icon incorrect');
    $this->assertRaw('quiz-score-icon should');
  }

  /**
   * Test that the question response can be edited.
   */
  public function testEditQuestionResponse() {
    // Create & link a question.
    $question = $this->testCreateQuizQuestion();
    $quiz = $this->linkQuestionToQuiz($question);
    $quiz->set('backwards_navigation', 1);
    $quiz->set('allow_change', 1);
    $quiz->save();

    $question2 = $this->testCreateQuizQuestion();
    $this->linkQuestionToQuiz($question2, $quiz);

    // Login as non-admin.
    $this->drupalLogin($this->user);

    // Take the quiz.
    $this->drupalGet("quiz/{$quiz->id()}/take");

    // Test editing a question.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->submitForm([
      "question[{$question->id()}][answer]" => 0,
    ], $this->t('Next'));
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->submitForm([
      "question[{$question->id()}][answer]" => 1,
    ], $this->t('Next'));
  }

}
