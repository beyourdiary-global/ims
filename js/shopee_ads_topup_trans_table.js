//export notification
function exportData() {
  var checkboxes = document.querySelectorAll(".export:checked");
  if (checkboxes.length === 0) {
    alert("Please select data to export.");
    return false;
  }
  return true;
}

function showExportNotification() {
  alert("Export successful!");
}

function auditExport(ids, tblName) {
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "../export.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  xhr.send("ids=" + ids.join(",") + "&tblName=" + encodeURIComponent(tblName));
}

function captureAndExport(tblName) {
  var checkboxValues = [];
  $("#shopee_ads_topup_trans_table")
    .DataTable()
    .$("tr", { filter: "applied" })
    .each(function () {
      var checkbox = $(this).find(".export:checked");
      if (checkbox.length > 0) {
        checkbox.each(function () {
          checkboxValues.push($(this).val());
        });
      }
    });

  if (checkboxValues.length > 0) {
    setCookie("rowID", checkboxValues.join(","), 1);
    auditExport(checkboxValues, tblName || "shopee_ads_topup");
    alert("Export successful!");
    window.location.href =
      "shopee_ads_topup_trans_table.php?export_ids=" +
      encodeURIComponent(checkboxValues.join(","));
  } else {
    alert("Please select data to export.");
  }
}

$(document).ready(function ($) {
  $(document).on("change", ".exportAll", function (event) {
    //checkbox handling
    event.preventDefault();

    var isChecked = $(this).prop("checked");
    $(".export").prop("checked", isChecked);
    $(".exportAll").prop("checked", isChecked);

    updateCheckboxesOnOtherPages(isChecked);
  });

  $(document).on(
    "click",
    "a[name='exportBtnShopee'], #exportBtn",
    function (event) {
      event.preventDefault();
      var checkboxValues = [];
      var checkedBoxes = document.querySelectorAll(
        "#shopee_ads_topup_trans_table .export:checked",
      );
      checkedBoxes.forEach(function (checkbox) {
        checkboxValues.push(checkbox.value);
      });

      console.log("[shopee_ads_topup_export] selected_ids:", checkboxValues);

      if (checkboxValues.length > 0) {
        auditExport(checkboxValues, "shopee_ads_topup");
        //uncheck checkboxes
        var checkboxes = document.querySelectorAll(".export");
        checkboxes.forEach(function (checkbox) {
          checkbox.checked = false;
        });

        var selectAllCheckbox = document.querySelector(".exportAll");
        if (selectAllCheckbox) {
          selectAllCheckbox.checked = false;
        }

        var exportUrl =
          "shopee_ads_topup_trans_table.php?export_ids=" +
          encodeURIComponent(checkboxValues.join(","));
        console.log("[shopee_ads_topup_export] redirect_url:", exportUrl);
        alert("Export successful!");
        window.location.assign(exportUrl);
      } else {
        alert("Please select data to export.");
      }
    },
  );

  function updateCheckboxesOnOtherPages(isChecked) {
    // Get all cells in the DataTable
    var cells = $("#shopee_ads_topup_trans_table").DataTable().cells().nodes();

    // Check/uncheck all checkboxes in the DataTable
    $(cells).find(".export").prop("checked", isChecked);
  }
});
