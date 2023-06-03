<?php

namespace Drupal\quiz\Form;

use Drupal;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizQuestionRelationship;
use Drupal\quiz\Util\QuizUtil;
use function _quiz_get_quiz_name;
use function _quiz_update_items;
use function count;
use function quiz_get_question_types;
use function render;

/**
 * Form to manage questions in a quiz.
 */
class QuizQuestionsForm extends FormBase {

  /**
   * Fields for creating new questions are added to the quiz_questions_form.
   *
   * @param $form
   *   FAPI form(array).
   * @param $types
   *   All the question types(array).
   * @param $quiz
   *   The quiz node.
   */
  function _quiz_add_fields_for_creating_questions(&$form, &$types, Quiz $quiz) {
    // Display links to create other questions.
    $form['additional_questions'] = [
      '#type' => 'details',
      '#title' => $this->t('Create new question'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $create_question = FALSE;

    $entity_manager = Drupal::entityTypeManager();
    $access_handler = $entity_manager->getAccessControlHandler('quiz_question');

    foreach ($types as $type => $info) {

      $options = [
        'query' => [
          'qid' => $quiz->id(),
          'vid' => $quiz->getRevisionId(),
        ],
        'attributes' => [
          'class' => 'use-ajax',
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 800]),
        ],
      ];

      $access = $access_handler->createAccess($type);
      if ($access) {
        $create_question = TRUE;
      }
      $url = Url::fromRoute('entity.quiz_question.add_form', ['quiz_question_type' => $type], $options);
      $form['additional_questions'][$type] = [
        '#markup' => '<div class="add-questions">' .
          Link::fromTextAndUrl($info['label'], $url)->toString() . '</div>',
        '#access' => $access,
      ];
    }
    if (!$create_question) {
      $form['additional_questions']['create'] = [
        '#type' => 'markup',
        '#markup' => $this->t('You have not enabled any question type module or no has permission been given to create any question.'),
        // @todo revisit UI text
      ];
    }
  }

  /**
   * Handles "manage questions" tab.
   *
   * Displays form which allows questions to be assigned to the given quiz.
   *
   * This function is not used if the question assignment type "categorized
   * random questions" is chosen.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $types = quiz_get_question_types();
    $quiz = $form_state->getBuildInfo()['args'][0];
    $this->_quiz_add_fields_for_creating_questions($form, $types, $quiz);

    $header = ['Question', 'Type', 'Max score', 'Auto max score'];
    if ($quiz->get('randomization')->getString() == 2) {
      $header[] = 'Required';
    }
    $header = array_merge($header, [
      'Revision',
      'Operations',
      'Weight',
      'Parent',
    ]);

    // Display questions in this quiz.
    $form['question_list'] = [
      '#type' => 'table',
      '#title' => $this->t('Questions in this @quiz', ['@quiz' => QuizUtil::getQuizName()]),
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('There are currently no questions in this @quiz. Assign existing questions by using the question browser below. You can also use the links above to create new questions.', ['@quiz' => QuizUtil::getQuizName()]),
      '#tabledrag' => [
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'qqr-pid',
          'source' => 'qqr-id',
          'hidden' => TRUE,
          'limit' => 1,
        ],
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    // @todo deal with $include_random.
    $all_questions = $quiz->getQuestions();

    uasort($all_questions, ['self', 'sortQuestions']);
    $questions = [];
    foreach ($all_questions as $qqr_id => $question) {
      if (!$question->get('qqr_pid')->getString()) {
        // This is a parent question.
        $questions[$qqr_id] = $question;
        $questions += $this->getSubQuestions($question, $all_questions);
      }
    }

    // We add the questions to the form array.
    $this->_quiz_add_questions_to_form($form, $questions, $quiz, $types);


    // @todo Show the number of questions in the table header.
    $always_count = isset($form['question_list']['titles']) ?
      count($form['question_list']['titles']) : 0;
    //$form['question_list']['#title'] .= ' (' . $always_count . ')';
    // Timestamp is needed to avoid multiple users editing the same quiz at the
    // same time.
    $form['timestamp'] = [
      '#type' => 'hidden',
      '#default_value' => \Drupal::time()->getRequestTime(),
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    // Give the user the option to create a new revision of the quiz.
    $this->_quiz_add_revision_checkbox($form, $quiz);

    return $form;
  }

  /**
   *
   * @return array
   *   of QuizQuestion
   */
  function getSubQuestions($root_question, $all_questions) {
    $append = [];
    foreach ($all_questions as $sub_question) {
      if ($root_question->id() == $sub_question->get('qqr_pid')->getString()) {
        // Question is a leaf of this parent.
        $append[$sub_question->id()] = $sub_question;
      }
    }
    return $append;
  }

  /**
   * Entity type sorter for quiz questions.
   */
  function sortQuestions($a, $b) {
    $aw = $a->get('weight')->getString();
    $bw = $b->get('weight')->getString();
    if ($aw == $bw) {
      return 0;
    }
    return ($aw < $bw) ? -1 : 1;
  }

  public function getFormId(): string {
    return 'quiz_questions_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $question_list = $form_state->getValue('question_list');
    foreach ($question_list as $qqr_id => $row) {
      $qqr = QuizQuestionRelationship::load($qqr_id);
      foreach ($row as $name => $value) {
        if ($name == 'qqr_pid' && empty($value)) {
          $value = NULL;
        }

        $qqr->set($name, $value);
      }
      $qqr->save();
    }

    \Drupal::messenger()->addMessage($this->t('Questions updated successfully.'));
    return;

    // @todo below logic in D8, maybe we get rid of "remove questions"
    //
    // Load the quiz node.
    $quiz = $form_state['build_info']['args'][0];
    // Update the refresh latest quizzes table so that we know what the users
    // latest quizzes are.
    if (\Drupal::config('quiz.settings')->get('auto_revisioning', 1)) {
      $is_new_revision = $quiz->hasAttempts();
    }
    else {
      $is_new_revision = !empty($form_state['values']['new_revision']);
    }

    $num_random = isset($form_state['values']['num_random_questions']) ?
      $form_state['values']['num_random_questions'] : 0;
    $quiz->max_score_for_random = isset($form_state['values']['max_score_for_random']) ?
      $form_state['values']['max_score_for_random'] : 1;

    // Store what questions belong to the quiz.
    $questions = _quiz_update_items($quiz, $weight_map, $max_scores, $auto_update_max_scores, $is_new_revision, $refreshes, $stayers, $qnr_ids_map, $qqr_pids_map, $compulsories);

    // If using random questions and no term ID is specified, make sure we have
    // enough.
    $assigned_random = 0;

    foreach ($questions as $question) {
      if ($question->question_status == QuizQuestion::QUESTION_RANDOM) {
        ++$assigned_random;
      }
    }

    // Adjust number of random questions downward to match number of selected
    // questions.
    if ($num_random > $assigned_random) {
      $num_random = $assigned_random;
      \Drupal::messenger()
        ->addWarning($this->t('The number of random questions for this @quiz have been lowered to %anum to match the number of questions you assigned.', [
          '@quiz' => QuizUtil::getQuizName(),
          '%anum' => $assigned_random,
        ]));
    }

    if ($quiz->type == 'quiz') {
      // Update the quiz node properties.
      // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
      // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
      \Drupal::database()->update('quiz_node_properties')
        ->fields([
          'number_of_random_questions' => $num_random ? $num_random : 0,
          'max_score_for_random' => $quiz->max_score_for_random,
        ])
        ->condition('vid', $quiz->vid)
        ->condition('nid', $quiz->nid)
        ->execute();

      // Get sum of max_score.
      // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
      // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
      $query = \Drupal::database()->select('quiz_node_relationship', 'qnr');
      $query->addExpression('SUM(max_score)', 'sum');
      $query->condition('parent_vid', $quiz->vid);
      $query->condition('question_status', QuizQuestion::QUESTION_ALWAYS);
      $score = $query->execute()->fetchAssoc();

      // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
      // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
      \Drupal::database()->update('quiz_node_properties')
        ->expression('max_score', 'max_score_for_random * number_of_random_questions + :sum', [':sum' => (int) $score['sum']])
        ->condition('vid', $quiz->vid)
        ->execute();
    }
  }

  /**
   * Adds checkbox for creating new revision. Checks it by default if answers
   * exists.
   *
   * @param $form
   *   FAPI form(array).
   *
   * @param $quiz
   *   Quiz node(object).
   */
  function _quiz_add_revision_checkbox(&$form, $quiz) {
    $config = $this->config('quiz.settings');
    if ($quiz->hasAttempts()) {
      $results_url = Url::fromRoute('view.quiz_results.list', ['quiz' => $quiz->id()])
        ->toString();
      $quiz_url = Url::fromRoute('entity.quiz.edit_form', ['quiz' => $quiz->id()], [
        'query' => \Drupal::destination()
          ->getAsArray(),
      ])->toString();
      $form['revision_help'] = [
        '#markup' => $this->t('This quiz has been answered. To make changes to the quiz you must either <a href="@results_url">delete all results</a> or <a href="@quiz_url">create a new revision</a>.', [
          '@results_url' => $results_url,
          '@quiz_url' => $quiz_url,
        ]),
      ];
      $form['actions']['submit']['#access'] = FALSE;
    }
  }

  /**
   * Adds the questions in the $questions array to the form.
   *
   * @param array $form
   *   FAPI form(array).
   * @param Drupal\Quiz\Entity\QuizQuestionRelationship[] $questions
   *   The questions to be added to the question list(array).
   * @param Quiz $quiz
   *   The quiz.
   * @param $question_types
   *   array of all available question types.
   *
   * @todo Not bringing in revision data yet.
   *
   */
  function _quiz_add_questions_to_form(&$form, &$questions, &$quiz, &$question_types) {
    foreach ($questions as $id => $question_relationship) {
      $question_vid = $question_relationship->get('question_vid')->getString();

      /* @var $quiz Quiz */
      $quiz_question = Drupal::entityTypeManager()
        ->getStorage('quiz_question')
        ->loadRevision($question_vid);

      if (!$quiz_question) {
        // Question no longer exists.
        // @see https://www.drupal.org/project/quiz/issues/2923829
        continue;
      }

      $table = &$form['question_list'];

      $view_url = Url::fromRoute('entity.quiz_question.canonical', ['quiz_question' => $quiz_question->id()], [
        'attributes' => [
          'class' => 'use-ajax',
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 800]),
        ],
        'query' => \Drupal::destination()->getAsArray(),
      ]);

      $edit_url = Url::fromRoute('entity.quiz_question.edit_form', ['quiz_question' => $quiz_question->id()], [
        'attributes' => [
          'class' => 'use-ajax',
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 800]),
        ],
        'query' => \Drupal::destination()->getAsArray(),
      ]);

      $remove_url = Url::fromRoute('entity.quiz_question_relationship.delete_form', ['quiz_question_relationship' => $question_relationship->id()], [
        'attributes' => [
          'class' => 'use-ajax',
          'data-dialog-type' => 'modal',
        ],
        'query' => \Drupal::destination()->getAsArray(),
      ]);

      if ($quiz_question->access('view')) {
        $question_titles = [
          '#markup' => Link::fromTextAndUrl($quiz_question->get('title')
            ->getString(), $view_url)->toString(),
        ];
      }
      else {
        $question_titles = [
          '#plain_text' => $quiz_question->get('title')
            ->getString(),
        ];
      }

      $table[$id]['#attributes']['class'][] = 'draggable';

      if ($quiz_question->bundle() != 'page') {
        $table[$id]['#attributes']['class'][] = 'tabledrag-leaf';
      }


      $table[$id]['title'] = $question_titles;

      if ($question_relationship->get('qqr_pid')->getString()) {
        $indentation = [
          '#theme' => 'indentation',
          '#size' => 1,
        ];
        $table[$id]['title']['#prefix'] = render($indentation);
      }


      $table[$id]['type'] = [
        '#markup' => $quiz_question->bundle(),
      ];

      // Toggle the max score input based on the auto max score checkbox
      // Hide for ungraded questions (directions, pages, etc.)
      $table[$id]['max_score'] = [
        '#type' => $quiz_question->isGraded() ? 'textfield' : 'hidden',
        '#size' => 2,
        '#disabled' => (bool) $question_relationship->get('auto_update_max_score')
          ->getString(),
        '#default_value' => $question_relationship->get('max_score')
          ->getString(),
        '#states' => [
          'disabled' => [
            "#edit-question-list-$id-auto-update-max-score" => ['checked' => TRUE],
          ],
        ],
      ];

      $table[$id]['auto_update_max_score'] = [
        '#type' => $quiz_question->isGraded() ? 'checkbox' : 'hidden',
        '#default_value' => $question_relationship->get('auto_update_max_score')
          ->getString() ?
          $question_relationship->get('auto_update_max_score')->getString() : 0,
      ];


      // Add checkboxes to mark compulsory questions for randomized quizzes.
      if ($quiz->get('randomization')->getString() == 2) {
        $table[$id]['question_status'] = [
          '#type' => 'checkbox',
          '#default_value' => $question_relationship->get('question_status')
            ->getString(),
        ];
      }


      $entity_manager = Drupal::entityTypeManager();
      $access_handler = $entity_manager->getAccessControlHandler('quiz_question');

      // Add a checkbox to update to the latest revision of the question.
      $latest_quiz_question = Drupal::entityTypeManager()
        ->getStorage('quiz_question')
        ->load($quiz_question->id());
      if ($question_relationship->get('question_vid')->value ==
        $latest_quiz_question->getRevisionId()) {
        $update_cell = [
          '#markup' => $this->t('<em>Up to date</em>'),
        ];
      }
      else {
        $revisions_url = Url::fromRoute('entity.quiz_question.edit_form', ['quiz_question' => $quiz_question->id()]);
        $update_cell = [
          '#type' => 'checkbox',
          '#return_value' => $latest_quiz_question->getRevisionId(),
          '#title' => $this->t('Update to latest'),
        ];
      }
      $table[$id]['question_vid'] = $update_cell;

      $update_question = $access_handler->access($quiz_question, 'update');

      $table[$id]['operations'] = [
        '#type' => 'operations',
        '#links' => [
          [
            'title' => $this->t('Edit'),
            'url' => $edit_url,
          ],
          [
            'title' => $this->t('Remove'),
            'url' => $remove_url,
          ],
        ],
      ];

      $table[$id]['#weight'] = (int) $question_relationship->get('weight')
        ->getString();
      $table[$id]['weight'] = [
        '#title_display' => 'invisible',
        '#title' => $this
          ->t('Weight for ID @id', [
            '@id' => $id,
          ]),
        '#type' => 'number',
        '#default_value' => (int) $question_relationship->get('weight')
          ->getString(),
        '#attributes' => [
          'class' => [
            'table-sort-weight',
          ],
        ],
      ];

      $table[$id]['parent']['qqr_id'] = [
        '#title' => $this->t('Relationship ID'),
        '#type' => 'hidden',
        '#default_value' => $question_relationship->get('qqr_id')->getString(),
        '#attributes' => [
          'class' => [
            'qqr-id',
          ],
        ],
        '#parents' => [
          'question_list',
          $id,
          'qqr_id',
        ],
      ];

      $table[$id]['parent']['qqr_pid'] = [
        '#title' => $this->t('Parent ID'),
        '#title_display' => 'invisible',
        '#type' => 'number',
        '#size' => 3,
        '#min' => 0,
        '#default_value' => $question_relationship->get('qqr_pid')->getString(),
        '#attributes' => [
          'class' => [
            'qqr-pid',
          ],
        ],
        '#parents' => [
          'question_list',
          $id,
          'qqr_pid',
        ],
      ];
    }
  }

}
