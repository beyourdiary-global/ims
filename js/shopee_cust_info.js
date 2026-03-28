// autocomplete
$(document).ready(function () {
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
      searchInput(param, "<?= $SITEURL ?>");
    });
  }

  bindSearch("#scr_pic", "name", "<?= USR_USER ?>");
  bindSearch("#scr_country", "nicename", "<?= COUNTRIES ?>");
  bindSearch("#scr_brand", "name", "<?= BRAND ?>");
  bindSearch("#scr_series", "name", "<?= BRD_SERIES ?>");
});

// jQuery form validation
$("#scr_username").on("input", function () {
  $(".scr-name-err").remove();
});

$("#scr_pic").on("input", function () {
  $(".scr-pic-err").remove();
});

$("#scr_country").on("input", function () {
  $(".scr-country-err").remove();
});

$("#scr_brand").on("input", function () {
  $(".scr-brand-err").remove();
});

$("#scr_series").on("input", function () {
  $(".scr-series-err").remove();
});

$("#scr_contact").on("input", function () {
  $(".scr-contact-err").remove();
});

$(".submitBtn").on("click", function (event) {
  event.preventDefault();
  $(".error-message").remove();
  var $form = $(this).closest("form");
  var clickedAction = $(this).val() || $(this).attr("value") || "";

  var name_chk = 0;
  var pic_chk = 0;
  var brand_chk = 0;
  var series_chk = 0;
  var contact_chk = 0;
  var country_chk = 0;

  if (
    $("#scr_username").val() === "" ||
    $("#scr_username").val() === null ||
    $("#scr_username").val() === undefined
  ) {
    name_chk = 0;
    $("#scr_username").after(
      '<span class="error-message scr-name-err">Shopee Buyer Username is required!</span>',
    );
  } else {
    $(".scr-name-err").remove();
    name_chk = 1;
  }

  if (
    $("#scr_pic").val() === "" ||
    $("#scr_pic").val() === null ||
    $("#scr_pic").val() === undefined
  ) {
    pic_chk = 0;
    $("#scr_pic").after(
      '<span class="error-message scr-pic-err">Sales Person In Charge is required!</span>',
    );
  } else {
    $(".scr-pic-err").remove();
    pic_chk = 1;
  }

  if (
    $("#scr_brand").val() === "" ||
    $("#scr_brand").val() === null ||
    $("#scr_brand").val() === undefined
  ) {
    brand_chk = 0;
    $("#scr_brand").after(
      '<span class="error-message scr-brand-err">Brand is required!</span>',
    );
  } else {
    $(".scr-brand-err").remove();
    brand_chk = 1;
  }

  if (
    $("#scr_country").val() === "" ||
    $("#scr_country").val() === null ||
    $("#scr_country").val() === undefined
  ) {
    country_chk = 0;
    $("#scr_country").after(
      '<span class="error-message scr-country-err">Country is required!</span>',
    );
  } else {
    $(".scr-country-err").remove();
    country_chk = 1;
  }

  if (
    $("#scr_series").val() === "" ||
    $("#scr_series").val() === null ||
    $("#scr_series").val() === undefined
  ) {
    series_chk = 0;
    $("#scr_series").after(
      '<span class="error-message scr-series-err">Series is required!</span>',
    );
  } else {
    $(".scr-series-err").remove();
    series_chk = 1;
  }

  if (
    $("#scr_contact").val() === "" ||
    $("#scr_contact").val() === null ||
    $("#scr_contact").val() === undefined
  ) {
    contact_chk = 0;
    $("#scr_contact").after(
      '<span class="error-message scr-contact-err">Whatsapp / Contact Number is required!</span>',
    );
  } else {
    $(".scr-contact-err").remove();
    contact_chk = 1;
  }

  if (
    name_chk == 1 &&
    pic_chk == 1 &&
    country_chk == 1 &&
    brand_chk == 1 &&
    series_chk == 1 &&
    contact_chk == 1
  ) {
    // Programmatic submit does not include clicked submit button value.
    // Keep it in a hidden field so backend can read the intended action.
    var $actionHidden = $form.find("input[name='actionBtnHidden']");
    if (!$actionHidden.length) {
      $actionHidden = $('<input type="hidden" name="actionBtnHidden">');
      $form.append($actionHidden);
    }
    $actionHidden.val(clickedAction);
    if ($form.length && $form.get(0)) {
      $form.get(0).submit();
    }
  } else {
    return false;
  }
});
