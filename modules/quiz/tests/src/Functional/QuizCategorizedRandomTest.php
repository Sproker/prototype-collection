<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests for random questions.
 *
 * Since this is random by nature, there is a chance that these will fail. We
 * use 5 layout builds to try and mitigate that chance.
 *
 * @group Quiz
 */
class QuizCategorizedRandomTest extends QuizTestBase {

  protected static $modules = ['quiz_truefalse', 'taxonomy'];

  /**
   * Test pulling questions from categories.
   *
   * @todo add test for weighted questions
   */
  public function testCategorizedRandomQuestions() {
    // Vocabs.
    $v1 = Vocabulary::create(['name' => 'Vocab 1', 'vid' => 'vocab1']);
    $v1->save();

    $v1t1 = Term::create(['name' => 'Vocab 1 Term 1', 'vid' => 'vocab1']);
    $v1t1->save();
    $v1t2 = Term::create(['name' => 'Vocab 1 Term 2', 'vid' => 'vocab1']);
    $v1t2->save();
    $v1t3 = Term::create(['name' => 'Vocab 1 Term 3', 'vid' => 'vocab1']);
    $v1t3->save();

    $pg1 = Paragraph::create([
      'type' => 'quiz_question_term_pool',
      'quiz_question_tid' => ['target_id' => $v1t1->id()],
      'quiz_question_number' => 2,
    ]);
    $pg1->save();
    $pg2 = Paragraph::create([
      'type' => 'quiz_question_term_pool',
      'quiz_question_tid' => ['target_id' => $v1t2->id()],
      'quiz_question_number' => 2,
    ]);
    $pg2->save();

    $quiz = $this->createQuiz([
      'randomization' => 3,
    ]);
    $quiz->get('quiz_terms')->appendItem($pg1);
    $quiz->get('quiz_terms')->appendItem($pg2);
    $quiz->save();

    $field_storage = FieldStorageConfig::create([
      'id' => 'quiz_question.question_category',
      'field_name' => 'question_category',
      'entity_type' => 'quiz_question',
      'type' => 'entity_reference',
      'settings' =>
        [
          'target_type' => 'taxonomy_term',
        ],
      'module' => 'core',
    ]);
    $field_storage->save();
    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'truefalse',
      'label' => 'Question category',
      'field_name' => 'question_category',
      'entity_type' => 'quiz_question',
    ]);
    $instance->save();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay('quiz_question', 'truefalse', 'default')
      ->setComponent('question_category', [
        'type' => 'options_select',
      ])
      ->save();

    $questions[] = $this->createQuestion([
      'title' => 'tf 1 v1t1',
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'question_category' => ['target_id' => $v1t1->id()],
      'body' => 'TF 1 body text',
    ])->id();
    $questions[] = $this->createQuestion([
      'title' => 'tf 2 v1t1',
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'question_category' => ['target_id' => $v1t1->id()],
      'body' => 'TF 1 body text',
    ])->id();
    $questions[] = $this->createQuestion([
      'title' => 'tf 3 v1t1',
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'question_category' => ['target_id' => $v1t1->id()],
      'body' => 'TF 1 body text',
    ])->id();
    $questions[] = $this->createQuestion([
      'title' => 'tf 4 v1t2',
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'question_category' => ['target_id' => $v1t2->id()],
      'body' => 'TF 1 body text',
    ])->id();
    $questions[] = $this->createQuestion([
      'title' => 'tf 5 v1t2',
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'question_category' => ['target_id' => $v1t2->id()],
      'body' => 'TF 1 body text',
    ])->id();
    $questions[] = $this->createQuestion([
      'title' => 'tf 6 v1t2',
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'question_category' => ['target_id' => $v1t2->id()],
      'body' => 'TF 1 body text',
    ])->id();

    $list = $quiz->buildLayout();
    $this->assertEqual(count($list), 4, 'Quiz had 4 questions.');
    foreach ($list as $qinfo) {
      $qq_ids[] = $qinfo['qqid'];
    }
    $this->assertEqual(count(array_intersect($qq_ids, $questions)), 4, 'Questions were from the terms excluding 2.');

    // Test number of questions.
    $num_questions = $quiz->getNumberOfQuestions();
    $this->assertEqual($num_questions, 4);

    // Start the quiz.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->assertText('Page 1 of 4');
  }

}
