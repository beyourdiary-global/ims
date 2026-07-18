$(document).ready(function () {
  $(document).on("change", ".exportAll", function (event) {
    event.preventDefault();
    const checked = $(this).prop("checked");
    $(this).closest("table").find("tbody tr:visible .export").prop("checked", checked);
    $(".exportAll").prop("checked", checked);
    updateCheckboxesOnOtherPages(checked);
  });

  $(document).on("click", 'a[name="exportBtn"]', function (event) {
    event.preventDefault();
    const selected = [];
    if ($.fn.DataTable && $.fn.DataTable.isDataTable("#supplier_payment_table")) {
      $("#supplier_payment_table").DataTable().rows({ search: "applied", page: "current" }).nodes().to$().find(".export:checked").each(function () { selected.push($(this).val()); });
    } else {
      $(".export:checked").each(function () { selected.push($(this).val()); });
    }
    if (!selected.length) {
      showNotification("Please select data to export.", "warning");
      return;
    }
    if (typeof auditExport === "function") auditExport(selected, "supplier_payment");
    setCookie("rowID", selected.join(","), 1);
    window.location.href = "supplier_payment_table.php";
  });
});
