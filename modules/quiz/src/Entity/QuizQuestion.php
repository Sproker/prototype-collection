<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\quiz\QuizQuestionInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\h5p\Field\Type\H5PFieldItemList;

/**
 * Defines the Quiz question entity class.
 *
 * @ContentEntityType(
 *   id = "quiz_question",
 *   label = @Translation("Quiz question"),
 *   label_collection = @Translation("Quiz question"),
 *   label_singular = @Translation("quiz question"),
 *   label_plural = @Translation("quiz questions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz questions",
 *     plural = "@count quiz questions",
 *   ),
 *   bundle_label = @Translation("Quiz question type"),
 *   bundle_entity_type = "quiz_question_type",
 *   admin_permission = "administer quiz_question",
 *   permission_granularity = "bundle",
 *   base_table = "quiz_question",
 *   fieldable = TRUE,
 *   field_ui_base_route = "entity.quiz_question_type.edit_form",
 *   show_revision_ui = TRUE,
 *   revision_table = "quiz_question_revision",
 *   revision_data_table = "quiz_question_field_revision",
 *   entity_keys = {
 *     "id" = "qqid",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "title",
 *     "published" = "status",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\quiz\Config\Entity\QuizQuestionListBuilder",
 *     "access" = "Drupal\entity\UncacheableEntityAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\UncacheableEntityPermissionProvider",
 *     "route_provider" = {
 *       "html" = "Drupal\entity\Routing\AdminHtmlRouteProvider",
 *     },
 *    "form" = {
 *       "default" = "Drupal\quiz\Form\QuizQuestionEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "views_data" = "Drupal\entity\EntityViewsData",
 *   },
 *   links = {
 *     "canonical" = "/quiz-question/{quiz_question}",
 *     "add-page" = "/quiz-question/add",
 *     "add-form" = "/quiz-question/add/{quiz_question_type}",
 *     "edit-form" = "/quiz-question/{quiz_question}/edit",
 *     "delete-form" = "/quiz-question/{quiz_question}/delete",
 *     "collection" = "/admin/quiz/questions",
 *   }
 * )
 */
abstract class QuizQuestion extends EditorialContentEntityBase implements QuizQuestionInterface, EntityOwnerInterface {

  /**
   * Define question statuses...
   */
  const QUESTION_RANDOM = 0;

  const QUESTION_ALWAYS = 1;

  const QUESTION_NEVER = 2;

  // For strongly typed bundles.
  use QuizQuestionEntityTrait;
  use EntityOwnerTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setSetting('weight', 1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textfield',
      ]);

    $fields['h5p'] = BaseFieldDefinition::create('h5p')
      ->setLabel(t('H5P Content'))
      ->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'h5p_editor',
      ])
      ->setDisplayOptions('view', [
        'weight' => 2,
        'label' => 'above',
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    $fields['max_score'] = BaseFieldDefinition::create('integer')
      ->setRevisionable(TRUE)
      ->setLabel(t('The unscaled calculated max score of this Question.'));

    $fields['feedback'] = BaseFieldDefinition::create('text_long')
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setLabel(t('Generic feedback text'))
      ->setDescription(t('Will always display if configured on the Quiz.'));

    // @todo not working yet
    //    $fields['tid'] = BaseFieldDefinition::create('entity_reference_revisions')
    //      ->setSetting('target_type', 'taxonomy_term')
    //      //->setSetting('handler_settings', ['target_bundles' => ['quiz_question_term_pool' => 'quiz_question_term_pool']])
    //      ->setDisplayConfigurable('form', TRUE)
    //      ->setDisplayOptions('form', [
    //        'type' => 'entity_reference_autocomplete',
    //      ])
    //      ->setLabel('Quiz terms');

    return $fields;
  }

  public function save() {
    // Store the calculated max score from the question implementation.
    $this->set('max_score', $this->getMaximumScore());
    parent::save();
  }

}
