<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Core\Config\Development\ConfigSchemaChecker;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\Tests\BrowserTestBase;
use function quiz_get_feedback_options;

/**
 * Base test class for Quiz questions.
 */
abstract class QuizTestBase extends BrowserTestBase {

  /**
   * @var bool
   * @see ConfigSchemaChecker
   *
   * @todo Remove once there is 8.x-3.0-alpha6 which fixes a schema issue.
   *
   */
  protected $strictConfigSchema = FALSE;

  protected $defaultTheme = 'stark';

  protected static $modules = ['quiz', 'quiz_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp($admin_permissions = [], $user_permissions = []) {
    parent::setUp();

    $admin_permissions[] = 'administer quiz configuration';
    $admin_permissions[] = 'administer quiz_question';
    $admin_permissions[] = 'administer quiz_result_answer';
    $admin_permissions[] = 'administer quiz_result';
    $admin_permissions[] = 'administer quiz';
    // Unevaluated results view is tied to this.
    $admin_permissions[] = 'update any quiz_result';

    if ($this->profile == 'standard') {
      $admin_permissions[] = 'use text format basic_html';
      $admin_permissions[] = 'use text format restricted_html';
      $admin_permissions[] = 'use text format full_html';
      $user_permissions[] = 'use text format basic_html';
      $user_permissions[] = 'use text format restricted_html';
    }

    $user_permissions[] = 'access quiz';
    $user_permissions[] = 'view any quiz';
    $user_permissions[] = 'view own quiz_result';

    $this->admin = $this->drupalCreateUser(array_unique($admin_permissions));
    $this->user = $this->drupalCreateUser(array_unique($user_permissions));
  }

  /**
   * Link a question to a new or provided quiz.
   *
   * @param QuizQuestion $quiz_question
   *   A quiz question.
   * @param Quiz $quiz
   *   A Quiz, or NULL to create one.
   *
   * @return Quiz
   *   The quiz.
   */
  public function linkQuestionToQuiz(QuizQuestion $quiz_question, Quiz $quiz = NULL) {
    static $weight = 0;

    if (!$quiz) {
      // Create a new quiz with defaults.
      $quiz = $this->createQuiz();
    }

    // Test helper - weight questions one after another.
    $quiz->addQuestion($quiz_question)->set('weight', $weight)->save();
    $weight++;

    return $quiz;
  }

  /**
   * Create a quiz with all end feedback settings enabled by default.
   *
   * @return Quiz
   */
  public function createQuiz($settings = []) {
    $settings += [
      'title' => 'Quiz',
      'body' => 'Quiz description',
      'type' => 'quiz',
      'result_type' => 'quiz_result',
      'review_options' => ['end' => array_combine(array_keys(quiz_get_feedback_options()), array_keys(quiz_get_feedback_options()))],
    ];
    $quiz = Quiz::create($settings);
    $quiz->save();
    return $quiz;
  }

  /**
   * @return QuizQuestion
   */
  public function createQuestion($settings = []) {
    $question = QuizQuestion::create($settings);
    $question->save();
    return $question;
  }

}
