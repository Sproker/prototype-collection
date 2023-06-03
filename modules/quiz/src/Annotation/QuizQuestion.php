<?php

namespace Drupal\quiz\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\quiz\Plugin\QuizQuestionPluginManager;

/**
 * Defines a Quiz Question annotation object.
 *
 * @see QuizQuestionPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class QuizQuestion extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
