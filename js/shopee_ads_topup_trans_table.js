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
    .rows({ search: "applied", page: "current" })
    .nodes()
    .to$()
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
  var filterState = window.shopeeAdsTableFilters || {};

  var table = new DataTable("#shopee_ads_topup_trans_table", {
    paging: $("#shopee_ads_topup_trans_table tbody tr").length > 10,
    searching: $("#shopee_ads_topup_trans_table tbody tr").length > 10,
    autoWidth: false,
    order: [[2, "asc"]],
    lengthMenu: [10, 25, 50, 100],
    columnDefs: [
      {
        orderable: false,
        searchable: false,
        targets: [0, 1, 3],
      },
    ],
  });

  function setupDatepicker() {
    if (typeof $.fn.datepicker !== "function") {
      return;
    }

    var commonDatepickerOptions = {
      autoclose: true,
      todayHighlight: true,
      container: "body",
      orientation: "bottom auto",
    };

    $("#datepicker input").datepicker({
      format: "yyyy-mm-dd",
      autoclose: commonDatepickerOptions.autoclose,
      todayHighlight: commonDatepickerOptions.todayHighlight,
      container: commonDatepickerOptions.container,
      orientation: commonDatepickerOptions.orientation,
    });

    $(
      "#datepicker2 input[name='start'], #datepicker2 input[name='end']",
    ).datepicker({
      format: "yyyy-mm-dd",
      autoclose: commonDatepickerOptions.autoclose,
      todayHighlight: commonDatepickerOptions.todayHighlight,
      container: commonDatepickerOptions.container,
      orientation: commonDatepickerOptions.orientation,
    });

    $(
      "#datepicker3 input[name='start'], #datepicker3 input[name='end']",
    ).datepicker({
      format: "yyyy-mm",
      autoclose: commonDatepickerOptions.autoclose,
      container: commonDatepickerOptions.container,
      orientation: commonDatepickerOptions.orientation,
      minViewMode: 1,
    });

    $(
      "#datepicker4 input[name='start'], #datepicker4 input[name='end']",
    ).datepicker({
      format: "yyyy",
      autoclose: commonDatepickerOptions.autoclose,
      container: commonDatepickerOptions.container,
      orientation: commonDatepickerOptions.orientation,
      minViewMode: 2,
    });
  }

  function toggleDateFilters(interval) {
    $("#datepicker").hide();
    $("#datepicker2").hide();
    $("#datepicker3").hide();
    $("#datepicker4").hide();

    if (interval === "daily") {
      $("#datepicker").show();
    } else if (interval === "weekly") {
      $("#datepicker2").show();
    } else if (interval === "monthly") {
      $("#datepicker3").show();
    } else if (interval === "yearly") {
      $("#datepicker4").show();
    }
  }

  function hydrateFilters() {
    var interval = filterState.timeInterval || "daily";
    $("#timeInterval").val(interval);
    $("#group").val(filterState.group || "");
    $("#datepicker input").val(filterState.date || "");
    $("#datepicker2 input[name='start']").val(filterState.start || "");
    $("#datepicker2 input[name='end']").val(filterState.end || "");
    $("#datepicker3 input[name='start']").val(filterState.start || "");
    $("#datepicker3 input[name='end']").val(filterState.end || "");
    $("#datepicker4 input[name='start']").val(filterState.start || "");
    $("#datepicker4 input[name='end']").val(filterState.end || "");
    toggleDateFilters(interval);
  }

  function applyFilters() {
    var interval = $("#timeInterval").val() || "daily";
    var group = $("#group").val() || "";

    var params = new URLSearchParams();
    params.set("timeInterval", interval);

    if (group !== "") {
      params.set("group", group);
    }

    if (interval === "daily") {
      var day = $("#datepicker input").val();
      if (day) params.set("date", day);
    } else {
      var start = "";
      var end = "";

      if (interval === "weekly") {
        start = $("#datepicker2 input[name='start']").val();
        end = $("#datepicker2 input[name='end']").val();
      } else if (interval === "monthly") {
        start = $("#datepicker3 input[name='start']").val();
        end = $("#datepicker3 input[name='end']").val();
      } else if (interval === "yearly") {
        start = $("#datepicker4 input[name='start']").val();
        end = $("#datepicker4 input[name='end']").val();
      }

      if (start) params.set("start", start);
      if (end) params.set("end", end);
    }

    window.location.href =
      "shopee_ads_topup_trans_table.php?" + params.toString();
  }

  setupDatepicker();
  hydrateFilters();

  $("#timeInterval").on("change", function () {
    toggleDateFilters($(this).val());
  });

  $("#applyFilterBtn").on("click", function () {
    applyFilters();
  });

  $(document).on("change", ".exportAll", function (event) {
    //checkbox handling
    event.preventDefault();

    var isChecked = $(this).prop("checked");
    $(this)
      .closest("table")
      .find("tbody tr:visible .export")
      .prop("checked", isChecked);
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
    var cells = table.rows({ page: "current" }).nodes();

    // Check/uncheck all checkboxes in the DataTable
    $(cells).find(".export").prop("checked", isChecked);
  }
});
