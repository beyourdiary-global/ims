

$(document).ready(function () {
  const cfg = window.__PURCHASE_ORDER_TABLE_CONFIG || {};
  const page = cfg.page || "Purchase Order";
  const action = cfg.action || "";

  createSortingTable("table");
  checkCurrentPage(page, action);
  dropdownMenuDispFix();
  datatableAlignment("table");
  setButtonColor();

  $(document).on("change", ".exportAll", function (event) {
    event.preventDefault();

    const isChecked = $(this).prop("checked");
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

      const checkboxValues = [];

      if ($.fn.DataTable.isDataTable("#table")) {
        $("#table")
          .DataTable()
          .rows({ search: "applied", page: "current" })
          .nodes()
          .to$()
          .each(function () {
            const checkbox = $(this).find(".export:checked");
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
        showNotification("Please select data to export.", "warning");
        return;
      }

      $("#export_ids").val(checkboxValues.join(","));
      if (
        window.location.hostname === "localhost" ||
        window.location.hostname === "127.0.0.1"
      ) {
        showNotification("Export succesful.", "success");
        window.setTimeout(function () {
          $("#exportForm").trigger("submit");
        }, 600);
        return;
      }
      $("#exportForm").trigger("submit");
    },
  );
});
