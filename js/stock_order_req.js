(function () {
  var config = window.stockOrderReqConfig || {};
  var page = config.page || "Stock Order Request";
  var siteURL = config.siteURL || "";
  var action = config.action || "";

  checkCurrentPage(page, action);
  centerAlignment("formContainer");
  setButtonColor();
  preloader(300, action);

  function normalizeText(text) {
    return String(text || "")
      .toLowerCase()
      .replace(/\s+/g, " ")
      .trim();
  }

  function closeAutocomplete(input) {
    if (!input) return;
    var listId = input.getAttribute("data-list-id");
    if (!listId) return;
    var list = document.getElementById(listId);
    if (list) list.remove();
  }

  function renderAutocompleteList(input, hiddenInput, options, onSelect) {
    closeAutocomplete(input);

    var wrapper = input.closest(".autocomplete");
    if (!wrapper || input.hasAttribute("readonly")) return;

    var keyword = normalizeText(input.value);
    if (keyword === "") {
      return;
    }

    var filtered = (options || [])
      .filter(function (opt) {
        return normalizeText(opt.name).indexOf(keyword) !== -1;
      })
      .slice(0, 20);

    if (filtered.length === 0) {
      return;
    }

    var listId = "searchResult_" + input.id;
    input.setAttribute("data-list-id", listId);

    var ul = document.createElement("ul");
    ul.className = "searchResult";
    ul.id = listId;
    ul.style.width = input.offsetWidth + "px";

    filtered.forEach(function (opt) {
      var li = document.createElement("li");
      li.setAttribute("value", String(opt.id));
      li.textContent = opt.name;
      li.addEventListener("mousedown", function (e) {
        e.preventDefault();
        input.value = opt.name;
        hiddenInput.value = String(opt.id);
        hiddenInput.dispatchEvent(new Event("input", { bubbles: true }));
        input.dispatchEvent(new Event("change", { bubbles: true }));
        closeAutocomplete(input);
        if (typeof onSelect === "function") onSelect(opt);
      });
      ul.appendChild(li);
    });

    input.after(ul);
  }

  function bindTextAutocomplete(input, hiddenInput, optionsOrGetter, onSelect) {
    if (!input || !hiddenInput || input.dataset.autocompleteBound === "1") {
      return;
    }

    input.dataset.autocompleteBound = "1";
    var wrapper = input.closest(".autocomplete");

    input.addEventListener("input", function () {
      hiddenInput.value = "";
      var options =
        typeof optionsOrGetter === "function"
          ? optionsOrGetter()
          : optionsOrGetter;
      renderAutocompleteList(input, hiddenInput, options, onSelect);
    });

    input.addEventListener("blur", function () {
      setTimeout(function () {
        closeAutocomplete(input);
      }, 120);
    });

    document.addEventListener("click", function (e) {
      if (wrapper && !wrapper.contains(e.target)) {
        closeAutocomplete(input);
      }
    });
  }

  var warehouseOptions = (config.warehouses || []).map(function (w) {
    return { id: parseInt(w.id, 10), name: String(w.name || "") };
  });
  var courierOptions = (config.couriers || []).map(function (c) {
    return { id: parseInt(c.id, 10), name: String(c.name || "") };
  });
  var productOptions = (config.products || []).map(function (p) {
    return { id: parseInt(p.id, 10), name: String(p.name || "") };
  });
  var packageOptions = (config.packages || []).map(function (p) {
    var prodIds = Array.isArray(p.product_ids)
      ? p.product_ids
          .map(function (x) {
            return parseInt(x, 10);
          })
          .filter(function (x) {
            return !isNaN(x) && x > 0;
          })
      : [];
    return {
      id: parseInt(p.id, 10),
      name: String(p.name || ""),
      item_description: String(p.item_description || ""),
      price: String(p.price || "0"),
      product_ids: prodIds,
    };
  });

  var warehouseLookupById = {};
  var courierLookupById = {};
  var productLookupById = {};
  var productLookupByName = {};
  var packageLookupById = {};
  var packageLookupByName = {};

  warehouseOptions.forEach(function (opt) {
    warehouseLookupById[opt.id] = opt;
  });
  courierOptions.forEach(function (opt) {
    courierLookupById[opt.id] = opt;
  });
  productOptions.forEach(function (opt) {
    productLookupById[opt.id] = opt;
    productLookupByName[normalizeText(opt.name)] = opt;
  });
  packageOptions.forEach(function (opt) {
    packageLookupById[opt.id] = opt;
    packageLookupByName[normalizeText(opt.name)] = opt;
  });

  function isPackageAllowedForProduct(pkg, productId) {
    if (!pkg || productId <= 0) return false;
    if (!Array.isArray(pkg.product_ids) || pkg.product_ids.length === 0) {
      return false;
    }
    return pkg.product_ids.indexOf(productId) !== -1;
  }

  function getPackageOptionsForProduct(productId) {
    if (!productId || productId <= 0) {
      return [];
    }
    return packageOptions.filter(function (pkg) {
      return isPackageAllowedForProduct(pkg, productId);
    });
  }

  function getSelectedProductInRow(row) {
    var prodNameInput = row.querySelector(".sor-item-prod-name");
    var prodIdInput = row.querySelector(".sor-item-prod-id");
    var productId = prodIdInput ? parseInt(prodIdInput.value || "0", 10) : 0;
    var product = null;

    if (productId > 0 && productLookupById[productId]) {
      product = productLookupById[productId];
    } else if (prodNameInput) {
      product = productLookupByName[normalizeText(prodNameInput.value)] || null;
      if (product && prodIdInput) {
        prodIdInput.value = String(product.id);
      }
    }

    return product;
  }

  function clearPackageSelection(row) {
    var pkgNameInput = row.querySelector(".sor-item-pkg-name");
    var pkgIdInput = row.querySelector(".sor-item-pkg-id");
    var descInput = row.querySelector(".sor-item-desc");
    if (pkgNameInput) pkgNameInput.value = "";
    if (pkgIdInput) pkgIdInput.value = "";
    if (descInput) descInput.value = "";
  }

  function validatePackageAvailability(row) {
    var product = getSelectedProductInRow(row);
    var pkgNameInput = row.querySelector(".sor-item-pkg-name");
    if (!pkgNameInput) return;

    if (!product) {
      pkgNameInput.setCustomValidity("");
      return;
    }

    var allowedPackages = getPackageOptionsForProduct(product.id);
    if (allowedPackages.length === 0) {
      pkgNameInput.setCustomValidity(
        "Selected product does not exist in any package, please add product into package first.",
      );
    } else {
      pkgNameInput.setCustomValidity("");
    }
  }

  function updateRowDescAndPrice(row) {
    var product = getSelectedProductInRow(row);
    var productId = product ? product.id : 0;
    var prodNameInput = row.querySelector(".sor-item-prod-name");
    var prodIdInput = row.querySelector(".sor-item-prod-id");
    var pkgNameInput = row.querySelector(".sor-item-pkg-name");
    var pkgIdInput = row.querySelector(".sor-item-pkg-id");
    var descInput = row.querySelector(".sor-item-desc");

    if (prodNameInput && prodIdInput) {
      if (product) {
        prodIdInput.value = String(product.id);
      } else {
        prodIdInput.value = "";
      }
    }

    var name = pkgNameInput ? pkgNameInput.value.trim() : "";
    var pkgId = pkgIdInput ? parseInt(pkgIdInput.value || "0", 10) : 0;
    var match = null;
    var allowedPackages = getPackageOptionsForProduct(productId);

    validatePackageAvailability(row);

    if (pkgId > 0 && packageLookupById[pkgId]) {
      if (isPackageAllowedForProduct(packageLookupById[pkgId], productId)) {
        match = packageLookupById[pkgId];
      }
      if (match && pkgNameInput && pkgNameInput.value.trim() === "") {
        pkgNameInput.value = match.name || "";
      }
    } else {
      var normalized = normalizeText(name);
      match =
        allowedPackages.find(function (pkg) {
          return normalizeText(pkg.name) === normalized;
        }) || null;
    }

    if (pkgIdInput) {
      pkgIdInput.value = match ? String(match.id) : "";
    }
    if (descInput) {
      descInput.value = match ? match.item_description || "" : "";
    }
  }

  function recalculateTotalPrice() {
    var total = 0;
    document.querySelectorAll("#sorItemBody tr").forEach(function (row) {
      var pkgIdInput = row.querySelector(".sor-item-pkg-id");
      var qtyInput = row.querySelector(".sor-item-qty");
      if (!pkgIdInput || !qtyInput) return;

      var pkgId = parseInt(pkgIdInput.value || "0", 10);
      var pkgData = packageLookupById[pkgId] || null;
      var price = pkgData ? parseFloat(pkgData.price || "0") : 0;
      var qty = parseInt(qtyInput.value || "0", 10);

      if (!isNaN(price) && !isNaN(qty) && qty > 0) {
        total += price * qty;
      }
    });

    var totalInput = document.getElementById("sor_total_price");
    if (totalInput) totalInput.value = total.toFixed(2);
  }

  function reindexRows() {
    var rows = document.querySelectorAll("#sorItemBody tr");
    rows.forEach(function (row, idx) {
      var no = row.querySelector(".row-no");
      if (no) no.textContent = String(idx + 1);
    });
  }

  function bindRowAutocomplete(row) {
    var prodNameInput = row.querySelector(".sor-item-prod-name");
    var prodIdInput = row.querySelector(".sor-item-prod-id");
    var nameInput = row.querySelector(".sor-item-pkg-name");
    var idInput = row.querySelector(".sor-item-pkg-id");
    if (!prodNameInput || !prodIdInput || !nameInput || !idInput) return;

    bindTextAutocomplete(
      prodNameInput,
      prodIdInput,
      productOptions,
      function () {
        clearPackageSelection(row);
        validatePackageAvailability(row);
        updateRowDescAndPrice(row);
        recalculateTotalPrice();
      },
    );

    bindTextAutocomplete(
      nameInput,
      idInput,
      function () {
        var product = getSelectedProductInRow(row);
        return getPackageOptionsForProduct(product ? product.id : 0);
      },
      function () {
        validatePackageAvailability(row);
        updateRowDescAndPrice(row);
        recalculateTotalPrice();
      },
    );

    nameInput.addEventListener("focus", function () {
      validatePackageAvailability(row);
      if (nameInput.validationMessage) {
        nameInput.reportValidity();
      }
    });
  }

  var warehouseNameInput = document.getElementById("sor_warehouse_name");
  var warehouseIdInput = document.getElementById("sor_warehouse");
  var courierNameInput = document.getElementById("sor_courier_name");
  var courierIdInput = document.getElementById("sor_courier");

  bindTextAutocomplete(warehouseNameInput, warehouseIdInput, warehouseOptions);
  bindTextAutocomplete(courierNameInput, courierIdInput, courierOptions);

  if (
    warehouseIdInput &&
    parseInt(warehouseIdInput.value || "0", 10) > 0 &&
    warehouseNameInput &&
    warehouseNameInput.value.trim() === ""
  ) {
    var wh = warehouseLookupById[parseInt(warehouseIdInput.value, 10)] || null;
    if (wh) warehouseNameInput.value = wh.name;
  }

  if (
    courierIdInput &&
    parseInt(courierIdInput.value || "0", 10) > 0 &&
    courierNameInput &&
    courierNameInput.value.trim() === ""
  ) {
    var cr = courierLookupById[parseInt(courierIdInput.value, 10)] || null;
    if (cr) courierNameInput.value = cr.name;
  }

  document.querySelectorAll("#sorItemBody tr").forEach(function (row) {
    bindRowAutocomplete(row);
    updateRowDescAndPrice(row);
  });
  recalculateTotalPrice();

  var sorItemBody = document.getElementById("sorItemBody");
  if (sorItemBody) {
    sorItemBody.addEventListener("change", function (e) {
      var row = e.target.closest("tr");
      if (!row) return;

      if (
        e.target.classList.contains("sor-item-prod-name") ||
        e.target.classList.contains("sor-item-prod-id") ||
        e.target.classList.contains("sor-item-pkg-name") ||
        e.target.classList.contains("sor-item-pkg-id")
      ) {
        updateRowDescAndPrice(row);
        recalculateTotalPrice();
      }
      if (e.target.classList.contains("sor-item-qty")) {
        recalculateTotalPrice();
      }
    });

    sorItemBody.addEventListener("input", function (e) {
      if (e.target.classList.contains("sor-item-prod-name")) {
        var row = e.target.closest("tr");
        if (!row) return;
        clearPackageSelection(row);
        validatePackageAvailability(row);
        updateRowDescAndPrice(row);
        recalculateTotalPrice();
      }
      if (e.target.classList.contains("sor-item-pkg-name")) {
        updateRowDescAndPrice(e.target.closest("tr"));
        recalculateTotalPrice();
      }
      if (e.target.classList.contains("sor-item-pkg-id")) {
        updateRowDescAndPrice(e.target.closest("tr"));
        recalculateTotalPrice();
      }
      if (e.target.classList.contains("sor-item-qty")) {
        recalculateTotalPrice();
      }
    });

    sorItemBody.addEventListener("click", function (e) {
      if (e.target.classList.contains("remove-item-btn")) {
        var rowCount = document.querySelectorAll("#sorItemBody tr").length;
        if (rowCount > 1) {
          e.target.closest("tr").remove();
          reindexRows();
          recalculateTotalPrice();
        }
      }
    });
  }

  var addRowBtn = document.getElementById("addItemRowBtn");
  if (addRowBtn) {
    addRowBtn.addEventListener("click", function () {
      var tbody = document.getElementById("sorItemBody");
      if (!tbody) return;

      var rowKey = Date.now().toString();
      var tr = document.createElement("tr");
      tr.setAttribute("data-row-key", rowKey);
      tr.innerHTML =
        '<td class="row-no"></td>' +
        '<td><div class="autocomplete"><input class="form-control sor-item-prod-name" type="text" id="sor_item_prod_name_' +
        rowKey +
        '" name="sor_item_prod_name[]" placeholder="Type Product Name"><input type="hidden" class="sor-item-prod-id" id="sor_item_prod_id_' +
        rowKey +
        '" name="sor_item_prod_id[]" value=""></div></td>' +
        '<td><div class="autocomplete"><input class="form-control sor-item-pkg-name" type="text" id="sor_item_pkg_name_' +
        rowKey +
        '" name="sor_item_pkg_name[]" placeholder="Type Package"><input type="hidden" class="sor-item-pkg-id" id="sor_item_pkg_id_' +
        rowKey +
        '" name="sor_item_pkg_id[]" value=""></div></td>' +
        '<td><input class="form-control sor-item-desc" type="text" name="sor_item_desc[]" readonly></td>' +
        '<td><input class="form-control sor-item-qty" type="number" min="1" name="sor_item_qty[]" value="1"></td>' +
        '<td><button type="button" class="btn btn-sm btn-rounded btn-primary remove-item-btn">Remove</button></td>';

      tbody.appendChild(tr);
      bindRowAutocomplete(tr);
      reindexRows();
      recalculateTotalPrice();
    });
  }

  if (config.showQrPanel) {
    var copyBtn = document.getElementById("copyOrderLinkBtn");
    var orderLinkInput = document.getElementById("sorOrderLink");
    if (copyBtn && orderLinkInput) {
      copyBtn.addEventListener("click", function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(orderLinkInput.value);
        } else {
          orderLinkInput.focus();
          orderLinkInput.select();
          document.execCommand("copy");
        }
      });
    }

    var sec = 15;
    var target = config.redirectPage || "";
    var cd = document.getElementById("countdownSec");
    var goBtn = document.getElementById("goNowBtn");
    var timer = setInterval(function () {
      sec -= 1;
      if (cd) cd.textContent = String(sec);
      if (sec <= 0) {
        clearInterval(timer);
        if (target !== "") {
          window.location.href = target;
        }
      }
    }, 1000);

    if (goBtn) {
      goBtn.addEventListener("click", function () {
        clearInterval(timer);
        if (target !== "") {
          window.location.href = target;
        }
      });
    }
  }
})();
