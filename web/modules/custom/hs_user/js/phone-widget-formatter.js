const phoneFields = document.querySelectorAll('.input--phone');

const formatInput = (input) => {
    let targetValue = input.value.replaceAll('-', '');
    let newNum = '';
    for(let i = 0; i < targetValue.length; i++){
        (i === 2 || i === 5)
            ? newNum += targetValue[i] + '-'
            : newNum += targetValue[i];
    }
    input.value = newNum;
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