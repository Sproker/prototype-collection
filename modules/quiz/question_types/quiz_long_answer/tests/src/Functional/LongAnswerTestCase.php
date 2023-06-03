<?php

namespace Drupal\Tests\quiz_long_answer\Functional;

use Drupal\quiz\Entity\QuizQuestion;
use Drupal\Tests\quiz\Functional\QuizQuestionTestBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * @file
 * Unit tests for the long_answer Module.
 */

/**
 * Test class for long answer.
 *
 * @group Quiz
 */
class LongAnswerTestCase extends QuizQuestionTestBase {

  use StringTranslationTrait;

  // We need some text formats.
  protected $profile = 'standard';

  public static $modules = ['quiz_long_answer'];

  public function getQuestionType() {
    return 'long_answer';
  }

  /**
   * Test manually graded questions.
   *
   * Also test feedback here instead of its own test case.
   *
   * Note: we use two questions here to make sure the grading form is handled
   * correctly.
   */
  public function testGradeAnswerManualFeedback() {
    $this->drupalLogin($this->admin);

    $question1 = $this->testCreateQuizQuestion();
    $quiz = $this->linkQuestionToQuiz($question1);

    $question2 = $this->testCreateQuizQuestion();
    $this->linkQuestionToQuiz($question2, $quiz);

    // Test correct.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 'the answer is the zero one infinity rule',
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 'the number two really is ridiculous',
    ], $this->t('Finish'));
    $this->assertText('Your score: 0%');
    // Strange behavior - extra spacing in the HTML.
    //$this->assertText('Score ? of 10');
    $this->assertText('This answer has not yet been scored.');
    $this->assertNoFieldByName('question[0][score]');
    $this->assertNoFieldByName('question[1][score]');
    $url_of_result = $this->getUrl();

    // Test grading the question.
    $this->drupalLogin($this->admin);
    $this->drupalGet('admin/quiz/reports/unevaluated');
    $this->clickLink($this->t('Score'));
    $this->submitForm([
      "question[1][score]" => 3,
      "question[2][score]" => 7,
      "question[1][answer_feedback][value]" => 'Feedback for answer 1.',
      "question[2][answer_feedback][value]" => 'Feedback for answer 2.',
      "question[1][answer_feedback][format]" => 'basic_html',
      "question[2][answer_feedback][format]" => 'basic_html',
    ], $this->t('Save score'));
    $this->assertText('The scoring data you provided has been saved.');

    // Test the score and feedback are visible to the user.
    $this->drupalLogin($this->user);
    $this->drupalGet($url_of_result);
    $this->assertText('You got 10 of 20 possible points.');
    $this->assertText('Your score: 50%');
    // Strange behavior - extra spacing in the HTML.
    //$this->assertText('Score 3 of 10');
    //$this->assertText('Score 7 of 10');
    $this->assertText('Feedback for answer 1.');
    $this->assertText('Feedback for answer 2.');
  }

  /**
   * Test adding and taking a long answer question.
   */
  public function testCreateQuizQuestion($settings = []) {
    if (!$settings) {
      $settings = [
        'long_answer_rubric' => [
          'value' => 'LA 1 rubric.',
          'format' => 'plain_text',
        ],
        'answer_text_processing' => 0,
      ];
    }

    // Login as our privileged user.
    $this->drupalLogin($this->admin);

    $question = QuizQuestion::create([
        'type' => 'long_answer',
        'title' => 'LA 1 title',
        'body' => 'LA 1 body text.',
      ] + $settings);
    $question->save();

    return $question;
  }

  /**
   * Test that rubric and answer filter settings are respected.
   */
  public function testFilterFormats() {
    // Login as our privileged user.
    $this->drupalLogin($this->admin);

    // Question that has no filtering, for rubric or answer.
    $question1 = QuizQuestion::create([
      'type' => 'long_answer',
      'title' => 'LA 1 title',
      'body' => 'LA 1 body text.',
      'long_answer_rubric' => [
        'value' => 'Rubric for LA 1, you will see the next tag <img src="http://httpbin.org/image/png?findmeRubricPlaintext">',
        'format' => 'restricted_html',
      ],
      'answer_text_processing' => 0,
    ]);
    $question1->save();

    // Question that has filtering, for rubric and answer.
    $question2 = QuizQuestion::create([
      'type' => 'long_answer',
      'title' => 'LA 2 title',
      'body' => 'LA 2 body text.',
      'long_answer_rubric' => [
        'value' => 'Rubric for LA 2, you will not see the next tag <img src="http://httpbin.org/image/png?findmeRubricFiltered">',
        'format' => 'full_html',
      ],
      'answer_text_processing' => 1,
    ]);
    $question2->save();

    $quiz = $this->linkQuestionToQuiz($question1);
    $this->linkQuestionToQuiz($question2, $quiz);

    // Login as a user and take the quiz.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    // Post plaintext answer.
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 'plaintext answer, you will see the next tag: <img src="http://httpbin.org/image/png?findmeAnswerPlaintext">',
    ], $this->t('Next'));
    // Post rich text answer.
    $this->submitForm([
      "question[{$question2->id()}][answer][value]" => 'filtered answer, you will see not see the next tag: <img src="http://httpbin.org/image/png?findmeAnswerFiltered">',
      "question[{$question2->id()}][answer][format]" => 'basic_html',
    ], $this->t('Finish'));

    // Login as a user and check the result.
    $this->drupalLogin($this->admin);
    $this->drupalGet("quiz/{$quiz->id()}/result/1");
    $this->assertText('&lt;img src', 'Plain text rubric image tag did not get rendered on page');
    $this->assertNoText('findmeRubricFiltered', 'Filtered text rubric image tag got stripped');
    $this->assertText('findmeAnswerPlaintext', 'Plain text answer image tag did not get rendered on page');
    $this->assertNoText('findmeAnswerFiltered', 'Filtered text answer image tag got stripped');
  }

  /**
   * Test that the question response can be edited.
   */
  public function testEditQuestionResponse() {
    $this->drupalLogin($this->admin);

    // Create & link a question.
    $question1 = $this->testCreateQuizQuestion();
    $quiz = $this->linkQuestionToQuiz($question1);
    $question2 = $this->testCreateQuizQuestion();
    $this->linkQuestionToQuiz($question2, $quiz);

    // Login as non-admin.
    $this->drupalLogin($this->user);

    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 'um some rule, I forget',
    ], $this->t('Next'));
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->assertSession()->responseContains('um some rule, I forget');
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 'um some rule, I forget',
    ], $this->t('Next'));
  }

  /**
   * Test that the question response can be exported.
   */
  public function testViews() {
    // Create & link a question.
    $question1 = $this->testCreateQuizQuestion();
    $quiz = $this->linkQuestionToQuiz($question1);

    // Login as non-admin.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 'um some rule, I forget',
    ], $this->t('Finish'));

    $this->drupalGet("quiz/{$quiz->id()}/quiz-result-export-test");
    $this->assertText('um some rule, I forget');
  }

}
