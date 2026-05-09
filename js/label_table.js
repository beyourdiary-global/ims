(function () {
  function init() {
    var cfg = window.labelTableConfig || {};
    var table = document.getElementById("table");

    if (table) {
      createSortingTable("table");
      dropdownMenuDispFix();
      datatableAlignment("table");
    }
    checkCurrentPage(cfg.pageTitle || "", cfg.action || "");
    setButtonColor();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
