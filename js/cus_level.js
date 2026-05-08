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
        if (isNaN(parseFloat(document.getElementById("purchaseAmountFrom").value)) && isNaN(parseFloat(document.getElementById("purchaseAmountUntil").value))) {
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

$(document).ready(function() {
    $("#currency").on("input", function() {
        $("#currency_hidden").val("");
    });

    if (!$("#currency").attr("disabled")) {
        $("#currency").keyup(function() {
            var param = {
                search: $(this).val(),
                searchType: "unit",
                elementID: $(this).attr("id"),
                hiddenElementID: $(this).attr("id") + "_hidden",
                dbTable: "<?= CUR_UNIT ?>",
            };
            searchInput(param, "<?= $SITEURL ?>");
        });
    }
});
