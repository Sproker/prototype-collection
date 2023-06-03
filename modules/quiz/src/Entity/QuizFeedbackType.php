<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\rules\Engine\RulesComponent;
use Drupal\rules\Ui\RulesUiComponentProviderInterface;

/**
 * Defines the quiz type entity class.
 *
 * @ConfigEntityType(
 *   id = "quiz_feedback_type",
 *   label = @Translation("Quiz feedback type"),
 *   label_collection = @Translation("Quiz feedback types"),
 *   label_singular = @Translation("Quiz feedback type"),
 *   label_plural = @Translation("Quiz feedback type"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz feedback type",
 *     plural = "@count quiz feedback types",
 *   ),
 *   admin_permission = "administer quiz",
 *   config_prefix = "feedback.type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "component"
 *   },
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "list_builder" = "Drupal\quiz\Config\Entity\QuizFeedbackTypeListBuilder",
 *     "form" = {
 *       "default" = "Drupal\quiz\Form\QuizFeedbackTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *   },
 *   links = {
 *     "add-form" = "/admin/quiz/feedback/type/add",
 *     "edit-form" = "/admin/quiz/feedback/type/{quiz_feedback_type}/edit",
 *     "delete-form" = "/admin/quiz/feedback/type/{quiz_feedback_type}/delete",
 *     "collection" = "/admin/quiz/feedback"
 *   }
 * )
 */
class QuizFeedbackType extends ConfigEntityBase implements RulesUiComponentProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getComponent() {
    if (empty($this->component)) {
      // Provide a default for now.
      // @todo make expression configurable.
      $this->component = [
        'expression' => ['id' => 'rules_and'],
        'context_definitions' => [
          'quiz_result' => [
            'type' => 'entity:quiz_result',
            'label' => 'Quiz result',
            'description' => 'Quiz result to evaluate feedback',
          ],
        ],
      ];
    }

    if (!isset($this->componentObject)) {
      $this->componentObject = RulesComponent::createFromConfiguration($this->component);
    }
    return $this->componentObject;
  }

  /**
   * {@inheritdoc}
   */
  public function updateFromComponent(RulesComponent $component) {
    $this->component = $component->getConfiguration();
    $this->componentObject = $component;

    return $this;
  }

}
