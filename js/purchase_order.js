

$(document).ready(function () {
  var cfg = window.__PURCHASE_ORDER_CONFIG || {};
  var page = cfg.page || "Purchase Order";
  var action = cfg.action || "";
  var companyMap = cfg.companyMap || {};

  checkCurrentPage(page, action);
  centerAlignment("formContainer");
  setButtonColor();
  dropdownMenuDispFix();

  function fillCompanyDetails(companyName) {
    var data = companyMap[companyName] || null;

    $("#company_code").val(data ? data.code || "" : "");
    $("#company_id_no").val(data ? data.id_no || "" : "");
    $("#company_address1").val(data ? data.address1 || "" : "");
    $("#company_address2").val(data ? data.address2 || "" : "");
    $("#company_address3").val(data ? data.address3 || "" : "");
    $("#company_address4").val(data ? data.address4 || "" : "");
    $("#company_postcode").val(data ? data.postcode || "" : "");
    $("#company_city").val(data ? data.city || "" : "");
    $("#company_state").val(data ? data.state || "" : "");
    $("#company_country").val(data ? data.country || "" : "");
    $("#company_phone1").val(data ? data.phone1 || "" : "");
    $("#company_sales_tax_no").val(data ? data.sales_tax_no || "" : "");
    $("#company_service_tax_no").val(data ? data.service_tax_no || "" : "");
    $("#company_tin").val(data ? data.tin || "" : "");
    $("#company_id_type").val(data ? data.id_type || "" : "");
    $("#company_tourism_no").val(data ? data.tourism_no || "" : "");
    $("#company_sic").val(data ? data.sic || "" : "");
    $("#company_income").val(data ? data.income || "" : "");
    $("#company_submission_type").val(data ? data.submission_type || "" : "");
    $("#company_irbm_classification").val(
      data ? data.irbm_classification || "" : "",
    );
    $("#company_tax_exemption_reason").val(
      data ? data.tax_exemption_reason || "" : "",
    );
    $("#company_remark").val(data ? data.remark || "" : "");
    $("#company_sql_account_id").val(data ? data.sql_account_id || "" : "");

    if (data && data.sql_account_name) {
      $("#company_sql_account_name").val(data.sql_account_name);
    } else {
      $("#company_sql_account_name").val("");
    }
  }

  function recalcAmount() {
    var qty = parseFloat($("#qty").val() || "0");
    var unitPrice = parseFloat($("#unit_price").val() || "0");
    if (!isNaN(qty) && !isNaN(unitPrice)) {
      $("#amount").val((qty * unitPrice).toFixed(2));
    }
  }

  $("#company_name").on("change", function () {
    fillCompanyDetails($(this).val());
  });

  $("#qty, #unit_price").on("input", function () {
    recalcAmount();
  });

  fillCompanyDetails($("#company_name").val());
});
