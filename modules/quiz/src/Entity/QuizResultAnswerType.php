<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the quiz result answer type entity class.
 *
 * @ConfigEntityType(
 *   id = "quiz_result_answer_type",
 *   label = @Translation("Quiz result answer type"),
 *   label_collection = @Translation("Quiz result answer types"),
 *   label_singular = @Translation("quiz result answer type"),
 *   label_plural = @Translation("quiz result answer types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz result answer type",
 *     plural = "@count quiz result answer types",
 *   ),
 *   admin_permission = "administer quiz",
 *   config_prefix = "result.answer.type",
 *   bundle_of = "quiz_result_answer",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "list_builder" = "Drupal\quiz\Config\Entity\QuizResultAnswerTypeListBuilder",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\BundleEntityFormBase",
 *       "add" = "Drupal\Core\Entity\BundleEntityFormBase",
 *       "edit" = "Drupal\Core\Entity\BundleEntityFormBase",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   links = {
 *     "add-form" = "/admin/quiz/quiz-result-answer-types/add",
 *     "edit-form" = "/admin/quiz/quiz-result-answer-types/manage/{quiz_result_answer_type}",
 *     "delete-form" = "/admin/quiz/quiz-result-answer-types/manage/{quiz_result_answer_type}/delete",
 *     "collection" = "/admin/quiz/quiz-result-answer-types"
 *   }
 * )
 */
class QuizResultAnswerType extends ConfigEntityBundleBase {

}
