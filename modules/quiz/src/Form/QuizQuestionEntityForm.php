<?php

namespace Drupal\quiz\Form;

use Drupal\quiz\Entity\QuizQuestionType;
use Drupal;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Quiz question authoring form.
 */
class QuizQuestionEntityForm extends ContentEntityForm {

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $entity_manager = Drupal::entityTypeManager();
    $access_handler = $entity_manager->getAccessControlHandler('quiz');

    if ($qid = Drupal::request()->get('qid')) {
      // Requested addition to an existing quiz.
      $vid = Drupal::request()->get('vid');

      $quiz = \Drupal::entityTypeManager()
        ->getStorage('quiz')
        ->loadRevision($vid);

      // Check if the user can add a question to the requested quiz.
      if ($access_handler->access($quiz, 'update')) {
        $form['quiz_id'] = [
          '#title' => $this->t('Quiz ID'),
          '#type' => 'value',
          '#value' => $qid,
        ];

        $form['quiz_vid'] = [
          '#title' => $this->t('Quiz revision ID'),
          '#type' => 'value',
          '#value' => $vid,
        ];
      }
    }

    if ($this->entity->hasBeenAnswered()) {
      $override = \Drupal::currentUser()->hasPermission('override quiz revisioning');
      if (Drupal::config('quiz.settings')->get('revisioning', FALSE)) {
        $form['revision']['#required'] = !$override;
      }
      else {
        $message = $override ?
          $this->t('<strong>Warning:</strong> This question has attempts. You can edit this question, but it is not recommended.<br/>Attempts in progress and reporting will be affected.<br/>You should delete all attempts on this question before editing.') :
          $this->t('You must delete all attempts on this question before editing.');
        // Revisioning is disabled.
        $form['revision_information']['#access'] = FALSE;
        $form['revision']['#access'] = FALSE;
        $form['actions']['warning'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $message,
        ];
        \Drupal::messenger()->addWarning($message);
        $form['actions']['#disabled'] = TRUE;
      }
      $form['revision']['#description'] = '<strong>Warning:</strong> This question has attempts.<br/>In order to update this question you must create a new revision.<br/>This will affect reporting.<br/>You must update the quizzes with the new revision of this question.';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Redirect to questions form after quiz creation.
   */
  public function save(array $form, FormStateInterface $form_state) {
    $quiz_question = $this->entity;
    $insert = $quiz_question->isNew();

    parent::save($form, $form_state);

    if ($qid = $form_state->getValue('quiz_id')) {
      // Add to quiz if coming from the questions form.
      $vid = $form_state->getValue('quiz_vid');

      /* @var $quiz Quiz */
      $quiz = Drupal::entityTypeManager()
        ->getStorage('quiz')
        ->loadRevision($vid);
      $quiz->addQuestion($this->entity);
    }

    $type = QuizQuestionType::load($quiz_question->bundle());
    $t_args = ['@type' => $type->label(), '%title' => $quiz_question->toLink()->toString()];

    if ($insert) {
      $this->messenger()->addStatus($this->t('@type %title has been created.', $t_args));
    }
    else {
      $this->messenger()->addStatus($this->t('@type %title has been updated.', $t_args));
    }

    if ($qid = $form_state->getValue('quiz_id')) {
      $form_state->setRedirect('quiz.questions', ['quiz' => $qid]);
    }
  }

}
