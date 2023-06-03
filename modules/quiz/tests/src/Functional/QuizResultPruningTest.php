<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;
use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use function count;

/**
 * Test quiz result pruning behavior.
 *
 * @group Quiz
 */
class QuizResultPruningTest extends QuizTestBase {

  use CronRunTrait;
  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse'];

  /**
   * Test the all, best, and last quiz result pruning.
   */
  public function testResultPruning() {
    $this->drupalLogin($this->admin);

    $quiz_node = $this->createQuiz([
      'keep_results' => Quiz::KEEP_ALL,
    ]);

    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz_node);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question2, $quiz_node);
    $question3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question3, $quiz_node);
    $question4 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question4, $quiz_node);

    $this->drupalLogin($this->user);

    // Create 2 100% results.
    for ($i = 1; $i <= 2; $i++) {
      $this->drupalGet("quiz/{$quiz_node->id()}/take");
      $this->submitForm([
        "question[{$question1->id()}][answer]" => 1,
      ], $this->t('Next'));
      $this->submitForm([
        "question[{$question2->id()}][answer]" => 1,
      ], $this->t('Next'));
      $this->submitForm([
        "question[{$question3->id()}][answer]" => 1,
      ], $this->t('Next'));
      $this->submitForm([
        "question[{$question4->id()}][answer]" => 1,
      ], $this->t('Finish'));
    }

    // Storing all results.
    $results = QuizResult::loadMultiple();
    $this->assertEqual(count($results), 2, 'Found 2 quiz results.');

    $quiz_node->keep_results = Quiz::KEEP_LATEST;
    $quiz_node->save();

    // Create a 50% result.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question3->id()}][answer]" => 0,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question4->id()}][answer]" => 0,
    ], $this->t('Finish'));

    // We should now have 2 invalid results.
    $results = Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 1]);
    $this->assertEqual(count($results), 2, 'Found 2 invalid quiz results');

    // We should only have one valid 50% result.
    $results = Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 0]);
    $this->assertEqual(count($results), 1, 'Found only one quiz result');
    $quiz_result = reset($results);
    $this->assertEqual($quiz_result->get('score')->value, 50, 'Quiz result was 50%');

    $quiz_node->keep_results = Quiz::KEEP_BEST;
    $quiz_node->save();

    // Create a 25% result.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 0,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question3->id()}][answer]" => 0,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question4->id()}][answer]" => 0,
    ], $this->t('Finish'));

    $this->assertText('Your previous score on this Quiz was equal or better. This result will not be saved.');

    // We should now have 3 invalid results.
    $results = Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 1]);
    $this->assertEqual(count($results), 3, 'Found 3 invalid quiz results');

    // And since we failed we should still have a valid 50% result.
    $results = Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 0]);
    $this->assertTrue(count($results) == 1, 'Found only one quiz result');
    $quiz_result = reset($results);
    $this->assertEqual($quiz_result->get('score')->value, 50, 'Quiz score was 50%');

    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question3->id()}][answer]" => 1,
    ], $this->t('Next'));
    $this->submitForm([
      "question[{$question4->id()}][answer]" => 0,
    ], $this->t('Finish'));

    // We should now have 4 invalid results.
    $results = Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 1]);
    $this->assertEqual(count($results), 4, 'Found 4 invalid quiz results');

    // And we should have one valid 75% result.
    $results = Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 0]);
    $this->assertEqual(count($results), 1, 'Found only one quiz result');
    $quiz_result = reset($results);
    $this->assertEqual($quiz_result->get('score')->value, 75, 'Quiz score was 75%');

    $config = \Drupal::service('config.factory')->getEditable('quiz.settings');

    // Set quiz_remove_invalid_quiz_record to the default value of a single day
    // and trigger a cron run. Since we haven't passed a day we should still
    // have 4 invalid results and one valid result with a score of 75%.
    $config->set('remove_invalid_quiz_record', 86400)->save();
    $this->cronRun();

    $results = Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 1]);
    $this->assertEqual(count($results), 4, 'Found 4 invalid quiz results');
    $results = Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['is_invalid' => 0]);
    $this->assertEqual(count($results), 1, 'Found only one quiz result');
    $quiz_result = reset($results);
    $this->assertEqual($quiz_result->get('score')->value, 75, 'Quiz score was 75%');

    // Set quiz_remove_invalid_quiz_record with a negative value to ensure
    // purging invalid results and run the cron itself. After this purge we
    // should only have one valid result left with a score of 75%.
    $config->set('remove_invalid_quiz_record', -86400)->save();
    $this->cronRun();

    $results = QuizResult::loadMultiple();
    $this->assertEqual(count($results), 1, 'Found only one quiz result');
    $quiz_result = reset($results);
    $this->assertEqual($quiz_result->get('score')->value, 75, 'Quiz score was 75%');
    $this->assertEqual($quiz_result->get('is_invalid')->value, 0, 'Quiz score was valid');
  }

}
