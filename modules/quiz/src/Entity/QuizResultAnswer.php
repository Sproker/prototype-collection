<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\quiz\QuizAnswerInterface;

/**
 * Defines the Quiz entity class.
 *
 * @ContentEntityType(
 *   id = "quiz_result_answer",
 *   label = @Translation("Quiz result answer"),
 *   label_collection = @Translation("Quiz result answer"),
 *   label_singular = @Translation("quiz result answer"),
 *   label_plural = @Translation("quiz result answers"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz result answer",
 *     plural = "@count quiz result answers",
 *   ),
 *   bundle_label = @Translation("Quiz result answer type"),
 *   bundle_entity_type = "quiz_result_answer_type",
 *   admin_permission = "administer quiz_result_answer",
 *   base_table = "quiz_result_answer",
 *   fieldable = TRUE,
 *   field_ui_base_route = "entity.quiz_result_answer_type.edit_form",
 *   show_revision_ui = FALSE,
 *   entity_keys = {
 *     "id" = "result_answer_id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\quiz\View\QuizResultAnswerViewBuilder",
 *     "access" = "Drupal\quiz\Access\QuizResultAnswerAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\UncacheableEntityPermissionProvider",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *    "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "views_data" = "Drupal\entity\EntityViewsData",
 *   },
 *   links = {
 *     "canonical" = "/quiz/{quiz}/result/{quiz_result}/answer/{quiz_result_answer}",
 *   }
 * )
 */
abstract class QuizResultAnswer extends ContentEntityBase implements QuizAnswerInterface {

  use QuizResultAnswerEntityTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['result_id'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'quiz_result')
      ->setLabel('Quiz result ID');

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel('Answer type');

    $fields['question_id'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'quiz_question')
      ->setLabel('Question ID');

    $fields['question_vid'] = BaseFieldDefinition::create('integer')
      ->setLabel('Question revision ID');

    $fields['tid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Taxonomy term');

    $fields['is_correct'] = BaseFieldDefinition::create('boolean')
      ->setLabel('Correct');

    $fields['is_skipped'] = BaseFieldDefinition::create('boolean')
      ->setLabel('Skipped');

    $fields['points_awarded'] = BaseFieldDefinition::create('integer')
      ->setLabel('Scaled points awarded');

    $fields['answer_timestamp'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Date answered');

    $fields['number'] = BaseFieldDefinition::create('integer')
      ->setLabel('Question number');

    $fields['display_number'] = BaseFieldDefinition::create('integer')
      ->setLabel('Display number');

    $fields['is_doubtful'] = BaseFieldDefinition::create('boolean')
      ->setLabel('Doubtful');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel('Created');

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel('Changed');

    $fields['is_evaluated'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(0)
      ->setLabel('Evaluated');

    $fields['answer_feedback'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Answer feedback'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ]);

    return $fields;
  }

  /**
   * Get the next question.
   *
   * @return QuizResultAnswer
   *   The next question in the layout or NULL.
   */
  function getNext() {
    $result = $this->getQuizResult();
    foreach ($result->getLayout() as $idx => $qra) {
      if ($qra == $this) {
        if (isset($result->getLayout()[$idx + 1])) {
          return $result->getLayout()[$idx + 1];
        }
      }
    }
  }

  /**
   * Get the previous question.
   *
   * @return QuizResultAnswer
   *   The next question in the layout or NULL.
   */
  function getPrevious() {
    $result = $this->getQuizResult();
    foreach ($result->getLayout() as $idx => $qra) {
      if ($qra == $this) {
        if (isset($result->getLayout()[$idx - 1])) {
          return $result->getLayout()[$idx - 1];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Quiz result answers are never viewed outside of a Quiz result, so we
   * enforce that a Quiz result route parameter is added.
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    $url = parent::toUrl($rel, $options);
    $url->setRouteParameter('quiz', $this->getQuizResult()->getQuiz()->id());
    $url->setRouteParameter('quiz_result', $this->getQuizResult()->id());
    return $url;
  }

}
