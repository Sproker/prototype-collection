<?php

namespace Drupal\quiz\Entity;

class QuizQuestionBroken extends QuizQuestion {

  public function getMaximumScore(): int {
    return 0;
  }

}
