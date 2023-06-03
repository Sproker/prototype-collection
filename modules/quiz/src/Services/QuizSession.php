<?php

namespace Drupal\quiz\Services;

use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Default implementation of the quiz session.
 */
class QuizSession implements QuizSessionInterface {

  /**
   * The session.
   *
   * @var SessionInterface
   */
  protected $session;

  /**
   * Constructs a new QuizSession object.
   *
   * @param SessionInterface $session
   *   The session.
   */
  public function __construct(SessionInterface $session) {
    $this->session = $session;
  }

  /**
   * {@inheritdoc}
   */
  public function isTakingQuiz(Quiz $quiz = NULL) {
    return (bool) $this->getResult($quiz);
  }

  /**
   * {@inheritdoc}
   */
  public function startQuiz(QuizResult $quiz_result) {
    $current_quizzes = $this->getCurrentQuizzes();
    $current_quizzes[$quiz_result->getQuiz()->id()][self::RESULT_ID] = $quiz_result->id();
    $current_quizzes[$quiz_result->getQuiz()->id()][self::CURRENT_QUESTION] = 1;
    $this->setCurrentQuizzes($current_quizzes);
  }

  /**
   * {@inheritdoc}
   */
  public function removeQuiz(Quiz $quiz) {
    $current_quizzes = $this->getCurrentQuizzes();
    unset($current_quizzes[$quiz->id()]);
    $this->setCurrentQuizzes($current_quizzes);
  }

  /**
   * {@inheritdoc}
   */
  public function getResult(Quiz $quiz = NULL) {
    $current_quizzes = $this->getCurrentQuizzes();
    if ($quiz && isset($current_quizzes[$quiz->id()]) && !empty($current_quizzes[$quiz->id()][self::RESULT_ID])) {
      $result_id = $current_quizzes[$quiz->id()][self::RESULT_ID];
      return QuizResult::load($result_id);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTemporaryResult() {
    $current_quizzes = $this->getCurrentQuizzes();
    if (!empty($current_quizzes[self::TEMP_ID])) {
      $result_id = $current_quizzes[self::TEMP_ID];
      return QuizResult::load($result_id);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setResult(QuizResult $quiz_result) {
    $current_quizzes = $this->getCurrentQuizzes();
    $current_quizzes[$quiz_result->getQuiz()->id()][self::RESULT_ID] = $quiz_result->id();
    $this->setCurrentQuizzes($current_quizzes);
  }

  /**
   * {@inheritdoc}
   */
  public function setTemporaryResult(QuizResult $quiz_result) {
    $current_quizzes = $this->getCurrentQuizzes();
    $current_quizzes[self::TEMP_ID] = $quiz_result->id();
    $this->setCurrentQuizzes($current_quizzes);
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentQuestion(Quiz $quiz) {
    $current_quizzes = $this->getCurrentQuizzes();
    if (isset($current_quizzes[$quiz->id()])) {
      return !empty($current_quizzes[$quiz->id()][self::CURRENT_QUESTION]) ? $current_quizzes[$quiz->id()][self::CURRENT_QUESTION] : NULL;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrentQuestion(Quiz $quiz, int $current_question) {
    $current_quizzes = $this->getCurrentQuizzes();
    if (isset($current_quizzes[$quiz->id()])) {
      $current_quizzes[$quiz->id()][self::CURRENT_QUESTION] = $current_question;
      $this->setCurrentQuizzes($current_quizzes);
    }
  }

  /**
   * Gets the current quizzes the user is taking
   *
   * @return array
   *   The quizzes
   */
  protected function getCurrentQuizzes() {
    $key = $this->getSessionKey();
    return $this->session->get($key, []);
  }

  /**
   * Gets the current quizzes the user is taking
   *
   * @return array
   *   The quizzes
   */
  protected function setCurrentQuizzes(array $current_quizzes) {
    $key = $this->getSessionKey();
    if (sizeOf($current_quizzes) == 0) {
      $this->session->remove($key);
    }
    else {
      $this->session->set($key, $current_quizzes);
    }
  }

  /**
   * Gets the session key for the quiz session type.
   *
   * @return string
   *   The session key.
   */
  protected function getSessionKey() {
    return 'quiz';
  }

}
