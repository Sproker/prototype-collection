<?php

namespace Drupal\quiz\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the quiz entity type.
 */
class QuizViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   *
   * @see datetime_type_field_views_data_helper()
   *   Unfortunately we can't use this helper for base fields.
   *
   * @todo Cleanup once https://www.drupal.org/node/2489476 lands.
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    foreach (['quiz', 'quiz_revision'] as $table) {
      foreach (['quiz_date__value', 'quiz_date__end_value'] as $column) {
        $data[$table][$column]['filter']['id'] = 'datetime';
        $data[$table][$column]['filter']['field_name'] = $data[$table][$column]['entity field'];
        $data[$table][$column]['argument']['id'] = 'datetime';
        $data[$table][$column]['argument']['field_name'] = $data[$table][$column]['entity field'];
        $data[$table][$column]['sort']['id'] = 'datetime';
        $data[$table][$column]['sort']['field_name'] = $data[$table][$column]['entity field'];
      }
    }

    return $data;
  }

}
