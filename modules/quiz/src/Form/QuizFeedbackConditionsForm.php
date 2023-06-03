<?php

namespace Drupal\quiz\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rules\Ui\RulesUiConfigHandler;

/**
 * Rules condition form for feedback types.
 */
class QuizFeedbackConditionsForm extends ConfigFormBase {

  /**
   * The RulesUI handler of the currently active UI.
   *
   * @var RulesUiConfigHandler
   */
  protected $rulesUiHandler;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'quiz_feedback_conditions';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, RulesUiConfigHandler $rules_ui_handler = NULL) {
    $form = parent::buildForm($form, $form_state);
    $this->rulesUiHandler = $rules_ui_handler;

    $form['conditions'] = $this->rulesUiHandler->getForm()
      ->buildForm([], $form_state);

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#limit_validation_errors' => [['locked']],
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancel'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $this->rulesUiHandler->getForm()
      ->validateForm($form['conditions'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->rulesUiHandler->getForm()
      ->submitForm($form['conditions'], $form_state);

    // Save the configuration that submitForm() updated (the config entity).
    $config = $this->rulesUiHandler->getConfig();
    $config->save();

    // Also remove the temporarily stored component, it has been persisted now.
    $this->rulesUiHandler->clearTemporaryStorage();

    parent::submitForm($form, $form_state);
  }

  /**
   * Form submission handler for the 'cancel' action.
   */
  public function cancel(array $form, FormStateInterface $form_state) {
    $this->rulesUiHandler->clearTemporaryStorage();
    $this->messenger()->addMessage($this->t('Canceled.'));
    $form_state->setRedirectUrl($this->rulesUiHandler->getBaseRouteUrl());
  }

}
