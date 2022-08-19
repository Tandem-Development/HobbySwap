(function ($, Drupal, drupalSettings, window) {

  let updateNotifications = () => {
    let transactionAjax = $.ajax({
      url: drupalSettings.getNotifications,
      success: function(response){
        let unreadTransactions = response[0].unread_transactions;
        if(unreadTransactions.length > 0){
          $('#hs--notifications .transaction-notifications .notification-dot').css('display', 'flex');
          $('#hs--notifications .transaction-notifications .notification-dot .notification-number').html(unreadTransactions.length);
        }
        let unreadMessages = response[0].unread_threads;
        if(unreadMessages.length > 0){
          $('#hs--notifications .message-notifications .notification-dot').css('display', 'flex');
          $('#hs--notifications .message-notifications .notification-dot .notification-number').html(unreadMessages.length);
        }
      }
    });
  }

  updateNotifications();
  setInterval(updateNotifications, 20000);

})(jQuery, Drupal, drupalSettings, window);
