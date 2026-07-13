var sorCfg = window.__SOR_IMPORT_CONFIG || {};
var brandToCompanyMap = sorCfg.brandToCompanyMap || {};
var brandNameMap = sorCfg.brandNameMap || {};
var companyNameMap = sorCfg.companyNameMap || {};
var productOptions = Array.isArray(sorCfg.products) ? sorCfg.products : [];
var packageOptions = Array.isArray(sorCfg.packages) ? sorCfg.packages : [];
var existingInvoiceNosNormalized = Array.isArray(
  sorCfg.existingInvoiceNosNormalized,
)
  ? sorCfg.existingInvoiceNosNormalized
  : [];

var page = "Stock Order Request";
var action = "";
checkCurrentPage(page, action);
dropdownMenuDispFix();

packageOptions = packageOptions.map(function (pkg) {
  var productRows = Array.isArray(pkg && pkg.product_rows)
    ? pkg.product_rows
        .map(function (row) {
          var productId = parseInt(
            row && row.product_id !== undefined ? row.product_id : 0,
            10,
          );
          var baseQty = parseInt(
            row && row.base_qty !== undefined ? row.base_qty : 1,
            10,
          );
          if (isNaN(productId) || productId <= 0) return null;
          if (isNaN(baseQty) || baseQty <= 0) baseQty = 1;
          return {
            id: productId,
            name: String(row && row.product_name ? row.product_name : ""),
            base_qty: baseQty,
          };
        })
        .filter(function (row) {
          return !!row;
        })
    : [];
  var productIds = productRows.map(function (row) {
    return row.id;
  });
  if (productIds.length === 0 && Array.isArray(pkg && pkg.product_ids)) {
    productIds = pkg.product_ids
      .map(function (id) {
        return parseInt(id, 10);
      })
      .filter(function (id) {
        return !isNaN(id) && id > 0;
      });
  }
  return Object.assign({}, pkg, {
    item_code: String((pkg && pkg.item_code) || "").trim(),
    display_name:
      String((pkg && pkg.item_code) || "").trim() !== ""
        ? String((pkg && pkg.name) || "") +
          " (Item Code: " +
          String((pkg && pkg.item_code) || "").trim() +
          ")"
        : String((pkg && pkg.name) || ""),
    product_ids: productIds,
    product_rows: productRows,
  });
});

function findPackageById(pkgId) {
  var id = parseInt(pkgId || "0", 10);
  if (isNaN(id) || id <= 0) return null;
  for (var i = 0; i < packageOptions.length; i++) {
    var optId = parseInt(packageOptions[i].id || 0, 10);
    if (!isNaN(optId) && optId === id) return packageOptions[i];
  }
  return null;
}

function findPackageByName(name) {
  var key = normalizeText(name || "");
  if (key === "") return null;
  for (var i = 0; i < packageOptions.length; i++) {
    if (normalizeText(packageOptions[i].name || "") === key) {
      return packageOptions[i];
    }
  }
  return null;
}

function findProductById(productId) {
  var id = parseInt(productId || "0", 10);
  if (isNaN(id) || id <= 0) return null;
  for (var i = 0; i < productOptions.length; i++) {
    var optId = parseInt(productOptions[i].id || 0, 10);
    if (!isNaN(optId) && optId === id) return productOptions[i];
  }
  return null;
}

function packageHasLinkedProducts(pkgOpt) {
  return !!(
    pkgOpt &&
    Array.isArray(pkgOpt.product_ids) &&
    pkgOpt.product_ids.length > 0
  );
}

function getPackageProductRows(pkgOpt) {
  if (pkgOpt && Array.isArray(pkgOpt.product_rows) && pkgOpt.product_rows.length) {
    return pkgOpt.product_rows
      .map(function (row) {
        var productId = parseInt(row && row.id !== undefined ? row.id : 0, 10);
        var baseQty = parseInt(
          row && row.base_qty !== undefined ? row.base_qty : 1,
          10,
        );
        if (isNaN(productId) || productId <= 0) return null;
        if (isNaN(baseQty) || baseQty <= 0) baseQty = 1;
        var product = findProductById(productId);
        return {
          id: productId,
          name:
            String(row && row.name ? row.name : "") ||
            (product ? String(product.name || "") : ""),
          base_qty: baseQty,
        };
      })
      .filter(function (row) {
        return !!row;
      });
  }

  if (!pkgOpt || !Array.isArray(pkgOpt.product_ids)) {
    return [];
  }

  return pkgOpt.product_ids
    .map(function (productId) {
      var parsedId = parseInt(productId, 10);
      if (isNaN(parsedId) || parsedId <= 0) return null;
      var product = findProductById(parsedId);
      return {
        id: parsedId,
        name: product ? String(product.name || "") : "",
        base_qty: 1,
      };
    })
    .filter(function (row) {
      return !!row;
    });
}

function getNextPreviewRowIndex() {
  var maxIdx = -1;
  document.querySelectorAll('[name^="rows["]').forEach(function (el) {
    var match = String(el.name || "").match(/^rows\[(\d+)\]/);
    if (!match) return;
    var idx = parseInt(match[1], 10);
    if (!isNaN(idx) && idx > maxIdx) {
      maxIdx = idx;
    }
  });
  return maxIdx + 1;
}

function rewritePreviewRowIndex(row, newIdx) {
  row.querySelectorAll("[name]").forEach(function (el) {
    var currentName = String(el.name || "");
    el.name = currentName.replace(/^rows\[\d+\]/, "rows[" + newIdx + "]");
  });
  row.querySelectorAll("[id]").forEach(function (el) {
    var currentId = String(el.id || "");
    el.id = currentId.replace(/_(\d+)$/, "_" + newIdx);
  });
}

function recalcGroupPackagePrice(groupKey, pkgOpt) {
  if (!groupKey) return;
  var qtyField = document.querySelector(
    '.group-qty-field[data-group="' + groupKey + '"]',
  );
  var packageQty = qtyField ? parseInt(qtyField.value || "0", 10) : 1;
  if (isNaN(packageQty) || packageQty <= 0) packageQty = 1;

  var unitPrice = pkgOpt ? parseFloat(pkgOpt.price || "0") : 0;
  if (isNaN(unitPrice) || unitPrice < 0) unitPrice = 0;
  var packagePrice = unitPrice * packageQty;

  document
    .querySelectorAll('.group-package-price[data-group="' + groupKey + '"]')
    .forEach(function (el) {
      el.value = packagePrice.toFixed(2);
    });

  var packageRow = document.querySelector(
    'tr.preview-package-row[data-package-group="' + groupKey + '"]',
  );
  if (packageRow) {
    var totalInput = packageRow.querySelector("td:nth-child(6) input");
    if (totalInput) {
      totalInput.value = packagePrice > 0 ? packagePrice.toFixed(2) : "";
    }
  }
}

function applyPackageSelection(groupKey, pkgOpt, options) {
  if (!groupKey) return;
  options = options || {};

  var pkgId = pkgOpt ? parseInt(pkgOpt.id || 0, 10) : 0;
  var pkgName = pkgOpt ? String(pkgOpt.name || "") : "";
  var desc = pkgOpt ? String(pkgOpt.item_description || "") : "";
  var brandId = pkgOpt ? parseInt(pkgOpt.brand_id || 0, 10) : 0;
  if (isNaN(brandId) || brandId < 0) brandId = 0;
  var companyId =
    brandId > 0 && brandToCompanyMap.hasOwnProperty(String(brandId))
      ? parseInt(brandToCompanyMap[String(brandId)] || 0, 10)
      : 0;
  if (isNaN(companyId) || companyId < 0) companyId = 0;

  document
    .querySelectorAll('.pkg-hidden-id[data-group="' + groupKey + '"]')
    .forEach(function (el) {
      el.value = String(pkgId > 0 ? pkgId : 0);
    });
  document
    .querySelectorAll('.pkg-hidden-name[data-group="' + groupKey + '"]')
    .forEach(function (el) {
      el.value = pkgName;
    });
  document
    .querySelectorAll('.pkg-hidden-desc[data-group="' + groupKey + '"]')
    .forEach(function (el) {
      el.value = desc;
    });
  document
    .querySelectorAll('.pkg-brand-hidden[data-group="' + groupKey + '"]')
    .forEach(function (el) {
      el.value = String(brandId > 0 ? brandId : 0);
    });
  document
    .querySelectorAll('.pkg-company-hidden[data-group="' + groupKey + '"]')
    .forEach(function (el) {
      el.value = String(companyId > 0 ? companyId : 0);
    });

  document
    .querySelectorAll('.group-desc-field[data-group="' + groupKey + '"]')
    .forEach(function (el) {
      el.value = desc;
    });

  if (!options.skipProductSync) {
    syncGroupProductRowsToPackage(groupKey, pkgOpt);
  }

  recalcGroupPackagePrice(groupKey, pkgOpt);
}

function applyServerRenderedValues() {
  var previewForm = document.getElementById("sorImportPreviewForm");
  if (!previewForm) return;

  previewForm.querySelectorAll(".sor-server-value").forEach(function (el) {
    var serverValue = el.getAttribute("data-server-value");
    if (serverValue === null) return;

    if (el.tagName === "SELECT") {
      var matched = false;
      Array.prototype.forEach.call(el.options, function (opt) {
        if (!matched && String(opt.value) === String(serverValue)) {
          el.value = String(serverValue);
          matched = true;
        }
      });
      if (!matched) {
        el.value = "";
      }
    } else {
      el.value = String(serverValue);
    }
  });
}

// Override browser-restored stale values with authoritative values from server output.
applyServerRenderedValues();
window.addEventListener("pageshow", function () {
  setTimeout(applyServerRenderedValues, 0);
});
setTimeout(applyServerRenderedValues, 60);

function syncReceiptField(receiptKey, field, value) {
  document
    .querySelectorAll(".receipt-hidden-" + field + "-" + receiptKey)
    .forEach(function (el) {
      el.value = value;
    });
}

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

function renderAutocompleteList(input, options, onSelect) {
  closeAutocomplete(input);
  if (!input) return;

  var wrapper = input.closest(".autocomplete");
  if (!wrapper || input.hasAttribute("readonly")) return;

  var keyword = normalizeText(input.value);
  if (keyword === "") return;

  var filtered = (options || [])
    .filter(function (opt) {
      return normalizeText(
        String(opt.name || "") + " " + String(opt.item_code || ""),
      ).indexOf(keyword) !== -1;
    })
    .slice(0, 20);

  if (filtered.length === 0) return;

  var listId = "searchResult_" + input.id;
  input.setAttribute("data-list-id", listId);

  var ul = document.createElement("ul");
  ul.className = "searchResult";
  ul.id = listId;
  ul.style.width = input.offsetWidth + "px";

  filtered.forEach(function (opt) {
    var li = document.createElement("li");
    li.setAttribute("value", String(opt.id));
    li.textContent = opt.display_name || opt.name;
    li.addEventListener("mousedown", function (e) {
      e.preventDefault();
      input.value = opt.name;
      closeAutocomplete(input);
      if (typeof onSelect === "function") onSelect(opt);
    });
    ul.appendChild(li);
  });

  input.after(ul);
}

function clearInlineError(el) {
  if (!el) return;
  el.classList.remove("sor-invalid");
  var receipt = el.getAttribute("data-receipt");
  var field = el.getAttribute("data-field");
  if (!receipt || !field) return;
  var box = document.querySelector(
    '.sor-inline-error[data-receipt="' +
      receipt +
      '"][data-field-err="' +
      field +
      '"]',
  );
  if (box) {
    box.textContent = "";
    box.style.display = "none";
  }
}

function setItemInlineError(input, message) {
  if (!input) return;
  var td = input.closest("td");
  var box = td ? td.querySelector(".sor-item-inline-error") : null;
  if (!box) return;
  box.textContent = String(message || "");
}

function clearItemInlineError(input) {
  if (!input) return;
  input.classList.remove("sor-invalid");
  setItemInlineError(input, "");
}

document.querySelectorAll(".receipt-sync").forEach(function (el) {
  var receiptKey = el.getAttribute("data-receipt");
  var field = el.getAttribute("data-field");
  if (!receiptKey || !field) return;

  var pushValue = function () {
    syncReceiptField(receiptKey, field, el.value || "");
  };

  el.addEventListener("change", pushValue);
  el.addEventListener("input", pushValue);
  el.addEventListener("change", function () {
    clearInlineError(el);
  });
  el.addEventListener("input", function () {
    clearInlineError(el);
  });
  pushValue();
});

function reindexPreviewRows(tbody) {
  if (!tbody) return;
  var groupCounter = {};
  tbody.querySelectorAll("tr.preview-row").forEach(function (row) {
    var groupKey = row.getAttribute("data-package-group") || "";
    var isPackageRow = row.classList.contains("preview-package-row");
    var rowNoCell = row.querySelector(".row-no");
    if (!rowNoCell) return;

    if (isPackageRow) {
      rowNoCell.textContent = "";
      groupCounter[groupKey] = 0;
      return;
    }

    if (!groupCounter.hasOwnProperty(groupKey)) {
      groupCounter[groupKey] = 0;
    }
    groupCounter[groupKey] += 1;
    rowNoCell.textContent = String(groupCounter[groupKey]);
  });
}

function getResolvedPackageForGroup(groupKey) {
  if (!groupKey) return null;

  var pkgIdInput = document.querySelector(
    '.pkg-hidden-id[data-group="' + groupKey + '"]',
  );
  var pkgId = pkgIdInput ? parseInt(pkgIdInput.value || "0", 10) : 0;
  if (!isNaN(pkgId) && pkgId > 0) {
    var pkgById = findPackageById(pkgId);
    if (pkgById) {
      return pkgById;
    }
  }

  var pkgNameInput = document.querySelector(
    '.sor-pkg-name-input[data-group="' + groupKey + '"]',
  );
  return pkgNameInput ? findPackageByName(pkgNameInput.value || "") : null;
}

function bindGroupProductQtyField(input) {
  if (!input || input.getAttribute("data-sor-bound-qty") === "1") return;
  input.setAttribute("data-sor-bound-qty", "1");

  var updateBaseQty = function () {
    var row = input.closest("tr.preview-product-row");
    var groupKey = row ? row.getAttribute("data-package-group") || "" : "";
    if (!groupKey) return;

    var packageQtyField = document.querySelector(
      '.group-qty-field[data-group="' + groupKey + '"]',
    );
    var packageQty = packageQtyField
      ? parseInt(packageQtyField.value || "0", 10)
      : 1;
    if (isNaN(packageQty) || packageQty <= 0) packageQty = 1;

    var productQty = parseInt(input.value || "0", 10);
    if (isNaN(productQty) || productQty <= 0) productQty = packageQty;

    var baseInput = row.querySelector(
      '.group-product-base-qty[data-group="' + groupKey + '"]',
    );
    if (baseInput) {
      baseInput.value = String(Math.max(1, Math.round(productQty / packageQty)));
    }
  };

  input.addEventListener("input", updateBaseQty);
  input.addEventListener("change", updateBaseQty);
}

function bindRemovePreviewButton(btn) {
  if (!btn || btn.getAttribute("data-sor-bound-remove") === "1") return;
  btn.setAttribute("data-sor-bound-remove", "1");

  btn.addEventListener("click", function () {
    var row = btn.closest("tr.preview-row");
    if (!row) return;
    var tbody = row.parentElement;
    var removeScope = btn.getAttribute("data-remove-scope") || "product";
    var groupKey = row.getAttribute("data-package-group") || "";

    if (removeScope === "package") {
      if (groupKey !== "") {
        tbody
          .querySelectorAll(
            'tr.preview-row[data-package-group="' + groupKey + '"]',
          )
          .forEach(function (gr) {
            gr.remove();
          });
      } else {
        row.remove();
      }
    } else {
      row.remove();
      if (groupKey !== "") {
        var remainingProducts = tbody.querySelectorAll(
          'tr.preview-product-row[data-package-group="' + groupKey + '"]',
        );
        if (remainingProducts.length === 0) {
          tbody
            .querySelectorAll(
              'tr.preview-package-row[data-package-group="' + groupKey + '"]',
            )
            .forEach(function (headerRow) {
              headerRow.remove();
            });
        }
      }
    }

    reindexPreviewRows(tbody);
  });
}

function bindProductNameInput(input) {
  if (!input || input.getAttribute("data-sor-bound-product") === "1") return;
  input.setAttribute("data-sor-bound-product", "1");

  var row = input.closest("tr.preview-product-row");
  if (!row) return;
  var wrapper = input.closest(".autocomplete");
  var productIdHidden = row.querySelector('input[name$="[product_id]"]');
  if (!productIdHidden) return;

  var clearProductId = function () {
    productIdHidden.value = "0";
  };

  input.addEventListener("input", clearProductId);
  input.addEventListener("change", clearProductId);
  input.addEventListener("input", function () {
    clearItemInlineError(input);
  });
  input.addEventListener("change", function () {
    clearItemInlineError(input);
  });

  input.addEventListener("input", function () {
    var groupKey = row.getAttribute("data-package-group") || "";
    var pkgIdInput = row.querySelector(
      '.pkg-hidden-id[data-group="' + groupKey + '"]',
    );
    var pkgId = pkgIdInput ? parseInt(pkgIdInput.value || "0", 10) : 0;

    var allowedIds = null;
    if (pkgId > 0) {
      var pkg = packageOptions.find(function (p) {
        return parseInt(p.id || 0, 10) === pkgId;
      });
      if (pkg && Array.isArray(pkg.product_ids) && pkg.product_ids.length > 0) {
        allowedIds = pkg.product_ids.map(function (id) {
          return parseInt(id, 10);
        });
      }
    }

    var options = productOptions;
    if (Array.isArray(allowedIds) && allowedIds.length > 0) {
      options = productOptions.filter(function (p) {
        return allowedIds.indexOf(parseInt(p.id || 0, 10)) !== -1;
      });
    }

    renderAutocompleteList(input, options, function (opt) {
      productIdHidden.value = String(opt.id || 0);
      input.value = String(opt.name || "");
    });
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

function syncGroupProductRowsToPackage(groupKey, pkgOpt) {
  if (!groupKey) return;

  var compositionRows = getPackageProductRows(pkgOpt);
  var tbody = document.querySelector(
    'tr.preview-product-row[data-package-group="' + groupKey + '"]',
  );
  tbody = tbody ? tbody.parentElement : null;
  if (!tbody) return;

  var productRows = Array.prototype.slice.call(
    tbody.querySelectorAll(
      'tr.preview-product-row[data-package-group="' + groupKey + '"]',
    ),
  );
  if (productRows.length === 0 || compositionRows.length === 0) {
    return;
  }

  var templateRow = productRows[0];
  while (productRows.length < compositionRows.length) {
    var cloneRow = templateRow.cloneNode(true);
    rewritePreviewRowIndex(cloneRow, getNextPreviewRowIndex());
    cloneRow.setAttribute("data-package-group", groupKey);
    cloneRow.querySelectorAll(".sor-item-inline-error").forEach(function (box) {
      box.textContent = "";
    });
    var lastRow = productRows[productRows.length - 1];
    lastRow.after(cloneRow);
    productRows.push(cloneRow);
    bindProductNameInput(cloneRow.querySelector(".sor-product-name-input"));
    bindGroupProductQtyField(cloneRow.querySelector(".group-product-qty"));
    bindRemovePreviewButton(cloneRow.querySelector(".remove-preview-row"));
  }

  while (productRows.length > compositionRows.length) {
    var extraRow = productRows.pop();
    extraRow.remove();
  }

  var qtyField = document.querySelector(
    '.group-qty-field[data-group="' + groupKey + '"]',
  );
  var packageQty = qtyField ? parseInt(qtyField.value || "0", 10) : 1;
  if (isNaN(packageQty) || packageQty <= 0) packageQty = 1;

  productRows.forEach(function (row, rowIdx) {
    var productMeta = compositionRows[rowIdx];
    var productIdHidden = row.querySelector('input[name$="[product_id]"]');
    var productNameInput = row.querySelector(".sor-product-name-input");
    var baseQtyInput = row.querySelector(
      '.group-product-base-qty[data-group="' + groupKey + '"]',
    );
    var productQtyInput = row.querySelector(
      '.group-product-qty[data-group="' + groupKey + '"]',
    );

    if (productIdHidden) {
      productIdHidden.value = String(productMeta.id || 0);
    }
    if (productNameInput) {
      productNameInput.value = String(productMeta.name || "");
      productNameInput.readOnly = false;
      clearItemInlineError(productNameInput);
    }
    if (baseQtyInput) {
      baseQtyInput.value = String(productMeta.base_qty || 1);
    }
    if (productQtyInput) {
      productQtyInput.value = String(
        Math.max(1, parseInt(productMeta.base_qty || 1, 10)) * packageQty,
      );
    }
  });

  reindexPreviewRows(tbody);
}

function getPreviewProductRows(groupKey) {
  if (!groupKey) return [];
  return Array.prototype.slice.call(
    document.querySelectorAll(
      'tr.preview-product-row[data-package-group="' + groupKey + '"]',
    ),
  );
}

function shouldSyncResolvedPreviewPackage(groupKey, pkgOpt) {
  if (!groupKey || !pkgOpt) return false;

  var compositionRows = getPackageProductRows(pkgOpt);
  if (compositionRows.length === 0) return false;

  var productRows = getPreviewProductRows(groupKey);
  if (productRows.length === 0) return false;
  if (productRows.length !== compositionRows.length) return true;

  return productRows.every(function (row) {
    var productIdHidden = row.querySelector('input[name$="[product_id]"]');
    var productNameInput = row.querySelector(".sor-product-name-input");
    var productId = productIdHidden
      ? parseInt(productIdHidden.value || "0", 10)
      : 0;
    var productName = productNameInput
      ? String(productNameInput.value || "").trim()
      : "";
    return productName === "" && (isNaN(productId) || productId <= 0);
  });
}

function applyGroupQty(groupKey) {
  if (!groupKey) return;
  var qtyField = document.querySelector(
    '.group-qty-field[data-group="' + groupKey + '"]',
  );
  if (!qtyField) return;

  var packageQty = parseInt(qtyField.value || "0", 10);
  if (isNaN(packageQty) || packageQty <= 0) {
    packageQty = 1;
    qtyField.value = "1";
  }

  document
    .querySelectorAll('.group-package-qty[data-group="' + groupKey + '"]')
    .forEach(function (el) {
      el.value = String(packageQty);
    });

  document
    .querySelectorAll('.group-product-qty[data-group="' + groupKey + '"]')
    .forEach(function (qtyInput) {
      var row = qtyInput.closest("tr.preview-product-row");
      if (!row) return;
      var baseInput = row.querySelector(
        '.group-product-base-qty[data-group="' + groupKey + '"]',
      );
      var baseQty = baseInput ? parseInt(baseInput.value || "0", 10) : 1;
      if (isNaN(baseQty) || baseQty <= 0) baseQty = 1;
      qtyInput.value = String(baseQty * packageQty);
    });
}

function bindGroupQtyField(input) {
  if (!input || input.getAttribute("data-sor-bound-group-qty") === "1") return;
  input.setAttribute("data-sor-bound-group-qty", "1");

  var sync = function () {
    var groupKey = input.getAttribute("data-group") || "";
    applyGroupQty(groupKey);
    recalcGroupPackagePrice(groupKey, getResolvedPackageForGroup(groupKey));
  };

  input.addEventListener("input", sync);
  input.addEventListener("change", sync);
  sync();
}

function bindPackageNameInput(input) {
  if (!input || input.getAttribute("data-sor-bound-package") === "1") return;
  input.setAttribute("data-sor-bound-package", "1");

  var groupKey = input.getAttribute("data-group") || "";
  var wrapper = input.closest(".autocomplete");

  var syncPackageGroup = function (clearLinkedIds) {
    if (!groupKey) return;
    var packageName = String(input.value || "").trim();

    document
      .querySelectorAll('.pkg-hidden-name[data-group="' + groupKey + '"]')
      .forEach(function (el) {
        el.value = packageName;
      });

    if (clearLinkedIds) {
      document
        .querySelectorAll('.pkg-hidden-id[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = "0";
        });

      var exactPkg = findPackageByName(packageName);
      if (exactPkg) {
        applyPackageSelection(groupKey, exactPkg);
      }
    }
  };

  input.addEventListener("input", function () {
    syncPackageGroup(true);
  });
  input.addEventListener("change", function () {
    syncPackageGroup(true);
  });
  input.addEventListener("input", function () {
    clearItemInlineError(input);
  });
  input.addEventListener("change", function () {
    clearItemInlineError(input);
  });

  input.addEventListener("input", function () {
    renderAutocompleteList(input, packageOptions, function (opt) {
      applyPackageSelection(groupKey, opt);
      clearItemInlineError(input);
    });
  });

  input.addEventListener("change", function () {
    var typedPkg = findPackageByName(input.value || "");
    if (typedPkg) {
      applyPackageSelection(groupKey, typedPkg);
      clearItemInlineError(input);
    }
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

  syncPackageGroup(false);
  var existingPkgIdInput = document.querySelector(
    '.pkg-hidden-id[data-group="' + groupKey + '"]',
  );
  var resolvedPkg = existingPkgIdInput
    ? findPackageById(existingPkgIdInput.value || "")
    : null;
  if (!resolvedPkg) {
    resolvedPkg = findPackageByName(input.value || "");
  }
  if (resolvedPkg) {
    applyPackageSelection(groupKey, resolvedPkg, {
      skipProductSync: !shouldSyncResolvedPreviewPackage(groupKey, resolvedPkg),
    });
  }
}

document.querySelectorAll(".group-product-qty").forEach(function (el) {
  bindGroupProductQtyField(el);
});

document.querySelectorAll(".remove-preview-row").forEach(function (btn) {
  bindRemovePreviewButton(btn);
});

document.querySelectorAll(".group-qty-field").forEach(function (el) {
  bindGroupQtyField(el);
});

document.querySelectorAll(".sor-pkg-name-input").forEach(function (input) {
  bindPackageNameInput(input);
});

document.querySelectorAll(".sor-product-name-input").forEach(function (input) {
  bindProductNameInput(input);
});

function resetPreviewBindingMarkers(root) {
  if (!root) return;
  root.querySelectorAll("[data-sor-bound-remove]").forEach(function (el) {
    el.removeAttribute("data-sor-bound-remove");
  });
  root.querySelectorAll("[data-sor-bound-product]").forEach(function (el) {
    el.removeAttribute("data-sor-bound-product");
  });
  root.querySelectorAll("[data-sor-bound-qty]").forEach(function (el) {
    el.removeAttribute("data-sor-bound-qty");
  });
  root.querySelectorAll("[data-sor-bound-group-qty]").forEach(function (el) {
    el.removeAttribute("data-sor-bound-group-qty");
  });
  root.querySelectorAll("[data-sor-bound-package]").forEach(function (el) {
    el.removeAttribute("data-sor-bound-package");
  });
  root.querySelectorAll("[data-sor-bound-add-package]").forEach(function (el) {
    el.removeAttribute("data-sor-bound-add-package");
  });
}

function updateRowGroupReferences(row, oldGroupKey, newGroupKey, newHiddenGroupValue) {
  if (!row) return;
  row.setAttribute("data-package-group", newGroupKey);

  row.querySelectorAll("[data-group]").forEach(function (el) {
    if (String(el.getAttribute("data-group") || "") === String(oldGroupKey || "")) {
      el.setAttribute("data-group", newGroupKey);
    }
  });

  row.querySelectorAll('[name$="[package_group]"]').forEach(function (el) {
    el.value = String(newHiddenGroupValue || "");
  });
}

function clearClonedPackageRow(row, newGroupKey) {
  if (!row) return;
  var pkgNameInput = row.querySelector(".sor-pkg-name-input");
  var descInput = row.querySelector(".group-desc-field");
  var qtyInput = row.querySelector(".group-qty-field");
  var totalInput = row.querySelector("td:nth-child(6) input");
  var errorBox = row.querySelector('.sor-item-inline-error[data-item-error="package"]');

  if (pkgNameInput) {
    pkgNameInput.value = "";
    pkgNameInput.setAttribute("data-server-value", "");
    pkgNameInput.classList.remove("sor-invalid");
    closeAutocomplete(pkgNameInput);
  }
  if (descInput) {
    descInput.value = "";
  }
  if (qtyInput) {
    qtyInput.value = "1";
  }
  if (totalInput) {
    totalInput.value = "";
  }
  if (errorBox) {
    errorBox.textContent = "";
  }

  row.querySelectorAll('.pkg-hidden-id[data-group="' + newGroupKey + '"]').forEach(
    function (el) {
      el.value = "0";
    },
  );
  row.querySelectorAll('.pkg-hidden-name[data-group="' + newGroupKey + '"]').forEach(
    function (el) {
      el.value = "";
    },
  );
  row.querySelectorAll('.pkg-hidden-desc[data-group="' + newGroupKey + '"]').forEach(
    function (el) {
      el.value = "";
    },
  );
  row.querySelectorAll('.group-package-price[data-group="' + newGroupKey + '"]').forEach(
    function (el) {
      el.value = "0.00";
    },
  );
}

function clearClonedProductRow(row, newGroupKey) {
  if (!row) return;

  var productNameInput = row.querySelector(".sor-product-name-input");
  var productQtyInput = row.querySelector(".group-product-qty");
  var productError = row.querySelector('.sor-item-inline-error[data-item-error="product"]');

  if (productNameInput) {
    productNameInput.value = "";
    productNameInput.setAttribute("data-server-value", "");
    productNameInput.readOnly = false;
    productNameInput.classList.remove("sor-invalid");
    closeAutocomplete(productNameInput);
  }
  if (productQtyInput) {
    productQtyInput.value = "1";
    productQtyInput.classList.remove("sor-invalid");
  }
  if (productError) {
    productError.textContent = "";
  }

  row.querySelectorAll('input[name$="[product_id]"]').forEach(function (el) {
    el.value = "0";
  });
  row.querySelectorAll('.pkg-hidden-id[data-group="' + newGroupKey + '"]').forEach(
    function (el) {
      el.value = "0";
    },
  );
  row.querySelectorAll('.pkg-hidden-name[data-group="' + newGroupKey + '"]').forEach(
    function (el) {
      el.value = "";
    },
  );
  row.querySelectorAll('.pkg-hidden-desc[data-group="' + newGroupKey + '"]').forEach(
    function (el) {
      el.value = "";
    },
  );
  row.querySelectorAll('.group-package-qty[data-group="' + newGroupKey + '"]').forEach(
    function (el) {
      el.value = "1";
    },
  );
  row.querySelectorAll(
    '.group-product-base-qty[data-group="' + newGroupKey + '"]',
  ).forEach(function (el) {
    el.value = "1";
  });
  row.querySelectorAll('.group-package-price[data-group="' + newGroupKey + '"]').forEach(
    function (el) {
      el.value = "0.00";
    },
  );
  row.querySelectorAll('.pkg-brand-hidden[data-group="' + newGroupKey + '"]').forEach(
    function (el) {
      el.value = "0";
    },
  );
  row.querySelectorAll('.pkg-company-hidden[data-group="' + newGroupKey + '"]').forEach(
    function (el) {
      el.value = "0";
    },
  );
}

function getLastPreviewGroupRow(tbody, groupKey) {
  if (!tbody || !groupKey) return null;
  var rows = tbody.querySelectorAll(
    'tr.preview-row[data-package-group="' + groupKey + '"]',
  );
  return rows.length > 0 ? rows[rows.length - 1] : null;
}

function bindAddPreviewPackageButton(btn) {
  if (!btn || btn.getAttribute("data-sor-bound-add-package") === "1") return;
  btn.setAttribute("data-sor-bound-add-package", "1");

  btn.addEventListener("click", function () {
    var packageRow = btn.closest("tr.preview-package-row");
    if (!packageRow) return;

    var tbody = packageRow.parentElement;
    var oldGroupKey = packageRow.getAttribute("data-package-group") || "";
    var receiptKey = packageRow.getAttribute("data-receipt") || "";
    var productTemplate = tbody.querySelector(
      'tr.preview-product-row[data-package-group="' + oldGroupKey + '"]',
    );
    if (!productTemplate) return;

    var seed = Date.now().toString() + "_" + Math.floor(Math.random() * 1000);
    var newGroupKey =
      "receipt_" +
      String(receiptKey || "manual") +
      "_pkg_manual_" +
      seed;
    var newHiddenGroupValue = "manual_pkg_" + seed;

    var newPackageRow = packageRow.cloneNode(true);
    var newProductRow = productTemplate.cloneNode(true);

    rewritePreviewRowIndex(newPackageRow, getNextPreviewRowIndex());
    rewritePreviewRowIndex(newProductRow, getNextPreviewRowIndex());
    resetPreviewBindingMarkers(newPackageRow);
    resetPreviewBindingMarkers(newProductRow);

    updateRowGroupReferences(
      newPackageRow,
      oldGroupKey,
      newGroupKey,
      newHiddenGroupValue,
    );
    updateRowGroupReferences(
      newProductRow,
      oldGroupKey,
      newGroupKey,
      newHiddenGroupValue,
    );

    clearClonedPackageRow(newPackageRow, newGroupKey);
    clearClonedProductRow(newProductRow, newGroupKey);

    var insertAfter = getLastPreviewGroupRow(tbody, oldGroupKey);
    if (insertAfter) {
      insertAfter.after(newPackageRow);
      newPackageRow.after(newProductRow);
    } else {
      tbody.appendChild(newPackageRow);
      tbody.appendChild(newProductRow);
    }

    bindPackageNameInput(newPackageRow.querySelector(".sor-pkg-name-input"));
    bindGroupQtyField(newPackageRow.querySelector(".group-qty-field"));
    bindRemovePreviewButton(newPackageRow.querySelector(".remove-preview-row"));
    bindAddPreviewPackageButton(
      newPackageRow.querySelector(".add-preview-package-row"),
    );
    bindProductNameInput(newProductRow.querySelector(".sor-product-name-input"));
    bindGroupProductQtyField(newProductRow.querySelector(".group-product-qty"));
    bindRemovePreviewButton(newProductRow.querySelector(".remove-preview-row"));

    reindexPreviewRows(tbody);
  });
}

document.querySelectorAll(".add-preview-package-row").forEach(function (btn) {
  bindAddPreviewPackageButton(btn);
});

var previewForm = document.getElementById("sorImportPreviewForm");
if (previewForm) {
  var allowAsyncValidatedSubmit = false;
  var lastSubmitActionValue = "";

  var insertButtons = previewForm.querySelectorAll(
    'button[name="actionBtn"][value="insertStockOrderPdf"]',
  );
  insertButtons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      lastSubmitActionValue = "insertStockOrderPdf";
    });
  });

  var ensureActionHiddenField = function (value) {
    var hidden = previewForm.querySelector(
      'input[type="hidden"][name="actionBtn"][data-js-action="1"]',
    );
    if (!hidden) {
      hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = "actionBtn";
      hidden.setAttribute("data-js-action", "1");
      previewForm.appendChild(hidden);
    }
    hidden.value = String(value || "");
  };

  var checkDuplicateInvoiceNos = function () {
    return new Promise(function (resolve) {
      var invoiceInputs = Array.prototype.slice.call(
        previewForm.querySelectorAll('.receipt-sync[data-field="invoice_no"]'),
      );
      var duplicateNorm = [];
      var existingSet = {};
      existingInvoiceNosNormalized.forEach(function (n) {
        var norm = String(n || "").toLowerCase();
        if (norm !== "") existingSet[norm] = true;
      });

      invoiceInputs.forEach(function (el) {
        var v = String(el.value || "").trim();
        var norm = v.toLowerCase().replace(/\s+/g, "");
        if (norm !== "" && existingSet.hasOwnProperty(norm)) {
          duplicateNorm.push(norm);
        }
      });

      resolve({ ok: true, duplicates: duplicateNorm });
    });
  };

  previewForm.addEventListener("submit", function (e) {
    if (allowAsyncValidatedSubmit) {
      allowAsyncValidatedSubmit = false;
      return;
    }

    var submitter = e.submitter || null;
    var actionBtnValue = submitter
      ? String(submitter.value || "")
      : String(lastSubmitActionValue || "");
    var submitterName = submitter ? String(submitter.name || "") : "";

    // Cancel should always be allowed and should not be blocked by validation.
    if (
      submitterName === "cancelImportBtn" ||
      actionBtnValue === "cancelImport"
    ) {
      return;
    }

    if (actionBtnValue !== "insertStockOrderPdf") {
      return;
    }

    e.preventDefault();

    var hasError = false;
    var requiredMsg = {
      warehouse_id: "Warehouse is required.",
      courier_id: "Courier is required.",
      invoice_no: "Invoice is required.",
      invoice_date: "Invoices Date is required.",
      total_price: "Total Price is required.",
    };

    previewForm.querySelectorAll(".receipt-sync").forEach(function (el) {
      var receipt = el.getAttribute("data-receipt");
      var field = el.getAttribute("data-field");
      if (!receipt || !field || !requiredMsg.hasOwnProperty(field)) return;

      var val = String(el.value || "").trim();
      var invalid =
        val === "" || (field === "total_price" && parseFloat(val || "0") <= 0);
      var box = previewForm.querySelector(
        '.sor-inline-error[data-receipt="' +
          receipt +
          '"][data-field-err="' +
          field +
          '"]',
      );

      if (invalid) {
        hasError = true;
        el.classList.add("sor-invalid");
        if (box) {
          box.textContent = requiredMsg[field];
          box.style.display = "block";
        }
      } else {
        el.classList.remove("sor-invalid");
        if (box) {
          box.textContent = "";
          box.style.display = "none";
        }
      }
    });

    previewForm.querySelectorAll(".sor-pkg-name-input").forEach(function (el) {
      var groupKey = el.getAttribute("data-group") || "";
      var hasValidPackageId = false;
      if (groupKey !== "") {
        previewForm
          .querySelectorAll('.pkg-hidden-id[data-group="' + groupKey + '"]')
          .forEach(function (pkgEl) {
            var pkgId = parseInt(pkgEl.value || "0", 10);
            if (!isNaN(pkgId) && pkgId > 0) {
              hasValidPackageId = true;
            }
          });
      }

      var val = String(el.value || "").trim();
      if (val === "") {
        hasError = true;
        el.classList.add("sor-invalid");
        setItemInlineError(el, "Package is required.");
      } else if (!hasValidPackageId) {
        hasError = true;
        el.classList.add("sor-invalid");
        setItemInlineError(
          el,
          "Package name not found. Please enter a valid package name from DB.",
        );
      } else {
        clearItemInlineError(el);
      }
    });

    previewForm
      .querySelectorAll(".sor-product-name-input")
      .forEach(function (el) {
        var row = el.closest("tr.preview-product-row");
        var groupKey = row ? row.getAttribute("data-package-group") || "" : "";
        var pkgIdInput = row
          ? row.querySelector('.pkg-hidden-id[data-group="' + groupKey + '"]')
          : null;
        var pkgId = pkgIdInput ? parseInt(pkgIdInput.value || "0", 10) : 0;
        var pkg = findPackageById(pkgId);

        // Package-first workflow: if package is not resolved yet,
        // show package error and do not block here with product error.
        if (isNaN(pkgId) || pkgId <= 0) {
          clearItemInlineError(el);
          return;
        }

        // If package has no linked products, do not require product input.
        if (!packageHasLinkedProducts(pkg)) {
          clearItemInlineError(el);
          return;
        }

        var val = String(el.value || "").trim();
        if (val === "") {
          hasError = true;
          el.classList.add("sor-invalid");
          setItemInlineError(el, "Product is required.");
        } else {
          clearItemInlineError(el);
        }
      });

    if (hasError) {
      return;
    }

    checkDuplicateInvoiceNos().then(function (dupResult) {
      var dupSet = {};
      (dupResult.duplicates || []).forEach(function (n) {
        dupSet[String(n || "").toLowerCase()] = true;
      });

      var hasDup = false;
      previewForm
        .querySelectorAll('.receipt-sync[data-field="invoice_no"]')
        .forEach(function (el) {
          var receipt = el.getAttribute("data-receipt");
          var box = previewForm.querySelector(
            '.sor-inline-error[data-receipt="' +
              receipt +
              '"][data-field-err="invoice_no"]',
          );
          var val = String(el.value || "").trim();
          var norm = val.toLowerCase().replace(/\s+/g, "");

          if (box) {
            box.textContent = "";
            box.style.display = "none";
          }

          if (norm !== "" && dupSet.hasOwnProperty(norm)) {
            hasDup = true;
            el.classList.add("sor-invalid");
            if (box) {
              box.textContent = "Invoice number already exists.";
              box.style.display = "block";
            }
          } else {
            el.classList.remove("sor-invalid");
          }
        });

      if (hasDup) {
        return;
      }

      allowAsyncValidatedSubmit = true;
      ensureActionHiddenField("insertStockOrderPdf");
      previewForm.submit();
    });
  });
}

document.querySelectorAll(".sor-pkg-select").forEach(function (sel) {
  sel.addEventListener("change", function () {
    var groupKey = sel.getAttribute("data-group") || "";
    var targetId = sel.getAttribute("data-brand-target");
    var target = targetId ? document.getElementById(targetId) : null;
    if (!target) return;

    var option = sel.options[sel.selectedIndex];
    var brandId = option ? option.getAttribute("data-brand-id") || "" : "";
    target.value = brandId !== "" ? brandId : "0";

    var idx = (targetId || "").replace("brand_", "");
    var companyField = document.getElementById("company_" + idx);
    var brandHidden = document.getElementById("brand_hidden_" + idx);
    var companyHidden = document.getElementById("company_hidden_" + idx);
    var descField = document.getElementById("desc_" + idx);

    if (brandHidden) {
      brandHidden.value = brandId !== "" ? brandId : "0";
    }

    var companyId =
      brandId !== "" && brandToCompanyMap.hasOwnProperty(brandId)
        ? String(brandToCompanyMap[brandId])
        : "";
    if (companyHidden) {
      companyHidden.value = companyId !== "" ? companyId : "0";
    }
    if (companyField) {
      if (companyId !== "") {
        var label = companyNameMap.hasOwnProperty(companyId)
          ? companyNameMap[companyId]
          : "";
        companyField.value = companyId + (label ? " - " + label : "");
      } else {
        companyField.value = "";
      }
    }

    if (descField) {
      var itemDesc = option ? option.getAttribute("data-item-desc") || "" : "";
      descField.value = itemDesc;

      if (groupKey !== "") {
        document
          .querySelectorAll('.group-desc-field[data-group="' + groupKey + '"]')
          .forEach(function (el) {
            el.value = itemDesc;
          });
      }
    }

    if (groupKey !== "") {
      var selectedPkgId = sel.value || "";
      var selectedPkgName = option
        ? (option.textContent || "").split(" - ")[0]
        : "";
      var selectedDesc = option
        ? option.getAttribute("data-item-desc") || ""
        : "";

      document
        .querySelectorAll('.pkg-hidden-id[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = selectedPkgId;
        });
      document
        .querySelectorAll('.pkg-hidden-name[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = selectedPkgName;
        });
      document
        .querySelectorAll('.pkg-hidden-desc[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = selectedDesc;
        });
      document
        .querySelectorAll('.pkg-brand-hidden[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = brandId !== "" ? brandId : "0";
        });
      document
        .querySelectorAll('.pkg-company-hidden[data-group="' + groupKey + '"]')
        .forEach(function (el) {
          el.value = companyId !== "" ? companyId : "0";
        });
    }
  });
});

// Browser OCR (no server setup, no API key). No progress bar UI.

// Airbill photo barcode scan for import preview.
(function () {
  var airbillInputs = document.querySelectorAll(".sor-import-airbill-image");
  if (!airbillInputs || airbillInputs.length === 0) {
    return;
  }

  function setAirbillStatus(receiptKey, message, isError) {
    var statusEl = document.querySelector(
      '.sor-import-airbill-status[data-receipt="' + receiptKey + '"]',
    );
    if (!statusEl) {
      return;
    }

    var msg = String(message || "");
    if (msg === "") {
      statusEl.style.display = "none";
      statusEl.innerHTML = "";
      return;
    }

    statusEl.style.display = "block";
    statusEl.style.color = isError ? "#dc3545" : "#6c757d";
    statusEl.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
      msg;
  }

  function waitForAirbillLoadingPaint() {
    return new Promise(function (resolve) {
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(resolve);
      });
    });
  }

  function extractAirbillCandidate(rawText) {
    var text = String(rawText || "").trim();
    if (text === "") {
      return "";
    }

    var upperText = text.toUpperCase();
    // Keep the suffix when it is present; otherwise the compact fallback
    // would accept only the numeric prefix.
    var directMatch = upperText.match(/\bMY\d{10,14}[A-Z]?\b/g);
    if (directMatch && directMatch.length > 0) {
      return String(directMatch[0] || "").toUpperCase();
    }

    var compactText = upperText.replace(/[^A-Z0-9]+/g, "");
    var compactMatch = compactText.match(/MY\d{10,14}[A-Z]?/g);
    if (compactMatch && compactMatch.length > 0) {
      return String(compactMatch[0] || "").toUpperCase();
    }

    var fixedText = compactText
      .replace(/MYO(?=\d{9,13})/g, "MY0")
      .replace(/MYQ(?=\d{9,13})/g, "MY0")
      .replace(/MYI(?=\d{9,13})/g, "MY1")
      .replace(/MYL(?=\d{9,13})/g, "MY1");

    var fixedMatch = fixedText.match(/MY\d{10,14}[A-Z]?/g);
    if (fixedMatch && fixedMatch.length > 0) {
      return String(fixedMatch[0] || "").toUpperCase();
    }

    var normalizedText = upperText.replace(/[^A-Z0-9]+/g, " ");
    var fallbackMatches = normalizedText.match(/\b[A-Z0-9]{10,30}\b/g) || [];
    for (var i = 0; i < fallbackMatches.length; i++) {
      var candidate = fallbackMatches[i];
      var digitCount = (candidate.match(/\d/g) || []).length;
      if (digitCount >= 6 && candidate.length >= 12) {
        return candidate;
      }
    }

    return "";
  }

  function getZXingReader() {
    if (
      window.ZXingBrowser &&
      typeof window.ZXingBrowser.BrowserMultiFormatReader === "function"
    ) {
      return new window.ZXingBrowser.BrowserMultiFormatReader();
    }

    if (
      window.ZXing &&
      typeof window.ZXing.BrowserMultiFormatReader === "function"
    ) {
      return new window.ZXing.BrowserMultiFormatReader();
    }

    return null;
  }

  function loadImageFromFile(file) {
    return new Promise(function (resolve, reject) {
      var imageUrl = URL.createObjectURL(file);
      var imageElement = new Image();
      imageElement.decoding = "async";

      imageElement.onload = function () {
        URL.revokeObjectURL(imageUrl);
        resolve(imageElement);
      };

      imageElement.onerror = function () {
        URL.revokeObjectURL(imageUrl);
        reject(new Error("Unable to load selected image."));
      };

      imageElement.src = imageUrl;
    });
  }

  function createAirbillCanvas(imageElement, region, scale, mode) {
    var sourceWidth = imageElement.naturalWidth || imageElement.width;
    var sourceHeight = imageElement.naturalHeight || imageElement.height;

    var cropX = Math.max(0, Math.floor(region.x * sourceWidth));
    var cropY = Math.max(0, Math.floor(region.y * sourceHeight));
    var cropWidth = Math.min(sourceWidth - cropX, Math.floor(region.w * sourceWidth));
    var cropHeight = Math.min(sourceHeight - cropY, Math.floor(region.h * sourceHeight));

    var canvas = document.createElement("canvas");
    canvas.width = Math.max(1, Math.floor(cropWidth * scale));
    canvas.height = Math.max(1, Math.floor(cropHeight * scale));

    var ctx = canvas.getContext("2d", { willReadFrequently: true });
    ctx.imageSmoothingEnabled = false;
    ctx.drawImage(
      imageElement,
      cropX,
      cropY,
      cropWidth,
      cropHeight,
      0,
      0,
      canvas.width,
      canvas.height,
    );

    if (mode === "normal") {
      return canvas;
    }

    var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    var data = imageData.data;

    for (var i = 0; i < data.length; i += 4) {
      var gray = Math.round(
        data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114,
      );

      var value = gray;
      if (mode === "threshold") {
        value = gray > 145 ? 255 : 0;
      } else if (mode === "contrast") {
        value = gray > 125 ? 255 : 0;
      } else if (mode === "invert") {
        value = 255 - gray;
      }

      data[i] = value;
      data[i + 1] = value;
      data[i + 2] = value;
    }

    ctx.putImageData(imageData, 0, 0);
    return canvas;
  }

  function buildAirbillCanvasCandidates(imageElement) {
    var regions = [
      { x: 0.00, y: 0.00, w: 1.00, h: 1.00 },
      { x: 0.42, y: 0.00, w: 0.58, h: 1.00 },
      { x: 0.45, y: 0.00, w: 0.52, h: 0.24 },
      { x: 0.76, y: 0.48, w: 0.22, h: 0.30 },
      { x: 0.50, y: 0.00, w: 0.48, h: 0.35 },
      { x: 0.47, y: 0.45, w: 0.51, h: 0.35 },
    ];

    var modes = ["normal", "threshold", "contrast", "invert"];
    var candidates = [];

    regions.forEach(function (region, regionIndex) {
      modes.forEach(function (mode) {
        var scale = regionIndex === 0 ? 1 : 2;
        candidates.push(createAirbillCanvas(imageElement, region, scale, mode));
      });
    });

    return candidates;
  }

  function getDecodedText(result) {
    if (!result) {
      return "";
    }

    if (typeof result.getText === "function") {
      return String(result.getText() || "");
    }

    if (result.text) {
      return String(result.text || "");
    }

    if (result.rawValue) {
      return String(result.rawValue || "");
    }

    return "";
  }

  async function decodeCanvasWithZXing(reader, canvas) {
    if (typeof reader.decodeFromCanvas === "function") {
      return reader.decodeFromCanvas(canvas);
    }

    var dataUrl = canvas.toDataURL("image/png");
    var imageElement = new Image();
    imageElement.decoding = "async";

    await new Promise(function (resolve, reject) {
      imageElement.onload = resolve;
      imageElement.onerror = reject;
      imageElement.src = dataUrl;
    });

    return reader.decodeFromImageElement(imageElement);
  }

  async function decodeCanvasWithNativeBarcodeDetector(canvas) {
    if (!("BarcodeDetector" in window)) {
      return "";
    }

    try {
      var detector = new BarcodeDetector({
        formats: ["code_128", "code_39", "ean_13", "qr_code"],
      });
      var results = await detector.detect(canvas);

      for (var i = 0; i < (results || []).length; i++) {
        var candidate = extractAirbillCandidate(results[i].rawValue || "");
        if (candidate !== "") {
          return candidate;
        }
      }
    } catch (error) {
      return "";
    }

    return "";
  }

  async function decodeAirbillFromImageFile(file) {
    if (!file) {
      return "";
    }

    var reader = getZXingReader();
    if (!reader && !("BarcodeDetector" in window)) {
      throw new Error("Barcode scanner library is not available.");
    }

    var imageElement = await loadImageFromFile(file);
    var canvases = buildAirbillCanvasCandidates(imageElement);

    for (var i = 0; i < canvases.length; i++) {
      var nativeCandidate = await decodeCanvasWithNativeBarcodeDetector(canvases[i]);
      if (nativeCandidate !== "") {
        return nativeCandidate;
      }
    }

    if (reader) {
      for (var j = 0; j < canvases.length; j++) {
        try {
          var result = await decodeCanvasWithZXing(reader, canvases[j]);
          var candidate = extractAirbillCandidate(getDecodedText(result));
          if (candidate !== "") {
            return candidate;
          }
        } catch (error) {
          // Try next crop/preprocess version.
        }
      }
    }

    return "";
  }

  airbillInputs.forEach(function (input) {
    input.addEventListener("change", async function () {
      var receiptKey = input.getAttribute("data-receipt") || "";
      var trackingInput = document.querySelector(
        '.receipt-tracking-input[data-receipt="' + receiptKey + '"]',
      );
      var file = input.files && input.files[0] ? input.files[0] : null;

      if (!file || receiptKey === "") {
        setAirbillStatus(receiptKey, "", false);
        return;
      }

      setAirbillStatus(receiptKey, "Extracting tracking number...", false);
      await waitForAirbillLoadingPaint();

      try {
        var scannedAirbill = await decodeAirbillFromImageFile(file);
        if (scannedAirbill === "") {
          setAirbillStatus(receiptKey, "Unable to detect tracking number. You can type it manually.", true);
          return;
        }

        if (trackingInput) {
          trackingInput.value = scannedAirbill;
          trackingInput.dispatchEvent(new Event("input", { bubbles: true }));
          trackingInput.dispatchEvent(new Event("change", { bubbles: true }));
        }

        setAirbillStatus(receiptKey, "", false);
      } catch (error) {
        console.error(error);
        setAirbillStatus(receiptKey, "Unable to scan image. You can type tracking number manually.", true);
      }
    });
  });
})();

(function () {
  if (typeof pdfjsLib === "undefined" || typeof Tesseract === "undefined")
    return;

  var workerSrc =
    (typeof sorCfg !== "undefined" && sorCfg.pdfjsWorkerSrc) ||
    "/finance/header/js/pdf.worker.min.js";
  if (workerSrc) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = workerSrc;
  }

  var fileInput = document.getElementById("import_file");
  var form = document.getElementById("sorUploadForm");
  var ocrField = document.getElementById("client_ocr_text");
  var ocrMapField = document.getElementById("client_ocr_map");
  var submitBtn = document.getElementById("sorSubmitBtn");
  if (!fileInput || !form || !ocrField || !ocrMapField || !submitBtn) return;

  var ocrRunning = false;
  var ocrLangPrimary =
    (typeof sorCfg !== "undefined" && sorCfg.ocrLangPrimary) || "eng+chi_sim";
  var ocrLangFallback =
    (typeof sorCfg !== "undefined" && sorCfg.ocrLangFallback) || "chi_sim";

  function setProcessingState(isProcessing, label) {
    ocrRunning = isProcessing;
    submitBtn.disabled = isProcessing;
    submitBtn.innerHTML = isProcessing
      ? '<i class="fa-solid fa-spinner fa-spin"></i> ' + label
      : '<i class="fa-solid fa-wand-magic-sparkles"></i> Load And Analyze';
  }

  function readAsArrayBuffer(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function (e) {
        resolve(e.target.result);
      };
      reader.onerror = reject;
      reader.readAsArrayBuffer(file);
    });
  }

  function basenameLower(path) {
    return String(path || "")
      .split("/")
      .pop()
      .split("\\\\")
      .pop()
      .toLowerCase();
  }

  function extractTextFromPdfBytes(pdfBytes) {
    return pdfjsLib
      .getDocument({ data: pdfBytes })
      .promise.then(function (pdfDoc) {
        var tasks = [];
        for (var i = 1; i <= pdfDoc.numPages; i++) {
          tasks.push(
            pdfDoc.getPage(i).then(function (page) {
              // Reduced scale from 3.0 to 2.0 to save memory and processing time
              var viewport = page.getViewport({ scale: 3.0 });
              var canvas = document.createElement("canvas");
              canvas.width = viewport.width;
              canvas.height = viewport.height;
              var ctx = canvas.getContext("2d");

              return page
                .render({ canvasContext: ctx, viewport: viewport })
                .promise.then(function () {
                  // Use Simplified Chinese model for consistent extraction output.
                  return Tesseract.recognize(canvas, ocrLangPrimary)
                    .catch(function () {
                      return Tesseract.recognize(canvas, ocrLangFallback);
                    })
                    .catch(function () {
                      return Tesseract.recognize(canvas, "eng");
                    })
                    .then(function (result) {
                      return result && result.data && result.data.text
                        ? result.data.text
                        : "";
                    })
                    .catch(function () {
                      return "";
                    });
                });
            }),
          );
        }

        return Promise.all(tasks).then(function (texts) {
          return texts.join("\n").trim();
        });
      })
      .catch(function () {
        return "";
      });
  }

  function processSinglePdf(file) {
    setProcessingState(true, "Processing PDF...");
    readAsArrayBuffer(file)
      .then(function (buffer) {
        return extractTextFromPdfBytes(new Uint8Array(buffer));
      })
      .then(function (text) {
        ocrField.value = text;
        ocrMapField.value = "";
        setProcessingState(false, "");
      })
      .catch(function () {
        ocrField.value = "";
        ocrMapField.value = "";
        setProcessingState(false, "");
      });
  }

  function processZip(file) {
    if (typeof JSZip === "undefined") {
      ocrField.value = "";
      ocrMapField.value = "";
      return;
    }

    setProcessingState(true, "Processing ZIP...");
    readAsArrayBuffer(file)
      .then(function (buffer) {
        return JSZip.loadAsync(buffer);
      })
      .then(function (zip) {
        var entryNames = Object.keys(zip.files).filter(function (name) {
          var zf = zip.files[name];
          return zf && !zf.dir && /\.pdf$/i.test(name);
        });

        var ocrMap = {};
        var chain = Promise.resolve();

        entryNames.forEach(function (name, idx) {
          chain = chain.then(function () {
            setProcessingState(
              true,
              "Processing ZIP (" + (idx + 1) + "/" + entryNames.length + ")...",
            );
            return zip.files[name]
              .async("uint8array")
              .then(function (pdfBytes) {
                return extractTextFromPdfBytes(pdfBytes).then(function (text) {
                  ocrMap[name.toLowerCase()] = text;
                  ocrMap[basenameLower(name)] = text;
                });
              });
          });
        });

        return chain.then(function () {
          ocrField.value = "";
          ocrMapField.value = JSON.stringify(ocrMap);
          setProcessingState(false, "");
        });
      })
      .catch(function () {
        ocrField.value = "";
        ocrMapField.value = "";
        setProcessingState(false, "");
      });
  }

  fileInput.addEventListener("change", function () {
    ocrField.value = "";
    ocrMapField.value = "";
    setProcessingState(false, "");

    if (!fileInput.files || !fileInput.files[0]) return;

    var file = fileInput.files[0];
    var ext = (file.name || "").split(".").pop().toLowerCase();

    if (ext === "pdf") {
      processSinglePdf(file);
      return;
    }

    if (ext === "zip") {
      processZip(file);
    }
  });

  form.addEventListener("submit", function (e) {
    if (!ocrRunning) return;
    e.preventDefault();
    showNotification(
      "Please wait. PDF text extraction is still processing.",
      "warning",
    );
  });
})();
