<?php

namespace Drupal\Tests\quiz_directions\Functional;

use Drupal\quiz\Entity\QuizQuestion;
use Drupal\Tests\quiz\Functional\QuizQuestionTestBase;

/**
 * @file
 * Unit tests for the quiz_directions Module.
 */

/**
 * Test class for quiz directions.
 *
 * @group QuizQuestion
 */
class QuizDirectionsTestCase extends QuizQuestionTestBase {

  public function getQuestionType() {
    return 'directions';
  }

  public static $modules = ['quiz_directions'];

  /**
   * Test adding and taking a quiz directions question.
   */
  public function testCreateQuizQuestion($settings = []) {
    // Login as our privileged user.
    $this->drupalLogin($this->admin);

    $question = QuizQuestion::create([
      'type' => $this->getQuestionType(),
      'title' => 'QD 1 title',
      'body' => 'QD 1 body text.',
    ]);
    $question->save();

    $quiz = $this->linkQuestionToQuiz($question);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->assertText('QD 1 body text.');

    return $question;
  }

}
