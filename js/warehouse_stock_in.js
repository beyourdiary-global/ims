function norm(v) {
  return String(v || "")
    .toLowerCase()
    .replace(/\s+/g, " ")
    .trim();
}

var productsJsonInput = document.getElementById("stockInProductsJson");
var products = [];
if (productsJsonInput && productsJsonInput.value) {
  try {
    products = JSON.parse(productsJsonInput.value);
  } catch (e) {
    products = [];
  }
}
var productByName = {};
products.forEach(function (p) {
  productByName[norm(p.name)] = p;
});

function closeList(input) {
  var listId = input.getAttribute("data-list-id");
  if (!listId) return;
  var el = document.getElementById(listId);
  if (el) el.remove();
}

function renderList(input, hiddenInput, options) {
  closeList(input);
  var keyword = norm(input.value);
  if (
    keyword === "" ||
    input.hasAttribute("readonly") ||
    input.hasAttribute("disabled")
  )
    return;

  var filtered = (options || [])
    .filter(function (opt) {
      return norm(opt.name).indexOf(keyword) !== -1;
    })
    .slice(0, 20);
  if (filtered.length === 0) return;

  var listId = "sr_" + (input.name || "x") + "_" + Date.now();
  input.setAttribute("data-list-id", listId);

  var ul = document.createElement("ul");
  ul.className = "searchResult";
  ul.id = listId;
  ul.style.width = input.offsetWidth + "px";

  filtered.forEach(function (opt) {
    var li = document.createElement("li");
    li.textContent = opt.name;
    li.addEventListener("mousedown", function (e) {
      e.preventDefault();
      input.value = opt.name;
      hiddenInput.value = String(opt.id);
      closeList(input);
    });
    ul.appendChild(li);
  });

  input.after(ul);
}

function bindRow(row) {
  var productName = row.querySelector(".product_name");
  var productId = row.querySelector(".product_id");
  if (!productName || !productId || productName.dataset.bound === "1") return;

  productName.dataset.bound = "1";

  productName.addEventListener("input", function () {
    productId.value = "";
    renderList(productName, productId, products);
  });

  productName.addEventListener("change", function () {
    var byName = productByName[norm(productName.value)] || null;
    productId.value = byName ? String(byName.id) : "";
  });

  productName.addEventListener("blur", function () {
    setTimeout(function () {
      closeList(productName);
    }, 120);
  });
}

function reindexRows() {
  document.querySelectorAll("#stockInItemBody tr").forEach(function (row, idx) {
    var no = row.querySelector(".row-no");
    if (no) no.textContent = String(idx + 1);
  });
}

var isViewModeInput = document.getElementById("isViewModeFlag");
var isViewMode = !!(isViewModeInput && isViewModeInput.value === "1");

function updateRowActionButtons() {
  if (isViewMode) {
    return;
  }

  document.querySelectorAll("#stockInItemBody tr").forEach(function (row, idx) {
    var actionCell = row.querySelector(".row-action-cell");
    if (!actionCell) {
      return;
    }

    if (idx === 0) {
      actionCell.innerHTML =
        '<button class="mt-1" id="action_menu_btn" type="button" onclick="AddStockInRow()"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>';
    } else {
      actionCell.innerHTML =
        '<button class="mt-1 remove-stock-row" id="action_menu_btn" type="button" onclick="RemoveStockInRow(this)"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>';
    }
  });
}

function AddStockInRow() {
  if (isViewMode) {
    return;
  }

  var tbody = document.getElementById("stockInItemBody");
  var tr = document.createElement("tr");
  tr.innerHTML =
    '<td class="row-no"></td>' +
    '<td class="autocomplete"><input type="text" class="form-control product_name" name="product_name[]" placeholder="Type Product" required><input type="hidden" name="product_id[]" class="product_id" value=""></td>' +
    '<td><input class="form-control" type="number" name="product_quantity[]" min="1" value="" required></td>' +
    '<td class="row-action-cell" style="text-align:center;"></td>';

  tbody.appendChild(tr);
  bindRow(tr);
  reindexRows();
  updateRowActionButtons();
}

function RemoveStockInRow(btn) {
  if (isViewMode) {
    return;
  }

  var row = btn.closest("tr");
  if (!row) {
    return;
  }

  var tbody = document.getElementById("stockInItemBody");
  var rows = tbody.querySelectorAll("tr");
  if (rows.length <= 1) {
    var productNameInput = row.querySelector('input[name="product_name[]"]');
    var productIdInput = row.querySelector('input[name="product_id[]"]');
    var qtyInput = row.querySelector('input[name="product_quantity[]"]');
    if (productNameInput) productNameInput.value = "";
    if (productIdInput) productIdInput.value = "";
    if (qtyInput) qtyInput.value = "";
    return;
  }

  row.remove();
  reindexRows();
  updateRowActionButtons();
}

document.querySelectorAll("#stockInItemBody tr").forEach(function (row) {
  bindRow(row);
});
updateRowActionButtons();

function setFieldError(input, message) {
  if (!input) return;

  var fieldKey =
    input.getAttribute("data-field-key") || input.name || "field_" + Date.now();
  input.setAttribute("data-field-key", fieldKey);

  input.classList.add("si-invalid");
  input.style.borderColor = "#ff0000";

  var container = input.closest("td") || input.parentElement;
  if (!container) return;

  var selector = '.si-field-error[data-field-key="' + fieldKey + '"]';
  var errorEl = container.querySelector(selector);
  if (!errorEl) {
    errorEl = document.createElement("div");
    errorEl.className = "si-field-error";
    errorEl.setAttribute("data-field-key", fieldKey);
    errorEl.style.color = "#ff0000";
    errorEl.style.marginTop = "4px";
    errorEl.style.fontSize = "0.95rem";
    container.appendChild(errorEl);
  }
  errorEl.textContent = message;
}

function clearAllFieldErrors() {
  document.querySelectorAll(".si-field-error").forEach(function (el) {
    el.remove();
  });
  document.querySelectorAll(".si-invalid").forEach(function (input) {
    input.classList.remove("si-invalid");
    input.style.borderColor = "";
  });
}

function isEmptyValue(value) {
  return String(value || "").trim() === "";
}

var stockInForm = document.getElementById("stockInForm");
if (stockInForm) {
  stockInForm.addEventListener("submit", function (e) {
    var submitter = e.submitter;
    if (!submitter) {
      return;
    }

    var actionValue = submitter.value || "";
    if (actionValue !== "addData" && actionValue !== "updData") {
      return;
    }

    clearAllFieldErrors();

    var hasError = false;

    var warehouseInput = document.getElementById("warehouse_id");
    var stockInDateInput = document.getElementById("stock_in_date");
    var orderNumberInput = document.getElementById("order_number");

    if (warehouseInput && isEmptyValue(warehouseInput.value)) {
      setFieldError(warehouseInput, "Warehouse is required!");
      hasError = true;
    }

    if (stockInDateInput && isEmptyValue(stockInDateInput.value)) {
      setFieldError(stockInDateInput, "Stock In Date is required!");
      hasError = true;
    }

    if (orderNumberInput && isEmptyValue(orderNumberInput.value)) {
      setFieldError(orderNumberInput, "Order Number is required!");
      hasError = true;
    }

    document
      .querySelectorAll("#stockInItemBody tr")
      .forEach(function (row, idx) {
        var productNameInput = row.querySelector(
          'input[name="product_name[]"]',
        );
        var qtyInput = row.querySelector('input[name="product_quantity[]"]');

        if (productNameInput) {
          productNameInput.setAttribute(
            "data-field-key",
            "product_name_" + idx,
          );
          if (isEmptyValue(productNameInput.value)) {
            setFieldError(productNameInput, "Product Name is required!");
            hasError = true;
          }
        }

        if (qtyInput) {
          qtyInput.setAttribute("data-field-key", "product_qty_" + idx);
          if (isEmptyValue(qtyInput.value)) {
            setFieldError(qtyInput, "Product Quantity is required!");
            hasError = true;
          }
        }
      });

    if (hasError) {
      e.preventDefault();
    }
  });
}
