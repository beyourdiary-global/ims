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

function captureAndExport(tblName) {
  var selectedIds = [];
  document
    .querySelectorAll("input.export:checked")
    .forEach(function (checkbox) {
      selectedIds.push(checkbox.value);
    });

  // Pass the selected IDs for auditing
  auditExport(selectedIds, tblName);

  // Trigger export action
  if (exportData()) {
    showExportNotification();
  }
}

function auditExport(ids, tblName) {
  // Use AJAX to send the selected IDs for auditing
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "../export.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  xhr.send("ids=" + ids.join(",") + "&tblName=" + tblName);
}

$(document).ready(function ($) {
  $(document).on("change", ".exportAll", function (event) {
    event.preventDefault();

    var isChecked = $(this).prop("checked");
    $(".export").prop("checked", isChecked);
    $(".exportAll").prop("checked", isChecked);

    updateCheckboxesOnOtherPages(isChecked);
  });

  $('a[name="exportBtn"]').on("click", function (event) {
    event.preventDefault();
    var checkboxValues = [];

    $("#stockInListTable tbody tr").each(function () {
      var checkbox = $(this).find(".export:checked");
      if (checkbox.length > 0) {
        checkbox.each(function () {
          checkboxValues.push($(this).val());
        });
      }
    });

    if (checkboxValues.length > 0) {
      auditExport(checkboxValues, "stock_in_order_item");
      setCookie("rowID", checkboxValues.join(","), 1);

      var checkboxes = document.querySelectorAll(".export");
      checkboxes.forEach(function (checkbox) {
        checkbox.checked = false;
      });

      var selectAllCheckbox = document.querySelector(".exportAll");
      if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
      }

      alert("Export successful!");
      var exportUrl =
        "warehouse_stock_in_table.php?export=excel&ids=" +
        encodeURIComponent(checkboxValues.join(","));
      window.open(exportUrl, "_blank");
    } else {
      alert("Please select data to export.");
    }
  });

  function updateCheckboxesOnOtherPages(isChecked) {
    var cells = $("#stockInListTable").DataTable().cells().nodes();
    $(cells).find(".export").prop("checked", isChecked);
  }
});
