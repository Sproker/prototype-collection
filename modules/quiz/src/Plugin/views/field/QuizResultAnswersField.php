<?php

namespace Drupal\quiz\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use function db_query;
use function node_load;

/**
 * A handler to provide a field that pulls all answers from quiz results of a
 * specific quiz.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("quiz_result_answers")
 */
class QuizResultAnswersField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // do nothing -- to override the parent query.
  }

}
