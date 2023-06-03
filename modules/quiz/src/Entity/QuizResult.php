<?php

namespace Drupal\quiz\Entity;

use Drupal;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz\Util\QuizUtil;
use Drupal\rules\Engine\RulesComponent;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use function count;
use function quiz_get_feedback_options;

/**
 * Defines the Quiz entity class.
 *
 * @ContentEntityType(
 *   id = "quiz_result",
 *   label = @Translation("Quiz result"),
 *   label_collection = @Translation("Quiz results"),
 *   label_singular = @Translation("quiz result"),
 *   label_plural = @Translation("quiz results"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz result",
 *     plural = "@count quiz results",
 *   ),
 *   bundle_label = @Translation("Quiz result type"),
 *   bundle_entity_type = "quiz_result_type",
 *   admin_permission = "administer quiz_result",
 *   permission_granularity = "entity_type",
 *   base_table = "quiz_result",
 *   fieldable = TRUE,
 *   field_ui_base_route = "entity.quiz_result_type.edit_form",
 *   show_revision_ui = FALSE,
 *   entity_keys = {
 *     "id" = "result_id",
 *     "published" = "released",
 *     "owner" = "uid",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\quiz\Entity\QuizResultListBuilder",
 *     "view_builder" = "Drupal\quiz\View\QuizResultViewBuilder",
 *     "access" = "Drupal\quiz\Access\QuizResultAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\UncacheableEntityPermissionProvider",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *    "form" = {
 *       "default" = "Drupal\quiz\Form\QuizResultEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "views_data" = "Drupal\entity\EntityViewsData",
 *   },
 *   links = {
 *     "canonical" = "/quiz/{quiz}/result/{quiz_result}",
 *     "edit-form" = "/quiz/{quiz}/result/{quiz_result}/edit",
 *     "delete-form" = "/quiz/{quiz}/result/{quiz_result}/delete"
 *   }
 * )
 */
class QuizResult extends ContentEntityBase implements EntityOwnerInterface, EntityChangedInterface {

  use EntityOwnerTrait;
  use EntityChangedTrait;
  use StringTranslationTrait;

  /**
   * Get the layout for this quiz result.
   *
   * The layout contains the questions to be delivered.
   *
   * @return QuizResultAnswer[]
   */
  public function getLayout() {
    if ($this->isNew()) {
      // New results do not have layouts yet.
      return [];
    }

    $quiz_result_answers = Drupal::entityTypeManager()
      ->getStorage('quiz_result_answer')
      ->loadByProperties([
        'result_id' => $this->id(),
      ]);

    // @todo when we load the layout we have to load the question relationships
    // too because we need to know the parentage
    $quiz_question_relationship = Drupal::entityTypeManager()
      ->getStorage('quiz_question_relationship')
      ->loadByProperties([
        'quiz_vid' => $this->get('vid')->getString(),
      ]);
    $id_qqr = [];
    foreach ($quiz_question_relationship as $rel) {
      $id_qqr[$rel->get('question_id')->getString()] = $rel;
    }


    $layout = [];
    foreach ($quiz_result_answers as $quiz_result_answer) {
      $layout[$quiz_result_answer->get('number')->getString()] = $quiz_result_answer;
      $question_id = $quiz_result_answer->get('question_id')->getString();
      if (isset($id_qqr[$question_id])) {
        // Question is in a relationship.
        // @todo better way to do this? We need to load the relationship
        // hierarchy onto the result answers.
        $quiz_result_answer->qqr_id = $id_qqr[$question_id]->get('qqr_id')->getString();
        $quiz_result_answer->qqr_pid = $id_qqr[$question_id]->get('qqr_pid')->getString();
      }
    }

    ksort($layout, SORT_NUMERIC);

    return $layout;
  }

  /**
   * Get the label for this quiz result.
   *
   * @return string
   */
  public function label() {
    $quiz = $this->getQuiz();
    $user = $this->getOwner();

    return $this->t('@user\'s @quiz result in "@title"', [
      '@user' => $user->getDisplayName(),
      '@quiz' => QuizUtil::getQuizName(),
      '@title' => $quiz->get('title')->getString(),
    ]);
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['result_id'] = BaseFieldDefinition::create('integer')
      ->setRequired(TRUE)
      ->setLabel('Quiz result ID');

    $fields['qid'] = BaseFieldDefinition::create('entity_reference')
      ->setRequired(TRUE)
      ->setSetting('target_type', 'quiz')
      ->setLabel(t('Quiz'));

    $fields['vid'] = BaseFieldDefinition::create('integer')
      ->setRequired(TRUE)
      ->setLabel('Quiz revision ID');

    $fields['time_start'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Attempt start time');

    $fields['time_end'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Attempt end time');

    $fields['released'] = BaseFieldDefinition::create('boolean')
      ->setLabel('Released')
      ->setDefaultValue(0);

    $fields['score'] = BaseFieldDefinition::create('integer')
      ->setLabel('Score');

    $fields['is_invalid'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(0)
      ->setLabel('Invalid');

    $fields['is_evaluated'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(0)
      ->setLabel('Evaluated');

    $fields['attempt'] = BaseFieldDefinition::create('integer')
      ->setRequired(TRUE)
      ->setDefaultValue(1)
      ->setLabel('Attempt');

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel('Result type');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel('Created');

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel('Changed');

    return $fields;
  }

  /**
   * Save the Quiz result and do any post-processing to the result.
   *
   * @param type $this
   * @param \DatabaseTransaction $transaction
   *
   * @return bool
   */
  public function save() {
    if ($this->get('time_start')->isEmpty()) {
      $this->set('time_start', \Drupal::time()->getRequestTime());
    }


    $new = $this->isNew();

    if ($new) {
      // New attempt, we need to set the attempt number if there are previous
      // attempts.
      if ($this->get('uid')->getString() == 0) {
        // If anonymous, the attempt is always 1.
        $this->attempt = 1;
      }
      else {
        // Get the highest attempt number.
        $efq = \Drupal::entityQuery('quiz_result');
        $result = $efq->range(0, 1)
          ->condition('qid', $this->get('qid')->getString())
          ->condition('uid', $this->get('uid')->getString())
          ->sort('attempt', 'DESC')
          ->execute();
        if (!empty($result)) {
          $keys = array_keys($result);
          $existing = QuizResult::load(reset($keys));
          $this->set('attempt', $existing->get('attempt')->getString() + 1);
        }
      }
    }

    // Save the Quiz result.
    if (!$new) {
      $original = \Drupal::entityTypeManager()
        ->getStorage('quiz_result')
        ->loadUnchanged($this->id());
    }
    parent::save();

    // Post process the result.
    if ($new) {
      $quiz = \Drupal::entityTypeManager()
        ->getStorage('quiz')
        ->loadRevision($this->get('vid')->getString());

      // Create question list.
      $questions = $quiz->buildLayout();
      if (empty($questions)) {
        \Drupal::messenger()->addError($this->t('Not enough questions were found. Please add more questions before trying to take this @quiz.', array('@quiz' => QuizUtil::getQuizName())));
        return FALSE;
      }


      if (in_array($this->build_on_last, ['correct', 'all']) && $quiz_result_old = self::findOldResult($this)) {
        // Build on the last attempt the user took. If this quiz has build on
        // last attempt set, we need to search for a previous attempt with the
        // same version of the current quiz.
        // Now clone the answers on top of the new result.
        $quiz_result_old->copyToQuizResult($this);
      }
      else {
        $i = 0;
        $j = 0;
        foreach ($questions as $question) {
          $quizQuestion = \Drupal::entityTypeManager()
            ->getStorage('quiz_question')
            ->loadRevision($question['vid']);
          $quiz_result_answer = QuizResultAnswer::create([
            'result_id' => $this->id(),
            'question_id' => $question['qqid'],
            'question_vid' => $question['vid'],
            'type' => $quizQuestion->bundle(),
            'tid' => !empty($question['tid']) ? $question['tid'] : NULL,
            'number' => ++$i,
            'display_number' => $quizQuestion->isQuestion() ? ++$j : NULL,
          ]);
          $quiz_result_answer->save();
        }
      }
    }

    if (isset($original) && !$original->get('is_evaluated')->value && $this->get('is_evaluated')->value) {
      // Quiz is finished! Delete old results if necessary.
      $this->maintainResults();
    }
  }

  /**
   * Mark results as invalid for a quiz according to the keep results setting.
   *
   * This function will only mark the results as invalid. The actual delete
   * action happens based on a cron run.
   * If we would have deleted the results in this function the user might not
   * have been able to view the result screen of the quiz he just finished.
   *
   * @param QuizResult $quiz_result
   *   The result of the latest result for the current user.
   *
   * @return bool
   *   TRUE if results were marked as invalid, FALSE otherwise.
   */
  function maintainResults() {
    $db = \Drupal::database();
    $quiz = $this->getQuiz();
    $user = $this->getOwner();

    // Do not delete results for anonymous users.
    if ($user->id() == 0) {
      return FALSE;
    }

    $result_ids = [];
    switch ((int) $quiz->get('keep_results')->getString()) {
      case Quiz::KEEP_ALL:
        break;

      case Quiz::KEEP_BEST:
        $best_result_id = $db->select('quiz_result', 'qnr')
          ->fields('qnr', ['result_id'])
          ->condition('qnr.qid', $quiz->id())
          ->condition('qnr.uid', $user->id())
          ->condition('qnr.is_evaluated', 1)
          ->condition('qnr.is_invalid', 0)
          ->orderBy('score', 'DESC')
          ->execute()
          ->fetchField();
        if ($best_result_id) {
          $result_ids = $db->select('quiz_result', 'qnr')
            ->fields('qnr', ['result_id'])
            ->condition('qnr.qid', $quiz->id())
            ->condition('qnr.uid', $user->id())
            ->condition('qnr.is_evaluated', 1)
            ->condition('qnr.is_invalid', 0)
            ->condition('qnr.result_id', $best_result_id, '!=')
            ->execute()
            ->fetchCol('result_id');
        }
        break;

      case Quiz::KEEP_LATEST:
        $result_ids = $db->select('quiz_result', 'qnr')
          ->fields('qnr', ['result_id'])
          ->condition('qnr.qid', $quiz->id())
          ->condition('qnr.uid', $user->id())
          ->condition('qnr.is_evaluated', 1)
          ->condition('qnr.is_invalid', 0)
          ->condition('qnr.result_id', $this->id(), '!=')
          ->execute()
          ->fetchCol('result_id');
        break;
    }

    if ($result_ids) {
      $db->update('quiz_result')
        ->fields([
          'is_invalid' => 1,
        ])
        ->condition('result_id', $result_ids, 'IN')
        ->execute();
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Update the session for this quiz to the active question.
   *
   * @param int $question_number
   *   Question number starting at 1.
   */
  function setQuestion($question_number) {
    /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
    $quiz_session = \Drupal::service('quiz.session');
    $quiz = $this->getQuiz();
    $quiz_session->setCurrentQuestion($quiz, $question_number);
  }

  /**
   * Can the quiz taker view the requested review?
   *
   * There's a workaround in here: @kludge
   *
   * When review for the question is enabled, and it is the last question,
   * technically it is the end of the quiz, and the "end of quiz" review
   * settings apply. So we check to make sure that we are in question taking
   * and the feedback is viewed within 5 seconds of completing the
   * question/quiz.
   *
   * @param string $option
   *   An option key.
   * @param QuizResult $quiz_result
   *   A Quiz result.
   *
   * @return bool
   *   TRUE if the quiz taker can view this quiz option at this time, FALSE
   *   otherwise.
   */
  function canReview($option) {
    $config = Drupal::config('quiz.settings');

    // Load quiz associated with this result.
    /* @var $quiz Quiz */
    $quiz = \Drupal::entityTypeManager()
      ->getStorage('quiz')
      ->loadRevision($this->get('vid')->getString());

    $admin = $quiz->access('update');

    if ($config->get('override_admin_feedback') && $admin) {
      // Admin user uses the global feedback options.
      $review_options['end'] = $config->get('admin_review_options_end');
      $review_options['question'] = $config->get('admin_review_options_question');
    }
    else {
      // Use this Quiz's feedback options.
      if ($quiz->get('review_options')->get(0)) {
        $review_options = $quiz->get('review_options')->get(0)->getValue();
      }
      else {
        $review_options = [];
      }
    }

    // Hold combined review options from each feedback type.
    $all_shows = [];
    $rules = \Drupal::moduleHandler()->moduleExists('rules');
    $feedbackTypes = QuizFeedbackType::loadMultiple();
    foreach ($review_options as $time_key => $shows) {
      /* @var $component RulesComponent */
      $component = $feedbackTypes[$time_key]->getComponent();
      $component->setContextValue('quiz_result', $this);
      if ($component->getExpression()->executeWithState($component->getState())) {
        // Add selected feedbacks to the show list.
        $all_shows += array_filter($shows);
      }
    }

    // Hack: The quiz has ended but we're on the last question so we need to
    // show feedback, even though the Rule has failed.
    if ($this->get('time_end')->value + 5 > time()) {
      $all_shows += $review_options['question'] ?? [];
    }

    return !empty($all_shows[$option]);
  }

  /**
   * Get xAPI listener statements for current quiz and user.
   *
   * @param QuizResult $quiz_result
   *   The result of the latest result for the current user.
   * @return array
   *   Array of xAPI listener statements.
   */
  public function getXAPIListenerStatements(string $account) {
    $db = \Drupal::database();
    $quiz = $this->getQuiz();
    $query = $db->select('xapi_listener_statements', 'xapi');
    $query->condition('xapi.qid', $quiz->id());
    $query->condition('xapi.uid', $account);
    $query->addExpression('attempts', 'attempts');
    $query->addExpression('SUM(score_raw)', 'sum_score_raw');
    $query->addExpression('SUM(score_max)', 'sum_score_max');
    $query->addExpression('SUM(duration)', 'sum_duration');
    $query->groupBy('xapi.qid');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Score a quiz result.
   */
  function finalize() {
    $questions = $this->getLayout();

    // Mark all missing answers as blank. This is essential here for when we may
    // have pages of unanswered questions. Also kills a lot of the skip code that
    // was necessary before.
    foreach ($questions as $qinfo) {
      // If the result answer has not been marked as skipped and it hasn't been
      // answered.
      if (empty($qinfo->is_skipped) && empty($qinfo->answer_timestamp)) {
        $qinfo->is_skipped = 1;
        $qinfo->save();
      }
    }

    $score = $this->score();

    if (!isset($score['percentage_score'])) {
      $score['percentage_score'] = 0;
    }

    // @todo Could be removed if we implement any "released" functionality.
    $this->set('released', 1);

    $this->set('is_evaluated', $score['is_evaluated']);
    $this->set('score', $score['percentage_score']);
    $this->set('time_end', \Drupal::time()->getRequestTime());
    $this->save();
    return $this;
  }

  /**
   * Calculates the score user received on quiz.
   *
   * @param $quiz
   *   The quiz node.
   * @param $result_id
   *   Quiz result ID.
   *
   * @return array
   *   Contains five elements:
   *     - question_count
   *     - possible_score
   *     - numeric_score
   *     - percentage_score
   *     - is_evaluated
   */
  function score() {
    $quiz_result_answers = $this->getLayout();

    $numeric_score = $possible_score = 0;

    $is_evaluated = 1;

    foreach ($quiz_result_answers as $quiz_result_answer) {
      // Get the scaled point value for this question response.
      $numeric_score += $quiz_result_answer->getPoints();
      // Get the scaled max score for this question relationship.
      $possible_score += $quiz_result_answer->getMaxScore();
      if (!$quiz_result_answer->isEvaluated()) {
        $is_evaluated = 0;
      }
    }

    return [
      'question_count' => count($quiz_result_answers),
      'possible_score' => $possible_score,
      'numeric_score' => $numeric_score,
      'percentage_score' => ($possible_score == 0) ? 0 : round(($numeric_score * 100) / $possible_score),
      'is_evaluated' => $is_evaluated,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Delete all result answers when a result is deleted.
   */
  public function delete() {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('quiz_result_answer')
      ->loadByProperties(['result_id' => $this->id()]);
    foreach ($entities as $entity) {
      $entity->delete();
    }
    //\Drupal::entityTypeManager()->getStorage('quiz_result_answer')->delete($entities);

    parent::delete();
  }

  /**
   * Find a result that is not the same as the passed result.
   *
   * Note: the Quiz result does not have an actually exist - in that case, it
   * will return the first completed result found.
   *
   * // @todo what?
   * // Oh, this is to find a result for build-on-last.
   */
  public function findOldResult() {
    $efq = \Drupal::entityQuery('quiz_result');
    $result = $efq->condition('uid', $this->get('uid')->getString())
      ->condition('qid', $this->get('qid')->getString())
      ->condition('vid', $this->get('vid')->getString())
      ->condition('result_id', (int) $this->id(), '!=')
      ->condition('time_start', 0, '>')
      ->sort('time_start', 'DESC')
      ->range(0, 1)
      ->execute();
    if (!empty($result)) {
      return QuizResult::load(key($result));
    }
    return NULL;
  }

  /**
   * Can the quiz taker view any reviews right now?
   *
   * @return bool
   */
  public function hasReview() {
    foreach (quiz_get_feedback_options() as $option => $label) {
      if ($this->canReview($option)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Quiz results are never viewed outside of a Quiz, so we enforce that a Quiz
   * route parameter is added.
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    $url = parent::toUrl($rel, $options);
    $url->setRouteParameter('quiz', $this->get('qid')->getString());
    return $url;
  }

  /**
   * Get the Quiz of this result.
   *
   * @return \Drupal\quiz\Entity\Quiz
   */
  public function getQuiz() {
    return Drupal::entityTypeManager()
      ->getStorage('quiz')
      ->loadRevision($this->get('vid')->getString());
  }

  /**
   * Copy this result's answers onto another Quiz result, based on the new Quiz
   * result's settings.
   *
   * @param QuizResult $result_new
   *   An empty QuizResult.
   */
  function copyToQuizResult(QuizResult $result_new) {
    // Re-take all the questions.
    foreach ($this->getLayout() as $qra) {
      if (($result_new->build_on_last == 'all' || $qra->isCorrect()) && !$qra->isSkipped()) {
        // Populate answer.
        $duplicate = $qra->createDuplicate();
        $duplicate->set('uuid', \Drupal::service('uuid')->generate());
      }
      else {
        // Create new answer.
        $duplicate = QuizResultAnswer::create([
          'type' => $qra->bundle(),
        ]);
        foreach ($qra->getFields() as $name => $field) {
          /* @var $field Drupal\Core\Field\FieldItemList */
          if (!in_array($name, ['result_answer_id', 'uuid']) && is_a($field->getFieldDefinition(), '\Drupal\Core\Field\BaseFieldDefinition')) {
            // Copy any base fields, but not the answer.
            $duplicate->set($name, $field->getValue());
          }
        }
      }

      // Set new result ID.
      $duplicate->set('result_id', $result_new->id());
      $duplicate->save();
    }
  }

  /**
   * Determine if the time limit has been reached for this attempt.
   *
   * @return bool
   */
  function isTimeReached() {
    $quiz = $this->getQuiz();

    $config = \Drupal::config('quiz.settings');
    $time_limit = $quiz->get('time_limit')->getString();
    $time_limit_buffer = $config->get('time_limit_buffer', 5);
    $time_start = $this->get('time_start')->getString();
    $request_time = \Drupal::time()->getRequestTime();

    return ($time_limit > 0 &&
      ($request_time > ($time_start + $time_limit + $time_limit_buffer)));
  }

  /**
   * Check if this result has been evaluated (graded).
   *
   * @return bool
   */
  function isEvaluated() {
    return (bool) $this->get('is_evaluated')->getString();
  }

}
