(function ($, Drupal, drupalSettings, window) {

  let incomingTab = document.querySelector('.hs--incoming--label');
  let outgoingTab = document.querySelector('.hs--outgoing--label');
  let incomingTransactions = document.querySelector('.hs--incoming-container');
  let outgoingTransactions = document.querySelector('.hs--outgoing-container');

  function renderTransactions(){
    $.ajax({
      url: drupalSettings.getRenderedTransactions,
      success: function(response){
        //Only replace the markup if a transaction has been altered
        if($('.hs--incoming-container').html() === response[2].incoming_transactions){
          return;
        }else{
          //Update transactions
          $('.hs--incoming-container').html(response[2].incoming_transactions);
          $('.hs--outgoing-container').html(response[2].outgoing_transactions);
          //Update incoming and outgoing transaction count
          let newIncomingCount = document.querySelectorAll('.hs--incoming-container .hs--transaction').length;
          let newOutgoingCount = document.querySelectorAll('.hs--outgoing-container .hs--transaction').length;
          $('.hs--incoming--label span').html(newIncomingCount);
          $('.hs--outgoing--label span').html(newOutgoingCount);
        }
      }
    });
  }
  setInterval(renderTransactions, 20000);

  incomingTab.addEventListener('click', () => {
    if(!incomingTab.classList.contains('active')){
      incomingTab.classList.add('active');
      incomingTransactions.style.display = 'block';
      outgoingTab.classList.remove('active');
      outgoingTransactions.style.display = 'none';
    }
  });
  outgoingTab.addEventListener('click', () => {
    if(!outgoingTab.classList.contains('active')){
      outgoingTab.classList.add('active');
      outgoingTransactions.style.display = 'block';
      incomingTab.classList.remove('active');
      incomingTransactions.style.display = 'none';
    }
  });

})(jQuery, Drupal, drupalSettings, window);

