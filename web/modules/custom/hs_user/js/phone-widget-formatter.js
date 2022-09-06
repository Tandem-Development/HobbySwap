const phoneFields = document.querySelectorAll('.input--phone');

const formatInput = (input) => {
    let targetValue = input.value.replaceAll('-', '');
    let lastSelection = input.selectionStart;
    let newNum = '';
    for(let i = 0; i < targetValue.length; i++){
        (i === 3 || i === 6) ? newNum += '-' + targetValue[i] : newNum += targetValue[i];
    }
    (lastSelection === 4 && targetValue.length === 4) ? lastSelection++ : null;
    (lastSelection === 8 && targetValue.length === 7) ? lastSelection++ : null;
    input.value = newNum;
    input.setSelectionRange(lastSelection, lastSelection);
}

phoneFields.forEach((input) => {
    let parentForm = input.closest('form');
    parentForm.addEventListener('submit', (e) => {
       let oldTargetValue = input.value;
       input.value = oldTargetValue.replaceAll('-', '');
    });
    formatInput(input);
    input.addEventListener('input', (e) => {
       formatInput(input);
    });
});