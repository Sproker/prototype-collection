<?php

namespace Drupal\quiz\Entity;

class QuizResultAnswerBroken extends QuizResultAnswer {

  public function getResponse() {
    return NULL;
  }

  public function score(array $values): ?int {
    return NULL;
  }

}
