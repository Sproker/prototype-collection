<?php

namespace Drupal\quiz\Entity;

use Drupal;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use function count;

/**
 * Defines the Quiz entity class.
 *
 * @ContentEntityType(
 *   id = "quiz",
 *   label = @Translation("Quiz"),
 *   label_collection = @Translation("Quiz"),
 *   label_singular = @Translation("quiz"),
 *   label_plural = @Translation("quizzes"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz",
 *     plural = "@count quizzes",
 *   ),
 *   bundle_label = @Translation("Quiz type"),
 *   bundle_entity_type = "quiz_type",
 *   admin_permission = "administer quiz",
 *   permission_granularity = "bundle",
 *   base_table = "quiz",
 *   fieldable = TRUE,
 *   field_ui_base_route = "entity.quiz_type.edit_form",
 *   show_revision_ui = TRUE,
 *   revision_table = "quiz_revision",
 *   revision_data_table = "quiz_field_revision",
 *   entity_keys = {
 *     "id" = "qid",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "title",
 *     "published" = "status",
 *     "owner" = "uid",
 *     "uuid" = "uuid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message",
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\quiz\View\QuizViewBuilder",
 *     "list_builder" = "Drupal\quiz\Config\Entity\QuizListBuilder",
 *     "access" = "Drupal\quiz\Access\QuizAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\UncacheableEntityPermissionProvider",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *    "form" = {
 *       "default" = "Drupal\quiz\Form\QuizEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "views_data" = "Drupal\quiz\Entity\QuizViewsData",
 *     "storage" = "Drupal\quiz\Storage\QuizStorage"
 *   },
 *   links = {
 *     "canonical" = "/quiz/{quiz}",
 *     "add-page" = "/quiz/add",
 *     "add-form" = "/quiz/add/{quiz_type}",
 *     "edit-form" = "/quiz/{quiz}/edit",
 *     "delete-form" = "/quiz/{quiz}/delete",
 *     "collection" = "/admin/quiz/quizzes",
 *     "take" = "/quiz/{quiz}/take",
 *   }
 * )
 */
class Quiz extends EditorialContentEntityBase implements EntityChangedInterface, EntityOwnerInterface, RevisionLogInterface, EntityPublishedInterface {

  /**
   * Define options for keeping results.
   */
  const KEEP_BEST = 0;

  const KEEP_LATEST = 1;

  const KEEP_ALL = 2;

  use EntityOwnerTrait;
  use EntityChangedTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['status']
      ->setDisplayConfigurable('form', TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('This is only visible to Quiz admnistrators.'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ]);


    $fields['body'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setSetting('weight', 0)
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setRevisionable(TRUE)
      ->setLabel('Created');

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setRevisionable(TRUE)
      ->setLabel('Changed');

    $fields['number_of_random_questions'] = BaseFieldDefinition::create('integer')
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'number',
      ])
      ->setLabel(t('Number of random questions'));

    $fields['max_score_for_random'] = BaseFieldDefinition::create('integer')
      ->setDefaultValue(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'number',
      ])
      ->setLabel(t('Max score for random'));

    $fields['pass_rate'] = BaseFieldDefinition::create('integer')
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'number',
      ])
      ->setSettings([
        'min' => 0,
        'max' => 100,
      ])
      ->setDescription(t('Passing rate for this Quiz as a percentage score.'))
      ->setLabel(t('Passing rate for Quiz (%)'));

    $fields['summary_pass'] = BaseFieldDefinition::create('text_long')
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ])
      ->setDescription(t('Summary text for when the user passes the Quiz. Leave blank to not give summary text if passed, or if not using the "passing rate" field above. If not using the "passing rate" field above, this text will not be used.'))
      ->setLabel(t('Result text for a passing grade.'));

    $fields['summary_default'] = BaseFieldDefinition::create('text_long')
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ])
      ->setDescription(t('Summary text for when the user fails the Quiz. Leave blank to not give summary text if failed, or if not using the "passing rate" field above. If not using the "passing rate" field above, this text will not be used.'))
      ->setLabel(t('Result text for any grade.'));

    $fields['randomization'] = BaseFieldDefinition::create('list_integer')
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
      ])
      ->setCardinality(1)
      ->setSetting('allowed_values', [
        0 => t('No randomization'),
        1 => t('Random order'),
        2 => t('Random questions'),
        3 => t('Categorized random questions'),
      ])
      ->setDescription(t("<strong>Random order</strong> - all questions display in random order<br>
<strong>Random questions</strong> - specific number of questions are drawn randomly from this Quiz's pool of questions<br>
<strong>Categorized random questions</strong> - specific number of questions are drawn from each specified taxonomy term"))
      ->setDefaultValue(0)
      ->setRequired(TRUE)
      ->setLabel(t('Randomize questions'));

    $fields['backwards_navigation'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDescription(t('Allow users to go back and revisit questions already answered.'))
      ->setLabel(t('Backwards navigation'));

    $fields['keep_results'] = BaseFieldDefinition::create('list_integer')
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setCardinality(1)
      ->setDefaultValue(2)
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
      ])
      ->setSetting('allowed_values', [
        0 => t('The best'),
        1 => ('The newest'),
        2 => ('All'),
      ])
      ->setLabel(t('Store results'))
      ->setDescription(t('These results should be stored for each user.'));

    $fields['repeat_until_correct'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(0)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDescription(t('Require the user to retry the question until answered correctly.'))
      ->setLabel(t('Repeat until correct'));

    $fields['quiz_date'] = BaseFieldDefinition::create('daterange')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'daterange_default',
      ])
      ->setDescription(t('The date and time during which this Quiz will be available. Leave blank to always be available.'))
      ->setLabel(t('Quiz date'));

    $fields['takes'] = BaseFieldDefinition::create('integer')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'number',
      ])
      ->setLabel(t('Allowed number of attempts'))
      ->setDescription(t('The number of times a user is allowed to take this Quiz. Anonymous users are only allowed to take Quiz that allow an unlimited number of attempts.'));

    $fields['show_attempt_stats'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setSetting('min', 0)
      ->setDescription(t('Display the allowed number of attempts on the starting page for this Quiz.'))
      ->setLabel(t('Display allowed number of attempts'));

    $fields['time_limit'] = BaseFieldDefinition::create('integer')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'number',
      ])
      ->setSetting('min', 0)
      ->setDescription(t('Set the maximum allowed time in seconds for this Quiz. Use 0 for no limit.'))
      ->setLabel(t('Time limit'));

    $fields['max_score'] = BaseFieldDefinition::create('integer')
      ->setRevisionable(TRUE)
      ->setLabel(t('Calculated max score of this quiz.'));

    $fields['allow_skipping'] = BaseFieldDefinition::create('boolean')
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue(1)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDescription(t('Allow users to skip questions in this Quiz.'))
      ->setLabel(t('Allow skipping'));

    $fields['allow_resume'] = BaseFieldDefinition::create('boolean')
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue(1)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDescription(t('Allow users to leave this Quiz incomplete and then resume it from where they left off.'))
      ->setLabel(t('Allow resume'));

    $fields['allow_jumping'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(0)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDescription(t('Allow users to jump to any question using a menu or pager in this Quiz.'))
      ->setLabel(t('Allow jumping'));

    $fields['allow_change'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDescription(t('If the user is able to visit a previous question, allow them to change the answer.'))
      ->setLabel(t('Allow changing answers'));

    $fields['allow_change_blank'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(0)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDescription(t('Allow users to go back and revisit questions already answered.'))
      ->setLabel(t('Allow changing blank answers'));

    $fields['build_on_last'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Each attempt builds on the last'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDefaultValue('fresh')
      ->setRequired(TRUE)
      ->setCardinality(1)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
      ])
      ->setSetting('allowed_values', [
        'fresh' => t('Fresh attempt every time'),
        'correct' => t('Prepopulate with correct answers from last result'),
        'all' => t('Prepopulate with all answers from last result'),
      ])
      ->setDescription(t('Instead of starting a fresh Quiz, users can base a new attempt on the last attempt, with correct answers prefilled. Set the default selection users will see. Selecting "fresh attempt every time" will not allow the user to choose.'));

    $fields['show_passed'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDescription(t('Show a message if the user has previously passed the Quiz.'))
      ->setLabel(t('Show passed message'));

    $fields['mark_doubtful'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(0)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDescription(t('Allow users to mark their answers as doubtful.'))
      ->setLabel(t('Mark doubtful'));

    $fields['review_options'] = BaseFieldDefinition::create('map')
      ->setRevisionable(TRUE)
      ->setLabel(t('Review options'));

    $fields['result_type'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'quiz_result_type')
      ->setRequired(TRUE)
      ->setDefaultValue('quiz_result')
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setLabel(t('Result type to use'));

    $fields['result_options'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'paragraph')
      ->setSetting('handler_settings', ['target_bundles' => ['quiz_result_feedback' => 'quiz_result_feedback']])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_paragraphs',
      ])
      ->setLabel(t('Result options'));

    $fields['quiz_terms'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'paragraph')
      ->setSetting('handler_settings', ['target_bundles' => ['quiz_question_term_pool' => 'quiz_question_term_pool']])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_paragraphs',
      ])
      ->setLabel(t('Quiz terms'));

    return $fields;
  }

  /**
   * Add a question to this quiz.
   *
   * @param QuizQuestion $quiz_question
   *
   * @return \Drupal\quiz\Entity\QuizQuestionRelationship
   *   Newly created or found QuizQuestionRelationship.
   * @todo return value may change
   *
   */
  function addQuestion(QuizQuestion $quiz_question) {
    $relationships = Drupal::entityTypeManager()
      ->getStorage('quiz_question_relationship')
      ->loadByProperties([
        'quiz_vid' => $this->getRevisionId(),
        'question_id' => $quiz_question->id(),
        'question_vid' => $quiz_question->getRevisionId(),
      ]);

    $query = Drupal::database()->select('quiz_question_relationship', 'qqr')
      ->condition('quiz_vid', $this->getRevisionId());
    $query->addExpression('max(weight)', 'max_weight');
    $new_weight = $query->execute()->fetchField();

    if (empty($relationships)) {
      // Save a new relationship.
      $qqr = QuizQuestionRelationship::create([
        'quiz_id' => $this->id(),
        'quiz_vid' => $this->getRevisionId(),
        'question_id' => $quiz_question->id(),
        'question_vid' => $quiz_question->getRevisionId(),
        'weight' => $new_weight + 1,
      ]);
      $qqr->save();
      return $qqr;
    }
    else {
      return reset($relationships);
    }

    // @todo update the max score of the quiz.
    // quiz_update_max_score_properties(array($quiz->vid));
  }

  /**
   * Find a resumable Quiz attempt in progress.
   *
   * @param AccountInterface $user
   *   A user.
   *
   * @return Drupal\quiz\Entity\QuizResult
   *   The Quiz result to resume, or NULL if one is not found.
   */
  function getResumeableResult(AccountInterface $user) {
    $query = Drupal::entityQuery('quiz_result')
      ->condition('qid', $this->get('qid')->getString())
      ->condition('uid', $user->id())
      ->condition('time_end', NULL, 'IS NULL')
      ->sort('time_start', 'DESC')
      ->range(0, 1);

    if ($result = $query->execute()) {
      return QuizResult::load(key($result));
    }

    return NULL;
  }

  /**
   * Delete all quiz results and question relationships when a quiz is deleted.
   *
   * @todo This should probably gather keys instead of loading all entities and
   * looping through to ensure their hooks get fired.
   *
   * {@inheritdoc}
   */
  public function delete() {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('quiz_question_relationship')
      ->loadByProperties(['quiz_id' => $this->id()]);
    foreach ($entities as $entity) {
      $entity->delete();
    }

    $entities = \Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['qid' => $this->id()]);
    foreach ($entities as $entity) {
      $entity->delete();
    }

    parent::delete();
  }

  /**
   * Retrieves a list of questions (to be taken) for a given quiz.
   *
   * If the quiz has random questions this function only returns a random
   * selection of those questions. This function should be used to decide
   * what questions a quiz taker should answer.
   *
   * This question list is stored in the user's result, and may be different
   * when called multiple times. It should only be used to generate the layout
   * for a quiz attempt and NOT used to do operations on the questions inside of
   * a quiz.
   *
   * @return array
   *   Array of question data keyed by the weight of the question. This should
   *   be used to create QuizResultAnswer entities.
   */
  function buildLayout() {
    $questions = [];

    if ($this->get('randomization')->getString() == 3) {
      $questions = $this->buildCategorizedQuestionList();
    }
    else {
      // Get required questions first.
      $query = \Drupal::database()->query('SELECT qqr.question_id as qqid, qqr.question_vid as vid, qq.type, qqr.qqr_id, qqr.qqr_pid, qq.title
    FROM {quiz_question_relationship} qqr
    JOIN {quiz_question} qq ON qqr.question_id = qq.qqid
    LEFT JOIN {quiz_question_relationship} qqr2 ON (qqr.qqr_pid = qqr2.qqr_id OR (qqr.qqr_pid IS NULL AND qqr.qqr_id = qqr2.qqr_id))
    WHERE qqr.quiz_vid = :quiz_vid
    AND qqr.question_status = :question_status
    ORDER BY qqr2.weight, qqr.weight', [
        ':quiz_vid' => $this->getRevisionId(),
        ':question_status' => QuizQuestion::QUESTION_ALWAYS,
      ]);
      $i = 0;
      while ($question_node = $query->fetchAssoc()) {
        // Just to make it easier on us, let's use a 1-based index.
        $i++;
        $questions[$i] = $question_node;
      }

      // Get random questions for the remainder.
      if ($this->get('randomization')->getString() == 2) {
        if ($this->get('number_of_random_questions')->getString() > 0) {
          $random_questions = $this->getRandomQuestions();
          $questions = array_merge($questions, $random_questions);
          if ($this->get('number_of_random_questions')->getString() > count($random_questions)) {
            // Unable to find enough requested random questions.
            return FALSE;
          }
        }
      }

      // Shuffle questions if required.
      if ($this->get('randomization')->getString() == '1') {
        $question_to_shuffle = [];
        $mark = NULL;
        foreach ($questions as $qidx => $question) {
          if ($mark) {
            if ($question['type'] == 'page') {
              // Found another page.
              shuffle($question_to_shuffle);
              array_splice($questions, $mark, $qidx - $mark - 1, $question_to_shuffle);
              $mark = 0;
              $question_to_shuffle = [];
            }
            else {
              $question_to_shuffle[] = $question;
            }
          }
          if ($question['type'] == 'page') {
            $mark = $qidx;
          }
        }

        if ($mark) {
          shuffle($question_to_shuffle);
          array_splice($questions, $mark, $qidx - $mark, $question_to_shuffle);
        }
        elseif (is_null($mark)) {
          shuffle($questions);
        }
      }
    }

    $count = 0;
    $display_count = 0;
    $questions_out = [];
    foreach ($questions as &$question) {
      $count++;
      $display_count++;
      $question['number'] = $count;
      if ($question['type'] != 'page') {
        $question['display_number'] = $display_count;
      }
      $questions_out[$count] = $question;
    }
    return $questions_out;
  }

  /**
   * Check if this Quiz revision has attempts.
   *
   * @return bool
   *   If the version of this Quiz has attempts.
   */
  function hasAttempts() {
    $result = \Drupal::entityQuery('quiz_result')
      ->condition('qid', $this->id())
      ->condition('vid', $this->getRevisionId())
      ->range(0, 1)
      ->execute();
    return !empty($result);
  }

  /**
   * Build a question list for quizzes with categorized random questions.
   *
   * @return array
   *   Array of question information.
   */
  function buildCategorizedQuestionList() {
    /* @var $terms Drupal\paragraphs\Entity\Paragraph[] */
    $terms = $this->get('quiz_terms')->referencedEntities();
    $total_questions = [];
    foreach ($terms as $term) {
      // Get the term ID referenced on the quiz question pool.
      $tid = $term->get('quiz_question_tid')->referencedEntities()[0]->id();
      $query = \Drupal::entityQuery('quiz_question');

      // Find all taxonomy fields on questions.
      $fields = \Drupal::service('entity_field.manager')
        ->getFieldStorageDefinitions('quiz_question');
      $or = $query->orConditionGroup();
      foreach ($fields as $field_name => $field) {
        if ($field->getType() == 'entity_reference' && $field->getSetting('target_type') == 'taxonomy_term') {
          $or->condition("{$field_name}.target_id", $tid);
        }
      }
      $query->condition($or);
      $query->condition('status', 1);
      $query->addTag('quiz_build_categorized_questions');
      $query->addTag('quiz_random');
      $query->range(0, $term->get('quiz_question_number')->getString());
      $question_ids = $query->execute();
      if (count($question_ids) != $term->get('quiz_question_number')->getString()) {
        // Didn't find enough questions in this category.
        return [];
      }

      $found_questions = QuizQuestion::loadMultiple($question_ids);

      foreach ($found_questions as $qqid => $question) {
        $total_questions[] = [
          'qqid' => $qqid,
          'tid' => $tid,
          'type' => $question->bundle(),
          'vid' => $question->getRevisionId(),
        ];
      }
    }


    // Optionally shuffle all categories together?
    //shuffle($total_questions);

    return $total_questions;
  }

  /**
   * Get the number of required questions for a quiz.
   *
   * @return int
   *   Number of required questions.
   */
  function getNumberOfRequiredQuestions() {
    $query = Drupal::entityQuery('quiz_question_relationship');
    $query->condition('quiz_vid', $this->getRevisionId());
    $query->condition('question_status', QuizQuestion::QUESTION_ALWAYS);
    $result = $query->execute();
    return count($result);
  }

  /**
   * Finds out the number of configured questions for the quiz, taking into
   * account random questions pulled from a pool
   *
   * @return int
   *   The number of quiz questions.
   */
  function getNumberOfQuestions() {

    $count = 0;
    $relationships = $this->getQuestions();
    $random = $this->get('randomization')->getString();
    switch ($random) {
      case 2:
      case 3:
        $count = $this->getNumberOfRequiredQuestions() + $this->get('number_of_random_questions')->value;
        break;

      case 0:
      case 1:
      default:
        foreach ($relationships as $relationship) {
          if ($relationship->getQuestion()) {
              $count++;
          }
        }
    }
    return intval($count);
  }

  /**
   * Show the finish button?
   */
  function isLastQuestion() {
    /* @var $quiz_session \Drupal\quiz\Services\QuizSessionInterface */
    $quiz_session = \Drupal::service('quiz.session');
    $quiz_result = $quiz_session->getResult($this);
    $current = $quiz_session->getCurrentQuestion($this);

    $layout = $quiz_result->getLayout();

    foreach ($layout as $idx => $qra) {
      if ($qra->get('question_id')->referencedEntities()[0]->bundle() == 'page') {
        if ($current == $idx) {
          // Found a page that we are on.
          $in_page = TRUE;
          $last_page = TRUE;
        }
        else {
          // Found a quiz page that we are not on.
          $last_page = FALSE;
        }
      }
      elseif (empty($qra->qqr_pid)) {
        // A question without a parent showed up.
        $in_page = FALSE;
        $last_page = FALSE;
      }
    }

    return $last_page || !isset($layout[$current + 1]);
  }

  /**
   * Store old revision ID for copying questions.
   */
  function createDuplicate() {
    $vid = $this->getRevisionId();
    $dupe = parent::createDuplicate();
    $dupe->old_vid = $vid;
    return $dupe;
  }

  /**
   * Retrieve list of published questions assigned to quiz.
   *
   * This function should be used for question browsers and similar. It
   * should not be used to decide what questions a user should answer when
   * taking a quiz. Quiz::buildLayout is written for that purpose.
   *
   * @return QuizQuestionRelationship[]
   *   An array of questions.
   */
  function getQuestions() {
    $relationships = Drupal::entityTypeManager()
      ->getStorage('quiz_question_relationship')
      ->loadByProperties([
        'quiz_vid' => $this->getRevisionId(),
      ]);
    return $relationships;
  }

  /**
   * Copy questions to a new quiz revision.
   *
   * @param Quiz $old_quiz
   *   The old quiz revision.
   */
  function copyFromRevision(Quiz $old_quiz) {
    $quiz_questions = \Drupal::entityTypeManager()
      ->getStorage('quiz_question_relationship')
      ->loadByProperties([
        'quiz_vid' => $old_quiz->getRevisionId(),
      ]);

    foreach ($quiz_questions as $quiz_question) {
      $new_question = $quiz_question->createDuplicate();
      $new_question->set('quiz_vid', $this->getRevisionId());
      $new_question->set('quiz_id', $this->id());
      $old_id = $quiz_question->id();
      $new_question->save();
      $new_questions[$old_id] = $new_question;
    }

    foreach ($new_questions as $old_id => $quiz_question) {
      if (!$quiz_question->get('qqr_pid')->isEmpty()) {
        $quiz_question->set('qqr_pid', $new_questions[$quiz_question->get('qqr_pid')->getString()]->id());
        $quiz_question->save();
      }
    }
  }

  /**
   * Get random questions for a quiz.
   *
   * @return array
   *   Array questions.
   */
  function getRandomQuestions() {
    $num_random = $this->get('number_of_random_questions')->getString();
    $questions = [];
    if ($num_random > 0) {
      // Select random question from assigned pool.

      $query = Drupal::entityQuery('quiz_question_relationship');
      $query->condition('quiz_vid', $this->getRevisionId());
      $query->condition('question_status', QuizQuestion::QUESTION_RANDOM);
      $query->addTag('quiz_random');
      $query->range(0, $this->get('number_of_random_questions')->getString());
      if ($relationships = $query->execute()) {
        /* @var $qqrs QuizQuestionRelationship[] */
        $qqrs = QuizQuestionRelationship::loadMultiple($relationships);

        foreach ($qqrs as $qqr) {
          $questionEntity = Drupal::entityTypeManager()
            ->getStorage('quiz_question')
            ->loadRevision($qqr->get('question_vid')->getString());

          $question = [
            'qqid' => $questionEntity->id(),
            'vid' => $questionEntity->getRevisionId(),
            'type' => $questionEntity->bundle(),
            'random' => TRUE,
            'relative_max_score' => $this->get('max_score_for_random')->getString(),
          ];
          $questions[] = $question;
        }
        return $questions;
      }
    }

    return [];
  }

  /**
   * Check if a user passed this Quiz.
   *
   * @param AccountInterface $account
   *   The user to check.
   *
   * @return bool
   *   Returns TRUE if the user has passed the quiz at least once, and FALSE
   *   otherwise. Note that a FALSE may simply indicate that the user has not
   *   taken the quiz.
   */
  function isPassed(AccountInterface $account) {
    // @todo convert to select()
    $passed = Drupal::database()->query('SELECT COUNT(result_id) AS passed_count
    FROM {quiz_result} qnrs
    INNER JOIN {quiz} USING (vid, qid)
    WHERE qnrs.vid = :vid
    AND qnrs.qid = :qid
    AND qnrs.uid = :uid
    AND score >= pass_rate', [
      ':vid' => $this->getRevisionId(),
      ':qid' => $this->id(),
      ':uid' => $account->id(),
    ])->fetchField();
    return ($passed > 0);
  }

}
