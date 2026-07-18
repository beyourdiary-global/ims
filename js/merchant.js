$(document).ready(function () {
  if (!$("#mrcht_pic").attr("disabled")) {
    $("#mrcht_pic").keyup(function () {
      const param = {
        search: $(this).val(),
        searchType: "name", // column of the table
        elementID: $(this).attr("id"), // id of the input
        hiddenElementID: $(this).attr("id") + "_hidden", // hidden input for storing the value
        dbTable: "<?= USR_USER ?>", // json filename (generated when login)
      };
      searchInput(param, "<?= $SITEURL ?>");
    });
  }
});
$("#merchant_name").on("input", function () {
  $(".mrcht-name-err").remove();
});

$("#mrcht_email").on("input", function () {
  $(".mrcht-email-err").remove();
});

$("#mrcht_pic").on("input", function () {
  $(".pic-name-err").remove();
});

$("#mrcht_pic_contact").on("input", function () {
  $(".contact-err").remove();
});

function formatMerchantValue(value) {
  const cleaned = (value || "").toUpperCase().replace(/[^A-Z0-9]/g, "");
  const prefix = cleaned.replace(/\D/g, "").slice(0, 3);
  const suffix = cleaned.slice(prefix.length).replace(/[^A-Z0-9]/g, "").slice(0, 5);

  if (prefix === "") {
    return suffix;
  }

  if (suffix === "") {
    return prefix;
  }

  return `${prefix}-${suffix}`;
}

function formatMerchantControlAccountValue(value) {
  const cleaned = (value || "").toUpperCase().replace(/[^A-Z0-9]/g, "");
  const prefix = cleaned.slice(0, 3);
  const suffix = cleaned.slice(3, 6);

  if (prefix === "") {
    return suffix;
  }

  if (suffix === "") {
    return prefix;
  }

  return `${prefix}-${suffix}`;
}

$("#mrcht_control_account").on("input", function () {
  $(this).val(formatMerchantControlAccountValue($(this).val()));
  $(".control-account-err").remove();
});

$("#mrcht_code").on("input", function () {
  $(this).val(formatMerchantValue($(this).val()));
  $(".code-err").remove();
});

$(".submitBtn").on("click", function (event) {
  $(".error-message").remove();
  //event.preventDefault();
  let name_chk = 0;
  let email_chk = 0;
  let pic_chk = 0;
  let contact_chk = 0;
  let control_account_chk = 1;
  let code_chk = 1;
  const controlAccount = formatMerchantControlAccountValue($("#mrcht_control_account").val());
  const code = formatMerchantValue($("#mrcht_code").val());

  $("#mrcht_control_account").val(controlAccount);
  $("#mrcht_code").val(code);

  if (
    $("#currentDataName").val() === "" ||
    $("#currentDataName").val() === null ||
    $("#currentDataName").val() === undefined
  ) {
    name_chk = 0;
    $("#currentDataName").after(
      '<span class="error-message mrcht-name-err">Merchant name is required!</span>',
    );
  } else {
    name_chk = 1;
    $(".mrcht-name-err").remove();
  }

  if (
    $("#mrcht_email").val() === "" ||
    $("#mrcht_email").val() === null ||
    $("#mrcht_email").val() === undefined
  ) {
    email_chk = 0;
    $("#mrcht_email").after(
      '<span class="error-message mrcht-email-err">Email is required!</span>',
    );
  } else if (!isEmail($("#mrcht_email").val())) {
    email_chk = 0;
    $("#mrcht_email").after(
      '<span class="error-message mrcht-email-err">Wrong email format!</span>',
    );
  } else {
    email_chk = 1;
    $(".mrcht-email-err").remove();
  }

  if (
    $("#mrcht_pic").val() === "" ||
    $("#mrcht_pic").val() === null ||
    $("#mrcht_pic").val() === undefined
  ) {
    pic_chk = 0;
    $("#mrcht_pic").after(
      '<span class="error-message pic-name-err">Person in Charge is required!</span>',
    );
  } else {
    pic_chk = 1;
    $(".pic-name-err").remove();
  }

  if (
    $("#mrcht_pic_contact").val() === "" ||
    $("#mrcht_pic_contact").val() === null ||
    $("#mrcht_pic_contact").val() === undefined
  ) {
    contact_chk = 0;
    $("#mrcht_pic_contact").after(
      '<span class="error-message contact-err">Person in Charge Contact is required!</span>',
    );
  } else {
    contact_chk = 1;
    $(".contact-err").remove();
  }

  if (controlAccount !== "" && !/^[A-Z0-9]{3}-[A-Z0-9]{3}$/.test(controlAccount)) {
      control_account_chk = 0;
      $("#mrcht_control_account").after(
        '<span class="error-message control-account-err">Control A/C format must be like 000-000.</span>',
      );
  }

  if (code !== "" && !/^\d{3}-[A-Z0-9]{5}$/.test(code)) {
      code_chk = 0;
      $("#mrcht_code").after(
        '<span class="error-message code-err">Code format must be like 123-ABC01.</span>',
      );
  }

  if (
    name_chk == 1 &&
    email_chk == 1 &&
    pic_chk == 1 &&
    contact_chk == 1 &&
    control_account_chk == 1 &&
    code_chk == 1
  )
    $(this).closest("form").submit();
  else return false;
});
