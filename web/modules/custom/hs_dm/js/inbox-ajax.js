(function ($, Drupal, drupalSettings, window) {

  function getUnreadThreads(){

    $.ajax({
      url: drupalSettings.getUnreadThreadsCallback,
      success: function(data) {
        //Push unread thread data into an easy-to-manage array before using it
        let unreadThreads = [];
        for(let i in data[0].unread_threads){
          unreadThreads.push([i, data[0].unread_threads[i]]);
        }
        //Loop through the unread threads and look for changes
        for (let i = 0; i < unreadThreads.length; i++) {
          //Select the thread container that corresponds with the retrieved unread thread
          const thread = document.querySelector(`[data-thread-id="${unreadThreads[i][0]}"]`);
          //If the thread doesn't exist, that means it's an entirely new thread
          if(!thread){
            //Re-render the inbox
            $('.hs-dm--inbox').html(data[0].rendered_threads);
            break;
          }
          //Get the message markup in the thread
          const message = document.querySelector(`[data-thread-id="${unreadThreads[i][0]}"] .message`).innerHTML.trim();
          const newMessage = unreadThreads[i][1].trim();
          //Compare the current markup with the new retrieved message
          if(message !== newMessage){
            //If the markup is different, a new message has been sent in the thread, so re-render the inbox
            $('.hs-dm--inbox').html(data[0].rendered_threads);
            break;
          }
        }
      }
    });
  }

  //Refresh the inbox every 20 seconds
  setInterval(getUnreadThreads, 20000);

}(jQuery, Drupal, drupalSettings, window));
