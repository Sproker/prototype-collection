<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use function quiz_get_feedback_options;
use function user_role_grant_permissions;

/**
 * Test basic anonymous quiz taking.
 *
 * @group QuizQuestion
 */
class QuizAnonymousTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse'];

  /**
   * Test incorrect question with all feedbacks on.
   */
  public function testAnonymousQuizTake() {
    // Login as our privileged user.
    $this->drupalLogin($this->admin);

    // Create the quiz and question.
    $question1 = QuizQuestion::create([
      'type' => 'truefalse',
      'title' => 'TF 1 title',
      'truefalse_correct' => ['value' => 1],
      'body' => 'TF 1 body text',
    ]);
    $question1->save();
    $question2 = QuizQuestion::create([
      'type' => 'truefalse',
      'title' => 'TF 2 title',
      'truefalse_correct' => ['value' => 0],
      'body' => 'TF 2 body text',
    ]);
    $question2->save();

    // Link the question.
    $quiz = $this->linkQuestionToQuiz($question1);
    $quiz->addQuestion($question2)->set('weight', 10)->save();

    // Set FB.
    $quiz->set('review_options', ['end' => array_combine(array_keys(quiz_get_feedback_options()), array_keys(quiz_get_feedback_options()))]);
    $quiz->save();

    // Add permissions for anonymous user to take quizzes.
    user_role_grant_permissions(AccountInterface::ANONYMOUS_ROLE, ['view own quiz_result', 'view any quiz', 'access quiz']);

    // Logout.
    $this->drupalLogout();

    // Take the quiz, make sure we can view and click the link to start.
    $this->drupalGet("quiz/{$quiz->id()}");
    $this->clickLink('Start Quiz');
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 1,
    ], $this->t('Finish'));

    $this->assertText('You got 1 of 2 possible points.');
    $this->assertText('Your score: 50%');
  }

}
