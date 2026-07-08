(function () {
  var cfg = window.__PACKAGE_IMPORT_CONFIG || {};
  var siteUrl = typeof cfg.siteUrl === "string" ? cfg.siteUrl : "";
  var previewServerRows = Array.isArray(cfg.previewServerRows)
    ? cfg.previewServerRows
    : null;

  function normalizeServerValue(val) {
    if (val === null || typeof val === "undefined") {
      return "";
    }
    if (typeof val === "boolean") {
      return val ? "1" : "0";
    }
    return String(val);
  }

  function bindServerValue(el, serverValue) {
    if (!el) {
      return;
    }

    var released = false;
    var apply = function () {
      if (released) {
        return;
      }
      el.value = serverValue;
    };

    var release = function () {
      released = true;
    };

    apply();
    el.addEventListener("input", release, { once: true });
    el.addEventListener("change", release, { once: true });
    el.addEventListener("keydown", release, { once: true });

    [120, 350, 700, 1200].forEach(function (delay) {
      setTimeout(apply, delay);
    });
  }

  function enforcePreviewServerValues() {
    if (!Array.isArray(previewServerRows)) {
      return;
    }

    previewServerRows.forEach(function (row, idx) {
      if (!row || typeof row !== "object") {
        return;
      }

      Object.keys(row).forEach(function (key) {
        if (key === "changes" || key === "field_errors") {
          return;
        }

        var selector = '[name="data[' + idx + "][" + key + ']"]';
        var field = document.querySelector(selector);
        if (!field) {
          return;
        }

        bindServerValue(field, normalizeServerValue(row[key]));
      });
    });
  }

  enforcePreviewServerValues();

  function clearSearchList(inputId) {
    var list = document.getElementById("searchResult_" + inputId);
    if (list) list.remove();
    var clear = document.getElementById("clear_" + inputId);
    if (clear) clear.remove();
  }

  function norm(v) {
    return String(v || "")
      .toLowerCase()
      .replace(/\s+/g, " ")
      .trim();
  }

  var lookupMeta = cfg.lookupMeta || {
    brand_name: {
      names: [],
      ids: [],
      requiredMessage: "Brand field is required!",
      invalidMessage: "Brand not found in database.",
    },
    price_curr_name: {
      names: [],
      ids: [],
      requiredMessage: "Currency Unit field is required!",
      invalidMessage: "Price currency not found in database.",
    },
    cost_curr_name: {
      names: [],
      ids: [],
      requiredMessage: "Cost Currency Unit field is required!",
      invalidMessage: "Cost currency not found in database.",
    },
    parent_sku: {
      names: [],
      ids: [],
      altValues: [],
      invalidMessage: "Parent SKU item code not found in database.",
    },
    product_names: {
      names: [],
      ids: [],
    },
  };

  function singleValid(field, raw) {
    var value = String(raw || "").trim();
    if (value === "") return true;
    var meta = lookupMeta[field];
    if (!meta) return true;

    var byName = {};
    (meta.names || []).forEach(function (name) {
      byName[norm(name)] = true;
    });
    if (byName[norm(value)]) {
      return true;
    }

    var byAltValue = {};
    (meta.altValues || []).forEach(function (altValue) {
      byAltValue[norm(altValue)] = true;
    });
    return !!byAltValue[norm(value)];
  }

  function multiValid(field, raw) {
    var value = String(raw || "").trim();
    if (value === "") return true;
    var parts = value.split(",");
    for (var i = 0; i < parts.length; i++) {
      if (!singleValid(field, parts[i].trim())) return false;
    }
    return true;
  }

  function resolveFieldContainer(input) {
    return (
      input.closest(".col-md-3, .col-md-4, .col-md-6, .col-md-2, .col-md-12") ||
      input.parentElement
    );
  }

  function setFieldError(input, field, message) {
    var row = resolveFieldContainer(input);
    if (!row) return;

    var err = row.querySelector('.field-error[data-field="' + field + '"]');
    if (!err) {
      err = document.createElement("div");
      err.className = "field-error";
      err.setAttribute("data-field", field);
      row.appendChild(err);
    }

    if (message) {
      err.textContent = message;
      err.style.display = "block";
    } else {
      err.style.display = "none";
    }
  }

  function revalidateLookupField(input) {
    var field = input.getAttribute("data-lookup-field");
    if (!field) return;

    var meta = lookupMeta[field] || {};
    var value = String(input.value || "").trim();

    if (value === "" && meta.requiredMessage) {
      setFieldError(input, field, meta.requiredMessage);
      return;
    }

    var isValid = input.classList.contains("js-lookup-multi")
      ? multiValid(field, value)
      : singleValid(field, value);
    if (!isValid) {
      setFieldError(
        input,
        field,
        meta.invalidMessage || "Value not found in database.",
      );
      return;
    }

    setFieldError(input, field, "");
  }

  function revalidateRequiredField(input) {
    var field = input.getAttribute("data-required-field");
    if (!field) return;

    if (
      input.classList.contains("js-lookup-single") ||
      input.classList.contains("js-lookup-multi")
    ) {
      revalidateLookupField(input);
      return;
    }

    var message =
      input.getAttribute("data-required-message") || "This field is required!";
    var value = String(input.value || "").trim();
    setFieldError(input, field, value === "" ? message : "");
  }

  document
    .querySelectorAll(".js-live-search[data-search-type][data-db-table]")
    .forEach(function (el) {
      var hidden = document.getElementById(el.id + "_hidden");
      var check = function () {
        revalidateLookupField(el);
      };

      el.addEventListener("keyup", function () {
        if (hidden) hidden.value = "";
        searchInput(
          {
            search: el.value,
            searchType: el.getAttribute("data-search-type"),
            elementID: el.id,
            hiddenElementID: hidden ? hidden.id : "",
            dbTable: el.getAttribute("data-db-table"),
          },
          siteUrl,
        );
        check();
      });

      el.addEventListener("change", check);
      el.addEventListener("input", check);

      el.addEventListener("blur", function () {
        setTimeout(function () {
          clearSearchList(el.id);
          check();
        }, 200);
      });
    });

  document
    .querySelectorAll(
      ".js-lookup-single[data-lookup-field], .js-lookup-multi[data-lookup-field]",
    )
    .forEach(function (el) {
      el.addEventListener("input", function () {
        revalidateLookupField(el);
      });
      el.addEventListener("change", function () {
        revalidateLookupField(el);
      });
    });

  document
    .querySelectorAll(".js-required-field[data-required-field]")
    .forEach(function (el) {
      el.addEventListener("input", function () {
        revalidateRequiredField(el);
      });
      el.addEventListener("change", function () {
        revalidateRequiredField(el);
      });
    });
})();
