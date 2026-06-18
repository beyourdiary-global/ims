//export notification
$(document).ready(function ($) {
  $(document).on("change", ".exportAll", function (event) {
    event.preventDefault();

    const isChecked = $(this).prop("checked");
    $(this).closest("table").find("tbody tr:visible .export").prop("checked", isChecked);
    $(".exportAll").prop("checked", isChecked);

    updateCheckboxesOnOtherPages(isChecked);
  });

  $('a[name="exportBtn"]').on("click", function (event) {
    event.preventDefault();
    const checkboxValues = [];

    $("#stockInListTable tbody tr:visible").each(function () {
      const checkbox = $(this).find(".export:checked");
      if (checkbox.length > 0) {
        checkbox.each(function () {
          checkboxValues.push($(this).val());
        });
      }
    });

    if (checkboxValues.length > 0) {
      auditExport(checkboxValues, "stock_in_order_item");
      setCookie("rowID", checkboxValues.join(","), 1);

      const checkboxes = document.querySelectorAll(".export");
      checkboxes.forEach(function (checkbox) {
        checkbox.checked = false;
      });

      const selectAllCheckbox = document.querySelector(".exportAll");
      if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
      }

      alert("Export successful!");
      const exportUrl =
        "warehouse_stock_in_table.php?export=excel&ids=" +
        encodeURIComponent(checkboxValues.join(","));
      window.open(exportUrl, "_blank");
    } else {
      alert("Please select data to export.");
    }
  });
});
