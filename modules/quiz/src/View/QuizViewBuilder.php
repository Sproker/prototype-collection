<?php

namespace Drupal\quiz\View;

use Drupal;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Util\QuizUtil;

class QuizViewBuilder extends EntityViewBuilder {

  public function buildComponents(array &$build, array $entities, array $displays, $view_mode) {
    parent::buildComponents($build, $entities, $displays, $view_mode);

    /** @var \Drupal\quiz\Entity\Quiz $entity */
    foreach ($entities as $id => $entity) {
      $bundle = $entity->bundle();
      $display = $displays[$bundle];

      if ($display->getComponent('stats')) {
        $build[$id]['stats'] = $this->buildStatsComponent($entity);
      }

      if ($display->getComponent('take')) {
        $build[$id]['take'] = $this->buildTakeComponent($entity);
      }
    }
  }

  protected function buildStatsComponent(Quiz $quiz): array {
    $stats = [
      [
        ['header' => TRUE, 'width' => '25%', 'data' => $this->t('Questions')],
        $quiz->getNumberOfQuestions(),
      ],
    ];

    if ($quiz->get('show_attempt_stats')->value) {
      $takes = $quiz->get('takes')->value == 0 ? $this->t('Unlimited') : $quiz->get('takes')->value;
      $stats[] = [
        ['header' => TRUE, 'data' => $this->t('Attempts allowed')],
        $takes,
      ];
    }

    if ($quiz->get('quiz_date')->isEmpty()) {
      $stats[] = [
        ['header' => TRUE, 'data' => $this->t('Available')],
        $this->t('Always'),
      ];
    }
    else {
      $stats[] = [
        ['header' => TRUE, 'data' => $this->t('Opens')],
        $quiz->get('quiz_date')->value,
      ];
      $stats[] = [
        ['header' => TRUE, 'data' => $this->t('Closes')],
        $quiz->get('quiz_date')->end_value,
      ];
    }

    if (!$quiz->get('pass_rate')->isEmpty()) {
      $stats[] = [
        ['header' => TRUE, 'data' => $this->t('Pass rate')],
        $quiz->get('pass_rate')->value . ' %',
      ];
    }

    if (!$quiz->get('time_limit')->isEmpty()) {
      $stats[] = [
        ['header' => TRUE, 'data' => $this->t('Time limit')],
        _quiz_format_duration($quiz->get('time_limit')->value),
      ];
    }

    $stats[] = [
      ['header' => TRUE, 'data' => $this->t('Backwards navigation')],
      $quiz->get('backwards_navigation') ? $this->t('Allowed') : $this->t('Forbidden'),
    ];

    return [
      '#id' => 'quiz-view-table',
      '#theme' => 'table__quiz_stats',
      '#rows' => $stats,
    ];
  }

  protected function buildTakeComponent(Quiz $quiz): array {
    $build = [];

    $access = $quiz->access('take', NULL, TRUE);
    // Check the permission before displaying start button.
    if (!$access->isForbidden()) {
      if (is_a($access, AccessResultReasonInterface::class)) {
        // There's a friendly success message available. Only display if we are
        // viewing the quiz.
        // @todo does not work because we cannot pass allowed reason, only
        // forbidden reason. The message is displayed in quiz_quiz_access().
        if (\Drupal::routeMatch() == 'entity.quiz.canonical') {
          Drupal::messenger()->addMessage($access->getReason());
        }
      }

      $build['link'] = $quiz
        ->toLink($this->t('Start @quiz', ['@quiz' => QuizUtil::getQuizName()]), 'take', [
          'attributes' => [
            'class' => [
              'quiz-start-link',
              'button',
            ],
          ],
        ])
        ->toRenderable();
    }
    // Only display a message when there is a reason available.
    elseif ($access instanceof AccessResultReasonInterface && $access->getReason()) {
      $build['message'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'quiz-not-available',
          ],
        ],
        '#markup' => $access->getReason(),
      ];
    }

    CacheableMetadata::createFromObject($access)
      ->setCacheMaxAge(0)
      ->applyTo($build);

    return $build;
  }

}
