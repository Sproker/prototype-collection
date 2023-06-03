<?php

namespace Drupal\quiz\EventSubscriber;

use Drupal;
use Drupal\quiz\Entity\Quiz;
use Drupal\replicate\Events\AfterSaveEvent;
use Drupal\replicate\Events\ReplicateEntityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class QuizEventSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      'replicate__after_save' => ['afterSave'],
    ];
  }

  /**
   * If the current node is a course object, fulfill it for the current user.
   *
   * @param ReplicateEntityEvent $event
   */
  public function afterSave(AfterSaveEvent $event) {
    /* @var $quiz Quiz */
    $quiz = $event->getEntity();
    $old_quiz = Drupal::entityTypeManager()
      ->getStorage('quiz')
      ->loadRevision($quiz->old_vid);
    $quiz->copyFromRevision($old_quiz);
  }

}
