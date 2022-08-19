(function ($, Drupal, drupalSettings, window) {

  let renderTransactions = () => {
    let transactionAjax = $.ajax({
      url: drupalSettings.getRenderedTransactions,
      success: function(response){
        console.log(response[2].rendered_transactions);
      }
    });
  }
  // renderTransactions();
  // setInterval(renderTransactions, 15000);

})(jQuery, Drupal, drupalSettings, window);
