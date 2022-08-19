const imageFields = document.querySelectorAll('.item-image');

imageFields.forEach((field) => {
  let slideIndex = 0;
  let fieldItems = field.querySelectorAll(".item-image ul li");
  const nextButton = field.querySelector('.next');
  const prevButton = field.querySelector('.prev');

  showSlides(slideIndex, field);

  nextButton.addEventListener('click', () => {
    slideIndex < fieldItems.length-1 ? slideIndex++ : slideIndex = 0;
    showSlides(slideIndex, field);
  });
  prevButton.addEventListener('click', () => {
    slideIndex <= 0 ? slideIndex = fieldItems.length-1 : slideIndex--;
    showSlides(slideIndex, field);
  });

});

function showSlides(active, field) {
  let fieldItems = field.querySelectorAll(".item-image ul li");
  for(i = 0; i < fieldItems.length; i++){
    i === active ? fieldItems[i].style.display = 'block' : fieldItems[i].style.display = 'none';
  }
}
