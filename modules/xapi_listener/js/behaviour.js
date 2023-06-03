  (function ($, Drupal) {
  Drupal.behaviors.xAPIListener = {
    attach: function (context, settings) {

      if (context !== window.document) return;

      if (window.H5P && window.H5P.externalDispatcher) {
        let moduleSettings = settings.xAPIListener;
        let statements = [];
        let quizId;
        let questionId;
        const url = new URL(window.location.href);

        if (url.pathname.split('/')[4] === 'take') {
          quizId = parseInt(url.pathname.split('/')[3]);
          const getQuestionId = $('[id^="edit-question-"]').attr('id');
          questionId = parseInt(getQuestionId.match(/\d+/)[0]);
        }

        H5P.externalDispatcher.on('xAPI', function (event) {
          if ((event.getVerb() === 'completed' || event.getVerb() === 'answered')
            && !event.getVerifiedStatementValue(['object', 'definition', 'extensions', 'http://h5p.org/x-api/h5p-subContentId'])) {
            processStatement(event.getScore(), event.getMaxScore(), event.getVerifiedStatementValue(['result', 'duration']));
          }
        });

        function processStatement(score, max, duration) {
          statements.push({
            qid: quizId,
            qqid: questionId,
            score: score,
            max: max,
            duration: duration
          });
        }

        $('#edit-navigation-submit').on('click', function (event) {
          if (statements.length > 0) {
            event.preventDefault();
            const finalStatement = statements[statements.length - 1];
            $.ajax({
              url: moduleSettings.endpointUrl,
              type: 'POST',
              contentType: 'application/json',
              data: JSON.stringify(finalStatement),
              success: function () {
                $('#quiz-question-answering-form').submit();
              }
            });
          }
        });
      }
    },
  };
})(jQuery, Drupal);
