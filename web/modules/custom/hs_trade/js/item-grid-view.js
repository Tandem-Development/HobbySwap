const filters = document.querySelector('.view-hs-item-grid .form--inline');
filters.addEventListener('click', (e) => {
  if(e.target.classList.contains('slideout-arrow')){
    filters.classList.toggle('collapsed');
    if(e.target.innerHTML === '❯'){
      e.target.innerHTML = '❮';
    }else{
      e.target.innerHTML = '❯';
    }
  }
});



