<?php

namespace Drupal\Tests\quiz\Traits;

use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizQuestion;

/**
 * Provides common helper methods for Quiz module tests.
 */
trait QuizTestTrait {

  /**
   * Create a quiz with all end feedback settings enabled by default.
   *
   * @return \Drupal\quiz\Entity\Quiz
   *   The quiz.
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
   * Link a question to a new or provided quiz.
   *
   * @param \Drupal\quiz\Entity\QuizQuestion $quiz_question
   *   A quiz question.
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   A Quiz, or NULL to create one.
   *
   * @return \Drupal\quiz\Entity\Quiz
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
   * Returns a quiz question.
   *
   * @return \Drupal\quiz\Entity\QuizQuestion
   *   The quiz question.
   */
  public function createQuestion($settings = []) {
    $question = QuizQuestion::create($settings);
    $question->save();
    return $question;
  }

}
