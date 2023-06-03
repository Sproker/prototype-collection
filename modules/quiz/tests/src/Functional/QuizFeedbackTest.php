<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\quiz\Entity\QuizFeedbackType;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test quiz feedback.
 *
 * @group Quiz
 */
class QuizFeedbackTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse'];

  /**
   * Test question feedback.
   *
   * Note that we are only testing if any feedback displays, each question type
   * has its own tests for testing feedback returned from that question type.
   */
  public function testAnswerFeedback() {
    $this->drupalLogin($this->admin);
    $quiz = $this->createQuiz();

    // 2 questions.
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question2, $quiz);

    $review_options = [
      'attempt' => $this->t('Your answer'),
      'correct' => $this->t('Correct?'),
      'score' => $this->t('Score'),
      'answer_feedback' => $this->t('Feedback'),
      'solution' => $this->t('Correct answer'),
    ];

    $this->drupalLogin($this->user);

    // Answer the first question.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));

    // Check feedback after the Question.
    foreach ($review_options as $option => $text) {
      // Loop through each review option, set it, then reload the quiz page to
      // see if it had an effect.
      $quiz->review_options = ['question' => [$option => $option]];
      $quiz->save();

      // Refresh feedback page.
      $this->drupalGet("quiz/{$quiz->id()}/take/1/feedback");

      // As long as there is some feedback there should be a question title
      // header.
      $this->assertText('Question 1');
      $this->assertNoText('Question 2');

      $this->assertRaw('<th>' . $text . '</th>');
      foreach ($review_options as $option2 => $text2) {
        if ($option != $option2) {
          $this->assertNoRaw('<th>' . $text2 . '</th>');
        }
      }
    }

    // Answer the last question
    $this->clickLink($this->t('Next question'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 1,
    ], $this->t('Finish'));

    // Check that we can access the feedback for the final question before quiz
    // feedback is shown. Verify the first question feedback is not shown.
    $this->assertNoText('Question 1');
    $this->assertText('Question 2');

    // Press the finish button on the last question's feedback page.
    $this->submitForm([
    ], $this->t('Finish'));

    // Check feedback after the Quiz.
    foreach ($review_options as $option => $text) {
      // Loop through each review option, set it, then reload the quiz page to
      // see if it had an effect.
      $quiz->review_options = ['end' => [$option => $option]];
      $quiz->save();

      // Refresh feedback page.
      $this->drupalGet("quiz/{$quiz->id()}/result/1");

      // Verify both questions appear. As long as there is some feedback there
      //should be a question title header.
      $this->assertText('Question 1');
      $this->assertText('Question 2');

      $this->assertRaw('<th>' . $text . '</th>');
      foreach ($review_options as $option2 => $text2) {
        if ($option != $option2) {
          $this->assertNoRaw('<th>' . $text2 . '</th>');
        }
      }
    }
  }

  /**
   * Test general Quiz question feedback.
   */
  public function testQuestionFeedback() {
    $this->drupalLogin($this->admin);

    // Turn on question feedback at the end.
    $quiz = $this->createQuiz(
      [
        'review_options' => ['end' => ['question_feedback' => 'question_feedback']],
      ]
    );

    // Add 2 questions with general question feedback.
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'feedback' => 'Feedback for TF test.',
    ]);
    $this->linkQuestionToQuiz($question1, $quiz);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'feedback' => 'Feedback for TF test.',
    ]);
    $this->linkQuestionToQuiz($question2, $quiz);

    // Test.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->assertNoText('Feedback for TF test.');
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 1,
    ], $this->t('Finish'));
    $this->assertText('Feedback for TF test.');
  }

  /**
   * Test no feedback.
   */
  public function testNoFeedback() {
    $this->drupalLogin($this->admin);

    // Turn off question feedback.
    $quiz = $this->createQuiz(
      [
        'review_options' => [],
      ]
    );

    // Add 2 questions with general question feedback.
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'feedback' => 'Feedback for TF test.',
    ]);
    $this->linkQuestionToQuiz($question1, $quiz);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'feedback' => 'Feedback for TF test.',
    ]);
    $this->linkQuestionToQuiz($question2, $quiz);

    // Test.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 1,
    ], $this->t('Finish'));
    $this->assertText('You have finished this Quiz');
  }

  /**
   * Test Quiz question body feedback.
   */
  public function testQuestionBodyFeedback() {
    $this->drupalLogin($this->admin);

    // Asolutely no feedback.
    $quiz = $this->createQuiz(
      [
        'review_options' => [],
      ]
    );

    // Set up a Quiz with one question that has a body and a summary.
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'body' => 'TF 1 body text',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz);

    // Test no feedback.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Finish'));
    $this->assertNoText('TF 1 body text');

    // Test full feedback.
    $quiz->review_options = ['end' => ['quiz_question_view_full' => 'quiz_question_view_full']];
    $quiz->save();
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Finish'));
    $this->assertText('TF 1 body text');
  }

  /**
   * Test custom feedback types.
   */
  public function testFeedbackTimes() {
    $this->drupalLogin($this->admin);

    $component = [
      'expression' => [
        'id' => 'rules_and',
        'conditions' => [
          [
            'id' => 'rules_condition',
            'uuid' => 'ca2a6b2f-3b17-449e-b913-d64b52c17203',
            'weight' => 2,
            'context_values' => [
              'operation' => '==',
              'value' => '2',
            ],
            'context_mapping' => [
              'data' => 'quiz_result.attempt.value',
            ],
            'condition_id' => 'rules_data_comparison',
            'negate' => 0,
          ],
        ],
      ],
      'context_definitions' => [
        'quiz_result' => [
          'type' => 'entity:quiz_result',
          'label' => 'Quiz result',
          'description' => 'Quiz result to evaluate feedback',
        ],
      ],
    ];

    QuizFeedbackType::create([
        'label' => 'After two attempts',
        'id' => 'after2attempts',
        'component' => $component,
      ]
    )->save();

    // Feedback but, only after second attempt (rule).
    $quiz = $this->createQuiz(
      [
        'review_options' => ['after2attempts' => ['solution' => 'solution']],
      ]
    );

    // Set up a Quiz with one question that has a body and a summary.
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz);

    // Test no feedback.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Finish'));
    $this->assertNoText('Correct answer');

    // Take again.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Finish'));
    $this->assertText('Correct answer');
  }

  /**
   * Test question feedback on the last question.
   */
  public function testLastAnswerFeedback() {
    $this->drupalLogin($this->admin);
    $quiz = $this->createQuiz([
      'review_options' => ['question' => ['question_feedback' => 'question_feedback']],
    ]);

    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'feedback' => 'Feedback for TF test.',
    ]);
    $this->linkQuestionToQuiz($question1, $quiz);

    $this->drupalLogin($this->user);

    // Answer the first question.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Finish'));

    // Check the the last question still produced question feedback.
    $this->drupalGet("quiz/{$quiz->id()}/take/1/feedback");
    $this->assertSession()->pageTextContains('Feedback for TF test.');
    // The hack is to allow last question feedback for 5 seconds.
    sleep(6);
    $this->drupalGet("quiz/{$quiz->id()}/take/1/feedback");
    $this->assertSession()->pageTextNotContains('Feedback for TF test.');
  }

}
