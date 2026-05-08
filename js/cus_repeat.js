const colorInput = document.getElementById("segmentationColor");
const colorDisplay = document.getElementById("color-display");

if (colorInput && colorDisplay) {
    colorInput.addEventListener("input", function() {
        const selectedColor = colorInput.value;
        colorDisplay.textContent = selectedColor;
    });
}

function validateNumericInput(inputField, errorMsgId, otherErrorMsgId) {
    const inputValue = inputField.value;
    const numericValue = parseFloat(inputValue);

    if (isNaN(numericValue)) {
        inputField.value = inputValue.replace(/[^0-9.]/g, '');
    }

    const currentErrorMsg = document.getElementById(errorMsgId);
    const otherErrorMsg = document.getElementById(otherErrorMsgId);

    if (currentErrorMsg && otherErrorMsg) {
        if (isNaN(parseFloat(document.getElementById("orderFrequencyFrom").value)) && isNaN(parseFloat(document.getElementById("orderFrequencyUntil").value))) {
            currentErrorMsg.textContent = "Please enter a number.";
            currentErrorMsg.classList.add("error-message");
            otherErrorMsg.textContent = "";
            otherErrorMsg.classList.remove("error-message");
        } else {
            currentErrorMsg.textContent = "";
            currentErrorMsg.classList.remove("error-message");
        }
    }
}
