// Supplier Invoice export selection
$(document).ready(function () {
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

  $('a[name="exportBtn"]').on("click", function (event) {
    event.preventDefault();
    const checkboxValues = [];

      if ($.fn.DataTable && $.fn.DataTable.isDataTable("#supplier_invoice_table")) {
      $("#supplier_invoice_table")
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

    if (checkboxValues.length > 0) {
      auditExport(checkboxValues, "supplier_invoice");
      setCookie("rowID", checkboxValues.join(","), 1);

      document.querySelectorAll(".export").forEach(function (checkbox) {
        checkbox.checked = false;
      });
      const selectAllCheckbox = document.querySelector(".exportAll");
      if (selectAllCheckbox) selectAllCheckbox.checked = false;

      showNotification("Export successful!", "success");
      window.setTimeout(function () {
        window.location.href = "supplier_invoice_table.php";
      }, 600);
    } else {
      showNotification("Please select data to export.", "warning");
    }
  });
});
