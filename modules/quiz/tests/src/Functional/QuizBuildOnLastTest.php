<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\quiz\Util\QuizUtil;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test quiz results behavior.
 *
 * @group Quiz
 */
class QuizBuildOnLastTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse', 'quiz_multichoice'];

  /**
   * Test the build on last attempt functionality.
   */
  public function testBuildOnLastAttempt() {
    $this->drupalLogin($this->admin);

    // Prepopulate correct answers.
    $quiz = $this->createQuiz([
      'build_on_last' => 'correct',
    ]);

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
    $question3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question3, $quiz);
    $question4 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question4, $quiz);

    $this->drupalLogin($this->user);

    // Take the quiz.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    // No build on last form here.
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 0,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question3->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question4->id()}][answer]" => 0,
    ], $this->t('Finish'));

    // Take it again, ensure the correct answers are prefilled.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      'build_on_last' => 'correct',
    ], $this->t('Start @quiz', ['@quiz' => QuizUtil::getQuizName()]));
    $this->assertFieldChecked("edit-question-{$question1->id()}-answer-1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->assertNoFieldChecked("edit-question-{$question2->id()}-answer-1");
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 0,
    ], $this->t('Next'));
    $this->assertFieldChecked("edit-question-{$question3->id()}-answer-1");
    $this->submitForm([
      "question[{$question3->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->assertNoFieldChecked("edit-question-{$question4->id()}-answer-1");
    $this->submitForm([
      "question[{$question4->id()}][answer]" => 0,
    ], $this->t('Finish'));

    // Switch the build option.
    $quiz->set('build_on_last', 'all');
    $quiz->setNewRevision();
    $quiz->save();

    // @todo the change above would have forced a new revision, need to account
    // for this in D8. User is getting a screen, they should not because there
    // shouldn't be an existing attempt for this version.
    // Take the quiz with this revision.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Leave blank'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 0,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question3->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question4->id()}][answer]" => 0,
    ], $this->t('Finish'));

    // Take it again, ensure all the answers are prefilled.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      'build_on_last' => 'all',
    ], $this->t('Start @quiz', ['@quiz' => QuizUtil::getQuizName()]));
    $this->assertNoFieldChecked("edit-question-{$question1->id()}-answer-1");
    $this->assertNoFieldChecked("edit-question-{$question1->id()}-answer-0");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->assertFieldChecked("edit-question-{$question2->id()}-answer-0");
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 0,
    ], $this->t('Next'));
    $this->assertFieldChecked("edit-question-{$question3->id()}-answer-1");
    $this->submitForm([
      "question[{$question3->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->assertFieldChecked("edit-question-{$question4->id()}-answer-0");
    $this->submitForm([
      "question[{$question4->id()}][answer]" => 0,
    ], $this->t('Finish'));
  }

}
