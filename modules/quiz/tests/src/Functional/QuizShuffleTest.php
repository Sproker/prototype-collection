<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Tests for random questions.
 *
 * Since this is random by nature, there is a chance that these will fail. We
 * use 5 layout builds to try and mitigate that chance.
 *
 * @group Quiz
 */
class QuizShuffleTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_page', 'quiz_truefalse'];

  /**
   * Test random order of questions.
   */
  public function testShuffle() {
    $this->drupalLogin($this->admin);

    $quiz = $this->createQuiz([
      'randomization' => 1,
    ]);

    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 1 body text',
    ]);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 2 body text',
    ]);
    $question3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 3 body text',
    ]);
    $question4 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 4 body text',
    ]);
    $question5 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 5 body text',
    ]);
    $this->linkQuestionToQuiz($question1, $quiz);
    $this->linkQuestionToQuiz($question2, $quiz);
    $this->linkQuestionToQuiz($question3, $quiz);
    $this->linkQuestionToQuiz($question4, $quiz);
    $this->linkQuestionToQuiz($question5, $quiz);

    for ($i = 1; $i <= 10; $i++) {
      $questions = $quiz->buildLayout();
      $out[$i] = '';
      foreach ($questions as $question) {
        $out[$i] .= $question['qqid'];
      }
    }

    // Check that at least one of the orders is different.
    $this->assertNotEqual(count(array_unique($out)), 1, $this->t('At least one set of questions was different.'));

    // Start the quiz.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}");
  }

  /**
   * Test that questions inside of pages are shuffled.
   */
  public function testShuffleInPages() {
    $this->drupalLogin($this->admin);

    $quiz = $this->createQuiz([
      'randomization' => 1,
    ]);

    // Create the questions.
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 1 body text',
    ]);
    $this->linkQuestionToQuiz($question1, $quiz);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 2 body text',
    ]);
    $this->linkQuestionToQuiz($question2, $quiz);
    $question3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 3 body text',
    ]);
    $this->linkQuestionToQuiz($question3, $quiz);
    $question4 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 4 body text',
    ]);
    $this->linkQuestionToQuiz($question4, $quiz);
    $question5 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 5 body text',
    ]);
    $this->linkQuestionToQuiz($question5, $quiz);

    // Create the pages.
    $page1 = $this->createQuestion(['type' => 'page']);
    $this->linkQuestionToQuiz($page1, $quiz);
    $page2 = $this->createQuestion(['type' => 'page']);
    $this->linkQuestionToQuiz($page2, $quiz);
    // Go to the manage questions form.
    $this->drupalGet("quiz/{$quiz->id()}/questions");
    $post = [
      // Make the questions have parents.
      "question_list[1][qqr_pid]" => 6, // Page 1
      "question_list[2][qqr_pid]" => 6, // Page 1
      "question_list[3][qqr_pid]" => 6, // Page 1
      "question_list[4][qqr_pid]" => 7, // Page 2
      "question_list[5][qqr_pid]" => 7, // Page 2
      // Adjust weight of pages.
      "question_list[6][weight]" => 1,
      "question_list[7][weight]" => 2,
    ];
    $this->submitForm($post, $this->t('Submit'));

    for ($i = 1; $i <= 10; $i++) {
      $questions = $quiz->buildLayout();
      $out[$i] = '';
      foreach ($questions as $question) {
        $out[$i] .= $question['qqid'];
      }
    }

    // Check that at least one of the orders is different.
    $this->assertNotEqual(count(array_unique($out)), 1, $this->t('At least one set of questions was different.'));

    // Start the quiz, make sure the questions stayed put on their pages.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->assertText('TF 1 body text');
    $this->assertText('TF 2 body text');
    $this->assertText('TF 3 body text');
    $this->assertNoText('TF 4 body text');
    $this->assertNoText('TF 5 body text');

    // We know the 3 questions on the page.
    $this->submitForm([
      "question[{$question1->id()}][answer]" => TRUE,
      "question[{$question2->id()}][answer]" => TRUE,
      "question[{$question3->id()}][answer]" => TRUE,
    ], $this->t('Next'));

    $this->assertNoText('TF 1 body text');
    $this->assertNoText('TF 2 body text');
    $this->assertNoText('TF 3 body text');
    $this->assertText('TF 4 body text');
    $this->assertText('TF 5 body text');
  }

}
