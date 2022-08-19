// ! -- Countdown -- !
// ! -- Navbar Slideout -- !
// ! -- Subscription Accordion -- !
// ! -- Tabbed Content Switcher -- !


// ! -- Countdown -- !
let countdown = function() {
  document.querySelectorAll('.countdown').forEach((element) => {
    const dateData = element.getAttribute('data-date').split('/');
    const date = new Date(dateData[2], dateData[0] - 1, dateData[1]).getTime() - new Date().getTime();
    if(date >= 0){
      const days = Math.floor(date / (1000 * 60 * 60 * 24));
      const hours = Math.floor((date % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const minutes = Math.floor((date % (1000 * 60 * 60)) / (1000 * 60));
      const seconds = Math.floor((date % (1000 * 60)) / 1000);
      element.innerHTML = `${days} Days ${hours}h:${minutes}m:${seconds}s`;
    }else{
      element.innerHTML = element.getAttribute('data-complete');
    }
  });
}
setInterval(countdown, 1000);


// ! -- TODO: Create paragraph background gradient function


// ! -- Navbar Slideout -- !
const navbarToggle = document.querySelector('#navbar-slide-toggle');
const navbarContent = document.querySelector('.navbar-content');
navbarToggle.addEventListener('click', () => {
  navbarToggle.classList.toggle('active');
  navbarContent.classList.toggle('hidden');
});


// ! -- Subscription Accordion -- !
const accordionFolds = document.querySelectorAll('.subscription-accordion .accordion-fold');
accordionFolds.forEach((fold) => {
  fold.addEventListener('click', (e) => {updateFolds(e.target)});
});
const updateFolds = function(element){
  accordionFolds.forEach((fold) => {
    if(fold === element){
      !fold.classList.contains('active') ? fold.classList.add('active') : null;
    }else{
      fold.classList.remove('active');
    }
  });
}


// ! -- Tabbed Content Switcher -- !
const tabWrappers = document.querySelectorAll('.tabbed-content');

if(tabWrappers !== null){
  tabWrappers.forEach((wrapper) => {
    wrapper.querySelector(`:scope [data-tab-content="${wrapper.getAttribute('data-default')}"]`).style.display = 'block';
    wrapper.querySelector(`:scope [data-tab="${wrapper.getAttribute('data-default')}"]`).classList.add('active');
    wrapper.addEventListener('click', (e) => {
      const tabs = wrapper.querySelectorAll(':scope .tab');
      tabs.forEach((tab) => {
        const tabNumber = tab.getAttribute('data-tab');
        const content = wrapper.querySelector(`:scope [data-tab-content="${tabNumber}"]`);
        if(e.target.classList.contains('tab')){
          tabNumber === e.target.getAttribute('data-tab') ?
            (tab.classList.add('active'), content.style.display = 'block') :
            (tab.classList.remove('active') ,content.style.display = 'none');
        }
      });
    });
  });
}
