//export notification
$(document).ready(function ($) {
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

  $('a[name="exportBtn"]').on("click", function (event) {
    event.preventDefault();
    var checkboxValues = [];

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

    if (checkboxValues.length > 0) {
      auditExport(checkboxValues, "prod");
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
      window.location.href = "product_table.php";
    } else {
      alert("Please select data to export.");
    }
  });
});
