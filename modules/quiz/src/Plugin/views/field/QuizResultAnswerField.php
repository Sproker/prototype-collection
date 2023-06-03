<?php

namespace Drupal\quiz\Plugin\views\field;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\PrerenderList;
use Drupal\views\ViewExecutable;
use function quiz_get_question_types;

/**
 * A handler to provide a field that pulls answers from a single question on a
 * single quiz result.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("quiz_result_answer")
 */
class QuizResultAnswerField extends PrerenderList {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->additional_fields['result_id'] = [
      'table' => 'quiz_result',
      'field' => 'result_id',
    ];
  }

  /**
   * Add this term to the query
   */
  public function query() {
    $this->addAdditionalFields();
    $this->field_alias = $this->aliases['result_id'];
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['qqid'] = [
      '#title' => $this->t('Question ID'),
      '#type' => 'textfield',
      '#default_value' => $this->options['qqid'],
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['qqid'] = [
      'default' => NULL,
    ];

    return $options;
  }

  public function preRender(&$values) {
    $this->items = [];

    $result_ids = [];
    foreach ($values as $value) {
      $result_ids[] = $value->result_id;
    }

    $qqid = $this->options['qqid'];
    $question = QuizQuestion::load($qqid);
    $info = quiz_get_question_types();
    $className = $info[$question->bundle()]['handlers']['response'];

    if ($result_ids) {
      $raids = \Drupal::entityQuery('quiz_result_answer')
        ->condition('question_id', $qqid)
        ->condition('result_id', $result_ids, 'in')
        ->execute();
      if ($raids) {
        $this->items = $className::viewsGetAnswers($raids);
      }
    }
  }

  public function render_item($count, $item) {
    return $item['answer'];
  }

}
