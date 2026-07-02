$(document).ready(function () {
  var lazadaConfig = window.lazadaCustomerRecordConfig || {};
  var siteURL = lazadaConfig.siteURL || "";
  var tables = lazadaConfig.tables || {};

  function bindSearch(inputSelector, searchType, dbTable) {
    var $input = $(inputSelector);
    if (!$input.length || $input.attr("disabled")) {
      return;
    }

    $input.on("keyup", function () {
      var param = {
        search: $(this).val(),
        searchType: searchType,
        elementID: $(this).attr("id"),
        hiddenElementID: $(this).attr("id") + "_hidden",
        dbTable: dbTable,
      };
      searchInput(param, siteURL);
    });
  }

  bindSearch("#lcr_pic", "name", tables.user || "");
  bindSearch("#lcr_country", "nicename", tables.countries || "");
  bindSearch("#lcr_brand", "name", tables.brand || "");
  bindSearch("#lcr_series", "name", tables.series || "");
});

$("#lcr_name").on("input", function () {
  $(".lcr-name-err").remove();
});

$("#lcr_pic").on("input", function () {
  $(".lcr-pic-err").remove();
});

$("#lcr_country").on("input", function () {
  $(".lcr-country-err").remove();
});

$("#lcr_brand").on("input", function () {
  $(".lcr-brand-err").remove();
});

$("#lcr_series").on("input", function () {
  $(".lcr-series-err").remove();
});

$("#lcr_rec_name").on("input", function () {
  $(".lcr-rec-name-err").remove();
});

$("#lcr_rec_ctc").on("input", function () {
  $(".lcr-rec-ctc-err").remove();
});

$("#lcr_rec_add").on("input", function () {
  $(".lcr-rec-add-err").remove();
});

$(".submitBtn").on("click", function (event) {
  event.preventDefault();
  $(".error-message").remove();

  var $form = $(this).closest("form");
  if (!$form.length) {
    var formId = $(this).attr("form");
    if (formId) {
      $form = $("#" + formId);
    }
  }

  var clickedAction = $(this).val() || $(this).attr("value") || "";

  var lcr_id_chk = 0;
  var name_chk = 0;
  var email_chk = 0;
  var phone_chk = 0;
  var pic_chk = 0;
  var country_chk = 0;
  var brand_chk = 0;
  var series_chk = 0;
  var rec_name_chk = 0;
  var rec_ctc_chk = 0;
  var rec_add_chk = 0;

  if (
    $("#lcr_id").val() === "" ||
    $("#lcr_id").val() === null ||
    $("#lcr_id").val() === undefined
  ) {
    $("#lcr_id").after(
      '<span class="error-message lcr-id-err">Customer ID is required!</span>',
    );
  } else {
    $(".lcr-id-err").remove();
    lcr_id_chk = 1;
  }

  if (
    $("#lcr_name").val() === "" ||
    $("#lcr_name").val() === null ||
    $("#lcr_name").val() === undefined
  ) {
    $("#lcr_name").after(
      '<span class="error-message lcr-name-err">Customer Name is required!</span>',
    );
  } else {
    $(".lcr-name-err").remove();
    name_chk = 1;
  }

  if (
    $("#lcr_email").val() === "" ||
    $("#lcr_email").val() === null ||
    $("#lcr_email").val() === undefined
  ) {
    $("#lcr_email").after(
      '<span class="error-message lcr-email-err">Customer Email is required!</span>',
    );
  } else {
    $(".lcr-email-err").remove();
    email_chk = 1;
  }

  if (
    $("#lcr_phone").val() === "" ||
    $("#lcr_phone").val() === null ||
    $("#lcr_phone").val() === undefined
  ) {
    $("#lcr_phone").after(
      '<span class="error-message lcr-phone-err">Customer Phone is required!</span>',
    );
  } else {
    $(".lcr-phone-err").remove();
    phone_chk = 1;
  }

  if (
    $("#lcr_pic").val() === "" ||
    $("#lcr_pic").val() === null ||
    $("#lcr_pic").val() === undefined
  ) {
    $("#lcr_pic").after(
      '<span class="error-message lcr-pic-err">Sales Person-In-Charge is required!</span>',
    );
  } else {
    $(".lcr-pic-err").remove();
    pic_chk = 1;
  }

  if (
    $("#lcr_country").val() === "" ||
    $("#lcr_country").val() === null ||
    $("#lcr_country").val() === undefined
  ) {
    $("#lcr_country").after(
      '<span class="error-message lcr-country-err">Country is required!</span>',
    );
  } else {
    $(".lcr-country-err").remove();
    country_chk = 1;
  }

  if (
    $("#lcr_brand").val() === "" ||
    $("#lcr_brand").val() === null ||
    $("#lcr_brand").val() === undefined
  ) {
    $("#lcr_brand").after(
      '<span class="error-message lcr-brand-err">Brand is required!</span>',
    );
  } else {
    $(".lcr-brand-err").remove();
    brand_chk = 1;
  }

  if (
    $("#lcr_series").val() === "" ||
    $("#lcr_series").val() === null ||
    $("#lcr_series").val() === undefined
  ) {
    $("#lcr_series").after(
      '<span class="error-message lcr-series-err">Series is required!</span>',
    );
  } else {
    $(".lcr-series-err").remove();
    series_chk = 1;
  }

  if (
    $("#lcr_rec_name").val() === "" ||
    $("#lcr_rec_name").val() === null ||
    $("#lcr_rec_name").val() === undefined
  ) {
    $("#lcr_rec_name").after(
      '<span class="error-message lcr-rec-name-err">Shipping Receiver Name is required!</span>',
    );
  } else {
    $(".lcr-rec-name-err").remove();
    rec_name_chk = 1;
  }

  if (
    $("#lcr_rec_ctc").val() === "" ||
    $("#lcr_rec_ctc").val() === null ||
    $("#lcr_rec_ctc").val() === undefined
  ) {
    $("#lcr_rec_ctc").after(
      '<span class="error-message lcr-rec-ctc-err">Shipping Receiver Contact is required!</span>',
    );
  } else {
    $(".lcr-rec-ctc-err").remove();
    rec_ctc_chk = 1;
  }

  if (
    $("#lcr_rec_add").val() === "" ||
    $("#lcr_rec_add").val() === null ||
    $("#lcr_rec_add").val() === undefined
  ) {
    $("#lcr_rec_add").after(
      '<span class="error-message lcr-rec-add-err">Shipping Receiver Address is required!</span>',
    );
  } else {
    $(".lcr-rec-add-err").remove();
    rec_add_chk = 1;
  }

  if (
    lcr_id_chk == 1 &&
    name_chk == 1 &&
    email_chk == 1 &&
    phone_chk == 1 &&
    pic_chk == 1 &&
    country_chk == 1 &&
    brand_chk == 1 &&
    series_chk == 1 &&
    rec_name_chk == 1 &&
    rec_add_chk == 1 &&
    rec_ctc_chk == 1
  ) {
    if (!$form.length) {
      return false;
    }

    var $actionHidden = $form.find("input[name='actionBtnHidden']");
    if (!$actionHidden.length) {
      $actionHidden = $('<input type="hidden" name="actionBtnHidden">');
      $form.append($actionHidden);
    }
    $actionHidden.val(clickedAction);
    $form.get(0).submit();
  } else {
    return false;
  }
});
