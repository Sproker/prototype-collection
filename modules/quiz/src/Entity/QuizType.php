<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the quiz type entity class.
 *
 * @ConfigEntityType(
 *   id = "quiz_type",
 *   label = @Translation("Quiz type"),
 *   label_collection = @Translation("Quiz types"),
 *   label_singular = @Translation("quiz type"),
 *   label_plural = @Translation("quiz types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz type",
 *     plural = "@count quiz types",
 *   ),
 *   admin_permission = "administer quiz",
 *   config_prefix = "type",
 *   bundle_of = "quiz",
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
 *     "list_builder" = "Drupal\quiz\Config\Entity\QuizTypeListBuilder",
 *     "form" = {
 *       "default" = "Drupal\quiz\Form\QuizTypeEntityForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   links = {
 *     "add-form" = "/admin/quiz/quiz-types/add",
 *     "edit-form" = "/admin/quiz/quiz-types/manage/{quiz_type}",
 *     "delete-form" = "/admin/quiz/quiz-types/manage/{quiz_type}/delete",
 *     "collection" = "/admin/quiz/quiz-types"
 *   }
 * )
 */
class QuizType extends ConfigEntityBundleBase {

}
