preloader(300);

$(document).ready(function () {
  var cfg = window.__PURCHASE_ORDER_TABLE_CONFIG || {};
  var page = cfg.page || "Purchase Order";
  var action = cfg.action || "";

  createSortingTable("table");
  checkCurrentPage(page, action);
  dropdownMenuDispFix();
  datatableAlignment("table");
  setButtonColor();

  $(document).on("change", ".exportAll", function (event) {
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
    "#exportBtn, button[name='exportBtn'], a[name='exportBtn']",
    function (event) {
      event.preventDefault();

      var checkboxValues = [];

      if ($.fn.DataTable.isDataTable("#table")) {
        $("#table")
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
      } else {
        $(".export:checked").each(function () {
          checkboxValues.push($(this).val());
        });
      }

      if (checkboxValues.length <= 0) {
        alert("Please select data to export.");
        return;
      }

      $("#export_ids").val(checkboxValues.join(","));
      if (
        window.location.hostname === "localhost" ||
        window.location.hostname === "127.0.0.1"
      ) {
        alert("Export succesful.");
      }
      $("#exportForm").trigger("submit");
    },
  );

  function updateCheckboxesOnOtherPages(isChecked) {
    if (!$.fn.DataTable.isDataTable("#table")) {
      return;
    }
    var cells = $("#table").DataTable().rows({ page: "current" }).nodes();
    $(cells).find(".export").prop("checked", isChecked);
  }
});
