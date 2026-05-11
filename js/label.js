(function () {
  function initPageState() {
    var cfg = window.labelPageConfig || {};

    checkCurrentPage(cfg.pageTitle || "", cfg.action || "");
    centerAlignment("formContainer");
    setButtonColor();
    preloader(300, cfg.action || "");
  }

  function bindParentLabelAutocomplete() {
    var cfg = window.labelPageConfig || {};
    var input = document.getElementById("parent_label_name");
    var hiddenInput = document.getElementById("parent_label");

    if (!input || !hiddenInput || input.hasAttribute("readonly")) {
      return;
    }

    input.addEventListener("keyup", function () {
      var param = {
        search: input.value,
        searchType: "name",
        elementID: input.id,
        hiddenElementID: hiddenInput.id,
        dbTable: cfg.dbTable || "label",
      };
      searchInput(param, cfg.siteUrl || "");
    });

    input.addEventListener("change", function () {
      if (input.value.trim() === "") {
        hiddenInput.value = "";
      }
    });
  }

  function setError(elementId, message) {
    var element = document.getElementById(elementId);
    if (element) {
      element.textContent = message || "";
    }
  }

  function clearCommonRequiredAlert(input) {
    if (!input || !input.parentNode) {
      return;
    }

    var alerts = input.parentNode.querySelectorAll('span[role="alert"]');
    alerts.forEach(function (alert) {
      if (alert && alert.parentNode) {
        alert.parentNode.removeChild(alert);
      }
    });
  }

  function validateForm() {
    var form = document.getElementById("form");
    if (!form) {
      return;
    }

    form.addEventListener("submit", function (event) {
      var submitter = event.submitter;
      if (submitter && submitter.value === "back") {
        return;
      }

      var hasError = false;
      var nameInput = document.getElementById("label_name");
      var parentSelect = document.getElementById("parent_label");
      var parentTextInput = document.getElementById("parent_label_name");
      var remarkInput = document.getElementById("label_remark");
      var cfg = window.labelPageConfig || {};

      setError("labelNameError", "");
      setError("parentLabelError", "");
      setError("labelRemarkError", "");
      clearCommonRequiredAlert(nameInput);
      clearCommonRequiredAlert(parentTextInput);
      clearCommonRequiredAlert(remarkInput);

      if (!nameInput || nameInput.value.trim() === "") {
        setError("labelNameError", "Label Name is required!");
        hasError = true;
      }

      if (
        parentSelect &&
        !cfg.useSelfParentFallback &&
        (
          String(parentSelect.value || "").trim() === "" ||
          !parentTextInput ||
          parentTextInput.value.trim() === ""
        )
      ) {
        setError("parentLabelError", "Parent Label is required!");
        hasError = true;
      }

      if (!remarkInput || remarkInput.value.trim() === "") {
        setError("labelRemarkError", "Remark is required!");
        hasError = true;
      }

      if (hasError) {
        event.preventDefault();
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initPageState();
      bindParentLabelAutocomplete();
      validateForm();
    });
  } else {
    initPageState();
    bindParentLabelAutocomplete();
    validateForm();
  }
})();
