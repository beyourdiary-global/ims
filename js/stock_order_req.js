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

  function escapeAttr(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
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

  function isProductInPackage(productId, pkg) {
    if (!pkg || productId <= 0) return false;
    if (!Array.isArray(pkg.product_ids) || pkg.product_ids.length === 0)
      return false;
    return pkg.product_ids.indexOf(productId) !== -1;
  }

  function getProductOptionsForPackage(pkgId) {
    var pkg = packageLookupById[parseInt(pkgId || 0, 10)] || null;
    if (!pkg || !Array.isArray(pkg.product_ids)) {
      return productOptions;
    }
    return productOptions.filter(function (p) {
      return pkg.product_ids.indexOf(p.id) !== -1;
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

  function getSelectedPackageInRow(row) {
    var pkgNameInput = row.querySelector(".sor-item-pkg-name");
    var pkgIdInput = row.querySelector(".sor-item-pkg-id");
    var pkgId = pkgIdInput ? parseInt(pkgIdInput.value || "0", 10) : 0;
    var pkg = null;

    if (pkgId > 0 && packageLookupById[pkgId]) {
      pkg = packageLookupById[pkgId];
    } else if (pkgNameInput) {
      pkg = packageLookupByName[normalizeText(pkgNameInput.value)] || null;
      if (pkg && pkgIdInput) {
        pkgIdInput.value = String(pkg.id);
      }
    }

    return pkg;
  }

  function getRowGroupId(row) {
    if (!row) return "";
    var groupId = row.getAttribute("data-package-group") || "";
    if (groupId !== "") return groupId;
    var rowKey = row.getAttribute("data-row-key") || Date.now().toString();
    groupId = "pkg_group_" + rowKey;
    row.setAttribute("data-package-group", groupId);
    return groupId;
  }

  function getRowsInGroup(row) {
    var groupId = getRowGroupId(row);
    if (!groupId) return [row];
    return Array.prototype.slice.call(
      document.querySelectorAll(
        '#sorItemBody tr[data-package-group="' + groupId + '"]',
      ),
    );
  }

  function isPackageRow(row) {
    return !!row && row.getAttribute("data-row-role") === "package";
  }

  function getPackageRowForGroup(groupId) {
    if (!groupId) return null;
    return document.querySelector(
      '#sorItemBody tr[data-package-group="' +
        groupId +
        '"][data-row-role="package"]',
    );
  }

  function ensurePackageHeaderForGroup(groupId) {
    if (!groupId) return;

    var groupRows = Array.prototype.slice.call(
      document.querySelectorAll(
        '#sorItemBody tr[data-package-group="' + groupId + '"]',
      ),
    );
    if (groupRows.length === 0) return;

    var existingHeader = groupRows.find(function (row) {
      return isPackageRow(row);
    });
    if (existingHeader) return;

    if (groupRows.length === 1) {
      var onlyRow = groupRows[0];
      var onlyProdName = onlyRow.querySelector(".sor-item-prod-name");
      var onlyProdId = onlyRow.querySelector(".sor-item-prod-id");
      var prodText = onlyProdName
        ? String(onlyProdName.value || "").trim()
        : "";
      var prodIdVal = onlyProdId ? parseInt(onlyProdId.value || "0", 10) : 0;
      if (prodText === "" && prodIdVal <= 0) {
        onlyRow.setAttribute("data-row-role", "package");
        return;
      }
    }

    var firstRow = groupRows[0];
    if (!firstRow) return;

    var pkgNameInput = firstRow.querySelector(".sor-item-pkg-name");
    var pkgIdInput = firstRow.querySelector(".sor-item-pkg-id");
    var descInput = firstRow.querySelector(".sor-item-desc");
    var qtyInput = firstRow.querySelector(".sor-item-qty");
    var packageQtyHidden = firstRow.querySelector(".sor-item-package-qty");

    var rowKey = Date.now().toString() + "_" + Math.floor(Math.random() * 1000);
    var headerRow = document.createElement("tr");
    headerRow.setAttribute("data-row-key", rowKey);
    headerRow.setAttribute("data-package-group", groupId);
    headerRow.setAttribute("data-row-role", "package");
    headerRow.innerHTML = buildPackageRowHtml(
      rowKey,
      pkgNameInput ? pkgNameInput.value : "",
      pkgIdInput ? pkgIdInput.value : "",
      "",
      "",
      descInput ? descInput.value : "",
      packageQtyHidden ? packageQtyHidden.value : qtyInput ? qtyInput.value : 1,
      "package",
    );

    firstRow.before(headerRow);
    bindRowAutocomplete(headerRow);

    groupRows.forEach(function (row) {
      row.setAttribute("data-row-role", "product");
    });
  }

  function syncGroupValues(baseRow) {
    var groupId = getRowGroupId(baseRow);
    var packageRow = getPackageRowForGroup(groupId);
    if (!packageRow) return;

    var rows = getRowsInGroup(packageRow);
    if (rows.length <= 1) return;

    var pkgNameInput = packageRow.querySelector(".sor-item-pkg-name");
    var pkgIdInput = packageRow.querySelector(".sor-item-pkg-id");
    var descInput = packageRow.querySelector(".sor-item-desc");
    var qtyInput = packageRow.querySelector(".sor-item-qty");
    var packageQtyHidden = packageRow.querySelector(".sor-item-package-qty");

    var pkgName = pkgNameInput ? pkgNameInput.value : "";
    var pkgId = pkgIdInput ? pkgIdInput.value : "";
    var desc = descInput ? descInput.value : "";
    var packageQty = qtyInput ? parseInt(qtyInput.value || "0", 10) : 0;
    if (isNaN(packageQty) || packageQty <= 0) packageQty = 1;
    if (qtyInput) qtyInput.value = String(packageQty);
    if (packageQtyHidden) packageQtyHidden.value = String(packageQty);

    rows.forEach(function (row) {
      if (row === packageRow) {
        var headerBaseQty = row.querySelector(".sor-item-base-qty");
        if (headerBaseQty) headerBaseQty.value = "1";
        return;
      }
      var rowPkgName = row.querySelector(".sor-item-pkg-name");
      var rowPkgId = row.querySelector(".sor-item-pkg-id");
      var rowDesc = row.querySelector(".sor-item-desc");
      var rowQty = row.querySelector(".sor-item-qty");
      var rowPackageQtyHidden = row.querySelector(".sor-item-package-qty");
      var rowBaseQty = row.querySelector(".sor-item-base-qty");

      if (rowPkgName) rowPkgName.value = pkgName;
      if (rowPkgId) rowPkgId.value = pkgId;
      if (rowDesc) rowDesc.value = desc;
      if (rowPackageQtyHidden) rowPackageQtyHidden.value = String(packageQty);
      if (rowQty) {
        var baseQty = rowBaseQty ? parseInt(rowBaseQty.value || "0", 10) : 1;
        if (isNaN(baseQty) || baseQty <= 0) baseQty = 1;
        rowQty.value = String(baseQty * packageQty);
      }
    });
  }

  function renderPackageGroups() {
    document.querySelectorAll("#sorItemBody tr").forEach(function (row) {
      ensurePackageHeaderForGroup(getRowGroupId(row));
    });

    var groups = {};
    document.querySelectorAll("#sorItemBody tr").forEach(function (row) {
      var groupId = getRowGroupId(row);
      if (!groups[groupId]) groups[groupId] = [];
      groups[groupId].push(row);
    });

    Object.keys(groups).forEach(function (groupId) {
      var rows = groups[groupId];
      var packageRow = rows.find(function (row) {
        return isPackageRow(row);
      });

      if (packageRow && rows[0] !== packageRow) {
        rows[0].before(packageRow);
      }

      var orderedRows = Array.prototype.slice.call(
        document.querySelectorAll(
          '#sorItemBody tr[data-package-group="' + groupId + '"]',
        ),
      );

      var productNo = 1;
      orderedRows.forEach(function (row) {
        var rowRole = isPackageRow(row) ? "package" : "product";

        row.classList.remove(
          "sor-group-primary",
          "sor-group-follow",
          "sor-group-package-row",
          "sor-group-product-row",
        );
        row.classList.add(
          rowRole === "package" ? "sor-group-primary" : "sor-group-follow",
        );
        row.classList.add(
          rowRole === "package"
            ? "sor-group-package-row"
            : "sor-group-product-row",
        );

        var rowNo = row.querySelector(".row-no");
        if (rowNo) {
          rowNo.textContent = rowRole === "package" ? "" : String(productNo++);
        }

        var prodNameInput = row.querySelector(".sor-item-prod-name");
        var prodIdInput = row.querySelector(".sor-item-prod-id");
        if (prodNameInput && prodIdInput) {
          if (rowRole === "package") {
            prodNameInput.value = "";
            prodIdInput.value = "";
            prodNameInput.readOnly = true;
          } else {
            prodNameInput.readOnly = false;
          }
        }

        var qtyInput = row.querySelector(".sor-item-qty");
        if (qtyInput) {
          qtyInput.readOnly = rowRole !== "package";
        }

        var removeBtn = row.querySelector(
          ".remove-item-btn, .remove-package-btn, .remove-product-btn",
        );
        if (removeBtn) {
          removeBtn.classList.remove(
            "remove-item-btn",
            "remove-package-btn",
            "remove-product-btn",
          );
          if (rowRole === "package") {
            removeBtn.classList.add("remove-package-btn");
            removeBtn.textContent = "Remove Package";
          } else {
            removeBtn.classList.add("remove-product-btn");
            removeBtn.textContent = "Remove Product";
          }
        }
      });

      if (packageRow) {
        syncGroupValues(packageRow);
      }
    });
  }

  function clearPackageSelection(row) {
    var pkgNameInput = row.querySelector(".sor-item-pkg-name");
    var pkgIdInput = row.querySelector(".sor-item-pkg-id");
    var descInput = row.querySelector(".sor-item-desc");
    if (pkgNameInput) pkgNameInput.value = "";
    if (pkgIdInput) pkgIdInput.value = "";
    if (descInput) descInput.value = "";
  }

  function validateProductBelongsPackage(row) {
    var product = getSelectedProductInRow(row);
    var pkg = getSelectedPackageInRow(row);
    var prodNameInput = row.querySelector(".sor-item-prod-name");
    if (!prodNameInput) return;

    if (!product || !pkg) {
      prodNameInput.setCustomValidity("");
      return;
    }

    if (!isProductInPackage(product.id, pkg)) {
      prodNameInput.setCustomValidity(
        "Selected product does not belong to selected package.",
      );
    } else {
      prodNameInput.setCustomValidity("");
    }
  }

  function updateRowDescAndPrice(row) {
    var product = getSelectedProductInRow(row);
    var prodNameInput = row.querySelector(".sor-item-prod-name");
    var prodIdInput = row.querySelector(".sor-item-prod-id");
    var pkgNameInput = row.querySelector(".sor-item-pkg-name");
    var pkgIdInput = row.querySelector(".sor-item-pkg-id");
    var descInput = row.querySelector(".sor-item-desc");
    var totalInput = row.querySelector(".sor-item-total");

    var pkg = getSelectedPackageInRow(row);
    if (pkgNameInput && pkg) {
      pkgNameInput.value = pkg.name || pkgNameInput.value;
    }
    if (pkgIdInput && pkg) {
      pkgIdInput.value = String(pkg.id);
    }
    if (descInput) {
      descInput.value = pkg ? pkg.item_description || "" : "";
    }
    if (totalInput) {
      var unitPrice = pkg ? parseFloat(pkg.price || "0") : 0;
      totalInput.value = !isNaN(unitPrice) ? unitPrice.toFixed(2) : "0.00";
    }

    if (prodNameInput && prodIdInput) {
      if (product) {
        prodIdInput.value = String(product.id);
      } else {
        prodIdInput.value = "";
      }
    }

    if (
      !isPackageRow(row) &&
      pkg &&
      !product &&
      Array.isArray(pkg.product_ids) &&
      pkg.product_ids.length > 0
    ) {
      var defaultProdId = parseInt(pkg.product_ids[0], 10);
      if (defaultProdId > 0 && productLookupById[defaultProdId]) {
        if (prodIdInput) prodIdInput.value = String(defaultProdId);
        if (prodNameInput)
          prodNameInput.value = productLookupById[defaultProdId].name;
      }
    }

    validateProductBelongsPackage(row);
    syncGroupValues(row);
  }

  function recalculateTotalPrice() {
    var total = 0;
    document.querySelectorAll("#sorItemBody tr").forEach(function (row) {
      if (!isPackageRow(row)) return;

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
    renderPackageGroups();
  }

  function bindRowAutocomplete(row) {
    var prodNameInput = row.querySelector(".sor-item-prod-name");
    var prodIdInput = row.querySelector(".sor-item-prod-id");
    var nameInput = row.querySelector(".sor-item-pkg-name");
    var idInput = row.querySelector(".sor-item-pkg-id");
    if (nameInput && idInput) {
      bindTextAutocomplete(nameInput, idInput, packageOptions, function () {
        updateRowDescAndPrice(row);
        expandPackageProducts(row);
        recalculateTotalPrice();
      });

      nameInput.addEventListener("focus", function () {
        validateProductBelongsPackage(row);
      });
    }

    if (prodNameInput && prodIdInput && !isPackageRow(row)) {
      bindTextAutocomplete(
        prodNameInput,
        prodIdInput,
        function () {
          var pkg = getSelectedPackageInRow(row);
          return getProductOptionsForPackage(pkg ? pkg.id : 0);
        },
        function () {
          updateRowDescAndPrice(row);
          recalculateTotalPrice();
        },
      );
    }
  }

  function buildPackageRowHtml(
    rowKey,
    pkgName,
    pkgId,
    prodName,
    prodId,
    desc,
    qty,
    rowRole,
  ) {
    var isPackageHeader = rowRole === "package";
    return (
      '<td class="row-no"></td>' +
      '<td class="cell-package"><div class="pkg-main-fields"><div class="autocomplete"><input class="form-control sor-item-pkg-name" type="text" id="sor_item_pkg_name_' +
      rowKey +
      '" name="sor_item_pkg_name[]" placeholder="Type Package" value="' +
      escapeAttr(pkgName || "") +
      '"><input type="hidden" class="sor-item-pkg-id" id="sor_item_pkg_id_' +
      rowKey +
      '" name="sor_item_pkg_id[]" value="' +
      escapeAttr(pkgId || "") +
      '"></div></div></td>' +
      '<td><div class="autocomplete"><input class="form-control sor-item-prod-name" type="text" id="sor_item_prod_name_' +
      rowKey +
      '" name="sor_item_prod_name[]" value="' +
      escapeAttr(prodName || "") +
      '" ' +
      (isPackageHeader ? "readonly" : "") +
      '"><input type="hidden" class="sor-item-prod-id" id="sor_item_prod_id_' +
      rowKey +
      '" name="sor_item_prod_id[]" value="' +
      escapeAttr(prodId || "") +
      '"></div></td>' +
      '<td class="cell-desc"><div class="desc-main-field"><input class="form-control sor-item-desc" type="text" name="sor_item_desc[]" readonly value="' +
      escapeAttr(desc || "") +
      '"></div></td>' +
      '<td><div class="qty-main-field"><input class="form-control sor-item-qty" type="number" min="1" name="sor_item_product_qty[]" value="' +
      escapeAttr(qty || 1) +
      '" ' +
      (isPackageHeader ? "" : "readonly") +
      '"><input class="sor-item-package-qty" type="hidden" name="sor_item_package_qty[]" value="' +
      escapeAttr(qty || 1) +
      '"><input class="sor-item-base-qty" type="hidden" name="sor_item_base_qty[]" value="' +
      (isPackageHeader ? "1" : "1") +
      '"></div></td>' +
      '<td class="cell-total"><div class="total-main-field"><input class="form-control sor-item-total" type="text" value="0.00" readonly></div></td>' +
      '<td><button type="button" class="btn btn-sm btn-rounded btn-primary ' +
      (isPackageHeader ? "remove-package-btn" : "remove-product-btn") +
      '">' +
      (isPackageHeader ? "Remove Package" : "Remove Product") +
      "</button></td>"
    );
  }

  function addPackageHeaderRow(groupId) {
    var tbody = document.getElementById("sorItemBody");
    if (!tbody) return null;

    var rowKey = Date.now().toString() + "_" + Math.floor(Math.random() * 1000);
    var tr = document.createElement("tr");
    tr.setAttribute("data-row-key", rowKey);
    tr.setAttribute("data-package-group", groupId || "pkg_group_" + rowKey);
    tr.setAttribute("data-row-role", "package");
    tr.innerHTML = buildPackageRowHtml(
      rowKey,
      "",
      "",
      "",
      "",
      "",
      1,
      "package",
    );
    tbody.appendChild(tr);
    bindRowAutocomplete(tr);
    return tr;
  }

  function addPackageRowWithData(pkg, prodId, groupId, rowRole) {
    var tbody = document.getElementById("sorItemBody");
    if (!tbody || !pkg) return;

    var product = productLookupById[parseInt(prodId || 0, 10)] || null;
    var role = rowRole === "package" ? "package" : "product";
    var rowKey = Date.now().toString() + "_" + Math.floor(Math.random() * 1000);
    var tr = document.createElement("tr");
    tr.setAttribute("data-row-key", rowKey);
    tr.setAttribute("data-package-group", groupId || "pkg_group_" + rowKey);
    tr.setAttribute("data-row-role", role);
    tr.innerHTML = buildPackageRowHtml(
      rowKey,
      pkg.name || "",
      pkg.id || "",
      role === "package" ? "" : product ? product.name : "",
      role === "package" ? "" : product ? product.id : "",
      pkg.item_description || "",
      1,
      role,
    );
    tbody.appendChild(tr);
    bindRowAutocomplete(tr);
    updateRowDescAndPrice(tr);
    renderPackageGroups();
  }

  function expandPackageProducts(row) {
    if (!isPackageRow(row)) return;

    var pkg = getSelectedPackageInRow(row);
    var groupId = getRowGroupId(row);

    var sig =
      pkg && Array.isArray(pkg.product_ids)
        ? String(pkg.id) + ":" + pkg.product_ids.join(",")
        : "";
    if (row.getAttribute("data-auto-expanded-sig") === sig) {
      return;
    }
    row.setAttribute("data-auto-expanded-sig", sig);
    row.setAttribute("data-package-group", groupId);

    document
      .querySelectorAll(
        '#sorItemBody tr[data-package-group="' +
          groupId +
          '"][data-row-role="product"]',
      )
      .forEach(function (productRow) {
        productRow.remove();
      });

    updateRowDescAndPrice(row);

    if (
      !pkg ||
      !Array.isArray(pkg.product_ids) ||
      pkg.product_ids.length === 0
    ) {
      reindexRows();
      renderPackageGroups();
      return;
    }

    pkg.product_ids.forEach(function (pid) {
      var extraProdId = parseInt(pid, 10);
      if (extraProdId > 0) {
        addPackageRowWithData(pkg, extraProdId, groupId, "product");
      }
    });

    reindexRows();
    renderPackageGroups();
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
    getRowGroupId(row);
    if (!row.getAttribute("data-row-role")) {
      row.setAttribute("data-row-role", "product");
    }
    bindRowAutocomplete(row);
    updateRowDescAndPrice(row);
  });
  renderPackageGroups();
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
        renderPackageGroups();
        recalculateTotalPrice();
      }
      if (e.target.classList.contains("sor-item-qty")) {
        if (isPackageRow(row)) {
          syncGroupValues(row);
        } else {
          var pkgRow = getPackageRowForGroup(getRowGroupId(row));
          if (pkgRow) {
            var pkgQtyInput = pkgRow.querySelector(".sor-item-qty");
            var baseQtyInput = row.querySelector(".sor-item-base-qty");
            var productQty = parseInt(e.target.value || "0", 10);
            var packageQty = pkgQtyInput
              ? parseInt(pkgQtyInput.value || "0", 10)
              : 1;
            if (isNaN(packageQty) || packageQty <= 0) packageQty = 1;
            if (isNaN(productQty) || productQty <= 0) productQty = packageQty;
            if (baseQtyInput) {
              baseQtyInput.value = String(
                Math.max(1, Math.round(productQty / packageQty)),
              );
            }
            syncGroupValues(pkgRow);
          }
        }
        recalculateTotalPrice();
      }
    });

    sorItemBody.addEventListener("input", function (e) {
      if (e.target.classList.contains("sor-item-prod-name")) {
        var row = e.target.closest("tr");
        if (!row) return;
        updateRowDescAndPrice(row);
        recalculateTotalPrice();
      }
      if (e.target.classList.contains("sor-item-pkg-name")) {
        updateRowDescAndPrice(e.target.closest("tr"));
        renderPackageGroups();
        recalculateTotalPrice();
      }
      if (e.target.classList.contains("sor-item-pkg-id")) {
        updateRowDescAndPrice(e.target.closest("tr"));
        renderPackageGroups();
        recalculateTotalPrice();
      }
      if (e.target.classList.contains("sor-item-qty")) {
        var qtyRow = e.target.closest("tr");
        if (qtyRow && isPackageRow(qtyRow)) {
          syncGroupValues(qtyRow);
        }
        recalculateTotalPrice();
      }
    });

    sorItemBody.addEventListener("click", function (e) {
      if (e.target.classList.contains("remove-package-btn")) {
        var baseRow = e.target.closest("tr");
        if (!baseRow) return;
        var groupRows = getRowsInGroup(baseRow);
        groupRows.forEach(function (row) {
          row.remove();
        });

        if (document.querySelectorAll("#sorItemBody tr").length === 0) {
          addPackageHeaderRow("pkg_group_" + Date.now().toString());
        }

        reindexRows();
        recalculateTotalPrice();
      } else if (
        e.target.classList.contains("remove-product-btn") ||
        e.target.classList.contains("remove-item-btn")
      ) {
        var removeRow = e.target.closest("tr");
        if (!removeRow || isPackageRow(removeRow)) return;

        var groupId = getRowGroupId(removeRow);
        var groupProducts = document.querySelectorAll(
          '#sorItemBody tr[data-package-group="' +
            groupId +
            '"][data-row-role="product"]',
        );
        if (groupProducts.length > 0) {
          removeRow.remove();
          reindexRows();
          recalculateTotalPrice();
        }
      }
    });
  }

  function bindStandaloneAutocomplete(row) {
    var prodNameInput = row.querySelector(".sor-standalone-prod-name");
    var prodIdInput = row.querySelector(".sor-standalone-prod-id");
    if (!prodNameInput || !prodIdInput) return;

    bindTextAutocomplete(prodNameInput, prodIdInput, productOptions);
  }

  function reindexStandaloneRows() {
    var rows = document.querySelectorAll("#sorStandaloneBody tr");
    rows.forEach(function (row, idx) {
      var no = row.querySelector(".row-no");
      if (no) no.textContent = String(idx + 1);
    });
  }

  document.querySelectorAll("#sorStandaloneBody tr").forEach(function (row) {
    bindStandaloneAutocomplete(row);
  });

  var standaloneBody = document.getElementById("sorStandaloneBody");
  if (standaloneBody) {
    standaloneBody.addEventListener("click", function (e) {
      if (e.target.classList.contains("remove-standalone-btn")) {
        var rowCount = document.querySelectorAll(
          "#sorStandaloneBody tr",
        ).length;
        if (rowCount > 1) {
          e.target.closest("tr").remove();
          reindexStandaloneRows();
        }
      }
    });
  }

  var addRowBtn = document.getElementById("addItemRowBtn");
  if (addRowBtn) {
    addRowBtn.addEventListener("click", function () {
      addPackageHeaderRow("pkg_group_" + Date.now().toString());
      reindexRows();
      renderPackageGroups();
      recalculateTotalPrice();
    });
  }

  var addStandaloneBtn = document.getElementById("addStandaloneRowBtn");
  if (addStandaloneBtn) {
    addStandaloneBtn.addEventListener("click", function () {
      var tbody = document.getElementById("sorStandaloneBody");
      if (!tbody) return;

      var rowKey = "st_" + Date.now().toString();
      var tr = document.createElement("tr");
      tr.setAttribute("data-row-key", rowKey);
      tr.innerHTML =
        '<td class="row-no"></td>' +
        '<td><div class="autocomplete"><input class="form-control sor-standalone-prod-name" type="text" id="sor_standalone_prod_name_' +
        rowKey +
        '" name="sor_standalone_prod_name[]" ><input type="hidden" class="sor-standalone-prod-id" id="sor_standalone_prod_id_' +
        rowKey +
        '" name="sor_standalone_prod_id[]" value=""></div></td>' +
        '<td><input class="form-control sor-standalone-qty" type="number" min="1" name="sor_standalone_qty[]" value="1"></td>' +
        '<td><button type="button" class="btn btn-sm btn-rounded btn-primary remove-standalone-btn">Remove</button></td>';

      tbody.appendChild(tr);
      bindStandaloneAutocomplete(tr);
      reindexStandaloneRows();
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
