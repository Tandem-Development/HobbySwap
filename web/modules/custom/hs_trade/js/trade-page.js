//! Start of slideshow functions

//Select image parents for slideshow functions
const imageFields = document.querySelectorAll('.item-images, .teaser-image');

imageFields.forEach((field) => {
  let slideIndex = 0;
  let fieldItems = field.querySelectorAll(".image-spacer, .teaser-image-spacer");
  const nextButton = field.querySelector('.next');
  const prevButton = field.querySelector('.prev');
  showSlides(slideIndex, field);

  nextButton.addEventListener('click', () => {
    slideIndex < fieldItems.length - 1 ? slideIndex++ : slideIndex = 0;
    showSlides(slideIndex, field);
  });
  prevButton.addEventListener('click', () => {
    slideIndex <= 0 ? slideIndex = fieldItems.length - 1 : slideIndex--;
    showSlides(slideIndex, field);
  });
});

function showSlides(active, field) {
  let fieldItems = field.querySelectorAll(".image-spacer, .teaser-image-spacer");
  for (i = 0; i < fieldItems.length; i++) {
    i === active ? fieldItems[i].style.display = 'block' : fieldItems[i].style.display = 'none';
  }
}
//!End of slideshow functions

//! Start of residual counter functions
const checkboxes = document.querySelectorAll('input[type="checkbox"]');

function calculateResidual(){
  const resValues = [];
  const reqValues = [];
  document.querySelectorAll('#edit-responder-item-selection--wrapper input[type="checkbox"]:checked')
    .forEach((el) => {resValues.push(parseInt(el.parentElement.querySelector(':scope .item-value h3').innerHTML))});
  document.querySelectorAll('#edit-requester-item-selection--wrapper input[type="checkbox"]:checked')
    .forEach((el) => {reqValues.push(parseInt(el.parentElement.querySelector(':scope .item-value h3').innerHTML))});
  return reqValues.reduce((prev, curr) => prev + curr, 0) - resValues.reduce((prev, curr) => prev + curr, 0);
}

function displayResidual(residual){
  const resResidual = document.querySelector('.responder-residual');
  const reqResidual = document.querySelector('.requester-residual');
  if(!document.getElementById('edit-enforce-residual').checked || residual === 0) {
    resResidual.style.display = 'none';
    reqResidual.style.display = 'none';
    return;
  }
  if(residual > 0){
    resResidual.style.display = 'flex';
    reqResidual.style.display = 'none';
    resResidual.querySelector(':scope h3').innerHTML = `+${residual}`;
  }else if(residual < 0){
    reqResidual.style.display = 'flex';
    resResidual.style.display = 'none';
    reqResidual.querySelector(':scope h3').innerHTML = `+${Math.abs(residual)}`;
  }
}

displayResidual(calculateResidual());
checkboxes.forEach((box) => {
  box.addEventListener(('click'), () => {
    displayResidual(calculateResidual());
  });
});
//! End of residual counter functions
