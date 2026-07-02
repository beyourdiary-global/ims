$(document).ready(function ($) {
  const luckyDrawPrizeTableSelector = "#lucky_draw_prizes_table";

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

    $(luckyDrawPrizeTableSelector)
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
      auditExport(checkboxValues, "lucky_draw_prize");
      setCookie("rowID", checkboxValues.join(","), 1);

      document.querySelectorAll(".export").forEach(function (checkbox) {
        checkbox.checked = false;
      });

      const selectAllCheckbox = document.querySelector(".exportAll");
      if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
      }

      showNotification("Export successful!", "success");
      window.setTimeout(function () {
        window.location.href = "prizes_table.php";
      }, 600);
    } else {
      showNotification("Please select voucher prize rows to export.", "warning");
    }
  });
});
