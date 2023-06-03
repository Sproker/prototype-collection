<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizQuestionRelationship;
use Drupal\quiz\Entity\QuizResult;
use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use function node_load;

/**
 * Test quiz results behavior.
 *
 * @group Quiz
 */
class QuizResultTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse', 'quiz_multichoice', 'quiz_test'];

  /**
   * Test the various result summaries and pass rate.
   */
  public function testPassRateSummary() {
    // Set up some alternatives.
    $a = Paragraph::create([
      'type' => 'quiz_result_feedback',
      'quiz_feedback' => 'You got 90 or more on the quiz',
      'quiz_feedback_range' => [
        'from' => 90,
        'to' => 100,
      ],
    ]);
    $a->save();

    $b = Paragraph::create([
      'type' => 'quiz_result_feedback',
      'quiz_feedback' => 'You got between 50 and 89',
      'quiz_feedback_range' => [
        'from' => 50,
        'to' => 89,
      ],
    ]);
    $b->save();

    $c = Paragraph::create([
      'type' => 'quiz_result_feedback',
      'quiz_feedback' => 'You failed bro',
      'quiz_feedback_range' => [
        'from' => 0,
        'to' => 49,
      ],
    ]);
    $c->save();

    // By default, the feedback is after the quiz.
    $quiz = $this->createQuiz([
      'pass_rate' => 75,
      'summary_pass' => 'This is the summary if passed',
      'summary_default' => 'This is the default summary text',
    ]);

    $quiz->get('result_options')->appendItem($a);
    $quiz->get('result_options')->appendItem($b);
    $quiz->get('result_options')->appendItem($c);

    $quiz->save();

    // 3 questions.
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'feedback' => 'Q1Feedback',
    ]);
    $this->linkQuestionToQuiz($question1, $quiz);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'feedback' => 'Q2Feedback',
    ]);
    $this->linkQuestionToQuiz($question2, $quiz);
    $question3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'feedback' => 'Q3Feedback',
    ]);
    $this->linkQuestionToQuiz($question3, $quiz);

    // Test 100%.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question3->id()}][answer]" => 1,
    ], $this->t('Finish'));
    $this->assertText('You got 90 or more on the quiz');
    $this->assertText('This is the summary if passed');
    $this->assertNoText('This is the default summary text');

    // Test 66%.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question3->id()}][answer]" => 0,
    ], $this->t('Finish'));
    $this->assertText('You got between 50 and 89');
    $this->assertNoText('This is the summary if passed');
    $this->assertText('This is the default summary text');

    // Test 33%.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 0,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question3->id()}][answer]" => 0,
    ], $this->t('Finish'));
    $this->assertText('You failed bro');
    $this->assertNoText('This is the summary if passed');
    $this->assertText('This is the default summary text');
  }

  /**
   * Test result CRUD operations.
   *
   * We have (at least) 3 different tables to clean up from on a Quiz result
   * deletion - the quiz_result, the result answers, and the question type's
   * answer storage. Let's ensure at least that happens.
   *
   * @todo rework for D8, at least we don't have to clean up question storage
   * because it's attached to QuizResultAnswer.
   */
  public function testQuizResultCrud() {
    $this->drupalLogin($this->admin);

    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $quiz = $this->linkQuestionToQuiz($question1);

    // Submit an answer.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Finish'));

    $q = Quiz::load(1);
    $qq = QuizQuestion::load(1);
    $qqr = QuizQuestionRelationship::load(1);
    $qr = QuizResult::load(1);
    $qra = QuizResultAnswer::load(1);

    $this->assertEquals($qq->id(), $qqr->getQuiz()
      ->id(), 'Question belongs to the relationship.');
    $this->assertEquals($qqr->getQuiz()
      ->id(), $q->id(), 'Relationship belongs to the quiz.');
    $this->assertEquals($qr->id(), $qra->getQuizResult()
      ->id(), 'Answer belongs to the result.');

    // Delete the quiz.
    $q->delete();

    $q = Quiz::load(1);
    $qq = QuizQuestion::load(1);
    $qqr = QuizQuestionRelationship::load(1);
    $qr = QuizResult::load(1);
    $qra = QuizResultAnswer::load(1);

    // Check that only the question remains.
    $this->assertEquals($q, NULL);
    $this->assertEquals($qqr, NULL);
    $this->assertNotEquals($qq, NULL);
    $this->assertEquals($qr, NULL);
    $this->assertEquals($qra, NULL);
  }

  /**
   * Test access to results.
   */
  public function testQuizResultAccess() {
    $this->drupalLogin($this->admin);

    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $quiz_node = $this->linkQuestionToQuiz($question1);

    // Submit an answer.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Finish'));

    $resultsUrl = $this->getUrl();

    $this->drupalGet($resultsUrl);
    $this->assertResponse(200, $this->t('User can view own result'));
    $this->drupalLogout();
    $this->drupalGet($resultsUrl);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test our wildcard answer exporter.
   */
  public function testQuizResultAnswerExport() {

    // Set up some alternatives.
    $a = Paragraph::create([
      'type' => 'multichoice',
      'multichoice_correct' => 1,
      'multichoice_answer' => 'This is the A answer',
      'multichoice_feedback_chosen' => 'You chose A',
      'multichoice_feedback_not_chosen' => 'You did not choose A',
      'multichoice_score_chosen' => 1,
      'multichoice_score_not_chosen' => 0,
    ]);
    $a->save();

    $b = Paragraph::create([
      'type' => 'multichoice',
      'multichoice_answer' => 'This is the B answer',
      'multichoice_feedback_chosen' => 'You chose B',
      'multichoice_feedback_not_chosen' => 'You did not choose B',
      'multichoice_score_chosen' => -1,
      'multichoice_score_not_chosen' => 0,
    ]);
    $b->save();

    $question = QuizQuestion::create([
      'title' => 'MCQ 1 Title',
      'type' => 'multichoice',
      'choice_multi' => 0,
      'choice_random' => 0,
      'choice_boolean' => 0,
      'body' => 'MCQ 1 body text',
    ]);

    $question->get('alternatives')->appendItem($a);
    $question->get('alternatives')->appendItem($b);

    $question->save();

    $quiz = $this->linkQuestionToQuiz($question);

    $this->drupalLogin($this->admin);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question->id()}][answer][user_answer]" => 1,
    ], $this->t('Finish'));

    // Verify the user's answer appears on our modified report.
    $this->drupalGet("quiz/{$quiz->id()}/quiz-result-export-test");
    $this->assertText('1. MCQ 1 Title');
    $this->assertText('This is the A answer');
    $this->assertNoText('This is the B answer');
  }

  /**
   * Test that deleting a question from a Quiz doesn't result in a fatal error.
   */
  public function testBrokenResults() {
    $this->drupalLogin($this->admin);

    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $quiz_node = $this->linkQuestionToQuiz($question1);

    // Submit an answer.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Finish'));

    // Delete the question.
    $question1->delete();

    // And there should not be a fatal error.
    $this->drupalGet("quiz/{$quiz_node->id()}/result/1");
    $this->assertResponse(200, 'Saw the results page.');
  }

}
