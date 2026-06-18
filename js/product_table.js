//export notification
$(document).ready(function ($) {
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

    if (checkboxValues.length > 0) {
      auditExport(checkboxValues, "prod");
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
      window.location.href = "product_table.php";
    } else {
      alert("Please select data to export.");
    }
  });
});
