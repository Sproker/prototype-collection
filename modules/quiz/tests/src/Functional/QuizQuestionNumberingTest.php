<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test question numbering.
 *
 * @group Quiz
 */
class QuizQuestionNumberingTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse', 'quiz_directions', 'quiz_page'];

  public function testQuestionNumbering() {
    $this->drupalLogin($this->admin);

    // Create Quiz with review of score.
    $quiz = $this->createQuiz();

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
      'type' => 'directions',
      'body' => 'QD 3 body text',
    ]);
    $this->linkQuestionToQuiz($question3, $quiz);
    // Create the page.
    $page1 = $this->createQuestion([
      'type' => 'page',
      'body' => 'PG 1 body text',
    ]);
    $this->linkQuestionToQuiz($page1, $quiz);
    // Go to the manage questions form.
    $this->drupalGet("quiz/{$quiz->id()}/questions");
    $post = [
      // Make the questions have parents.
      "question_list[{$question1->getRevisionId()}][qqr_pid]" => 4,
      "question_list[{$question2->getRevisionId()}][qqr_pid]" => 4,
      "question_list[{$question3->getRevisionId()}][qqr_pid]" => 4,
      // Mirror what JS would have done by adjusting the weights.
      "question_list[{$page1->getRevisionId()}][weight]" => 2,
      "question_list[{$question1->getRevisionId()}][weight]" => 3,
      "question_list[{$question2->getRevisionId()}][weight]" => 4,
      "question_list[{$question3->getRevisionId()}][weight]" => 5,
    ];
    $this->submitForm($post, $this->t('Submit'));

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");

    $this->assertText("PG 1 body text");

    $this->assertText("Question 1");
    $this->assertText("TF 1 body text");

    $this->assertText("Question 2");
    $this->assertText("TF 2 body text");

    // There we only 2 real questions. Verify another question is present
    // but we stopped numbering at 2.
    $this->assertNoText("Question 3");
    $this->assertText("QD 3 body text");
  }

}
