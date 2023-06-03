<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the quiz result type type entity class.
 *
 * @ConfigEntityType(
 *   id = "quiz_result_type",
 *   label = @Translation("Quiz result type"),
 *   label_collection = @Translation("Quiz result types"),
 *   label_singular = @Translation("quiz result type"),
 *   label_plural = @Translation("quiz result types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz result type",
 *     plural = "@count quiz result types",
 *   ),
 *   admin_permission = "administer quiz",
 *   config_prefix = "result.type",
 *   bundle_of = "quiz_result",
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
 *     "list_builder" = "Drupal\quiz\Config\Entity\QuizResultTypeListBuilder",
 *     "form" = {
 *       "default" = "Drupal\quiz\Form\QuizResultTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   links = {
 *     "add-form" = "/admin/quiz/quiz-result-types/add",
 *     "edit-form" = "/admin/quiz/quiz-result-types/manage/{quiz_result_type}",
 *     "delete-form" = "/admin/quiz/quiz-result-types/manage/{quiz_result_type}/delete",
 *     "collection" = "/admin/quiz/quiz-result-types"
 *   }
 * )
 */
class QuizResultType extends ConfigEntityBundleBase {

}
