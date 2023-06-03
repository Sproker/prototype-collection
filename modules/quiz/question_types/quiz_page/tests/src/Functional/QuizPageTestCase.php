<?php

namespace Drupal\Tests\quiz_page\Functional;

use Drupal\quiz\Entity\QuizResult;
use Drupal\Tests\quiz\Functional\QuizQuestionTestBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use function db_query;

/**
 * Test quiz page behavior.
 *
 * @group Quiz
 */
class QuizPageTestCase extends QuizQuestionTestBase {

  use StringTranslationTrait;

  protected $profile = 'standard';

  public static $modules = ['quiz_page', 'quiz_truefalse'];

  public function getQuestionType() {
    return 'page';
  }

  /**
   * Test that question parentage saves.
   */
  public function testQuizPageParentage() {
    $this->drupalLogin($this->admin);

    // Create Quiz with review of score.
    $quiz_node = $this->createQuiz();

    // Create the questions.
    $question_node1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 1 body text',
    ]);
    $this->linkQuestionToQuiz($question_node1, $quiz_node); // QNR ID 1
    $question_node2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 2 body text',
    ]);
    $this->linkQuestionToQuiz($question_node2, $quiz_node); // QNR ID 2
    $question_node3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 3 body text',
    ]);
    $this->linkQuestionToQuiz($question_node3, $quiz_node);  // QNR ID 3
    // Create the pages.
    $page_node1 = $this->createQuestion(['type' => 'page']);
    $this->linkQuestionToQuiz($page_node1, $quiz_node); // QNR ID 4
    $page_node2 = $this->createQuestion(['type' => 'page']);
    $this->linkQuestionToQuiz($page_node2, $quiz_node); // QNR ID 5
    // Go to the manage questions form.
    $this->drupalGet("quiz/{$quiz_node->id()}/questions");
    $post = [
      // Make the questions have parents.
      "question_list[{$question_node1->getRevisionId()}][qqr_pid]" => 4,
      "question_list[{$question_node2->getRevisionId()}][qqr_pid]" => 4,
      "question_list[{$question_node3->getRevisionId()}][qqr_pid]" => 5,
      // Mirror what JS would have done by adjusting the weights.
      "question_list[{$page_node1->getRevisionId()}][weight]" => 2,
      "question_list[{$question_node1->getRevisionId()}][weight]" => 3,
      "question_list[{$question_node2->getRevisionId()}][weight]" => 4,
      "question_list[{$page_node2->getRevisionId()}][weight]" => 3,
      "question_list[{$question_node3->getRevisionId()}][weight]" => 4,
    ];
    $this->submitForm($post, $this->t('Submit'));

    $sql = "SELECT * FROM {quiz_question_relationship}";
    $data = \Drupal::database()->query($sql)->fetchAllAssoc('qqr_id');
    // Check the relationships properly saved.
    foreach ($data as $qnr_id => $rel) {
      switch ($qnr_id) {
        case 1:
        case 2:
          $this->assertEqual($rel->qqr_pid, 4);
          break;

        case 3:
          $this->assertEqual($rel->qqr_pid, 5);
          break;

        case 4:
        case 5:
          $this->assertNull($rel->qqr_pid);
          break;
      };
    }

    // Take the quiz. Ensure the pages are correct.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    // Questions 1 and 2 are present. Question 3 is hidden.
    $this->assertFieldByName("question[{$question_node1->id()}][answer]");
    $this->assertFieldByName("question[{$question_node2->id()}][answer]");
    $this->assertNoFieldByName("question[{$question_node3->id()}][answer]");
    $this->submitForm([
      "question[{$question_node1->id()}][answer]" => 1,
      "question[{$question_node2->id()}][answer]" => 1,
    ], $this->t('Next'));
    // Questions 1 and 2 are gone. Question 3 is present.
    $this->assertNoFieldByName("question[{$question_node1->id()}][answer]");
    $this->assertNoFieldByName("question[{$question_node2->id()}][answer]");
    $this->assertFieldByName("question[{$question_node3->id()}][answer]");
    $this->submitForm([
      "question[{$question_node3->id()}][answer]" => 1,
    ], $this->t('Finish'));

    // Check that the results page contains all the questions.
    $this->assertText('You got 3 of 3 possible points.');
    $this->assertText('TF 1 body text');
    $this->assertText('TF 2 body text');
    $this->assertText('TF 3 body text');

    foreach (QuizResult::loadMultiple() as $quiz_result) {
      $quiz_result->delete();
    }

    // Check to make sure that saving a new revision of the Quiz does not affect
    // the parentage.
    $this->drupalLogin($this->admin);
    $this->drupalGet("quiz/{$quiz_node->id()}/edit");
    $this->submitForm(['revision' => 1], $this->t('Save'));

    // Take the quiz. Ensure the pages are correct.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    // Questions 1 and 2 are present. Question 3 is hidden.
    $this->assertText("Page 1 of 2");
    $this->assertFieldByName("question[{$question_node1->id()}][answer]");
    $this->assertFieldByName("question[{$question_node2->id()}][answer]");
    $this->assertNoFieldByName("question[{$question_node3->id()}][answer]");
    $this->submitForm([
      "question[{$question_node1->id()}][answer]" => 1,
      "question[{$question_node2->id()}][answer]" => 1,
    ], $this->t('Next'));

    // Questions 1 and 2 are gone. Question 3 is present.
    $this->assertText("Page 2 of 2");
    $this->assertNoFieldByName("question[{$question_node1->id()}][answer]");
    $this->assertNoFieldByName("question[{$question_node2->id()}][answer]");
    $this->assertFieldByName("question[{$question_node3->id()}][answer]");

    // Test backwards navigation.
    $this->submitForm([
    ], $this->t('Back'));
    $this->assertText("Page 1 of 2");
    $this->submitForm([
    ], $this->t('Next'));

    $this->submitForm([
      "question[{$question_node3->id()}][answer]" => 1,
    ], $this->t('Finish'));
  }

  /**
   * Test adding and taking a quiz page question.
   */
  public function testCreateQuizQuestion($settings = []) {
    // Login as our privileged user.
    $this->drupalLogin($this->admin);

    $question_node = $this->createQuestion([
        'type' => $this->getQuestionType(),
        'title' => 'PG 1 title',
        'body' => 'PG 1 body text.',
      ] + $settings);

    return $question_node;
  }

  public function testPageFeedback() {
    $this->drupalLogin($this->admin);

    $quiz_node = $this->createQuiz(
      [
        'review_options' => ['question' => ['question_feedback' => 'question_feedback']],
      ]
    );

    // Create the questions.
    $question_node1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 1 body text',
      'feedback' => 'This is the feedback for question 1.',
    ]);
    $this->linkQuestionToQuiz($question_node1, $quiz_node); // QNR ID 1
    $question_node2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 2 body text',
      'feedback' => 'This is the feedback for question 2.',
    ]);
    $this->linkQuestionToQuiz($question_node2, $quiz_node); // QNR ID 2
    $question_node3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'body' => 'TF 3 body text',
      'feedback' => 'This is the feedback for question 3.',
    ]);
    $this->linkQuestionToQuiz($question_node3, $quiz_node); // QNR ID 3
    //
    // Create the page.
    $page_node1 = $this->createQuestion([
      'type' => 'page',
      'body' => 'PG 1 body text',
    ]);
    $this->linkQuestionToQuiz($page_node1, $quiz_node); // QNR ID 4
    // Go to the manage questions form.
    $this->drupalGet("quiz/{$quiz_node->id()}/questions");
    $post = [
      // Make the questions have parents.
      "question_list[{$question_node1->getRevisionId()}][qqr_pid]" => 4,
      "question_list[{$question_node2->getRevisionId()}][qqr_pid]" => 4,
      // Mirror what JS would have done by adjusting the weights.
      "question_list[{$page_node1->getRevisionId()}][weight]" => 1,
      "question_list[{$question_node1->getRevisionId()}][weight]" => 2,
      "question_list[{$question_node2->getRevisionId()}][weight]" => 3,
      "question_list[{$question_node3->getRevisionId()}][weight]" => 4,
    ];
    $this->submitForm($post, $this->t('Submit'));

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");

    $this->submitForm([
      "question[{$question_node1->id()}][answer]" => 1,
      "question[{$question_node2->id()}][answer]" => 1,
    ], $this->t('Next'));

    $this->assertText('This is the feedback for question 1.');
    $this->assertText('This is the feedback for question 2.');
    $this->assertNoText('This is the feedback for question 3.');
  }

}
