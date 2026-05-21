var currentJtAttachmentPreviewUrl = null;

function clearJtAttachmentPreviewObjectUrl() {
  if (currentJtAttachmentPreviewUrl) {
    URL.revokeObjectURL(currentJtAttachmentPreviewUrl);
    currentJtAttachmentPreviewUrl = null;
  }
}

function renderJtAttachmentPreview(file) {
  var previewWrap = document.getElementById("jt_attach_preview_wrap");
  if (!previewWrap) {
    return;
  }

  if (!file) {
    clearJtAttachmentPreviewObjectUrl();
    previewWrap.innerHTML = "";
    previewWrap.style.display = "none";
    return;
  }

  clearJtAttachmentPreviewObjectUrl();
  var fileUrl = URL.createObjectURL(file);
  currentJtAttachmentPreviewUrl = fileUrl;
  var fileName = (file.name || "").toLowerCase();
  previewWrap.style.display = "block";

  if (file.type && file.type.indexOf("image/") === 0) {
    previewWrap.innerHTML =
      '<img id="jt_attach_preview" src="' +
      fileUrl +
      '" class="img-thumbnail" alt="Attachment Preview">';
  } else if (file.type === "application/pdf" || fileName.endsWith(".pdf")) {
    previewWrap.innerHTML =
      '<iframe id="jt_attach_preview_pdf" src="' +
      fileUrl +
      '" title="Attachment Preview"></iframe>';
  } else {
    clearJtAttachmentPreviewObjectUrl();
    previewWrap.innerHTML = "";
    previewWrap.style.display = "none";
  }
}

$("#jt_attach").on("change", function () {
  renderJtAttachmentPreview(this.files && this.files[0] ? this.files[0] : null);
});

window.addEventListener("beforeunload", clearJtAttachmentPreviewObjectUrl);

function toNum(value) {
  var n = parseFloat(value);
  return isNaN(n) ? 0 : n;
}

function canEditRows() {
  return !(typeof action !== "undefined" && action === "");
}

function getCurrencyOptionSet(datalistId) {
  var set = {};
  var list = document.getElementById(datalistId);
  if (!list) {
    return set;
  }

  var options = list.querySelectorAll("option");
  options.forEach(function (opt) {
    var val = (opt.value || "").trim().toUpperCase();
    if (val !== "") {
      set[val] = true;
    }
  });

  return set;
}

function updateDeliveryRowNumbers() {
  $("#deliveryItemsTable tbody tr").each(function (idx) {
    $(this)
      .find(".delivery-row-no")
      .text(idx + 1);
  });
}

function updateGstRowNumbers() {
  $("#gstAnalysisTable tbody tr").each(function (idx) {
    $(this)
      .find(".gst-row-no")
      .text(idx + 1);
  });
}

function updateDeliveryActionButtons() {
  if (!canEditRows()) {
    return;
  }

  $("#deliveryItemsTable tbody tr").each(function (idx) {
    var actionCell = $(this).find(".delivery-action-cell");
    if (actionCell.length === 0) {
      return;
    }

    if (idx === 0) {
      actionCell.html(
        '<button class="mt-1" id="action_menu_btn" type="button" onclick="addDeliveryRow()"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>',
      );
    } else {
      actionCell.html(
        '<button class="mt-1 removeDeliveryRowBtn" id="action_menu_btn" type="button"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>',
      );
    }
  });
}

function updateGstActionButtons() {
  if (!canEditRows()) {
    return;
  }

  $("#gstAnalysisTable tbody tr").each(function (idx) {
    var actionCell = $(this).find(".gst-action-cell");
    if (actionCell.length === 0) {
      return;
    }

    if (idx === 0) {
      actionCell.html(
        '<button class="mt-1" id="action_menu_btn" type="button" onclick="addGstRow()"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>',
      );
    } else {
      actionCell.html(
        '<button class="mt-1 removeGstRowBtn" id="action_menu_btn" type="button"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>',
      );
    }
  });
}

function recalcTotals() {
  var gstPaidValues = [];
  var totalGst = 0;

  $("#gstAnalysisTable tbody tr").each(function (idx) {
    var rate = toNum($(this).find('input[name="gst_rate[]"]').val());
    var amount = toNum($(this).find('input[name="gst_amount[]"]').val());
    var gstPaid = rate > 0 ? amount * (rate / 100) : 0;
    gstPaidValues[idx] = gstPaid;
    totalGst += gstPaid;
    $(this).find('input[name="gst_paid[]"]').val(gstPaid.toFixed(2));
  });

  var totalAmount = 0;
  $("#deliveryItemsTable tbody tr").each(function (idx) {
    var standardCharge = toNum(
      $(this).find('input[name="standard_charge[]"]').val(),
    );
    var gstPaid = gstPaidValues[idx] || 0;
    var nettCharge = standardCharge + gstPaid;
    totalAmount += nettCharge;
    $(this).find('input[name="nett_charge[]"]').val(nettCharge.toFixed(2));
  });

  $("#total_gst").val(totalGst.toFixed(2));
  $("#total_amount").val(totalAmount.toFixed(2));
}

function addDeliveryRow() {
  if (!canEditRows()) {
    return;
  }

  var rowHtml =
    "<tr>" +
    '<td class="delivery-row-no"></td>' +
    '<td><input class="form-control jt-service-type-input" type="text" name="service_type[]"></td>' +
    '<td><input class="form-control" type="number" name="shipments_count[]"></td>' +
    '<td><input class="form-control" type="number" step="0.01" name="total_weight_kg[]"></td>' +
    '<td><input class="form-control delivery-standard-charge" type="number" step="0.01" name="standard_charge[]"></td>' +
    '<td><input class="form-control" type="number" step="0.01" name="extra_charges[]"></td>' +
    '<td><input class="form-control delivery-nett-charge jt-auto-calc-field" type="number" step="0.01" name="nett_charge[]" readonly></td>' +
    '<td class="delivery-action-cell" style="text-align:center;"></td>' +
    "</tr>";

  $("#deliveryItemsTable tbody").append(rowHtml);
  updateDeliveryRowNumbers();
  updateDeliveryActionButtons();
  recalcTotals();
}

function addGstRow() {
  if (!canEditRows()) {
    return;
  }

  var rowHtml =
    "<tr>" +
    '<td class="gst-row-no"></td>' +
    '<td><input class="form-control" type="text" name="gst_type[]"></td>' +
    '<td><input class="form-control gst-rate" type="number" step="0.01" name="gst_rate[]"></td>' +
    '<td><input class="form-control gst-amount" type="number" step="0.01" name="gst_amount[]"></td>' +
    '<td><input class="form-control gst-paid jt-auto-calc-field" type="number" step="0.01" name="gst_paid[]" readonly></td>' +
    '<td class="gst-action-cell" style="text-align:center;"></td>' +
    "</tr>";

  $("#gstAnalysisTable tbody").append(rowHtml);
  updateGstRowNumbers();
  updateGstActionButtons();
  recalcTotals();
}

$(document).on("click", ".removeDeliveryRowBtn", function () {
  if (!canEditRows()) {
    return;
  }

  var totalRows = $("#deliveryItemsTable tbody tr").length;
  if (totalRows <= 1) {
    return;
  }
  $(this).closest("tr").remove();
  updateDeliveryRowNumbers();
  updateDeliveryActionButtons();
  recalcTotals();
});

$(document).on("click", ".removeGstRowBtn", function () {
  if (!canEditRows()) {
    return;
  }

  var totalRows = $("#gstAnalysisTable tbody tr").length;
  if (totalRows <= 1) {
    return;
  }
  $(this).closest("tr").remove();
  updateGstRowNumbers();
  updateGstActionButtons();
  recalcTotals();
});

$(document).on(
  "input",
  'input[name="gst_rate[]"], input[name="gst_amount[]"], input[name="standard_charge[]"]',
  function () {
    recalcTotals();
  },
);

$("#jt_inv_number").on("input", function () {
  $(".jt-inv-number-err").remove();
});

$("#jt_inv_date").on("input", function () {
  $(".jt-inv-date-err").remove();
});

$("#jt_attach").on("input", function () {
  $(".jt-attach-err").remove();
});

$("#currency").on("input", function () {
  $(".jt-currency-err").remove();
});

$(".submitBtn").on("click", function () {
  $(".error-message").remove();
  var jt_inv_number_chk = 0;
  var jt_inv_date_chk = 0;
  var currency_chk = 0;
  var attach_chk = 0;

  if (
    $("#jt_inv_number").val() === "" ||
    $("#jt_inv_number").val() === null ||
    $("#jt_inv_number").val() === undefined
  ) {
    jt_inv_number_chk = 0;
    $("#jt_inv_number").after(
      '<span class="error-message jt-inv-number-err">Invoice Number is required!</span>',
    );
  } else {
    $(".jt-inv-number-err").remove();
    jt_inv_number_chk = 1;
  }

  if (
    $("#jt_inv_date").val() === "" ||
    $("#jt_inv_date").val() === null ||
    $("#jt_inv_date").val() === undefined
  ) {
    jt_inv_date_chk = 0;
    $("#jt_inv_date").after(
      '<span class="error-message jt-inv-date-err">Invoice Date is required!</span>',
    );
  } else {
    $(".jt-inv-date-err").remove();
    jt_inv_date_chk = 1;
  }

  var fileInput = $("#jt_attach")[0];

  if (
    $("#currency").val() === "" ||
    $("#currency").val() === null ||
    $("#currency").val() === undefined
  ) {
    currency_chk = 0;
    $("#currency").after(
      '<span class="error-message jt-currency-err">Invoice Currency is required!</span>',
    );
  } else {
    $(".jt-currency-err").remove();
    var currencyOptions = getCurrencyOptionSet("currencyOptionsListMain");
    var currencyVal = String($("#currency").val()).trim().toUpperCase();
    if (
      Object.keys(currencyOptions).length > 0 &&
      !currencyOptions[currencyVal]
    ) {
      currency_chk = 0;
      $("#currency").after(
        '<span class="error-message jt-currency-err">Invalid Invoice Currency. Please select a valid currency from the list.</span>',
      );
    } else {
      currency_chk = 1;
    }
  }

  if (
    fileInput.files.length === 0 &&
    ($("#jt_attachmentValue").val() == "" ||
      $("#jt_attachmentValue").val() == "0" ||
      $("#jt_attachmentValue").val() === null ||
      $("#jt_attachmentValue").val() === undefined)
  ) {
    attach_chk = 0;
    $("#jt_attach").after(
      '<span class="error-message jt-attach-err">Attachment is required!</span>',
    );
  } else {
    attach_chk = 1;
  }

  if (
    jt_inv_number_chk == 1 &&
    jt_inv_date_chk == 1 &&
    currency_chk == 1 &&
    attach_chk == 1
  ) {
    recalcTotals();
    $(this).closest("form").submit();
  } else {
    return false;
  }
});

updateDeliveryRowNumbers();
updateGstRowNumbers();
updateDeliveryActionButtons();
updateGstActionButtons();
recalcTotals();
