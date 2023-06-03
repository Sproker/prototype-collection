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
class QuizRandomTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse'];

  /**
   * Test random plus required questions from a pool.
   *
   * @todo add test for weighted questions
   */
  public function testRandomQuestions() {
    $this->drupalLogin($this->admin);

    $quiz = $this->createQuiz([
      'randomization' => 2,
      'number_of_random_questions' => 2,
    ]);

    $question = $this->createQuestion([
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
    $this->linkQuestionToQuiz($question, $quiz);
    $this->linkQuestionToQuiz($question2, $quiz);
    $this->linkQuestionToQuiz($question3, $quiz);
    $this->linkQuestionToQuiz($question4, $quiz);
    $this->linkQuestionToQuiz($question5, $quiz);

    // Set up one required question.
    $this->drupalGet("quiz/{$quiz->id()}/questions");
    $this->submitForm([
      "question_list[1][question_status]" => TRUE,
    ], $this->t('Submit'));

    for ($i = 1; $i <= 10; $i++) {
      $questions = $quiz->buildLayout();
      $this->assertEqual(count($questions), 3, $this->t('Quiz has 3 questions.'));
      // Concatenate question IDs to build a hash to check.
      $out[$i] = '';
      foreach ($questions as $question) {
        $out[$i] .= $question['qqid'];
      }
      $this->assert(strpos($out[$i], $question['qqid']) !== FALSE, $this->t('Quiz always contains required question 1'));
    }

    // Also check that at least one of the orders is different.
    $this->assertNotEqual(count(array_unique($out)), 1, $this->t('At least one set of questions were different.'));

    // Test number of questions.
    $num_questions = $quiz->getNumberOfQuestions();
    $this->assertEqual($num_questions, 3);

    // Start the quiz.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}");
  }

}
