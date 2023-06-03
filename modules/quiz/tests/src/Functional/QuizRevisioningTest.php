<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Test quiz revisioning.
 *
 * @group Quiz
 */
class QuizRevisioningTest extends QuizTestBase {

  use StringTranslationTrait;

  protected static $modules = ['quiz_truefalse'];

  /**
   * Test quiz revisioning.
   */
  public function testQuizRevisioning() {
    $config = Drupal::configFactory()->getEditable('quiz.settings');
    $config->set('revisioning', TRUE)->save();

    $this->drupalLogin($this->admin);
    $question = $this->createQuestion([
      'title' => 'Revision 1',
      'body' => 'Revision 1',
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'feedback' => 'Question feedback for Revision 1',
    ]);
    $quiz_node = $this->linkQuestionToQuiz($question);
    $quiz_node->set('allow_resume', 1)->save();


    // Check for first revision.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertText("Revision 1");

    // Attempt to update question. We have to create a new revision.
    $this->drupalLogin($this->admin);
    $this->drupalGet("quiz-question/{$question->id()}/edit");
    $this->assertText('Warning: This question has attempts.');
    $this->submitForm([
    ], $this->t('Save'));
    $this->assertText('Create new revision field is required.');
    $this->submitForm([
      'title[0][value]' => 'Revision 2',
      'body[0][value]' => 'Revision 2',
      'truefalse_correct' => 0,
      'feedback[0][value]' => 'Question feedback for Revision 2',
      'revision' => TRUE,
    ], $this->t('Save'));
    // Reload the question to get current revision ID.
    Drupal::entityTypeManager()->getStorage('quiz_question')->resetCache();
    $question = QuizQuestion::load($question->id());

    // As the quiz taker, finish out the attempt.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertText("Revision 1");
    $this->submitForm([
      "question[{$question->id()}][answer]" => 1,
    ], $this->t('Finish'));
    $this->assertText('You got 1 of 1 possible points.');
    $this->assertText('Question feedback for Revision 1');

    // Take quiz again. Should be on SAME revision of the question. We have not
    // yet updated the Quiz with the new revision of the Question.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertText("Revision 1");

    // We have an updated question and one Quiz revision with an attempt. We
    // need to update the quiz to use the new question. But there are attempts
    // on the quiz. Update the quiz to use the latest revision.
    $this->drupalLogin($this->admin);
    $this->drupalGet("quiz/{$quiz_node->id()}/questions");
    $this->assertText('This quiz has been answered.');
    $this->clickLink('create a new revision');
    $this->assertText('Warning: This quiz has attempts.');
    $this->submitForm([
      'revision' => TRUE,
    ], $this->t('Save'));
    $this->assertNoText('This quiz has been answered.');
    $this->submitForm([
      "question_list[{$question->getRevisionId()}][question_vid]" => TRUE,
    ], $this->t('Submit'));

    // Take quiz again. Should be on SAME revision. We have not yet finished
    // this attempt.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertText("Revision 1");
    // Finish the attempt.
    $this->submitForm([
      "question[{$question->id()}][answer]" => 1,
    ], $this->t('Finish'));
    $this->assertText('You got 1 of 1 possible points.');
    $this->assertText('Question feedback for Revision 1');

    // Take quiz again we should be on the new result, finally.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertText("Revision 2");
    // Finish the attempt.
    $this->submitForm([
      "question[{$question->id()}][answer]" => 1,
    ], $this->t('Finish'));
    $this->assertText('You got 0 of 1 possible points.');
    $this->assertText('Question feedback for Revision 2');

    // Check admin override.
    $mega_admin = $this->createUser([
      'administer quiz',
      'administer quiz_question',
      'override quiz revisioning',
    ]);

    $this->drupalLogin($mega_admin);
    $this->drupalGet("quiz/{$quiz_node->id()}/questions");
  }

  /**
   * Test quiz with revisioning off.
   */
  public function testQuizNoRevisioning() {
    $this->drupalLogin($this->admin);
    $question_node = $this->createQuestion([
      'title' => 'Revision 1',
      'body' => 'Revision 1',
      'type' => 'truefalse',
      'truefalse_correct' => 1,
      'feedback' => 'Question feedback for Revision 1',
    ]);
    $quiz_node = $this->linkQuestionToQuiz($question_node);

    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertText("Revision 1");
    // Finish the attempt.
    $this->submitForm([
      "question[{$question_node->id()}][answer]" => 1,
    ], $this->t('Finish'));

    // Check blocked access to update quiz and question.
    $this->drupalGet("quiz/{$quiz_node->id()}/edit");
    $this->assertText('You must delete all attempts on this quiz before editing.');
    $this->assertSession()
      ->elementAttributeExists('css', '#edit-submit', 'disabled');

    $this->drupalGet("quiz-question/{$question_node->id()}/edit");
    $this->assertText('You must delete all attempts on this question before editing.');
    $this->assertSession()
      ->elementAttributeExists('css', '#edit-submit', 'disabled');

    // Check admin override.
    $mega_admin = $this->createUser([
      'administer quiz',
      'administer quiz_question',
      'override quiz revisioning',
    ]);

    $this->drupalLogin($mega_admin);

    $this->drupalGet("quiz/{$quiz_node->id()}/edit");
    $this->assertText('You should delete all attempts on this quiz before editing.');
    $this->submitForm([
    ], $this->t('Save'));

    $this->drupalGet("quiz-question/{$question_node->id()}/edit");
    $this->assertText('You should delete all attempts on this question before editing.');
    $this->submitForm([
    ], $this->t('Save'));
  }

}
