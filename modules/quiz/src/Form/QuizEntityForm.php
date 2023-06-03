<?php

namespace Drupal\quiz\Form;

use Drupal;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizFeedbackType;
use function quiz_get_feedback_options;

/**
 * Quiz authoring form.
 */
class QuizEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * Redirect to the questions form after quiz creation.
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $form_state->setRedirect('quiz.questions', ['quiz' => $this->entity->id()]);
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['quiz'] = [
      '#weight' => 5,
      '#type' => 'vertical_tabs',
    ];
    $form['taking_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Taking options'),
      '#group' => 'quiz',
    ];
    $form['random'] = [
      '#type' => 'details',
      '#title' => $this->t('Randomization'),
      '#group' => 'quiz',
    ];
    $form['availability_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Availability options'),
      '#group' => 'quiz',
    ];
    $form['pass_fail_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Pass/fail options'),
      '#group' => 'quiz',
    ];
    $form['question_feedback'] = [
      '#type' => 'details',
      '#title' => $this->t('Question feedback'),
      '#group' => 'quiz',
    ];
    $form['quiz_feedback'] = [
      '#type' => 'details',
      '#title' => $this->t('Quiz feedback'),
      '#group' => 'quiz',
    ];

    $form['allow_resume']['#group'] = 'taking_options';
    $form['allow_skipping']['#group'] = 'taking_options';
    $form['allow_jumping']['#group'] = 'taking_options';
    $form['allow_change']['#group'] = 'taking_options';
    $form['allow_change_blank']['#group'] = 'taking_options';
    $form['backwards_navigation']['#group'] = 'taking_options';
    $form['repeat_until_correct']['#group'] = 'taking_options';
    $form['build_on_last']['#group'] = 'taking_options';
    $form['mark_doubtful']['#group'] = 'taking_options';
    $form['show_passed']['#group'] = 'taking_options';
    $form['takes']['#group'] = 'availability_options';
    $form['time_limit']['#group'] = 'availability_options';
    $form['show_attempt_stats']['#group'] = 'taking_options';
    $form['status']['#group'] = 'availability_options';

    //$form['review_options']['#group'] = 'taking_options';
    $form['keep_results']['#group'] = 'taking_options';
    $form['build_on_last']['#group'] = 'taking_options';

    $form['randomization']['#group'] = 'random';
    $form['number_of_random_questions']['#group'] = 'random';
    $form['max_score_for_random']['#group'] = 'random';
    $form['quiz_terms']['#group'] = 'random';

    //$form['quiz_always']['#group'] = 'availability_options';
    $form['quiz_date']['#group'] = 'availability_options';

    $form['pass_rate']['#group'] = 'pass_fail_options';
    $form['summary_pass']['#group'] = 'pass_fail_options';
    $form['summary_default']['#group'] = 'pass_fail_options';

    $form['result_options']['#group'] = 'quiz_feedback';
    // Build the review options.
    $review_options = quiz_get_feedback_options();

    $form['question_feedback']['help']['#markup'] = $this->t('Control what feedback appears and when. To display any per-question feedback, one of the "Question" review options must be enabled.');
    $form['question_feedback']['review_options']['#tree'] = TRUE;
    $review_options_field = $this->getEntity()->get('review_options');
    foreach (QuizFeedbackType::loadMultiple() as $key => $when) {
      $form['question_feedback']['review_options'][$key] = [
        '#title' => $when->label(),
        '#description' => $when->get('description'),
        '#type' => 'checkboxes',
        '#options' => $review_options,
        '#weight' => 100,
        '#default_value' => $review_options_field->isEmpty() ? [] : ($review_options_field->get(0)->getValue()[$key] ?? []),
      ];
    }

    if ($this->entity->hasAttempts()) {
      $override = \Drupal::currentUser()->hasPermission('override quiz revisioning');
      if (Drupal::config('quiz.settings')->get('revisioning', FALSE)) {
        $form['revision']['#required'] = !$override;
      }
      else {
        $message = $override ?
          $this->t('<strong>Warning:</strong> This quiz has attempts. You can edit this quiz, but it is not recommended.<br/>Attempts in progress and reporting will be affected.<br/>You should delete all attempts on this quiz before editing.') :
          $this->t('You must delete all attempts on this quiz before editing.');
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
      $form['revision']['#description'] = '<strong>Warning:</strong> This quiz has attempts.<br/>In order to update this quiz you must create a new revision.<br/>This will affect reporting.<br/>This will only affect new attempts.';
    }

    return $form;
  }

}
