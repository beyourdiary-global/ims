async function setBarcodeSlotTotal(rowCount) {
  var num = 1;
  // init
  var totalSlot_id = $("#barcode_slot_total");
  totalSlot_id.text(0);

  while (num <= rowCount) {
    totalSlot = parseInt(totalSlot_id.text());
    var barcodeSlot_id = $("#barcode_slot_" + num);

    if (barcodeSlot_id !== 0) {
      var barcodeSlot = parseInt(barcodeSlot_id.val());

      if (!isNaN(barcodeSlot)) totalSlot += barcodeSlot;

      totalSlot_id.text(totalSlot);
      totalSlot_id.append(
        '<input name="barcode_slot_total_hidden" id="barcode_slot_total_hidden" type="hidden" value="' +
          totalSlot +
          '">',
      );

      num++;
    }
  }
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
  var numbering = +$("#productList > TBODY > TR:last > TD:first").text();
  numbering += 1;
  numbering = numbering.toFixed(0);

  //Add Row.
  row = tBody.insertRow(-1);

  //Add cell.
  var cell = $(row.insertCell(-1));
  cell.html(numbering);
  var cell = $(row.insertCell(-1));
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
  //Determine the reference of the Row using the Button.
  var row = $(button).closest("TR");
  var name = $("TD", row).eq(0).html();
  var rowCount = parseInt($("#productList TBODY TR:last TD").eq(0).html());

  if (confirm("Do you want to delete: " + name)) {
    //Get the reference of the Table.
    var table = $("#productList")[0];

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
  var id = $(element).attr("id").split("_");
  id = id[id.length - 1];
  var prodArr = [];
  var wgtArr = [];
  var rowCount = parseInt($("#productList TBODY TR:last TD").eq(0).html());

  var retrieveProdInfo = async () => {
    prodArr = await retrieveJSONData(
      $(element).attr("value"),
      "id",
      "<?= PROD ?>",
    );
  };

  var setProdInfo = async () => {
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
    wgtArr = await retrieveJSONData(
      $("#wgt_unit_val_" + id).attr("value"),
      "id",
      "<?= WGT_UNIT ?>",
    );
  };

  var setWgtUnit = async () => {
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
      if ($(this).val() == "") $("#" + $(this).attr("id") + "_hidden").val("");
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
      if ($(this).val() == "") $("#" + $(this).attr("id") + "_hidden").val("");
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
      if ($(this).val() == "") $("#" + $(this).attr("id") + "_hidden").val("");
    });
  }
});

//block "e" in input type number field
document
  .querySelector("#package_cost")
  .addEventListener("keypress", function (evt) {
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
        removeBtn.textContent = "×";
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