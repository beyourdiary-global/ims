async function setBarcodeSlotTotal(rowCount) {
  var totalSlotCell = $("#barcode_slot_total");
  if (!totalSlotCell.length) {
    return;
  }

  var totalSlot = 0;
  var maxRows = Number.isFinite(rowCount)
    ? rowCount
    : $("#productList TBODY TR").length;

  for (var num = 1; num <= maxRows; num++) {
    var barcodeSlotField = $("#barcode_slot_" + num);
    if (!barcodeSlotField.length) {
      continue;
    }

    var barcodeSlot = parseInt(barcodeSlotField.val(), 10);
    if (!isNaN(barcodeSlot)) {
      totalSlot += barcodeSlot;
    }
  }

  totalSlotCell.contents().filter(function () {
    return this.nodeType === Node.TEXT_NODE;
  }).remove();
  totalSlotCell.prepend(document.createTextNode(String(totalSlot)));

  var hiddenField = $("#barcode_slot_total_hidden");
  if (!hiddenField.length) {
    hiddenField = $(
      '<input name="barcode_slot_total_hidden" id="barcode_slot_total_hidden" type="hidden">',
    );
    totalSlotCell.append(hiddenField);
  }

  hiddenField.val(totalSlot);
}

function Add() {
  AddRow(
    $("#prod_name").val(),
    $("#prod_val").val(),
    $("#wgt").val(),
    $("#wgt_unit").val(),
    $("#wgt_unit_val").val(),
    $("#barcode_status").val(),
    $("#barcode_slot").val(),
  );
}

function AddRow() {
  //Get the reference of the Table's TBODY element.
  var tBody = $("#productList > TBODY")[0];
  if (!tBody) {
    return;
  }

  var numbering = parseInt($("#productList > TBODY > TR:last > TD:first").text(), 10);
  if (isNaN(numbering) || numbering < 0) {
    numbering = $("#productList > TBODY > TR").length;
  }
  numbering += 1;
  numbering = numbering.toFixed(0);

  //Add Row.
  var row = tBody.insertRow(-1);

  //Add cell.
  var cell = $(row.insertCell(-1));
  cell.html(numbering);
  cell = $(row.insertCell(-1));
  cell.html(
    '<input type="text" name="prod_name[]" id="prod_name_' +
      numbering +
      '" value="" onkeyup="prodInfo(this)"><input type="hidden" name="prod_val[]" id="prod_val_' +
      numbering +
      '" value="" oninput="prodInfoAutoFill(this)">',
  );
  cell.addClass("autocomplete");
  cell = $(row.insertCell(-1));
  cell.html(
    '<input class="readonlyInput" type="text" name="wgt[]" id="wgt_' +
      numbering +
      '" value="" readonly>',
  );
  cell = $(row.insertCell(-1));
  cell.html(
    '<input class="readonlyInput" type="text" name="wgt_unit[]" id="wgt_unit_' +
      numbering +
      '" value="" readonly><input type="hidden" name="wgt_unit_val[]" id="wgt_unit_val_' +
      numbering +
      '" value="" readonly>',
  );
  cell = $(row.insertCell(-1));
  cell.html(
    '<input class="readonlyInput" type="text" name="barcode_status[]" id="barcode_status_' +
      numbering +
      '" value="0" readonly>',
  );
  cell = $(row.insertCell(-1));
  cell.html(
    '<input class="readonlyInput" type="text" name="barcode_slot[]" id="barcode_slot_' +
      numbering +
      '" value="0" readonly>',
  );

  //Add Button cell.
  cell = $(row.insertCell(-1));
  var btnRemove = $(
    '<button class="mt-1" id="action_menu_btn"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>',
  );
  btnRemove.attr("type", "button");
  btnRemove.attr("onclick", "Remove(this);");
  btnRemove.val("Remove");
  cell.append(btnRemove);
}

function Remove(button) {
  if (!button) {
    return;
  }

  //Determine the reference of the Row using the Button.
  var row = $(button).closest("TR");
  if (!row.length) {
    return;
  }

  var name = $("TD", row).eq(0).html();
  var rowCount = parseInt($("#productList TBODY TR:last TD").eq(0).html(), 10);
  if (isNaN(rowCount) || rowCount < 1) {
    rowCount = $("#productList TBODY TR").length;
  }

  if (confirm("Do you want to delete: " + name)) {
    //Get the reference of the Table.
    var table = $("#productList")[0];
    if (!table || !row[0]) {
      return;
    }

    //Delete the Table row using it's Index.
    table.deleteRow(row[0].rowIndex);

    //Recalc barcode slot
    setBarcodeSlotTotal(rowCount);
  }
}

// product autofill
function prodInfo(element) {
  var id = $(element).attr("id").split("_");
  id = id[id.length - 1];

  if (!$(element).attr("readonly")) {
    var param = {
      search: $(element).val(),
      searchType: "name",
      page: "package",
      elementID: $(element).attr("id"),
      hiddenElementID: "prod_val_" + id,
      dbTable: "<?= PROD ?>",
    };
    searchInput(param, "<?= $SITEURL ?>");

    if ($(element).val() == "") {
      $("#prod_val_" + id).val("");
      $("#wgt_" + id).val("");
      $("#wgt_unit_" + id).val("");
      $("#wgt_unit_val_" + id).val("");
      $("#barcode_status_" + id).val("0");
      $("#barcode_slot_" + id).val("0");
    }
  }
}

function prodInfoAutoFill(element) {
  if (!element) {
    return;
  }

  var id = $(element).attr("id").split("_");
  id = id[id.length - 1];
  var prodArr = [];
  var wgtArr = [];
  var rowCount = parseInt($("#productList TBODY TR:last TD").eq(0).html(), 10);
  if (isNaN(rowCount) || rowCount < 1) {
    rowCount = $("#productList TBODY TR").length;
  }
  var productId = String($(element).val() || "").trim();

  var retrieveProdInfo = async () => {
    if (productId === "") {
      prodArr = [];
      return;
    }

    prodArr = await retrieveJSONData(productId, "id", "<?= PROD ?>");
  };

  var setProdInfo = async () => {
    if (!Array.isArray(prodArr) || !prodArr.length || !prodArr[0]) {
      $("#wgt_" + id).val("");
      $("#wgt_unit_" + id).val("");
      $("#wgt_unit_val_" + id).val("");
      $("#barcode_status_" + id).val("0");
      $("#barcode_slot_" + id).val("0");
      return;
    }

    $("#wgt_" + id).val(prodArr[0]["weight"]);
    $("#wgt_unit_val_" + id).val(prodArr[0]["weight_unit"]);
    var barcodeStatus =
      prodArr[0]["barcode_status"] === null ||
      prodArr[0]["barcode_status"] === undefined ||
      String(prodArr[0]["barcode_status"]).trim() === ""
        ? "0"
        : String(prodArr[0]["barcode_status"]);
    var barcodeSlot =
      prodArr[0]["barcode_slot"] === null ||
      prodArr[0]["barcode_slot"] === undefined ||
      String(prodArr[0]["barcode_slot"]).trim() === ""
        ? "0"
        : String(prodArr[0]["barcode_slot"]);
    $("#barcode_status_" + id).val(barcodeStatus);
    $("#barcode_slot_" + id).val(barcodeSlot);
  };

  var retrieveWgtUnit = async () => {
    var weightUnitId = String($("#wgt_unit_val_" + id).val() || "").trim();
    if (weightUnitId === "") {
      wgtArr = [];
      return;
    }

    wgtArr = await retrieveJSONData(weightUnitId, "id", "<?= WGT_UNIT ?>");
  };

  var setWgtUnit = async () => {
    if (!Array.isArray(wgtArr) || !wgtArr.length || !wgtArr[0]) {
      $("#wgt_unit_" + id).val("");
      return;
    }

    $("#wgt_unit_" + id).val(wgtArr[0]["unit"]);
  };

  var allFunc = async () => {
    await retrieveProdInfo();
    await setProdInfo();
    await retrieveWgtUnit();
    await setWgtUnit();
    await setBarcodeSlotTotal(rowCount);
  };

  allFunc();
}

$("#package_cost").on("input", function () {
  $(".package-cost-err").remove();
});

$(document).ready(function () {
  if (!$("#cur_unit").attr("readonly")) {
    $("#cur_unit").keyup(function () {
      var param = {
        search: $(this).val(),
        searchType: "unit",
        elementID: $(this).attr("id"),
        hiddenElementID: $(this).attr("id") + "_hidden",
        dbTable: "<?= CUR_UNIT ?>",
      };
      searchInput(param, "<?= $SITEURL ?>");
    });
    $("#cur_unit").change(function () {
      if ($(this).val() == "") {
        $("#" + $(this).attr("id") + "_hidden").val("");
      }
    });
  }
  if (!$("#brand").attr("readonly")) {
    $("#brand").keyup(function () {
      var param = {
        search: $(this).val(),
        searchType: "name",
        elementID: $(this).attr("id"),
        hiddenElementID: $(this).attr("id") + "_hidden",
        dbTable: "<?= BRAND ?>",
      };
      searchInput(param, "<?= $SITEURL ?>");
    });
    $("#brand").change(function () {
      if ($(this).val() == "") {
        $("#" + $(this).attr("id") + "_hidden").val("");
      }
    });
  }
  if (!$("#parent_package_name").attr("readonly")) {
    $("#parent_package_name").keyup(function () {
      var param = {
        search: $(this).val(),
        searchType: "name",
        elementID: $(this).attr("id"),
        hiddenElementID: "parent_package_id",
        dbTable: "<?= PKG ?>",
      };
      searchInput(param, "<?= $SITEURL ?>");
    });
    $("#parent_package_name").change(function () {
      if ($(this).val() == "") {
        $("#parent_package_id").val("");
      }
    });
  }
  if (!$("#cost_curr").attr("readonly")) {
    $("#cost_curr").keyup(function () {
      var param = {
        search: $(this).val(),
        searchType: "unit",
        elementID: $(this).attr("id"),
        hiddenElementID: $(this).attr("id") + "_hidden",
        dbTable: "<?= CUR_UNIT ?>",
      };
      searchInput(param, "<?= $SITEURL ?>");
    });
    $("#cost_curr").change(function () {
      if ($(this).val() == "") {
        $("#" + $(this).attr("id") + "_hidden").val("");
      }
    });
  }
});

//block "e" in input type number field
var packageCostInput = document.querySelector("#package_cost");
if (packageCostInput) {
  packageCostInput.addEventListener("keypress", function (evt) {
    var inputValue = this.value;

    if (
      evt.which != 8 &&
      evt.which != 0 &&
      (evt.which < 48 || evt.which > 57) &&
      evt.which != 46
    ) {
      evt.preventDefault();
    }

    // Allow only one decimal point
    if (inputValue.indexOf(".") !== -1 && evt.which == 46) {
      evt.preventDefault();
    }
  });
}

function initPlatformItemIdTags() {
  var hidden = document.getElementById("platform_item_id");
  var input = document.getElementById("platform_item_id_input");
  var tagsBox = document.getElementById("platform_item_id_tags");

  if (!hidden || !tagsBox) {
    return;
  }

  var tags = String(hidden.value || "")
    .split(",")
    .map(function (value) {
      return value.trim();
    })
    .filter(function (value, index, arr) {
      return value !== "" && arr.indexOf(value) === index;
    });

  function syncHidden() {
    hidden.value = tags.join(",");
  }

  function renderTags() {
    tagsBox.innerHTML = "";

    tags.forEach(function (tag, index) {
      var badge = document.createElement("span");
      badge.className = "platform-item-id-tag";

      var text = document.createElement("span");
      text.textContent = tag;
      badge.appendChild(text);

      if (input) {
        var removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "platform-item-id-remove";
        removeBtn.setAttribute("aria-label", "Remove platform item ID");
        removeBtn.textContent = "x";
        removeBtn.addEventListener("click", function () {
          tags.splice(index, 1);
          syncHidden();
          renderTags();
        });
        badge.appendChild(removeBtn);
      }

      tagsBox.appendChild(badge);
    });
  }

  function addTag(value) {
    value = String(value || "").trim();

    if (value === "") {
      return;
    }

    if (tags.indexOf(value) === -1) {
      tags.push(value);
    }

    if (input) {
      input.value = "";
    }

    syncHidden();
    renderTags();
  }

  if (input) {
    input.addEventListener("keydown", function (event) {
      if (event.key === "Enter") {
        event.preventDefault();
        addTag(input.value);
      }
    });

    input.addEventListener("blur", function () {
      addTag(input.value);
    });

    input.addEventListener("paste", function () {
      setTimeout(function () {
        var value = input.value;
        if (value.indexOf("\n") !== -1) {
          value.split(/\n+/).forEach(function (part) {
            addTag(part);
          });
          input.value = "";
        }
      }, 0);
    });
  }

  syncHidden();
  renderTags();
}

$(document).ready(function () {
  initPlatformItemIdTags();
});
