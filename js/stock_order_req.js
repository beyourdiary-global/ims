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
    return { id: String(c.id || "").trim(), name: String(c.name || "") };
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
    var hiddenGroupInput = row.querySelector(".sor-item-group-key");
    var hiddenGroup = hiddenGroupInput
      ? String(hiddenGroupInput.value || "")
      : "";
    var groupId = hiddenGroup || row.getAttribute("data-package-group") || "";
    if (groupId !== "") return groupId;
    var rowKey = row.getAttribute("data-row-key") || Date.now().toString();
    groupId = "pkg_group_" + rowKey;
    row.setAttribute("data-package-group", groupId);
    if (hiddenGroupInput) hiddenGroupInput.value = groupId;
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

  function syncGroupValues(baseRow, forceReprice) {
    var groupId = getRowGroupId(baseRow);
    var packageRow = getPackageRowForGroup(groupId);
    if (!packageRow) return;

    var rows = getRowsInGroup(packageRow);
    if (rows.length <= 1) return;

    var pkgNameInput = packageRow.querySelector(".sor-item-pkg-name");
    var pkgIdInput = packageRow.querySelector(".sor-item-pkg-id");
    var descInput = packageRow.querySelector(".sor-item-desc");
    var packageQtyEditInput = packageRow.querySelector(
      ".sor-item-package-qty-edit",
    );
    var qtyInput = packageRow.querySelector(".sor-item-qty");
    var packageQtyHidden = packageRow.querySelector(".sor-item-package-qty");
    var packagePriceHidden = packageRow.querySelector(
      ".sor-item-package-price",
    );
    var totalInput = packageRow.querySelector(".sor-item-total");

    var pkgName = pkgNameInput ? pkgNameInput.value : "";
    var pkgId = pkgIdInput ? pkgIdInput.value : "";
    var desc = descInput ? descInput.value : "";
    var packageQtySourceInput = packageQtyEditInput || qtyInput;
    var packageQty = packageQtySourceInput
      ? parseInt(packageQtySourceInput.value || "0", 10)
      : 0;
    if (isNaN(packageQty) || packageQty <= 0) packageQty = 1;
    if (packageQtyEditInput) packageQtyEditInput.value = String(packageQty);
    if (qtyInput) qtyInput.value = String(packageQty);
    if (packageQtyHidden) packageQtyHidden.value = String(packageQty);

    var existingGroupPrice = packagePriceHidden
      ? parseFloat(packagePriceHidden.value || "0")
      : 0;
    var groupPrice =
      !isNaN(existingGroupPrice) && existingGroupPrice > 0
        ? existingGroupPrice
        : 0;
    if (forceReprice || groupPrice <= 0) {
      var pkg = getSelectedPackageInRow(packageRow);
      var unitPrice = pkg ? parseFloat(pkg.price || "0") : 0;
      if (isNaN(unitPrice)) unitPrice = 0;
      groupPrice = unitPrice * packageQty;
    }
    if (totalInput) totalInput.value = groupPrice.toFixed(2);
    if (packagePriceHidden) packagePriceHidden.value = groupPrice.toFixed(2);

    rows.forEach(function (row) {
      var rowGroupHidden = row.querySelector(".sor-item-group-key");
      if (rowGroupHidden) rowGroupHidden.value = groupId;
      if (row === packageRow) {
        var headerBaseQty = row.querySelector(".sor-item-base-qty");
        var headerQtyEditInput = row.querySelector(
          ".sor-item-package-qty-edit",
        );
        var headerQtyInput = row.querySelector(".sor-item-qty");
        if (headerQtyEditInput) {
          var headerQty = parseInt(headerQtyEditInput.value || "0", 10);
          if (isNaN(headerQty) || headerQty <= 0) {
            headerQtyEditInput.value = String(packageQty);
          }
        }
        if (headerQtyInput) {
          var displayQty = parseInt(headerQtyInput.value || "0", 10);
          if (isNaN(displayQty) || displayQty <= 0) {
            headerQtyInput.value = String(packageQty);
          }
        }
        if (headerBaseQty) headerBaseQty.value = "1";
        return;
      }
      var rowPkgName = row.querySelector(".sor-item-pkg-name");
      var rowPkgId = row.querySelector(".sor-item-pkg-id");
      var rowDesc = row.querySelector(".sor-item-desc");
      var rowQty = row.querySelector(".sor-item-qty");
      var rowPackageQtyHidden = row.querySelector(".sor-item-package-qty");
      var rowBaseQty = row.querySelector(".sor-item-base-qty");
      var rowPackagePriceHidden = row.querySelector(".sor-item-package-price");

      if (rowPkgName) rowPkgName.value = pkgName;
      if (rowPkgId) rowPkgId.value = pkgId;
      if (rowDesc) rowDesc.value = desc;
      if (rowPackageQtyHidden) rowPackageQtyHidden.value = String(packageQty);
      if (rowPackagePriceHidden)
        rowPackagePriceHidden.value = groupPrice.toFixed(2);
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
            prodNameInput.readOnly = true;
          } else {
            prodNameInput.readOnly = false;
          }
        }

        var qtyInput = row.querySelector(".sor-item-qty");
        if (qtyInput) {
          qtyInput.readOnly = action === "";
        }
        var packageQtyEditInput = row.querySelector(
          ".sor-item-package-qty-edit",
        );
        if (packageQtyEditInput) {
          packageQtyEditInput.readOnly = action === "";
        }

        var actionCell = row.querySelector("td:last-child");
        if (actionCell && rowRole !== "package") {
          actionCell.innerHTML = "";
        }
      });

      if (packageRow) {
        var headerProdNameInput = packageRow.querySelector(
          ".sor-item-prod-name",
        );
        var headerProdIdInput = packageRow.querySelector(".sor-item-prod-id");
        var pkgForHeader = getSelectedPackageInRow(packageRow);
        if (headerProdNameInput && headerProdIdInput) {
          if (
            pkgForHeader &&
            Array.isArray(pkgForHeader.product_ids) &&
            pkgForHeader.product_ids.length > 0
          ) {
            var firstPid = parseInt(pkgForHeader.product_ids[0], 10);
            if (firstPid > 0 && productLookupById[firstPid]) {
              headerProdNameInput.value = productLookupById[firstPid].name;
              headerProdIdInput.value = String(firstPid);
            } else {
              headerProdNameInput.value = "";
              headerProdIdInput.value = "";
            }
          } else {
            headerProdNameInput.value = "";
            headerProdIdInput.value = "";
          }
        }
        syncGroupValues(packageRow, false);
      }
    });

    // Keep package action buttons by package order:
    // 1st package row keeps add button, and package rows stay removable when
    // there is more than one package group.
    var packageRows = Array.prototype.slice.call(
      document.querySelectorAll('#sorItemBody tr[data-row-role="package"]'),
    );
    var canRemovePackage = packageRows.length > 1;
    packageRows.forEach(function (pkgRow, idx) {
      var pkgActionCell = pkgRow.querySelector("td:last-child");
      if (!pkgActionCell) return;

      var removeBtnHtml =
        '<button type="button" class="mt-1 remove-package-btn" id="action_menu_btn" ' +
        (canRemovePackage ? "" : 'style="visibility:hidden"') +
        '><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>';

      if (idx === 0) {
        pkgActionCell.innerHTML =
          '<button type="button" class="mt-1 add-item-row-btn" id="action_menu_btn"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>' +
          removeBtnHtml;
      } else {
        pkgActionCell.innerHTML = removeBtnHtml;
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
      var packageRow = getPackageRowForGroup(getRowGroupId(row)) || row;
      var qtyInput = packageRow.querySelector(".sor-item-qty");
      var qty = qtyInput ? parseInt(qtyInput.value || "0", 10) : 1;
      if (isNaN(qty) || qty <= 0) qty = 1;
      var unitPrice = pkg ? parseFloat(pkg.price || "0") : 0;
      if (isNaN(unitPrice)) unitPrice = 0;
      totalInput.value = (unitPrice * qty).toFixed(2);
      var priceHidden = row.querySelector(".sor-item-package-price");
      if (priceHidden) {
        priceHidden.value = (unitPrice * qty).toFixed(2);
      }
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
    syncGroupValues(row, true);
  }

  function recalculateTotalPrice() {
    var total = 0;
    document.querySelectorAll("#sorItemBody tr").forEach(function (row) {
      if (!isPackageRow(row)) return;

      var pkgIdInput = row.querySelector(".sor-item-pkg-id");
      var qtyInput = row.querySelector(".sor-item-qty");
      var packagePriceInput = row.querySelector(".sor-item-package-price");
      if (!pkgIdInput || !qtyInput) return;

      var price = packagePriceInput
        ? parseFloat(packagePriceInput.value || "0")
        : 0;

      if (!isNaN(price) && price > 0) {
        total += price;
      } else {
        var pkgId = parseInt(pkgIdInput.value || "0", 10);
        var pkgData = packageLookupById[pkgId] || null;
        var qty = parseInt(qtyInput.value || "0", 10);
        var fallbackPrice = pkgData ? parseFloat(pkgData.price || "0") : 0;
        if (!isNaN(fallbackPrice) && !isNaN(qty) && qty > 0) {
          total += fallbackPrice * qty;
        }
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
      '"></div>' +
      (isPackageHeader
        ? '<div class="sor-package-qty-note">Quantity(package): <input class="form-control sor-item-package-qty-edit" type="number" min="1" value="' +
          escapeAttr(qty || 1) +
          '" ' +
          (action === "" ? "readonly" : "") +
          "></div>"
        : "") +
      "</div></td>" +
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
      (action === "" ? "readonly" : "") +
      '"><input class="sor-item-package-qty" type="hidden" name="sor_item_package_qty[]" value="' +
      escapeAttr(qty || 1) +
      '"><input class="sor-item-base-qty" type="hidden" name="sor_item_base_qty[]" value="' +
      (isPackageHeader ? "1" : "1") +
      '"><input class="sor-item-group-key" type="hidden" name="sor_item_group_key[]" value="' +
      escapeAttr(rowKey) +
      '"><input class="sor-item-package-price" type="hidden" name="sor_item_package_price[]" value="0.00' +
      '"></div></td>' +
      '<td class="cell-total"><div class="total-main-field"><input class="form-control sor-item-total" type="text" value="0.00" readonly></div></td>' +
      "<td>" +
      (isPackageHeader
        ? '<button type="button" class="mt-1 remove-package-btn" id="action_menu_btn"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>'
        : "") +
      "</td>"
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
      var pkgHeaderProdName = row.querySelector(".sor-item-prod-name");
      var pkgHeaderProdId = row.querySelector(".sor-item-prod-id");
      if (pkgHeaderProdName) pkgHeaderProdName.value = "";
      if (pkgHeaderProdId) pkgHeaderProdId.value = "";
      reindexRows();
      renderPackageGroups();
      return;
    }

    var firstProductId = parseInt(pkg.product_ids[0] || 0, 10);
    var pkgHeaderProdName = row.querySelector(".sor-item-prod-name");
    var pkgHeaderProdId = row.querySelector(".sor-item-prod-id");
    if (
      firstProductId > 0 &&
      productLookupById[firstProductId] &&
      pkgHeaderProdName &&
      pkgHeaderProdId
    ) {
      pkgHeaderProdName.value = productLookupById[firstProductId].name;
      pkgHeaderProdId.value = String(firstProductId);
    }

    pkg.product_ids.slice(1).forEach(function (pid) {
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
    String(courierIdInput.value || "").trim() !== "" &&
    courierNameInput &&
    courierNameInput.value.trim() === ""
  ) {
    var cr =
      courierLookupById[String(courierIdInput.value || "").trim()] || null;
    if (cr) courierNameInput.value = cr.name;
  }

  document.querySelectorAll("#sorItemBody tr").forEach(function (row) {
    getRowGroupId(row);
    if (!row.getAttribute("data-row-role")) {
      row.setAttribute("data-row-role", "product");
    }
    bindRowAutocomplete(row);
    var storedPriceInput = row.querySelector(".sor-item-package-price");
    var hasStoredPrice = storedPriceInput
      ? parseFloat(storedPriceInput.value || "0") > 0
      : false;
    if (!hasStoredPrice) {
      updateRowDescAndPrice(row);
    }
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
          syncGroupValues(row, true);
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
            syncGroupValues(pkgRow, true);
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
          syncGroupValues(qtyRow, true);
        }
        recalculateTotalPrice();
      }
    });

    sorItemBody.addEventListener("click", function (e) {
      var addItemBtn = e.target.closest(".add-item-row-btn");
      var packageRemoveBtn = e.target.closest(".remove-package-btn");
      var productRemoveBtn = e.target.closest(
        ".remove-product-btn, .remove-item-btn",
      );

      if (addItemBtn) {
        addPackageHeaderRow("pkg_group_" + Date.now().toString());
        reindexRows();
        renderPackageGroups();
        recalculateTotalPrice();
      } else if (packageRemoveBtn) {
        var baseRow = packageRemoveBtn.closest("tr");
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
      } else if (productRemoveBtn) {
        var removeRow = productRemoveBtn.closest("tr");
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

  function setFieldError(inputEl, errorEl, message) {
    if (errorEl) {
      errorEl.textContent = message || "";
      errorEl.style.display = message ? "block" : "none";
    }
    if (inputEl) {
      if (message) {
        inputEl.classList.add("sor-invalid");
      } else {
        inputEl.classList.remove("sor-invalid");
      }
    }
  }

  function clearAllFieldErrors() {
    document.querySelectorAll(".sor-field-error").forEach(function (el) {
      el.textContent = "";
      el.style.display = "none";
    });
    document.querySelectorAll(".sor-invalid").forEach(function (el) {
      el.classList.remove("sor-invalid");
    });
  }

  function hasValidRequestItems() {
    var hasPackageItem = false;
    document.querySelectorAll("#sorItemBody tr").forEach(function (row) {
      var prodIdInput = row.querySelector(".sor-item-prod-id");
      var pkgIdInput = row.querySelector(".sor-item-pkg-id");
      var qtyInput = row.querySelector(".sor-item-qty");
      var prodId = prodIdInput ? parseInt(prodIdInput.value || "0", 10) : 0;
      var pkgId = pkgIdInput ? parseInt(pkgIdInput.value || "0", 10) : 0;
      var qty = qtyInput ? parseInt(qtyInput.value || "0", 10) : 0;
      if (prodId > 0 && pkgId > 0 && qty > 0) {
        hasPackageItem = true;
      }
    });

    return hasPackageItem;
  }

  var sorForm = document.getElementById("sorForm");
  if (sorForm) {
    sorForm.addEventListener("submit", function (e) {
      var submitter = e.submitter;
      if (!submitter) return;
      var actionValue = String(submitter.value || "");
      if (actionValue !== "addRecord" && actionValue !== "updRecord") {
        return;
      }

      clearAllFieldErrors();

      var hasError = false;
      var warehouseName = document.getElementById("sor_warehouse_name");
      var warehouseId = document.getElementById("sor_warehouse");
      var invoiceNo = document.getElementById("sor_invoice_no");
      var invoiceDate = document.getElementById("sor_invoice_date");
      var requestDate = document.getElementById("sor_request_date");

      var warehouseError = document.getElementById("sor_warehouse_err");
      var invoiceNoError = document.getElementById("sor_invoice_no_err");
      var invoiceDateError = document.getElementById("sor_invoice_date_err");
      var requestDateError = document.getElementById("sor_request_date_err");
      var itemsError = document.getElementById("sor_items_err");

      var warehouseIdVal = warehouseId
        ? parseInt(warehouseId.value || "0", 10)
        : 0;
      if (warehouseIdVal <= 0) {
        setFieldError(
          warehouseName,
          warehouseError,
          "Warehouse cannot be empty.",
        );
        hasError = true;
      }

      if (!invoiceNo || String(invoiceNo.value || "").trim() === "") {
        setFieldError(invoiceNo, invoiceNoError, "Invoice cannot be empty.");
        hasError = true;
      }

      if (!invoiceDate || String(invoiceDate.value || "").trim() === "") {
        setFieldError(
          invoiceDate,
          invoiceDateError,
          "Invoice date cannot be empty.",
        );
        hasError = true;
      }

      if (!requestDate || String(requestDate.value || "").trim() === "") {
        setFieldError(
          requestDate,
          requestDateError,
          "Request date cannot be empty.",
        );
        hasError = true;
      }

      if (!hasValidRequestItems()) {
        setFieldError(
          null,
          itemsError,
          "Please add at least one package item with quantity.",
        );
        hasError = true;
      }

      if (hasError) {
        e.preventDefault();
      }
    });

    [
      "sor_warehouse_name",
      "sor_warehouse",
      "sor_invoice_no",
      "sor_invoice_date",
      "sor_request_date",
    ].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.addEventListener("input", clearAllFieldErrors);
      el.addEventListener("change", clearAllFieldErrors);
    });

    var sorItemBody = document.getElementById("sorItemBody");
    if (sorItemBody) {
      sorItemBody.addEventListener("input", clearAllFieldErrors);
      sorItemBody.addEventListener("change", clearAllFieldErrors);
    }
  }
})();
