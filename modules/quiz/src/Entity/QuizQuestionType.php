<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the quiz question type entity class.
 *
 * @ConfigEntityType(
 *   id = "quiz_question_type",
 *   label = @Translation("Quiz question type"),
 *   label_collection = @Translation("Quiz question types"),
 *   label_singular = @Translation("quiz question type"),
 *   label_plural = @Translation("quiz question types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz question type",
 *     plural = "@count quiz question types",
 *   ),
 *   admin_permission = "administer quiz",
 *   config_prefix = "question.type",
 *   bundle_of = "quiz_question",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "list_builder" = "Drupal\quiz\Config\Entity\QuizQuestionTypeListBuilder",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\BundleEntityFormBase",
 *       "edit" = "Drupal\Core\Entity\BundleEntityFormBase",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   links = {
 *     "add-form" = "/admin/quiz/quiz-question-types/add",
 *     "edit-form" = "/admin/quiz/quiz-question-types/manage/{quiz_question_type}",
 *     "delete-form" = "/admin/quiz/quiz-question-types/manage/{quiz_question_type}/delete",
 *     "collection" = "/admin/quiz/quiz-question-types"
 *   }
 * )
 */
class QuizQuestionType extends ConfigEntityBundleBase {

}
