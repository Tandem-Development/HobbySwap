const imageFields = document.querySelectorAll('.item-images');
document.querySelectorAll('.field__item').forEach(i => {
    if(i.children.length != 0 && i.children[0].localName == 'img') {
        i.classList.add('img');
    }
});

//! Start of slideshow functions
imageFields.forEach((field) => {
  let slideIndex = 0;
  let fieldItems = field.querySelectorAll(".img");
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
  let fieldItems = field.querySelectorAll(".img");
  for (i = 0; i < fieldItems.length; i++) {
    i === active ? fieldItems[i].style.display = 'block' : fieldItems[i].style.display = 'none';
  }
}
