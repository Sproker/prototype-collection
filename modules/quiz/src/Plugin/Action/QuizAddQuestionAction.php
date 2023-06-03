<?php

namespace Drupal\quiz\Plugin\Action;

use Drupal;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Access\AccessResult;

/**
 * Action description.
 *
 * @Action(
 *   id = "quiz_add_question_to_quiz",
 *   label = @Translation("Add questions to quiz"),
 *   type = "quiz_question"
 * )
 */
class QuizAddQuestionAction extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /* @var $url Drupal\Core\Url */
    $url = $this->context['redirect_url'];
    $qid = $url->getRouteParameters()['quiz'];
    $quiz = \Drupal::entityTypeManager()
      ->getStorage('quiz')
      ->load($qid);
    $quiz->addQuestion($entity);
    return $this->t('Added question to quiz.');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = TRUE;

    if ($return_as_object === TRUE) {
      $access = AccessResult::allowed();
    }

    return $access;
  }

}
