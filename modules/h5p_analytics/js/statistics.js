(function ($, Drupal) {
  Drupal.behaviors.h5pAnalyticsStatistics = {
    attach: function (context, settings) {
      if (context !== window.document) return;

      if (!(window.bb)) {
        console.warn(Drupal.t('Billboard.js is not present, graphs will not be shown! Please check the README file and make sure that all library dependencies are present.'));
        return;
      }

      var data = settings.h5pAnalyticsStatisticsData;

      var addGraph = function($container, id, type, columns) {
        $('<div>', {
          id: id,
          class: 'graph',
        }).prependTo($container).ready(function() {
          var chart = bb.generate({
            bindto: '#' + id,
            data: {
              columns: columns,
              type: type
            }
          });
        });
      };

      if (data.statements && data.statements.length > 0) {
        addGraph($('.statement-statistics > .graph-container', context), 'statement-statistics-graph', 'pie', data.statements.map(function(single) {
          return [single.code, parseInt(single.total)];
        }));
      }

      if (data.requests && data.requests.length > 0) {
        addGraph($('.request-statistics > .graph-container', context), 'request-statistics-graph', 'bar', data.requests.map(function(single) {
          return [single.code, parseInt(single.total)];
        }));
      }
    }
  };
})(jQuery, Drupal);
