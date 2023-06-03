<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\quiz\Entity\QuizResultType;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test quiz result bundle and fields behavior.
 *
 * @group Quiz
 */
class QuizResultBundleTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse'];

  /**
   * Test fieldable Quiz results.
   */
  public function testFieldableResults() {
    // Add a field to quiz result and make it required for starting.
    $field_storage = FieldStorageConfig::create([
      'id' => 'quiz_result.quiz_result_field_a',
      'field_name' => 'quiz_result_field_a',
      'entity_type' => 'quiz_result',
      'type' => 'string',
      'module' => 'core',
    ]);
    $field_storage->save();
    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'quiz_result',
      'label' => 'Result field A',
      'required' => TRUE,
      'field_name' => 'quiz_result_field_a',
      'entity_type' => 'quiz_result',
      'third_party_settings' =>
        [
          'quiz' => ['show_field' => TRUE],
        ],
    ]);
    $instance->save();

    Drupal::service('entity_display.repository')
      ->getFormDisplay('quiz_result', 'quiz_result', 'default')
      ->setComponent('quiz_result_field_a', [
        'type' => 'text_textfield',
      ])
      ->save();


    $quizNodeA = $this->createQuiz();
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'feedback' => 'Q1Feedback',
    ]);
    $this->linkQuestionToQuiz($question1, $quizNodeA);
    $this->drupalLogin($this->user);

    // Check if field shows up and user is not yet started.
    $this->drupalGet("quiz/{$quizNodeA->id()}/take");
    $this->assertFieldById('edit-quiz-result-field-a-0-value');

    // We haven't submitted the form so we should not have a Quiz result yet.
    $quiz_result = $quizNodeA->getResumeableResult($this->user);
    $this->assertNull($quiz_result, 'Quiz result does not yet exist.');

    // Submit the form.
    $this->submitForm([], $this->t('Start Quiz'));
    // Check that we hooked into Form API correctly.
    $this->assertText('field is required');

    // SUbmit the form with data.
    $this->submitForm(['quiz_result_field_a[0][value]' => 'test 123'], $this->t('Start Quiz'));
    $this->assertNotEmpty($quizNodeA->getResumeableResult($this->user), $this->t('Found quiz result.'));
    // Check the result exists now.
    $this->assertText('Question 1');
  }

  /**
   * Test quiz result bundles.
   */
  public function testQuizResultBundles() {
    QuizResultType::create([
      'id' => 'type_a',
      'label' => $this->t('Bundle type A'),
    ])->save();

    QuizResultType::create([
      'id' => 'type_b',
      'label' => $this->t('Bundle type B'),
    ])->save();

    // Add a field to quiz result and make it required for starting.
    $field_storagea = FieldStorageConfig::create([
      'id' => 'quiz_result.result_field_a',
      'field_name' => 'result_field_a',
      'entity_type' => 'quiz_result',
      'type' => 'string',
      'module' => 'core',
    ]);
    $field_storagea->save();
    $instancea = FieldConfig::create([
      'field_storage' => $field_storagea,
      'bundle' => 'type_a',
      'label' => 'Result field A',
      'required' => TRUE,
      'field_name' => 'result_field_a',
      'entity_type' => 'quiz_result',
      'third_party_settings' =>
        [
          'quiz' => ['show_field' => TRUE],
        ],
    ]);
    $instancea->save();

    Drupal::service('entity_display.repository')
      ->getFormDisplay('quiz_result', 'type_a', 'default')
      ->setComponent('result_field_a', [
        'type' => 'text_textfield',
      ])
      ->save();

    // Add a field to quiz result and make it required for starting.
    $field_storageb = FieldStorageConfig::create([
      'id' => 'quiz_result.result_field_b',
      'field_name' => 'result_field_b',
      'entity_type' => 'quiz_result',
      'type' => 'string',
      'module' => 'core',
    ]);
    $field_storageb->save();
    $instanceb = FieldConfig::create([
      'field_storage' => $field_storageb,
      'bundle' => 'type_b',
      'label' => 'Result field B',
      'required' => TRUE,
      'field_name' => 'result_field_b',
      'entity_type' => 'quiz_result',
      'third_party_settings' =>
        [
          'quiz' => ['show_field' => TRUE],
        ],
    ]);
    $instanceb->save();

    Drupal::service('entity_display.repository')
      ->getFormDisplay('quiz_result', 'type_b', 'default')
      ->setComponent('result_field_b', [
        'type' => 'text_textfield',
      ])
      ->save();

    $quizNodeA = $this->createQuiz(['result_type' => 'type_a']);
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quizNodeA);

    $quizNodeB = $this->createQuiz(['result_type' => 'type_b']);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question2, $quizNodeB);

    $this->drupalLogin($this->user);

    // Check if field shows up and user is not yet started.
    $this->drupalGet("quiz/{$quizNodeA->id()}/take");
    $this->assertFieldById('edit-result-field-a-0-value');
    $this->assertNoFieldById('edit-result-field-b-0-value');
    $results = Drupal::entityQuery('quiz_result')
      ->condition('qid', $quizNodeA->id())
      ->condition('uid', $this->user->id())
      ->execute();
    $this->assertEmpty($results);

    $this->submitForm([], $this->t('Start Quiz'));

    // Check that form API is working.
    $this->assertText('field is required');
    $this->submitForm(['result_field_a[0][value]' => 'test 123'], $this->t('Start Quiz'));

    // Check that a different field is on quiz B.
    $this->drupalGet("quiz/{$quizNodeB->id()}/take");
    $this->assertFieldById('edit-result-field-b-0-value');
    $this->assertNoFieldById('edit-result-field-a-0-value');

    // Mark field B to not show on result.
    $instanceb->setThirdPartySetting('quiz', 'show_field', FALSE);
    $instanceb->save();
    $this->drupalGet("quiz/{$quizNodeB->id()}/take");
    $this->assertNoFieldById('edit-result-field-a-0-value');
    $this->assertNoFieldById('edit-result-field-b-0-value');
  }

}
