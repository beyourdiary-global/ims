var wsCfg = window.__WAREHOUSE_STOCK_IN_IMPORT_CONFIG || {};
var page = "Stock In";
var action = "";
checkCurrentPage(page, action);
dropdownMenuDispFix();

(function () {
  var siteUrl = typeof wsCfg.siteUrl === "string" ? wsCfg.siteUrl : "";

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

  var warehouseSet = {};
  var productSet = {};
  var warehouseList = Array.isArray(wsCfg.warehouseList)
    ? wsCfg.warehouseList
    : [];
  var productList = Array.isArray(wsCfg.productList) ? wsCfg.productList : [];
  warehouseList.forEach(function (v) {
    warehouseSet[norm(v)] = true;
  });
  productList.forEach(function (v) {
    productSet[norm(v)] = true;
  });

  // Prevent browser autofill from reusing edited values from previous preview.
  // Some browsers apply autofill after initial script execution, so re-apply a few times
  // until the user interacts with the field.
  document.querySelectorAll(".js-server-bound").forEach(function (el) {
    var serverValue = el.getAttribute("data-server-value");
    if (serverValue === null) {
      return;
    }

    el.value = serverValue;

    var released = false;
    var releaseControl = function () {
      released = true;
    };

    el.addEventListener("input", releaseControl, { once: true });
    el.addEventListener("change", releaseControl, { once: true });
    el.addEventListener("keydown", releaseControl, { once: true });

    [120, 350, 700, 1200].forEach(function (delay) {
      setTimeout(function () {
        if (!released) {
          el.value = serverValue;
        }
      }, delay);
    });
  });

  function clearWarehouseSearchUI(inputId) {
    $("#searchResult_" + inputId)
      .empty()
      .remove();
    $("#clear_" + inputId).remove();
  }

  function ensureWarehouseSearchShell(input) {
    var $input = $(input);
    var inputId = input.id;
    var $wrapper = $input.closest(".autocomplete");

    if (!$wrapper.length) {
      $wrapper = $input.parent();
    }

    if (!$("#searchResult_" + inputId).length) {
      $wrapper.append(
        '<ul class="searchResult" id="searchResult_' + inputId + '"></ul>',
        '<div id="clear_' + inputId + '" class="clear"></div>',
      );
    } else if (
      $("#searchResult_" + inputId).parent().get(0) !== $wrapper.get(0)
    ) {
      $("#searchResult_" + inputId).appendTo($wrapper);
      $("#clear_" + inputId).appendTo($wrapper);
    }
  }

  function positionWarehouseSearchUI(input) {
    var result = document.getElementById("searchResult_" + input.id);
    if (!result) {
      return;
    }

    result.style.left = input.offsetLeft + "px";
    result.style.top = input.offsetTop + input.offsetHeight + 4 + "px";
    result.style.width = input.offsetWidth + "px";
  }

  function showWarehouseSearchUI(input) {
    var inputId = input.id;
    clearWarehouseSearchUI(inputId);

    var query = norm(input.value);
    if (query === "") {
      return;
    }

    var matches = [];
    for (var i = 0; i < warehouseList.length; i++) {
      var optionText = String(warehouseList[i] || "");
      if (norm(optionText).indexOf(query) !== -1) {
        matches.push(optionText);
      }
      if (matches.length >= 15) {
        break;
      }
    }

    if (matches.length === 0) {
      matches.push("<i>No result</i>");
    }

    ensureWarehouseSearchShell(input);

    setWidth(inputId, "searchResult_" + inputId);
    positionWarehouseSearchUI(input);

    $("#searchResult_" + inputId).empty();
    matches.forEach(function (match) {
      if (match === "<i>No result</i>") {
        $("#searchResult_" + inputId).append(
          "<li value='emptyValue'>" + match + "</li>",
        );
      } else {
        var safeText = $("<div/>").text(match).html();
        $("#searchResult_" + inputId).append(
          "<li value='" + safeText + "'>" + safeText + "</li>",
        );
      }
    });

    $("#searchResult_" + inputId + " li")
      .off("click")
      .on("click", function () {
        if ($(this).attr("value") === "emptyValue") {
          return;
        }
        setText(this, "#" + inputId, "#" + inputId + "_hidden");
        $("#" + inputId).change();
        clearWarehouseSearchUI(inputId);
      });
  }

  function resolveFieldContainer(input) {
    return (
      input.closest(".col-md-4, .col-md-2, .col-md-3, .col-md-6, .col-md-12") ||
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

  function validateLookupField(input) {
    var key = input.getAttribute("data-lookup-field");
    if (!key) return;

    var val = norm(input.value);
    if (key === "warehouse") {
      if (val === "") {
        setFieldError(input, key, "Warehouse field is required!");
      } else if (!warehouseSet[val]) {
        setFieldError(input, key, "Warehouse not found in database.");
      } else {
        setFieldError(input, key, "");
      }
    }

    if (key === "product_name") {
      if (val === "") {
        setFieldError(input, key, "Product Name field is required!");
      } else if (!productSet[val]) {
        setFieldError(input, key, "Product not found in database.");
      } else {
        setFieldError(input, key, "");
      }
    }
  }

  function validateSimpleRequiredField(input) {
    var field = input.getAttribute("data-required-field");
    if (!field) return;

    if (
      input.classList.contains("js-stock-warehouse-input") ||
      input.classList.contains("js-stock-live-search")
    ) {
      validateLookupField(input);
      return;
    }

    var value = String(input.value || "").trim();
    var requiredMessage =
      input.getAttribute("data-required-message") || "This field is required!";

    if (value === "") {
      setFieldError(input, field, requiredMessage);
      return;
    }

    if (field === "product_quantity" && Number(value) <= 0) {
      setFieldError(input, field, "Quantity must be greater than 0.");
      return;
    }

    setFieldError(input, field, "");
  }

  document
    .querySelectorAll(".js-stock-live-search[data-search-type][data-db-table]")
    .forEach(function (el) {
      var hidden = document.getElementById(el.id + "_hidden");
      var check = function () {
        validateLookupField(el);
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

      el.addEventListener("change", function () {
        if (el.value.trim() === "") {
          if (hidden) hidden.value = "";
          clearSearchList(el.id);
        }
        check();
      });

      el.addEventListener("input", check);

      // Check again right after the user clicks an autocomplete suggestion.
      el.addEventListener("blur", function () {
        setTimeout(function () {
          clearSearchList(el.id);
          check();
        }, 200);
      });

      if (hidden) {
        hidden.addEventListener("input", check);
        hidden.addEventListener("change", check);
      }
    });

  document
    .querySelectorAll(
      '.js-stock-warehouse-input[data-lookup-field="warehouse"]',
    )
    .forEach(function (el) {
      var check = function () {
        validateLookupField(el);
      };
      el.addEventListener("input", function () {
        showWarehouseSearchUI(el);
        check();
      });
      el.addEventListener("focus", function () {
        showWarehouseSearchUI(el);
      });
      el.addEventListener("change", function () {
        clearWarehouseSearchUI(el.id);
        check();
      });
      el.addEventListener("blur", function () {
        setTimeout(function () {
          clearWarehouseSearchUI(el.id);
          check();
        }, 120);
      });
    });

  document
    .querySelectorAll(".js-required-field[data-required-field]")
    .forEach(function (el) {
      el.addEventListener("input", function () {
        validateSimpleRequiredField(el);
      });
      el.addEventListener("change", function () {
        validateSimpleRequiredField(el);
      });
    });
})();
