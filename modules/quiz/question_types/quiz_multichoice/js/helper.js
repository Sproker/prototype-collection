(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.quiz_multichoice_refreshScores = {
    attach: function (context, settings) {
      $('.quiz-multichoice-correct-checkbox').once().change(function (e) {
        correct = $(this).is(':checked');
        delta = $(this).attr('data-multichoice-delta');

        multiple = $('#edit-choice-multi-value').is(':checked');
        scoring = drupalSettings.quiz_multichoice.scoring;

        chosen = $('#edit-alternatives-' + delta + '-subform-multichoice-score-chosen-0-value');
        not_chosen = $('#edit-alternatives-' + delta + '-subform-multichoice-score-not-chosen-0-value');

        if (correct) {
          chosen.val('1');
          not_chosen.val('0');
        }
        else {
          if (scoring == 0) {
            not_chosen.val('0');
            if (multiple) {
              chosen.val('-1');
            }
            else {
              chosen.val('0');
            }
          }
          else if (scoring == 1) {
            chosen.val('0');
            if (multiple) {
              not_chosen.val('1');
            }
            else {
              not_chosen.val('0');
            }
          }
        }
      });
    }
  };

  /**
   * Updates correct checkboxes according to changes of the score values for an alternative
   *
   * @param textfield
   *  The textfield(score) that is being updated
   */
  Drupal.behaviors.quiz_multichoice_refreshCorrect = function (textfield) {
    var prefix = '#' + Multichoice.getCorrectIdPrefix(textfield.id);
    var chosenScore;
    var notChosenScore;

    // Fetch the score if chosen and score if not chosen values for the active alternative
    if (Multichoice.isChosen(textfield.id)) {
      chosenScore = new Number(textfield.value);
      notChosenScore = new Number($(prefix + 'score-if-not-chosen').val());
    }
    else {
      chosenScore = new Number($(prefix + 'score-if-chosen').val());
      notChosenScore = new Number(textfield.value);
    }

    // Set the checked status for the checkbox in the active alternative
    if (notChosenScore < chosenScore) {
      $(prefix + 'correct').attr('checked', true);
    }
    else {
      $(prefix + 'correct').attr('checked', false);
    }
  }


  /**
   * Select row when label is clicked.
   *
   * @todo not working in D8.
   */
  Drupal.behaviors.quiz_multichoice_multichoiceAlternativeBehavior = {
    attach: function (context, settings) {
      $('.multichoice-row')
        .once()
        .filter(':has(:checkbox:checked)')
        .addClass('selected')
        .end()
        .click(function (event) {
          if (event.target.type !== 'checkbox' && !$(':radio').attr('disabled')) {
            $(this).toggleClass('selected');
            if (typeof $.fn.prop === 'function') {
              $(':checkbox', this).prop('checked', function (i, val) {
                return !val;
              });
              $(':radio', this).prop('checked', 'checked');
            }
            else {
              $(':checkbox', this).attr('checked', function () {
                return !this.checked;
              });
              $(':radio', this).attr('checked', true);
            }
            if ($(':radio', this).html() != null) {
              $(this).parent().find('.multichoice-row').removeClass('selected');
              $(this).addClass('selected');
            }
          }
        });
    }
  };

})(jQuery, Drupal, drupalSettings);
