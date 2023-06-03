(function ($) {
  /**
   * Determines if the dialog is active or not using "aria-expanded" attribute.
   *
   * @param element
   * @returns {boolean}
   */
  function isActive(element) {
    return element.attr('aria-expanded') === 'true';
  }

  /**
   * Extracts and returns content identifier from the material HTML.
   *
   * @returns {string}
   * @throws {Error}
   */
  function getContentId() {
    const elements = $(document).find('.h5p-content');

    if (elements.length !== 1) {
      throw new Error('Unable to find the content identifier!');
    }

    return elements.data('content-id');
  }

  /**
   * Extracts and returns content data from H5PIntegration.
   *
   * @param contentId {string}
   * @returns {object}
   * @throws {Error}
   */
  function getContentData(contentId) {
    const cid = 'cid-' + contentId;

    if (!H5PIntegration.contents.hasOwnProperty(cid)) {
      throw new Error('Unable to locate the content data for content ' + contentId + '!');
    }

    return H5PIntegration.contents[cid];
  }

  /**
   * Create and trigger an xAPI "opened-tip" event.
   *
   * @param verb {string}
   * @param text {string}
   * @param duration {number} [duration=0]
   */
  function sendTipEvent(verb, text, duration = 0) {
    let contentId, contentData;

    try {
      contentId = getContentId();
      contentData = getContentData(contentId);
    } catch (e) {
      console.error(e);
    }

    const extensions = {
      'http://h5p.org/x-api/h5p-local-content-id': contentId,
      'https://h5p.ee/xapi/extensions/tip-text': text
    };

    if (duration) {
      extensions['https://h5p.ee/xapi/extensions/duration'] = 'PT' + duration +'S';
    }

    eventDispatcher.triggerXAPI({
      id: 'https://h5p.ee/xapi/verbs/' + verb,
      display: {
        'en-US': verb.replace('-', ' ')
      }
    }, {
      object: {
        id: contentData.url,
        objectType: 'Activity',
        definition: {
          extensions: extensions,
          name: {
            'en-US': contentData.metadata.title
          }
        }
      },
      context: {
        contextActivities: {
          category: [
            {
              id: 'http://h5p.org/libraries/' + contentData.library.replace(' ', '-'),
              objectType: 'Activity'
            }
          ]
        }
      }
    });
  }

  // A local EventDispatcher instance is required because using H5P.externalDispatcher directly results in two events
  const eventDispatcher = new H5P.EventDispatcher();

  H5P.JoubelUI.createTipOriginal = H5P.JoubelUI.createTip;
  H5P.JoubelUI.createTip = function (text, params) {
    const element = H5P.JoubelUI.createTipOriginal(text, params);
    let openedDate;
    // MutationObserver is required because closing event can not be captured if the action does not happen to the tip activation element itself
    const observer = new MutationObserver(function (mutations, observer) {
      mutations.forEach(function(mutation) {
        if (mutation.removedNodes.length > 0) {
          mutation.removedNodes.forEach(function (node) {
            // TODO Consider comparing the textual content before sending an event
            if (node.className === 'joubel-speech-bubble') {
              const duration = Math.round((new Date().getTime() - openedDate.getTime()) / 1000);

              observer.disconnect();
              openedDate = null;
              sendTipEvent('closed-tip', text, duration);
            }
          });
        }
      });
    });

    /**
     * Handle event action on tip element.
     *
     * @param text
     * @param openedDate
     */
    function handleTipAction() {
      if (isActive(element)) {
        openedDate = new Date();
        // Timeout is required when another tip is activated not to send the closing event for both closed tip and the opened one
        setTimeout(function () {
          observer.observe(document.querySelector('.joubel-speech-bubble').parentNode, {
            childList: true
          });
        }, 500);
        sendTipEvent('opened-tip', text, 0);
      }
    }

    // Need to make sure that element is an instance of jQuery as a general object is returned in case of an empty tip text
    if (element && element instanceof $) {
      element.on('click', function () {
        handleTipAction();
      });
      element.on('keydown', function (event) {
        if (event.which === 32 || event.which === 13) { // Space & enter key
          handleTipAction();
        }
      });
    }

    return element;
  };

  // H5P.MultiChoice specific code
  const observer = new MutationObserver(function (mutations, observer) {
    mutations.forEach(function(mutation) {
      if (mutation.addedNodes.length > 0) {
        mutation.addedNodes.forEach(function (node) {
          if (node.className === 'h5p-feedback-dialog h5p-has-tip') {
            node.setAttribute('data-opened-at', (new Date()).getTime());
            sendTipEvent('opened-tip', node.textContent, 0);
          }
        });
      }
      if (mutation.removedNodes.length > 0) {
        mutation.removedNodes.forEach(function (node) {
          if (node.className === 'h5p-feedback-dialog h5p-has-tip') {
            const duration = Math.round((new Date().getTime() - parseInt(node.getAttribute('data-opened-at'), 10)) / 1000);
            sendTipEvent('closed-tip', node.textContent, duration);
          }
        });
      }
    });
  });

  observer.observe(document, {
    childList: true,
    subtree: true
  });
})(H5P.jQuery);
