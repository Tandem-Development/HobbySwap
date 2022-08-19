
Drupal.DMInboxBuilder = {};

(function ($, Drupal, drupalSettings, window) {

  'use strict';

  function getUnreadThreads(){

    $.ajax({
      url: drupalSettings.getUnreadThreadsCallback,
      success: function(data) {
        const unreadThreads = [];
        for(let i in data[0].unread_threads){
          unreadThreads.push([i, data[0].unread_threads[i]]);
        }
        for (let i = 0; i < unreadThreads.length; i++) {
          const thread = document.querySelector(`[data-thread-id="${unreadThreads[i][0]}"]`);
          if(!thread){$('.hs-dm--inbox').html(data[0].rendered_threads); break}
          const message = document.querySelector(`[data-thread-id="${unreadThreads[i][0]}"] .message`).innerHTML.trim();
          const newMessage = unreadThreads[i][1].trim();
          if(message !== newMessage){$('.hs-dm--inbox').html(data[0].rendered_threads);break}
        }
      }
    });
  }

  setInterval(getUnreadThreads, 20000);

}(jQuery, Drupal, drupalSettings, window));
