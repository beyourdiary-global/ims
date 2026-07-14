(function () {
  var config = window.stockOrderReqConfig || {};
  var page = config.page || "Stock Order Request";
  var siteURL = config.siteURL || "";
  var action = config.action || "";
  var modalAct = config.modalAct || "";

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

  function updateAirbillScanStatus(message, isError) {
    const statusEl = document.getElementById("sorScanAirbillStatus");
    if (!statusEl) {
      return;
    }

    const statusMessage = String(message || "");
    if (statusMessage === "") {
      statusEl.style.display = "none";
      statusEl.innerHTML = "";
      return;
    }

    statusEl.style.display = "block";
    statusEl.style.color = isError ? "#dc3545" : "#6c757d";
    statusEl.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
      statusMessage;
  }

  function waitForAirbillLoadingPaint() {
    return new Promise(function (resolve) {
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(resolve);
      });
    });
  }

  function extractAirbillCandidate(rawText) {
    const text = String(rawText || "").trim();
    if (text === "") {
      return "";
    }

    const upperText = text.toUpperCase();

    // Keep the suffix when it is present; otherwise the compact fallback
    // would accept only the numeric prefix.
    const directMatch = upperText.match(/\bMY\d{10,14}[A-Z]?\b/g);
    if (directMatch && directMatch.length > 0) {
      return String(directMatch[0] || "").toUpperCase();
    }

    const compactText = upperText.replace(/[^A-Z0-9]+/g, "");
    const compactMatch = compactText.match(/MY\d{10,14}[A-Z]?/g);
    if (compactMatch && compactMatch.length > 0) {
      return String(compactMatch[0] || "").toUpperCase();
    }

    const fixedText = compactText
      .replace(/MYO(?=\d{9,13})/g, "MY0")
      .replace(/MYQ(?=\d{9,13})/g, "MY0")
      .replace(/MYI(?=\d{9,13})/g, "MY1")
      .replace(/MYL(?=\d{9,13})/g, "MY1");

    const fixedMatch = fixedText.match(/MY\d{10,14}[A-Z]?/g);
    if (fixedMatch && fixedMatch.length > 0) {
      return String(fixedMatch[0] || "").toUpperCase();
    }

    const normalizedText = upperText.replace(/[^A-Z0-9]+/g, " ");
    const fallbackMatches = normalizedText.match(/\b[A-Z0-9]{10,30}\b/g) || [];
    for (const candidate of fallbackMatches) {
      const digitCount = (candidate.match(/\d/g) || []).length;
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
      const imageUrl = URL.createObjectURL(file);
      const imageElement = new Image();
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
    const sourceWidth = imageElement.naturalWidth || imageElement.width;
    const sourceHeight = imageElement.naturalHeight || imageElement.height;

    const cropX = Math.max(0, Math.floor(region.x * sourceWidth));
    const cropY = Math.max(0, Math.floor(region.y * sourceHeight));
    const cropWidth = Math.min(sourceWidth - cropX, Math.floor(region.w * sourceWidth));
    const cropHeight = Math.min(sourceHeight - cropY, Math.floor(region.h * sourceHeight));

    const canvas = document.createElement("canvas");
    canvas.width = Math.max(1, Math.floor(cropWidth * scale));
    canvas.height = Math.max(1, Math.floor(cropHeight * scale));

    const ctx = canvas.getContext("2d", { willReadFrequently: true });
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

    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imageData.data;

    for (let i = 0; i < data.length; i += 4) {
      const gray = Math.round(
        data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114,
      );

      let value = gray;
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
    const regions = [
      { x: 0.00, y: 0.00, w: 1.00, h: 1.00 },
      { x: 0.42, y: 0.00, w: 0.58, h: 1.00 },
      { x: 0.45, y: 0.00, w: 0.52, h: 0.24 },
      { x: 0.76, y: 0.48, w: 0.22, h: 0.30 },
      { x: 0.50, y: 0.00, w: 0.48, h: 0.35 },
      { x: 0.47, y: 0.45, w: 0.51, h: 0.35 },
    ];

    const modes = ["normal", "threshold", "contrast", "invert"];
    const candidates = [];

    regions.forEach(function (region, regionIndex) {
      modes.forEach(function (mode) {
        const scale = regionIndex === 0 ? 1 : 2;
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

    const dataUrl = canvas.toDataURL("image/png");
    const imageElement = new Image();
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
      const detector = new BarcodeDetector({
        formats: ["code_128", "code_39", "ean_13", "qr_code"],
      });
      const results = await detector.detect(canvas);

      for (const result of results || []) {
        const candidate = extractAirbillCandidate(result.rawValue || "");
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

    const reader = getZXingReader();
    if (!reader && !("BarcodeDetector" in window)) {
      throw new Error("Barcode scanner library is not available.");
    }

    const imageElement = await loadImageFromFile(file);
    const canvases = buildAirbillCanvasCandidates(imageElement);

    for (const canvas of canvases) {
      const nativeCandidate = await decodeCanvasWithNativeBarcodeDetector(canvas);
      if (nativeCandidate !== "") {
        return nativeCandidate;
      }
    }

    if (reader) {
      for (const canvas of canvases) {
        try {
          const result = await decodeCanvasWithZXing(reader, canvas);
          const candidate = extractAirbillCandidate(getDecodedText(result));
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
        return normalizeText(
          String(opt.name || "") + " " + String(opt.item_code || ""),
        ).indexOf(keyword) !== -1;
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
      li.textContent = opt.display_name || opt.name;
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
    var productRows = Array.isArray(p.product_rows)
      ? p.product_rows
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
    var prodIds = productRows.map(function (row) {
      return row.id;
    });
    if (prodIds.length === 0 && Array.isArray(p.product_ids)) {
      prodIds = p.product_ids
        .map(function (x) {
          return parseInt(x, 10);
        })
        .filter(function (x) {
          return !isNaN(x) && x > 0;
        });
    }
    return {
      id: parseInt(p.id, 10),
      name: String(p.name || ""),
      item_code: String(p.item_code || "").trim(),
      display_name:
        String(p.item_code || "").trim() !== ""
          ? String(p.name || "") +
            " (Item Code: " +
            String(p.item_code || "").trim() +
            ")"
          : String(p.name || ""),
      item_description: String(p.item_description || ""),
      price: String(p.price || "0"),
      product_ids: prodIds,
      product_rows: productRows,
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
    return !!pkg && productId > 0;
  }

  function getProductOptionsForPackage(pkgId) {
    return productOptions;
  }

  function getPackageProductRows(pkg) {
    if (pkg && Array.isArray(pkg.product_rows) && pkg.product_rows.length > 0) {
      return pkg.product_rows
        .map(function (row) {
          var productId = parseInt(row && row.id !== undefined ? row.id : 0, 10);
          var baseQty = parseInt(
            row && row.base_qty !== undefined ? row.base_qty : 1,
            10,
          );
          if (isNaN(productId) || productId <= 0) return null;
          if (isNaN(baseQty) || baseQty <= 0) baseQty = 1;
          var product = productLookupById[productId] || null;
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

    if (!pkg || !Array.isArray(pkg.product_ids)) {
      return [];
    }

    return pkg.product_ids
      .map(function (productId) {
        var parsedProductId = parseInt(productId, 10);
        if (isNaN(parsedProductId) || parsedProductId <= 0) return null;
        var product = productLookupById[parsedProductId] || null;
        return {
          id: parsedProductId,
          name: product ? String(product.name || "") : "",
          base_qty: 1,
        };
      })
      .filter(function (row) {
        return !!row;
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
    var dataGroup = String(row.getAttribute("data-package-group") || "");
    var hiddenGroupInput = row.querySelector(".sor-item-group-key");
    var hiddenGroup = hiddenGroupInput
      ? String(hiddenGroupInput.value || "")
      : "";
    var groupId = dataGroup || hiddenGroup || "";
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

  function getProductRowsForGroup(groupId) {
    if (!groupId) return [];
    return Array.prototype.slice.call(
      document.querySelectorAll(
        '#sorItemBody tr[data-package-group="' +
          groupId +
          '"][data-row-role="product"]',
      ),
    );
  }

  function hasOnlyBlankProductRows(groupId) {
    var productRows = getProductRowsForGroup(groupId);
    if (productRows.length === 0) return true;

    return productRows.every(function (row) {
      var prodNameInput = row.querySelector(".sor-item-prod-name");
      var prodIdInput = row.querySelector(".sor-item-prod-id");
      var prodName = prodNameInput ? String(prodNameInput.value || "").trim() : "";
      var prodId = prodIdInput ? parseInt(prodIdInput.value || "0", 10) : 0;
      return prodName === "" && (isNaN(prodId) || prodId <= 0);
    });
  }

  function syncResolvedPackageGroup(row, forceExpand) {
    var packageRow = isPackageRow(row)
      ? row
      : getPackageRowForGroup(getRowGroupId(row)) || row;
    if (!packageRow) return;

    var pkg = getSelectedPackageInRow(packageRow);
    if (!pkg) {
      updateRowDescAndPrice(packageRow);
      renderPackageGroups();
      recalculateTotalPrice();
      return;
    }

    var pkgIdInput = packageRow.querySelector(".sor-item-pkg-id");
    var pkgNameInput = packageRow.querySelector(".sor-item-pkg-name");
    if (pkgIdInput) pkgIdInput.value = String(pkg.id || "");
    if (pkgNameInput && String(pkg.name || "") !== "") {
      pkgNameInput.value = String(pkg.name || "");
    }

    var groupId = getRowGroupId(packageRow);
    if (getProductRowsForGroup(groupId).length === 0 && action !== "") {
      addPackageRowWithData(pkg, null, groupId, "product", packageRow);
    }

    updateRowDescAndPrice(packageRow);
    renderPackageGroups();
    recalculateTotalPrice();
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
      1,
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
    var packagePriceEditInput = packageRow.querySelector(
      ".sor-item-package-price-edit",
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

    var editedGroupPrice = packagePriceEditInput
      ? parseFloat(packagePriceEditInput.value || "")
      : NaN;
    var storedGroupPrice = packagePriceHidden
      ? parseFloat(packagePriceHidden.value || "")
      : NaN;
    var groupPrice = !isNaN(editedGroupPrice) && editedGroupPrice >= 0
      ? editedGroupPrice
      : !isNaN(storedGroupPrice) && storedGroupPrice >= 0
        ? storedGroupPrice
        : 0;
    if (groupPrice <= 0 && !packagePriceEditInput && !packagePriceHidden) {
      var pkg = getSelectedPackageInRow(packageRow);
      var unitPrice = pkg ? parseFloat(pkg.price || "0") : 0;
      if (isNaN(unitPrice)) unitPrice = 0;
      groupPrice = unitPrice * packageQty;
    }
    if (groupPrice <= 0 && !isNaN(storedGroupPrice) && storedGroupPrice === 0) {
      groupPrice = 0;
    }
    if (groupPrice <= 0 && isNaN(editedGroupPrice) && isNaN(storedGroupPrice)) {
      var pkg = getSelectedPackageInRow(packageRow);
      var unitPrice = pkg ? parseFloat(pkg.price || "0") : 0;
      if (isNaN(unitPrice)) unitPrice = 0;
      groupPrice = unitPrice * packageQty;
    }
    if (totalInput) totalInput.value = groupPrice.toFixed(2);
    if (packagePriceEditInput) packagePriceEditInput.value = groupPrice.toFixed(2);
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
          if (action !== "") {
            actionCell.innerHTML =
              '<button type="button" class="mt-1 add-product-row-btn" id="action_menu_btn"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>' +
              '<button type="button" class="mt-1 remove-product-btn" id="action_menu_btn"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>';
          } else {
            actionCell.innerHTML = "";
          }
        }
      });

      if (packageRow) {
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

      if (action === "") {
        pkgActionCell.innerHTML = "";
        return;
      }

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
    if (prodNameInput && prodIdInput) {
      if (product) {
        prodIdInput.value = String(product.id);
      } else {
        prodIdInput.value = "";
      }
    }

    validateProductBelongsPackage(row);
    syncGroupValues(row, false);
  }

  function recalculateTotalPrice() {
    var total = 0;
    document.querySelectorAll("#sorItemBody tr").forEach(function (row) {
      if (!isPackageRow(row)) return;

      var packagePriceInput = row.querySelector(".sor-item-package-price");
      if (!packagePriceInput) return;

      var price = packagePriceInput
        ? parseFloat(packagePriceInput.value || "0")
        : 0;

      if (!isNaN(price) && price > 0) {
        total += price;
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
        syncResolvedPackageGroup(row, true);
      });

      nameInput.addEventListener("focus", function () {
        validateProductBelongsPackage(row);
      });

      nameInput.addEventListener("change", function () {
        syncResolvedPackageGroup(row, false);
      });
    }

    if (idInput) {
      idInput.addEventListener("change", function () {
        syncResolvedPackageGroup(row, false);
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

    var packagePriceEditInput = row.querySelector(
      ".sor-item-package-price-edit",
    );
    if (packagePriceEditInput && packagePriceEditInput.dataset.priceBound !== "1") {
      packagePriceEditInput.dataset.priceBound = "1";
      packagePriceEditInput.addEventListener("input", function () {
        syncGroupValues(row, false);
        recalculateTotalPrice();
      });
      packagePriceEditInput.addEventListener("change", function () {
        syncGroupValues(row, false);
        recalculateTotalPrice();
      });
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
    baseQty,
    rowRole,
  ) {
    var isPackageHeader = rowRole === "package";
    var normalizedBaseQty = parseInt(baseQty || "1", 10);
    if (isNaN(normalizedBaseQty) || normalizedBaseQty <= 0) {
      normalizedBaseQty = 1;
    }
    var packagePriceField = isPackageHeader
      ? '<input class="form-control sor-item-total sor-item-package-price-edit" type="number" step="0.01" min="0" value="0.00" ' +
        (action === "" ? "readonly" : "") +
        ' aria-label="Package price">'
      : '<input class="form-control sor-item-total" type="text" value="" readonly>';
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
      "</div></td>" +
      (isPackageHeader
        ? '<td><input type="hidden" class="sor-item-prod-name" name="sor_item_prod_name[]" value=""><input type="hidden" class="sor-item-prod-id" name="sor_item_prod_id[]" value=""></td>'
        : '<td><div class="autocomplete"><input class="form-control sor-item-prod-name" type="text" id="sor_item_prod_name_' +
          rowKey +
          '" name="sor_item_prod_name[]" value="' +
          escapeAttr(prodName || "") +
          '"><input type="hidden" class="sor-item-prod-id" id="sor_item_prod_id_' +
          rowKey +
          '" name="sor_item_prod_id[]" value="' +
          escapeAttr(prodId || "") +
          '"></div></td>') +
      '<td class="cell-desc"><div class="desc-main-field"><input class="form-control sor-item-desc" type="text" name="sor_item_desc[]" readonly value="' +
      escapeAttr(desc || "") +
      '"></div></td>' +
      '<td><div class="qty-main-field"><input class="form-control sor-item-qty" type="number" min="1" name="sor_item_product_qty[]" value="' +
      escapeAttr(qty || 1) +
      '" ' +
      (action === "" ? "readonly" : "") +
      '"><input class="sor-item-id" type="hidden" name="sor_item_id[]" value="' +
      "" +
      '"><input class="sor-item-row-role" type="hidden" name="sor_item_row_role[]" value="' +
      (isPackageHeader ? "package" : "product") +
      '"><input class="sor-item-package-qty" type="hidden" name="sor_item_package_qty[]" value="' +
      escapeAttr(qty || 1) +
      '"><input class="sor-item-base-qty" type="hidden" name="sor_item_base_qty[]" value="' +
      escapeAttr(isPackageHeader ? 1 : normalizedBaseQty) +
      '"><input class="sor-item-group-key" type="hidden" name="sor_item_group_key[]" value="' +
      escapeAttr(rowKey) +
      '"><input class="sor-item-package-price" type="hidden" name="sor_item_package_price[]" value="0.00' +
      '"></div></td>' +
      '<td class="cell-total"><div class="total-main-field">' + packagePriceField + '</div></td>' +
      "<td>" +
      (isPackageHeader
        ? '<button type="button" class="mt-1 remove-package-btn" id="action_menu_btn"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>'
        : '<button type="button" class="mt-1 add-product-row-btn" id="action_menu_btn"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button><button type="button" class="mt-1 remove-product-btn" id="action_menu_btn"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>') +
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
      1,
      "package",
    );
    tbody.appendChild(tr);
    var groupHidden = tr.querySelector(".sor-item-group-key");
    if (groupHidden) {
      groupHidden.value = String(tr.getAttribute("data-package-group") || "");
    }
    bindRowAutocomplete(tr);
    return tr;
  }

  function addPackageRowWithData(
    pkg,
    productMeta,
    groupId,
    rowRole,
    insertAfterRow,
  ) {
    var tbody = document.getElementById("sorItemBody");
    if (!tbody || !pkg) return;

    var role = rowRole === "package" ? "package" : "product";
    var normalizedProductMeta = null;
    if (role !== "package") {
      if (productMeta && typeof productMeta === "object") {
        var metaProductId = parseInt(
          productMeta.id !== undefined ? productMeta.id : 0,
          10,
        );
        if (!isNaN(metaProductId) && metaProductId > 0) {
          var metaProduct = productLookupById[metaProductId] || null;
          normalizedProductMeta = {
            id: metaProductId,
            name:
              String(productMeta.name || "") ||
              (metaProduct ? String(metaProduct.name || "") : ""),
            base_qty: Math.max(
              1,
              parseInt(
                productMeta.base_qty !== undefined ? productMeta.base_qty : 1,
                10,
              ) || 1,
            ),
          };
        }
      } else {
        var legacyProductId = parseInt(productMeta || 0, 10);
        if (!isNaN(legacyProductId) && legacyProductId > 0) {
          var legacyProduct = productLookupById[legacyProductId] || null;
          normalizedProductMeta = {
            id: legacyProductId,
            name: legacyProduct ? String(legacyProduct.name || "") : "",
            base_qty: 1,
          };
        }
      }
    }
    var initialQty =
      role === "package"
        ? 1
        : normalizedProductMeta
          ? normalizedProductMeta.base_qty
          : 1;
    var rowKey = Date.now().toString() + "_" + Math.floor(Math.random() * 1000);
    var tr = document.createElement("tr");
    tr.setAttribute("data-row-key", rowKey);
    tr.setAttribute("data-package-group", groupId || "pkg_group_" + rowKey);
    tr.setAttribute("data-row-role", role);
    tr.innerHTML = buildPackageRowHtml(
      rowKey,
      pkg.name || "",
      pkg.id || "",
      role === "package"
        ? ""
        : normalizedProductMeta
          ? normalizedProductMeta.name
          : "",
      role === "package"
        ? ""
        : normalizedProductMeta
          ? normalizedProductMeta.id
          : "",
      pkg.item_description || "",
      initialQty,
      role === "package"
        ? 1
        : normalizedProductMeta
          ? normalizedProductMeta.base_qty
          : 1,
      role,
    );
    if (insertAfterRow && insertAfterRow.parentNode === tbody) {
      insertAfterRow.after(tr);
    } else {
      tbody.appendChild(tr);
    }
    var groupHidden = tr.querySelector(".sor-item-group-key");
    if (groupHidden) {
      groupHidden.value = String(tr.getAttribute("data-package-group") || "");
    }
    bindRowAutocomplete(tr);
    updateRowDescAndPrice(tr);
    return tr;
  }

  function expandPackageProducts(row) {
    if (!isPackageRow(row)) return;

    var pkg = getSelectedPackageInRow(row);
    var groupId = getRowGroupId(row);
    var packageProductRows = getPackageProductRows(pkg);

    var sig =
      pkg && packageProductRows.length > 0
        ? String(pkg.id) +
          ":" +
          packageProductRows
            .map(function (productRow) {
              return (
                String(productRow.id || "") + "x" + String(productRow.base_qty || 1)
              );
            })
            .join(",")
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

    if (!pkg || packageProductRows.length === 0) {
      reindexRows();
      renderPackageGroups();
      return;
    }

    var insertAfter = row;
    packageProductRows.forEach(function (productRow) {
      var added = addPackageRowWithData(
        pkg,
        productRow,
        groupId,
        "product",
        insertAfter,
      );
      if (added) insertAfter = added;
    });

    reindexRows();
    renderPackageGroups();
  }

  var warehouseNameInput = document.getElementById("sor_warehouse_name");
  var warehouseIdInput = document.getElementById("sor_warehouse");
  var courierNameInput = document.getElementById("sor_courier_name");
  var courierIdInput = document.getElementById("sor_courier");
  const trackingInput = document.getElementById("sor_tracking_no");
  const stockOrderImageInput = document.getElementById("sor_stock_order_image");

  bindTextAutocomplete(warehouseNameInput, warehouseIdInput, warehouseOptions);
  bindTextAutocomplete(courierNameInput, courierIdInput, courierOptions);

  if (stockOrderImageInput) {
    stockOrderImageInput.addEventListener("change", async function () {
      const file =
        stockOrderImageInput.files && stockOrderImageInput.files[0]
          ? stockOrderImageInput.files[0]
          : null;

      if (!file) {
        updateAirbillScanStatus("", false);
        return;
      }

      updateAirbillScanStatus("Extracting tracking number...", false);
      await waitForAirbillLoadingPaint();

      try {
        const scannedAirbill = await decodeAirbillFromImageFile(file);
        if (scannedAirbill === "") {
          updateAirbillScanStatus("", false);
          return;
        }

        if (trackingInput) {
          trackingInput.value = scannedAirbill;
          trackingInput.dispatchEvent(new Event("input", { bubbles: true }));
          trackingInput.dispatchEvent(new Event("change", { bubbles: true }));
        }

        updateAirbillScanStatus("", false);
      } catch (error) {
        console.error(error);
        updateAirbillScanStatus("", false);
      }
    });
  }

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

    if (isPackageRow(row)) {
      syncResolvedPackageGroup(row, false);
    } else {
      var storedPriceInput = row.querySelector(".sor-item-package-price");
      var hasStoredPrice = storedPriceInput
        ? parseFloat(storedPriceInput.value || "0") > 0
        : false;
      if (!hasStoredPrice) {
        updateRowDescAndPrice(row);
      }
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
      if (e.target.classList.contains("sor-item-package-price-edit")) {
        var priceRow = e.target.closest("tr");
        if (priceRow) {
          syncGroupValues(priceRow, false);
          recalculateTotalPrice();
        }
      }
    });

    sorItemBody.addEventListener("click", function (e) {
      var addItemBtn = e.target.closest(".add-item-row-btn");
      var addProductBtn = e.target.closest(".add-product-row-btn");
      var packageRemoveBtn = e.target.closest(".remove-package-btn");
      var productRemoveBtn = e.target.closest(
        ".remove-product-btn, .remove-item-btn",
      );

      if (addItemBtn) {
        addPackageHeaderRow("pkg_group_" + Date.now().toString());
        reindexRows();
        renderPackageGroups();
        recalculateTotalPrice();
      } else if (addProductBtn) {
        var productRow = addProductBtn.closest("tr");
        if (!productRow) return;
        var productGroupId = getRowGroupId(productRow);
        var productPackageRow = getPackageRowForGroup(productGroupId);
        var productPackage = productPackageRow
          ? getSelectedPackageInRow(productPackageRow)
          : null;
        if (!productPackage) return;
        addPackageRowWithData(
          productPackage,
          null,
          productGroupId,
          "product",
          productRow,
        );
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
          if (groupProducts.length === 1) {
            var productNameInput = removeRow.querySelector(".sor-item-prod-name");
            var productIdInput = removeRow.querySelector(".sor-item-prod-id");
            var productQtyInput = removeRow.querySelector(".sor-item-qty");
            if (productNameInput) productNameInput.value = "";
            if (productIdInput) productIdInput.value = "";
            if (productQtyInput) productQtyInput.value = "1";
          } else {
            removeRow.remove();
          }
          reindexRows();
          recalculateTotalPrice();
        }
      }
    });
  }

  function getStatusModalTitle(actCode) {
    if (actCode === "I") return "Successful Insert " + page;
    if (actCode === "E") return "Successful Edit " + page;
    if (actCode === "NC") return "No changes were made.";
    return "";
  }

  function showStatusModalIfNeeded() {
    var title = getStatusModalTitle(modalAct);
    if (!title || typeof bootstrap === "undefined") {
      return;
    }

    var modalElem = document.createElement("div");
    modalElem.className = "modal fade";

    // --- FIX: Build DOM elements safely to prevent XSS injection ---
    var modalDialog = document.createElement("div");
    modalDialog.className = "modal-dialog modal-dialog-centered";
    modalDialog.style.cssText =
      "font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;";

    var modalContent = document.createElement("div");
    modalContent.className = "modal-content";

    var modalBody = document.createElement("div");
    modalBody.className = "modal-body fs-6 mt-3";

    var titleElem = document.createElement("p");
    titleElem.style.cssText =
      "text-align:center;font-weight:bold;font-size:25px;";
    titleElem.textContent = title; // textContent safely escapes HTML
    modalBody.appendChild(titleElem);

    var modalFooter = document.createElement("div");
    modalFooter.className = "modal-footer d-flex justify-content-center mt-n3";
    modalFooter.style.cssText = "border-top:0;";

    var continueBtn = document.createElement("button");
    continueBtn.type = "button";
    continueBtn.className = "btn";
    continueBtn.id = "sorStatusContinueBtn";
    continueBtn.style.cssText =
      "border:1px solid #FF9B44;background-color:#FFFFFF;color:#FF9B44;box-shadow:0 0 !important;border-radius:24px;text-transform:none;";
    continueBtn.textContent = "Continue";

    modalFooter.appendChild(continueBtn);
    modalContent.appendChild(modalBody);
    modalContent.appendChild(modalFooter);
    modalDialog.appendChild(modalContent);
    modalElem.appendChild(modalDialog);
    // ---------------------------------------------------------------

    document.body.appendChild(modalElem);
    var modal = new bootstrap.Modal(modalElem, {
      keyboard: false,
      backdrop: "static",
    });
    modal.show();

    modalElem.addEventListener("click", function (e) {
      if (e.target && e.target.id === "sorStatusContinueBtn") {
        modal.hide();
      }
    });

    modalElem.addEventListener("hidden.bs.modal", function () {
      modalElem.remove();
    });
  }

  showStatusModalIfNeeded();

  if (config.showQrPanel) {
    var copyBtn = document.getElementById("copyOrderLinkBtn");
    var orderLinkInput = document.getElementById("sorOrderLink");
    if (copyBtn && orderLinkInput) {
      copyBtn.addEventListener("click", function () {
        var originalIcon = copyBtn.innerHTML;
        var doneIcon = '<i class="fa-solid fa-check"></i>';
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(orderLinkInput.value);
        } else {
          orderLinkInput.focus();
          orderLinkInput.select();
          document.execCommand("copy");
        }

        copyBtn.innerHTML = doneIcon;
        copyBtn.setAttribute("title", "Copied");
        setTimeout(function () {
          copyBtn.innerHTML = originalIcon;
          copyBtn.setAttribute("title", "Copy Link");
        }, 1200);
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
