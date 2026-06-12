function obj(str) {
  return document.getElementById(str);
}

function objValue(str) {
  return document.getElementById(str).value;
}

function toggle(str) {
  if (obj(str).style.display == "none") {
    obj(str).style.display = "block";
    return true;
  } else if (obj(str).style.display == "block") {
    obj(str).style.display = "none";
    return false;
  }
}

function isEmail(str) {
  var filter =
    /^[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,})$/;
  return filter.test(str);
}

function isNumber(str) {
  var filter = /^[0-9]+$/;
  return filter.test(str);
}

function MM_findObj(n, d) {
  var p, i, x;
  if (!d) d = document;
  if ((p = n.indexOf("?")) > 0 && parent.frames.length) {
    d = parent.frames[n.substring(p + 1)].document;
    n = n.substring(0, p);
  }
  if (!(x = d[n]) && d.all) x = d.all[n];
  for (i = 0; !x && i < d.forms.length; i++) x = d.forms[i][n];
  for (i = 0; !x && d.layers && i < d.layers.length; i++)
    x = MM_findObj(n, d.layers[i].document);
  if (!x && d.getElementById) x = d.getElementById(n);
  return x;
}

function MM_jumpMenu(targ, selObj, restore) {
  eval(targ + ".location='" + selObj.options[selObj.selectedIndex].value + "'");
  if (restore) selObj.selectedIndex = 0;
}

function MM_swapImage() {
  var i,
    j = 0,
    x,
    a = MM_swapImage.arguments;
  document.MM_sr = new Array();
  for (i = 0; i < a.length - 2; i += 3)
    if ((x = MM_findObj(a[i])) != null) {
      document.MM_sr[j++] = x;
      if (!x.oSrc) x.oSrc = x.src;
      x.src = a[i + 2];
    }
}

function MM_swapImgRestore() {
  var i,
    x,
    a = document.MM_sr;
  for (i = 0; a && i < a.length && (x = a[i]) && x.oSrc; i++) x.src = x.oSrc;
}

function isNumberKey(evt) {
  var charCode = evt.which ? evt.which : event.keyCode;
  if (charCode > 31 && (charCode < 48 || charCode > 57)) return false;

  return true;
}
/*clear input or textarea default value, style1:default style, style2:normal style*/
function clearDefaultText(ele, style1, style2, txt) {
  if (ele.value == txt) {
    ele.value = "";
    ele.className = style2;
  }
  ele.onblur = function () {
    if (!ele.value == txt || ele.value == "") {
      if (typeof txt == "undefined") ele.value = "";
      else ele.value = txt;
      ele.className = style1;
    }
  };
}

function popUp(webaddy, title, x, y) {
  var features =
    "toolbars=0, scrollbars=1, location=0, statusbars=0, menubars=0, resizable=0, width=" +
    x +
    ", height=" +
    y +
    ", left = 168, top = 118";
  props = window.open(webaddy, title, features);
}

function limitText(limitField, limitCount, limitNum) {
  if (limitField.value.length > limitNum)
    limitField.value = limitField.value.substring(0, limitNum);
  else limitCount.value = limitNum - limitField.value.length;
}

function colorInputValidationCheck(ob, ob_des, msg) {
  ob1 = obj(ob);
  ob2 = obj(ob_des);
  ob1.className = "redthickborder";
  ob2.innerHTML = '<span class="font_red">' + msg + "</span>";
}

function removeColorInput(ob, ob_des) {
  ob1 = obj(ob);
  ob2 = obj(ob_des);
  ob1.className = "";
  ob2.innerHTML = "";
}

function convertSpecialChars() {
  var chars = [
    "Â©",
    "Ã›",
    "Â®",
    "Å¾",
    "Ãœ",
    "Å¸",
    "Ã",
    "Ãž",
    "%",
    "Â¡",
    "ÃŸ",
    "Â¢",
    "Ã ",
    "Â£",
    "Ã¡",
    "Ã€",
    "Â¤",
    "Ã¢",
    "Ã",
    "Â¥",
    "Ã£",
    "Ã‚",
    "Â¦",
    "Ã¤",
    "Ãƒ",
    "Â§",
    "Ã¥",
    "Ã„",
    "Â¨",
    "Ã¦",
    "Ã…",
    "Â©",
    "Ã§",
    "Ã†",
    "Âª",
    "Ã¨",
    "Ã‡",
    "Â«",
    "Ã©",
    "Ãˆ",
    "Â¬",
    "Ãª",
    "Ã‰",
    "Â­",
    "Ã«",
    "ÃŠ",
    "Â®",
    "Ã¬",
    "Ã‹",
    "Â¯",
    "Ã­",
    "ÃŒ",
    "Â°",
    "Ã®",
    "Ã",
    "Â±",
    "Ã¯",
    "ÃŽ",
    "Â²",
    "Ã°",
    "Ã",
    "Â³",
    "Ã±",
    "Ã",
    "Â´",
    "Ã²",
    "Ã‘",
    "Âµ",
    "Ã³",
    "Ã•",
    "Â¶",
    "Ã´",
    "Ã–",
    "Â·",
    "Ãµ",
    "Ã˜",
    "Â¸",
    "Ã¶",
    "Ã™",
    "Â¹",
    "Ã·",
    "Ãš",
    "Âº",
    "Ã¸",
    "Ã›",
    "Â»",
    "Ã¹",
    "Ãœ",
    "@",
    "Â¼",
    "Ãº",
    "Ã",
    "Â½",
    "Ã»",
    "Ãž",
    "â‚¬",
    "Â¾",
    "Ã¼",
    "ÃŸ",
    "Â¿",
    "Ã½",
    "Ã ",
    "â€š",
    "Ã€",
    "Ã¾",
    "Ã¡",
    "Æ’",
    "Ã",
    "Ã¿",
    "Ã¥",
    "â€ž",
    "Ã‚",
    "Ã¦",
    "â€¦",
    "Ãƒ",
    "Ã§",
    "â€ ",
    "Ã„",
    "Ã¨",
    "â€¡",
    "Ã…",
    "Ã©",
    "Ë†",
    "Ã†",
    "Ãª",
    "â€°",
    "Ã‡",
    "Ã«",
    "Å ",
    "Ãˆ",
    "Ã¬",
    "â€¹",
    "Ã‰",
    "Ã­",
    "Å’",
    "ÃŠ",
    "Ã®",
    "Ã‹",
    "Ã¯",
    "Å½",
    "ÃŒ",
    "Ã°",
    "Ã",
    "Ã±",
    "ÃŽ",
    "Ã²",
    "â€˜",
    "Ã",
    "Ã³",
    "â€™",
    "Ã",
    "Ã´",
    "â€œ",
    "Ã‘",
    "Ãµ",
    "â€",
    "Ã’",
    "Ã¶",
    "â€¢",
    "Ã“",
    "Ã¸",
    "â€“",
    "Ã”",
    "Ã¹",
    "â€”",
    "Ã•",
    "Ãº",
    "Ëœ",
    "Ã–",
    "Ã»",
    "â„¢",
    "Ã—",
    "Ã½",
    "Å¡",
    "Ã˜",
    "Ã¾",
    "â€º",
    "Ã™",
    "Ã¿",
    "Å“",
    "Ãš",
  ];
  var codes = [
    "&copy;",
    "&#219;",
    "&reg;",
    "&#158;",
    "&#220;",
    "&#159;",
    "&#221;",
    "&#222;",
    "&#37;",
    "&#161;",
    "&#223;",
    "&#162;",
    "&#224;",
    "&#163;",
    "&#225;",
    "&Agrave;",
    "&#164;",
    "&#226;",
    "&Aacute;",
    "&#165;",
    "&#227;",
    "&Acirc;",
    "&#166;",
    "&#228;",
    "&Atilde;",
    "&#167;",
    "&#229;",
    "&Auml;",
    "&#168;",
    "&#230;",
    "&Aring;",
    "&#169;",
    "&#231;",
    "&AElig;",
    "&#170;",
    "&#232;",
    "&Ccedil;",
    "&#171;",
    "&#233;",
    "&Egrave;",
    "&#172;",
    "&#234;",
    "&Eacute;",
    "&#173;",
    "&#235;",
    "&Ecirc;",
    "&#174;",
    "&#236;",
    "&Euml;",
    "&#175;",
    "&#237;",
    "&Igrave;",
    "&#176;",
    "&#238;",
    "&Iacute;",
    "&#177;",
    "&#239;",
    "&Icirc;",
    "&#178;",
    "&#240;",
    "&Iuml;",
    "&#179;",
    "&#241;",
    "&ETH;",
    "&#180;",
    "&#242;",
    "&Ntilde;",
    "&#181;",
    "&#243;",
    "&Otilde;",
    "&#182;",
    "&#244;",
    "&Ouml;",
    "&#183;",
    "&#245;",
    "&Oslash;",
    "&#184;",
    "&#246;",
    "&Ugrave;",
    "&#185;",
    "&#247;",
    "&Uacute;",
    "&#186;",
    "&#248;",
    "&Ucirc;",
    "&#187;",
    "&#249;",
    "&Uuml;",
    "&#64;",
    "&#188;",
    "&#250;",
    "&Yacute;",
    "&#189;",
    "&#251;",
    "&THORN;",
    "&#128;",
    "&#190;",
    "&#252",
    "&szlig;",
    "&#191;",
    "&#253;",
    "&agrave;",
    "&#130;",
    "&#192;",
    "&#254;",
    "&aacute;",
    "&#131;",
    "&#193;",
    "&#255;",
    "&aring;",
    "&#132;",
    "&#194;",
    "&aelig;",
    "&#133;",
    "&#195;",
    "&ccedil;",
    "&#134;",
    "&#196;",
    "&egrave;",
    "&#135;",
    "&#197;",
    "&eacute;",
    "&#136;",
    "&#198;",
    "&ecirc;",
    "&#137;",
    "&#199;",
    "&euml;",
    "&#138;",
    "&#200;",
    "&igrave;",
    "&#139;",
    "&#201;",
    "&iacute;",
    "&#140;",
    "&#202;",
    "&icirc;",
    "&#203;",
    "&iuml;",
    "&#142;",
    "&#204;",
    "&eth;",
    "&#205;",
    "&ntilde;",
    "&#206;",
    "&ograve;",
    "&#145;",
    "&#207;",
    "&oacute;",
    "&#146;",
    "&#208;",
    "&ocirc;",
    "&#147;",
    "&#209;",
    "&otilde;",
    "&#148;",
    "&#210;",
    "&ouml;",
    "&#149;",
    "&#211;",
    "&oslash;",
    "&#150;",
    "&#212;",
    "&ugrave;",
    "&#151;",
    "&#213;",
    "&uacute;",
    "&#152;",
    "&#214;",
    "&ucirc;",
    "&#153;",
    "&#215;",
    "&yacute;",
    "&#154;",
    "&#216;",
    "&thorn;",
    "&#155;",
    "&#217;",
    "&yuml;",
    "&#156;",
    "&#218;",
  ];

  for (i = 0; i < arguments.length; i++) {
    for (x = 0; x < chars.length; x++) {
      arguments[i].value = arguments[i].value.replace(
        new RegExp(chars[x], "g"),
        codes[x],
      );
    }
  }
}

function isScrolledVisible(elem) {
  var docViewTop = jQuery(window).scrollTop();
  var elemTop = jQuery(elem).offset().top + jQuery(elem).height();
  if (elemTop < docViewTop)
    //scroll to elem
    return docViewTop - elemTop < 0 ? true : false;
  //!scroll to elem
  else return elemTop < jQuery(window).height() + docViewTop ? true : false; //elem not within browser window
}

function showStickybar(elem) {
  if (jQuery("#stickybar").length == 1) {
    if (!isScrolledVisible(elem))
      //elem not visible on load
      jQuery("#stickybar").css("display", "block");
    jQuery(window).scroll(function () {
      if (!isScrolledVisible(elem))
        //elem not visible after scrolling
        jQuery("#stickybar").css("display", "block");
      else jQuery("#stickybar").css("display", "none");
    });
    jQuery(window).resize(function () {
      if (!isScrolledVisible(elem))
        //elem not visible after scrolling
        jQuery("#stickybar").css("display", "block");
      else jQuery("#stickybar").css("display", "none");
    });
  }
}

var tooltipsfun = function (sensorele, tooltipID) {
  jQuery(sensorele).css("cursor", "pointer");
  jQuery(sensorele)
    .mouseenter(function () {
      timer = setTimeout(function () {
        jQuery("#" + tooltipID).show();
      }, 700);
    })
    .mouseleave(function () {
      clearTimeout(timer);
      setTimeout(function () {
        jQuery("#" + tooltipID).hide();
      }, 700);
    });
};

var vmoreHLnews = function (boxwidth, totalitems, nodata) {
  var n = jQuery(".hlitem").length,
    width = boxwidth,
    newwidth = width * n;

  jQuery("#hlstage, .hlitem").css("width", width);
  jQuery("#hlslide-holder").css({
    width: newwidth,
  });

  jQuery(".hlitem").each(function (i) {
    var thiswid = 730;
    jQuery(this).css({
      left: thiswid * i,
    });
  });

  jQuery("#hlprev").click(function () {
    var hlprev = jQuery("#hlslide-holder .active").prev();
    var curIndex =
      jQuery(".active").index() - 1 < 0 ? 0 : jQuery(".active").index() - 1;
    if (hlprev.length) {
      getHLpaging(curIndex, totalitems, n, nodata);
      jQuery("#hlstage").animate(
        {
          scrollLeft: hlprev.position().left,
        },
        1000,
      );
    }
  });
  /* on right button click scroll to the next sibling of the current visible slide */
  jQuery("#hlnext").click(function () {
    var hlnext = jQuery("#hlslide-holder .active").next();
    var curIndex =
      jQuery(".active").index() + 1 > n ? n : jQuery(".active").index() + 1;
    if (hlnext.length) {
      getHLpaging(curIndex, totalitems, n, nodata);
      jQuery("#hlstage").animate(
        {
          scrollLeft: hlnext.position().left,
        },
        1000,
      );
    }
  });

  /*on scroll move the indicator 'shown' class to the
    most visible slide on viewport
    */
  jQuery("#hlstage").scroll(function () {
    var scrollLeft = jQuery(this).scrollLeft();
    jQuery(".hlitem").each(function (i) {
      var posLeft = jQuery(this).position().left;
      var w = jQuery(this).width();

      if (scrollLeft >= posLeft && scrollLeft < posLeft + w) {
        jQuery(this).addClass("active").siblings().removeClass("active");
      }
    });
  });
};

function getHLpaging(curIndex, totalitems, totaldivitems, totaldata) {
  jQuery("a.hlnavleft").removeClass("inactiveleft");
  jQuery("a.hlnavright").removeClass("inactiveright");
  pagingIndex =
    curIndex == totaldivitems - 1 ? totaldata : (curIndex + 1) * totalitems;
  pagingText =
    curIndex * totalitems + 1 + "-" + pagingIndex + " of " + totaldata;
  jQuery(".hlpaging").text(pagingText);
  if (curIndex == 0) {
    jQuery("a.hlnavleft").addClass("inactiveleft");
    jQuery("a.hlnavright").removeClass("inactiveright");
  } else if (curIndex == totaldivitems - 1) {
    jQuery("a.hlnavright").addClass("inactiveright");
    jQuery("a.hlnavleft").removeClass("inactiveleft");
  }
}

function validCaptcha(formname, group) {
  var v = grecaptcha.getResponse();
  if (v.length == 0) {
    alert("Please Complete The Captcha");
    return false;
  } else {
    if (typeof formname != "undefined" && formname != "") {
      try {
        let hiddenInput = document.createElement("input");
        hiddenInput.setAttribute("type", "hidden");
        hiddenInput.setAttribute("name", "g-recaptcha-response");
        let gresponse = document.querySelector(".g-recaptcha-response").value;
        hiddenInput.setAttribute("value", gresponse);
        document[formname].appendChild(hiddenInput);
        document.forms[formname].submit();
      } catch (e) {
        document.forms[formname].submit();
      }
    } else {
      return true;
    }
  }
}

function checkValidDate(inDate, futurecheck) {
  /***get today date****/
  var today = new Date();
  var todaydd = today.getDate();
  var todaymm = today.getMonth() + 1; //January is 0!
  var todayyyyy = today.getFullYear();
  todayyyyy = todayyyyy.toString();

  if (todaydd < 10) var todaydd = pad(todaydd, 2);
  if (todaymm < 10) var todaymm = pad(todaymm, 2);

  if (inDate == "") return true;
  var d = "312831303130313130313031";

  /* For invalid dates, return false */
  if (inDate.length > 0 && inDate.length < 8) return false;

  // Expected inDate format: dd.mm.yyyy
  dd = inDate.substring(0, 2);
  mm = inDate.substring(2, 4);
  yy = inDate.substring(4, 8);

  /* Now, convert the string yr1 into a numeric and test for leap year.
  If it is, change the end of month day string for Feb to 29  */

  var isLeap = false;
  yy = yy * 1;
  if (yy % 400 == 0) isLeap = true;
  else if (yy % 100 == 0) isLeap = false;
  else if (yy % 4 == 0) isLeap = true;
  if (isLeap) d = d.substring(0, 2) + "29" + d.substring(4, d.length);

  /* Pick the end of month day from the d string for this month. */
  pos = mm * 2 - 2;
  ld = d.substring(pos, pos + 2) + 0;
  if (dd < 1 || dd > ld) return false;
  else if (mm < 1 || mm > 12) return false;
  else if (yy < 1900) return false;
  else if (
    typeof futurecheck !== "undefined" &&
    parseInt(yy + mm + dd) > parseInt(todayyyyy + todaymm + todaydd)
  )
    return false;

  return true;
}

function pad(str, max) {
  str = str.toString();
  return str.length < max ? pad("0" + str, max) : str;
}

function gInputNumbersDotOnly(myfield, e) {
  var key;
  var keychar;
  if (window.event) key = window.event.keyCode;
  else if (e) key = e.which;
  else return true;
  keychar = String.fromCharCode(key);
  keychar = keychar.toLowerCase();
  // control keys
  if (key == null || key == 0 || key == 8 || key == 9 || key == 13 || key == 27)
    return true;
  // numbers
  else if ("0123456789.".indexOf(keychar) > -1) return true;
  else return false;
}

function gAddLoadEvent(func) {
  var oldonload = window.onload;
  if (typeof window.onload != "function") {
    window.onload = func;
  } else {
    window.onload = function () {
      oldonload();
      func();
    };
  }
}

function gDestroycatfish() {
  /* catfish closer function */
  jQuery("#catfish").remove(); /* clip catfish off the tree */
  document.getElementsByTagName("html")[0].style.padding =
    "0"; /* reset the padding at the bottom */
  return false; /* disable the link's 'linkiness' -- so it won't jump you up the top of the page */
}

function gCloselink() {
  /* attach the catfish closer function to the link */
  if (document.getElementById("closeme")) {
    var closelink =
      document.getElementById("closeme"); /* find the 'close this' link */
    closelink.onclick =
      gDestroycatfish; /* attach the destroy function to it's 'onclick' */
  }
}

function gSetCookie(cname, cvalue, exdays) {
  var d = new Date();
  d.setTime(d.getTime() + exdays * 24 * 60 * 60 * 1000);
  var expires = "expires=" + d.toUTCString();
  document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

function convertDate(date, current_Date) {
  // format: DD/MM/YYYY (Check valid date and get date)
  var valid_date = "";

  var todayDate = new Date();
  var todayDD = todayDate.getDate().toString();
  var todayMM = (todayDate.getMonth() + 1).toString();
  var todayYY = todayDate.getFullYear().toString();

  if (todayDD < 10) var todayDD = pad(todayDD, 2);
  if (todayMM < 10) var todayMM = pad(todayMM, 2);

  if (date) {
    var splitDate = date.split(/[-\/.]/);
    var inputDD = splitDate[0] ? splitDate[0] : "";
    inputDD = inputDD.toString();
    var inputMM = splitDate[1] ? splitDate[1] : "";
    inputMM = inputMM.toString();
    var inputYY = splitDate[2] ? splitDate[2] : "";
    inputYY = inputYY.toString();

    if (inputDD < 10 && inputDD.length < 2) {
      inputDD = pad(inputDD, 2);
    }
    if (inputMM < 10 && inputMM.length < 2) {
      inputMM = pad(inputMM, 2);
    }

    if (inputDD.length != 2 || inputMM.length != 2 || inputYY.length != 4)
      return valid_date;

    if (checkValidDate(inputDD + inputMM + inputYY)) {
      if (typeof current_Date !== "undefined")
        return (valid_date = [
          inputYY + inputMM + inputDD,
          todayYY + todayMM + todayDD,
        ]);
      else return (valid_date = [inputYY + inputMM + inputDD]);
    } else return valid_date;
  } else return valid_date;
}

function getDefaultDataTableLengthMenu() {
  return [
    [10, 25, 50, 100, -1],
    [10, 25, 50, 100, "All"],
  ];
}

function applyGlobalDataTableDefaults() {
  if (typeof window.jQuery === "undefined" || !$.fn || !$.fn.dataTable) {
    return;
  }

  if (!$.fn.dataTable.defaults.lengthMenu) {
    $.extend(true, $.fn.dataTable.defaults, {
      lengthMenu: getDefaultDataTableLengthMenu(),
    });
  }
}

function ensureGlobalDataTableEmptyStateStyle() {
  if (document.getElementById("global-dt-empty-state-style")) {
    return;
  }

  var style = document.createElement("style");
  style.id = "global-dt-empty-state-style";
  style.textContent =
    ".global-dt-hidden{display:none !important;}" +
    ".global-dt-empty-state{padding:2.25rem 0;}";
  document.head.appendChild(style);
}

function getDataTableApiFromSettings(settings) {
  if (
    !settings ||
    typeof window.jQuery === "undefined" ||
    !$.fn ||
    !$.fn.dataTable
  ) {
    return null;
  }

  try {
    return new $.fn.dataTable.Api(settings);
  } catch (e) {
    return null;
  }
}

function syncGlobalDataTableEmptyState(dataTableApi) {
  if (
    !dataTableApi ||
    !dataTableApi.settings ||
    !dataTableApi.settings().length
  ) {
    return;
  }

  var settings = dataTableApi.settings()[0];
  if (!settings || !settings.sTableId) {
    return;
  }

  var tableId = settings.sTableId;
  var wrapperId = tableId + "_wrapper";
  var emptyStateId = tableId + "_global_no_result";
  var wrapper = document.getElementById(wrapperId);

  if (!wrapper || !wrapper.parentNode) {
    return;
  }

  var recordsTotal = 0;
  if (
    typeof dataTableApi.page === "function" &&
    typeof dataTableApi.page.info === "function"
  ) {
    var pageInfo = dataTableApi.page.info();
    if (pageInfo && typeof pageInfo.recordsTotal === "number") {
      recordsTotal = pageInfo.recordsTotal;
    }
  }

  if (recordsTotal === 0 && typeof dataTableApi.rows === "function") {
    recordsTotal = dataTableApi.rows().count();
  }

  var isEmpty = recordsTotal === 0;
  var emptyStateNode = document.getElementById(emptyStateId);

  if (!emptyStateNode) {
    emptyStateNode = document.createElement("div");
    emptyStateNode.id = emptyStateId;
    emptyStateNode.className = "global-dt-empty-state";
    emptyStateNode.innerHTML =
      '<div class="text-center"><h4>No Result!</h4></div>';
    wrapper.parentNode.insertBefore(emptyStateNode, wrapper.nextSibling);
  }

  wrapper.classList.toggle("global-dt-hidden", isEmpty);
  emptyStateNode.style.display = isEmpty ? "block" : "none";
}

function bindGlobalDataTableEmptyStateHandlers() {
  if (typeof window.jQuery === "undefined" || !$.fn || !$.fn.dataTable) {
    return;
  }

  if (window.__globalDataTableEmptyStateHandlersBound) {
    return;
  }

  ensureGlobalDataTableEmptyStateStyle();
  window.__globalDataTableEmptyStateHandlersBound = true;

  $(document).on("init.dt draw.dt", function (event, settings) {
    var tableApi = getDataTableApiFromSettings(settings);
    if (tableApi) {
      syncGlobalDataTableEmptyState(tableApi);
    }
  });

  var existingTables = $.fn.dataTable.tables({ api: true });
  if (
    existingTables &&
    typeof existingTables.count === "function" &&
    existingTables.count() > 0
  ) {
    existingTables.every(function () {
      syncGlobalDataTableEmptyState(this);
    });
  }
}

function resolveActionLabel(element) {
  var titleAttr = (element.getAttribute("title") || "").trim();
  if (titleAttr !== "") {
    return titleAttr;
  }

  var textLabel = (element.textContent || "").trim();
  if (textLabel !== "") {
    return textLabel;
  }

  var icon = element.querySelector("i");
  var iconClass = icon ? icon.className : "";
  if (iconClass.indexOf("fa-eye") !== -1) return "View";
  if (iconClass.indexOf("fa-edit") !== -1 || iconClass.indexOf("fa-pen") !== -1)
    return "Edit";
  if (iconClass.indexOf("fa-trash") !== -1) return "Delete";
  if (iconClass.indexOf("fa-address-card") !== -1) return "Urbanism Member";
  if (iconClass.indexOf("fa-user-plus") !== -1) return "Register Member";
  return "Action";
}

function resolveActionType(element) {
  var icon = element.querySelector("i");
  var iconClass = icon ? icon.className : "";

  if (iconClass.indexOf("fa-eye") !== -1) return "view";
  if (iconClass.indexOf("fa-edit") !== -1 || iconClass.indexOf("fa-pen") !== -1)
    return "edit";
  if (iconClass.indexOf("fa-trash") !== -1) return "delete";
  if (
    iconClass.indexOf("fa-address-card") !== -1 ||
    iconClass.indexOf("fa-user-plus") !== -1 ||
    iconClass.indexOf("fa-id-badge") !== -1
  )
    return "member";

  return "default";
}

function buildMobileActionItem(actionNode) {
  var node = actionNode.cloneNode(true);
  var actionLabel = resolveActionLabel(actionNode);
  var actionType = resolveActionType(actionNode);
  var iconNode = actionNode.querySelector("i");
  var iconHtml = iconNode
    ? iconNode.outerHTML
    : '<i class="fas fa-circle"></i>';

  node.className = "mobile-action-item mobile-action-item--" + actionType;
  node.removeAttribute("id");
  node.innerHTML = iconHtml + "<span>" + actionLabel + "</span>";
  node.setAttribute("aria-label", actionLabel);

  node.addEventListener("mouseenter", function () {
    node.classList.add("is-hover");
  });
  node.addEventListener("mouseleave", function () {
    node.classList.remove("is-hover");
  });

  if (node.tagName.toLowerCase() === "button" && !node.getAttribute("type")) {
    node.setAttribute("type", "button");
  }

  return node;
}

function convertTableActionButtonsForMobile() {
  var actionCells = document.querySelectorAll("td.btn-container");
  var isMobileView = window.matchMedia("(max-width: 768px)").matches;

  actionCells.forEach(function (cell) {
    if (!cell.dataset.originalActionHtml) {
      cell.dataset.originalActionHtml = cell.innerHTML;
    }

    if (!isMobileView) {
      if (cell.classList.contains("mobile-action-ready")) {
        cell.innerHTML = cell.dataset.originalActionHtml;
        cell.classList.remove("mobile-action-ready", "mobile-action-open");
      }
      return;
    }

    if (cell.classList.contains("mobile-action-ready")) {
      return;
    }

    var tempWrapper = document.createElement("div");
    tempWrapper.innerHTML = cell.dataset.originalActionHtml;

    var actionNodes = Array.prototype.slice
      .call(tempWrapper.querySelectorAll("a, button"))
      .filter(function (node) {
        return (
          !node.closest(".dropdown-menu") &&
          !node.classList.contains("dropdown-toggle")
        );
      });

    if (actionNodes.length === 0) {
      return;
    }

    var wrapper = document.createElement("div");
    wrapper.className = "mobile-action-wrapper";

    var trigger = document.createElement("button");
    trigger.type = "button";
    trigger.className = "mobile-action-trigger";
    trigger.setAttribute("aria-label", "Show row actions");
    trigger.innerHTML = '<i class="fa-solid fa-eye"></i>';
    trigger.addEventListener("mouseenter", function () {
      trigger.classList.add("is-hover");
    });
    trigger.addEventListener("mouseleave", function () {
      trigger.classList.remove("is-hover");
    });

    var menu = document.createElement("div");
    menu.className = "mobile-action-menu";

    actionNodes.forEach(function (node) {
      menu.appendChild(buildMobileActionItem(node));
    });

    wrapper.appendChild(trigger);
    wrapper.appendChild(menu);

    cell.innerHTML = "";
    cell.appendChild(wrapper);
    cell.classList.add("mobile-action-ready");
  });
}

function initMobileActionMenus() {
  var currentHoveredNode = null;
  var hoverSyncIntervalId = null;

  var setHoveredNode = function (node) {
    if (currentHoveredNode === node) {
      return;
    }

    if (currentHoveredNode) {
      currentHoveredNode.classList.remove("is-hover");
    }

    currentHoveredNode = node || null;
    if (currentHoveredNode) {
      currentHoveredNode.classList.add("is-hover");
    }
  };

  var clearMobileHoverState = function () {
    setHoveredNode(null);
  };

  var applyMobileHoverFromPoint = function (clientX, clientY) {
    if (
      typeof clientX !== "number" ||
      typeof clientY !== "number" ||
      clientX < 0 ||
      clientY < 0
    ) {
      return;
    }

    var findHoverCandidateByRect = function () {
      var candidates = document.querySelectorAll(
        "td.btn-container.mobile-action-open .mobile-action-item, td.btn-container.mobile-action-open .mobile-action-trigger",
      );

      for (var i = 0; i < candidates.length; i++) {
        var candidate = candidates[i];
        var rect = candidate.getBoundingClientRect();
        if (
          clientX >= rect.left &&
          clientX <= rect.right &&
          clientY >= rect.top &&
          clientY <= rect.bottom
        ) {
          return candidate;
        }
      }

      return null;
    };

    var rectCandidate = findHoverCandidateByRect();
    if (rectCandidate) {
      setHoveredNode(rectCandidate);
      return;
    }

    var target = document.elementFromPoint(clientX, clientY);
    if (!target) {
      return;
    }

    var hoveredItem = target.closest(".mobile-action-item");
    if (hoveredItem) {
      setHoveredNode(hoveredItem);
      return;
    }

    var hoveredTrigger = target.closest(".mobile-action-trigger");
    if (hoveredTrigger) {
      setHoveredNode(hoveredTrigger);
      return;
    }

    setHoveredNode(null);
  };

  var applyHoverByTarget = function (target) {
    if (!target) {
      setHoveredNode(null);
      return;
    }

    var hoveredItem = target.closest(".mobile-action-item");
    if (hoveredItem) {
      setHoveredNode(hoveredItem);
      return;
    }

    var hoveredTrigger = target.closest(".mobile-action-trigger");
    if (hoveredTrigger) {
      setHoveredNode(hoveredTrigger);
      return;
    }

    setHoveredNode(null);
  };

  var isInsideMobileActionUi = function (node) {
    if (!node || !(node instanceof Element)) {
      return false;
    }
    return !!node.closest(
      ".mobile-action-trigger, .mobile-action-menu, .mobile-action-item, td.btn-container.mobile-action-open",
    );
  };

  var closeAllMenus = function () {
    clearMobileHoverState();
    if (hoverSyncIntervalId !== null) {
      window.clearInterval(hoverSyncIntervalId);
      hoverSyncIntervalId = null;
    }
    document
      .querySelectorAll("td.btn-container.mobile-action-open")
      .forEach(function (cell) {
        cell.classList.remove(
          "mobile-action-open",
          "mobile-action-open-left",
          "mobile-action-open-right",
        );
      });
  };

  var syncHoverFromCssState = function () {
    if (!window.matchMedia("(max-width: 768px)").matches) {
      return;
    }

    var openMenuExists = document.querySelector(
      "td.btn-container.mobile-action-open",
    );
    if (!openMenuExists) {
      return;
    }

    try {
      var hoveredChain = document.querySelectorAll(":hover");
      var hoveredTarget = hoveredChain.length
        ? hoveredChain[hoveredChain.length - 1]
        : null;

      if (!hoveredTarget) {
        setHoveredNode(null);
        return;
      }

      var actionHoverTarget = hoveredTarget.closest(
        ".mobile-action-item, .mobile-action-trigger",
      );

      if (actionHoverTarget) {
        setHoveredNode(actionHoverTarget);
      } else if (!isInsideMobileActionUi(hoveredTarget)) {
        setHoveredNode(null);
      }
    } catch (e) {
      // no-op
    }
  };

  document.addEventListener("click", function (event) {
    var trigger = event.target.closest(".mobile-action-trigger");
    var insideMenu = event.target.closest(".mobile-action-menu");

    if (trigger) {
      var actionCell = trigger.closest("td.btn-container");
      var shouldOpen =
        actionCell && !actionCell.classList.contains("mobile-action-open");
      closeAllMenus();
      if (actionCell && shouldOpen) {
        var menu = actionCell.querySelector(".mobile-action-menu");
        var menuWidth = menu ? Math.max(menu.offsetWidth || 0, 160) : 160;
        var triggerRect = trigger.getBoundingClientRect();
        var spaceRight = window.innerWidth - triggerRect.right;

        actionCell.classList.add("mobile-action-open");
        if (spaceRight >= menuWidth + 12) {
          actionCell.classList.add("mobile-action-open-right");
        } else {
          actionCell.classList.add("mobile-action-open-left");
        }

        if (hoverSyncIntervalId === null) {
          hoverSyncIntervalId = window.setInterval(syncHoverFromCssState, 120);
        }
      }
      return;
    }

    if (insideMenu) {
      return;
    }

    closeAllMenus();
  });

  document.addEventListener("pointermove", function (event) {
    if (!window.matchMedia("(max-width: 768px)").matches) {
      return;
    }
    if (!document.querySelector("td.btn-container.mobile-action-open")) {
      return;
    }
    if (event.pointerType && event.pointerType !== "mouse") {
      return;
    }
    applyMobileHoverFromPoint(event.clientX, event.clientY);
  });

  document.addEventListener("mouseover", function (event) {
    applyHoverByTarget(event.target);
  });

  document.addEventListener("mouseout", function (event) {
    var toNode = event && event.relatedTarget ? event.relatedTarget : null;
    if (isInsideMobileActionUi(toNode)) {
      return;
    }
    setHoveredNode(null);
  });

  document.addEventListener("pointerover", function (event) {
    if (event.pointerType && event.pointerType !== "mouse") {
      return;
    }
    applyHoverByTarget(event.target);
  });

  document.addEventListener("pointerout", function (event) {
    if (event.pointerType && event.pointerType !== "mouse") {
      return;
    }
    var toNode = event && event.relatedTarget ? event.relatedTarget : null;
    if (isInsideMobileActionUi(toNode)) {
      return;
    }
    setHoveredNode(null);
  });

  window.addEventListener("resize", function () {
    closeAllMenus();
    convertTableActionButtonsForMobile();
  });

  applyGlobalDataTableDefaults();
  bindGlobalDataTableEmptyStateHandlers();
  convertTableActionButtonsForMobile();

  document.addEventListener("DOMContentLoaded", function () {
    applyGlobalDataTableDefaults();
    bindGlobalDataTableEmptyStateHandlers();
    convertTableActionButtonsForMobile();
  });

  if (typeof window.jQuery !== "undefined") {
    $(document).on("draw.dt", function () {
      convertTableActionButtonsForMobile();
    });
  }
}

initMobileActionMenus();

function createSortingTable(tableid, options) {
  options = options || {};

  let table = new DataTable("#" + tableid, {
    paging: $("#" + tableid + " tbody tr").length > 10,
    searching:
      typeof options.searching === "boolean"
        ? options.searching
        : $("#" + tableid + " tbody tr").length > 10,
    /* info: false, */
    order: options.order || [[1, "asc"]], // 0 = db id column; 1 = numbering column
    orderFixed: options.orderFixed || {},
    /* responsive: true, */
    lengthMenu: getDefaultDataTableLengthMenu(),
    autoWidth: false,
    columnDefs: options.columnDefs || [],
  });

  return table;
}

function normalizeCustomerRecordFilterValue(value) {
  return String(value == null ? "" : value)
    .replace(/\s+/g, " ")
    .trim()
    .toLowerCase();
}

function splitCustomerRecordFilterValues(rawValue) {
  var values = [];
  var uniqueMap = {};
  var sourceValue = String(rawValue == null ? "" : rawValue);

  sourceValue.split("||").forEach(function (value) {
    var trimmedValue = String(value == null ? "" : value)
      .replace(/\s+/g, " ")
      .trim();

    if (trimmedValue === "" || uniqueMap[trimmedValue]) {
      return;
    }

    uniqueMap[trimmedValue] = true;
    values.push(trimmedValue);
  });

  return values;
}

function getCustomerRecordRowFilterValues(rowNode, attrName) {
  if (!rowNode) {
    return [];
  }

  return splitCustomerRecordFilterValues(
    rowNode.getAttribute("data-filter-" + attrName) || "",
  );
}

function getCustomerRecordRowFilterNormalizedValues(rowNode, attrName) {
  return getCustomerRecordRowFilterValues(rowNode, attrName).map(function (value) {
    return normalizeCustomerRecordFilterValue(value);
  });
}

function getCustomerRecordFilterRowNode(settings, dataIndex, tableApi) {
  if (
    settings &&
    settings.aoData &&
    typeof dataIndex === "number" &&
    settings.aoData[dataIndex] &&
    settings.aoData[dataIndex].nTr
  ) {
    return settings.aoData[dataIndex].nTr;
  }

  if (tableApi && typeof tableApi.row === "function") {
    var rowApi = tableApi.row(dataIndex);
    if (rowApi && typeof rowApi.node === "function") {
      return rowApi.node();
    }
  }

  return null;
}

function getCustomerRecordFilterSourceRows(tableElement, tableApi) {
  var rowNodes = [];

  if (tableApi && typeof tableApi.rows === "function") {
    var apiNodes = tableApi.rows().nodes();

    if (apiNodes) {
      if (typeof apiNodes.toArray === "function") {
        rowNodes = apiNodes.toArray().filter(function (rowNode) {
          return !!rowNode;
        });
      } else if (typeof apiNodes.each === "function") {
        apiNodes.each(function (rowNode) {
          if (rowNode) {
            rowNodes.push(rowNode);
          }
        });
      } else if (typeof apiNodes.length === "number") {
        rowNodes = Array.prototype.slice
          .call(apiNodes)
          .filter(function (rowNode) {
            return !!rowNode;
          });
      }
    }
  }

  if (!rowNodes.length && tableElement) {
    rowNodes = Array.prototype.slice.call(
      tableElement.querySelectorAll("tbody tr"),
    );
  }

  return rowNodes;
}

function readCustomerRecordFilterStorage(storageKey) {
  if (!storageKey) {
    return {};
  }

  try {
    var rawValue = localStorage.getItem(storageKey);
    if (!rawValue) {
      return {};
    }

    var parsedValue = JSON.parse(rawValue);
    return parsedValue && typeof parsedValue === "object" ? parsedValue : {};
  } catch (error) {
    return {};
  }
}

function initCustomerRecordTableFilters(config) {
  if (
    typeof window.jQuery === "undefined" ||
    !$.fn ||
    !$.fn.DataTable ||
    !config ||
    !config.tableId
  ) {
    return null;
  }

  var tableElement = document.getElementById(config.tableId);
  if (!tableElement || !$.fn.DataTable.isDataTable(tableElement)) {
    return null;
  }

  var $tableElement = $(tableElement);
  var tableApi = $tableElement.DataTable();
  var wrapper = $("#" + config.tableId + "_wrapper");

  if (!wrapper.length) {
    wrapper = $tableElement.closest(".dataTables_wrapper, .dt-container");
  }

  if (!wrapper.length) {
    return null;
  }

  var fields = Array.isArray(config.filters) ? config.filters : [];
  if (!fields.length) {
    return null;
  }

  var storageKey = config.storageKey || "";
  var panelStorageKey = config.panelStorageKey || "";
  var activeFilters = readCustomerRecordFilterStorage(storageKey);
  var tableSearchFn = tableElement.__customerRecordFilterSearch || null;

  if (!tableSearchFn) {
    tableSearchFn = function (settings, rowData, dataIndex) {
      if (!settings || settings.nTable !== tableElement) {
        return true;
      }

      var rowNode = getCustomerRecordFilterRowNode(settings, dataIndex, tableApi);
      if (!rowNode) {
        return true;
      }

      for (var i = 0; i < fields.length; i++) {
        var field = fields[i];
        var filterValue = normalizeCustomerRecordFilterValue(activeFilters[field.key]);

        if (!filterValue) {
          continue;
        }

        var rowValues = getCustomerRecordRowFilterNormalizedValues(rowNode, field.attr);

        if (field.type === "text") {
          var textMatched = rowValues.some(function (rowValue) {
            return rowValue.indexOf(filterValue) !== -1;
          });

          if (!textMatched) {
            return false;
          }

          continue;
        }

        if (rowValues.indexOf(filterValue) === -1) {
          return false;
        }
      }

      return true;
    };

    tableElement.__customerRecordFilterSearch = tableSearchFn;
    $.fn.dataTable.ext.search.push(tableSearchFn);
  }

  var toolbarRow = wrapper.parent().find(
    ".customer-record-filter-toolbar-row[data-table-id='" + config.tableId + "']",
  );
  if (!toolbarRow.length) {
    toolbarRow = $('<div class="col-md-12 mb-3 customer-record-filter-toolbar-row"></div>');
    toolbarRow.attr("data-table-id", config.tableId);
    wrapper.before(toolbarRow);
  }

  var toolbar = toolbarRow.find(".customer-record-filter-toolbar");
  if (!toolbar.length) {
    toolbar = $('<div class="customer-record-filter-toolbar"></div>');
    toolbar.attr("data-table-id", config.tableId);
    toolbarRow.append(toolbar);
  }

  var filterButton = toolbar.find(".customer-record-filter-toggle");
  if (!filterButton.length) {
    filterButton = $(
      '<button type="button" class="btn btn-info customer-record-filter-toggle">Show/Hide Filters</button>',
    );
    toolbar.append(filterButton);
  }

  var panel = wrapper.find(
    ".customer-record-filter-panel[data-table-id='" + config.tableId + "']",
  );

  if (!panel.length) {
    panel = $(
      '<div class="row mb-3 customer-record-filter-panel" data-table-id="' +
        config.tableId +
        '" style="display: none;"></div>',
    );

    toolbarRow.after(panel);
  }
  panel.empty();

  var fieldNodes = {};

  fields.forEach(function (field) {
    var columnClass = field.columnClass || "col-md-3";
    var fieldWrap = $('<div class="' + columnClass + '"></div>');
    var labelNode = $(
      '<label class="form-label customer-record-filter-label" for="' +
        config.tableId +
        "_" +
        field.key +
        '">' +
        "Filter by " +
        field.label +
        "</label>",
    );

    var inputNode;
    if (field.type === "text") {
      inputNode = $(
        '<input type="text" class="form-control customer-record-filter-input" id="' +
          config.tableId +
          "_" +
          field.key +
          '" placeholder="' +
          (field.placeholder || "") +
          '">',
      );
    } else {
      inputNode = $(
        '<select class="form-select customer-record-filter-input" id="' +
          config.tableId +
          "_" +
          field.key +
          '"></select>',
      );

      inputNode.append(
        $("<option></option>")
          .attr("value", "")
          .text(field.placeholder || "All"),
      );

      var optionValues = {};
      getCustomerRecordFilterSourceRows(tableElement, tableApi).forEach(function (rowNode) {
        getCustomerRecordRowFilterValues(rowNode, field.attr).forEach(function (value) {
          optionValues[value] = true;
        });
      });

      Object.keys(optionValues)
        .sort(function (leftValue, rightValue) {
          return leftValue.localeCompare(rightValue);
        })
        .forEach(function (value) {
          inputNode.append(
            $("<option></option>")
              .attr("value", value)
              .text(value),
          );
        });
    }

    if (Object.prototype.hasOwnProperty.call(activeFilters, field.key)) {
      inputNode.val(activeFilters[field.key]);
    }

    fieldWrap.append(labelNode);
    fieldWrap.append(inputNode);
    panel.append(fieldWrap);

    fieldNodes[field.key] = inputNode;

    inputNode.off("change.customerRecordFilter").on("change.customerRecordFilter", function () {
      applyFilters();
    });
  });

  var resetWrap = $('<div class="col-md-2"></div>');
  resetWrap.append('<label class="form-label d-block invisible">Reset</label>');
  var resetButton = $(
    '<a href="#" class="btn btn-outline-danger filter-reset customer-record-filter-reset">Reset</a>',
  );
  resetWrap.append(resetButton);
  panel.append(resetWrap);

  var setPanelOpenState = function (shouldOpen) {
    panel.toggleClass("is-open", shouldOpen);
    panel.css("display", shouldOpen ? "flex" : "none");
    filterButton.toggleClass("active", shouldOpen);

    if (panelStorageKey) {
      localStorage.setItem(panelStorageKey, shouldOpen ? "1" : "0");
    }
  };

  var getCurrentFieldValues = function () {
    var values = {};

    fields.forEach(function (field) {
      values[field.key] = fieldNodes[field.key] ? fieldNodes[field.key].val() : "";
    });

    return values;
  };

  var saveActiveFilters = function () {
    activeFilters = getCurrentFieldValues();

    if (storageKey) {
      localStorage.setItem(storageKey, JSON.stringify(activeFilters));
    }
  };

  var applyFilters = function () {
    saveActiveFilters();
    setPanelOpenState(true);
    tableApi.draw(false);
  };

  var resetFilters = function () {
    activeFilters = {};

    fields.forEach(function (field) {
      if (fieldNodes[field.key]) {
        fieldNodes[field.key].val("");
      }
    });

    if (storageKey) {
      localStorage.removeItem(storageKey);
    }

    setPanelOpenState(true);
    tableApi.draw(false);
  };

  filterButton.off("click.customerRecordFilter").on("click.customerRecordFilter", function () {
    setPanelOpenState(!panel.hasClass("is-open"));
  });

  panel
    .find(".customer-record-filter-reset")
    .off("click.customerRecordFilter")
    .on("click.customerRecordFilter", function (event) {
      event.preventDefault();
      resetFilters();
    });

  if (panelStorageKey) {
    setPanelOpenState(localStorage.getItem(panelStorageKey) === "1");
  } else {
    setPanelOpenState(false);
  }

  tableApi.draw(false);

  return {
    apply: applyFilters,
    reset: resetFilters,
    table: tableApi,
  };
}

function createSortingMyLeaveTransactionTable(tableid) {
  let table = new DataTable("#" + tableid, {
    order: [[1, "asc"]],
    lengthMenu: getDefaultDataTableLengthMenu(),
    autoWidth: false,
    columnDefs: [
      {
        searchable: false,
        targets: [8],
      },
    ],
  });
}

function createSortingLeaveTransactionTable(tableid) {
  let table = new DataTable("#" + tableid, {
    order: [[1, "asc"]],
    lengthMenu: getDefaultDataTableLengthMenu(),
    autoWidth: false,
    columnDefs: [
      {
        searchable: false,
        targets: [12],
      },
    ],
  });
}

function setWidth(id, id2) {
  var one = document.getElementById(id);
  var two = document.getElementById(id2);
  style = window.getComputedStyle(one);
  wdt = style.getPropertyValue("width");
  two.style.width = wdt;
}

function datatableAlignment(elementID) {
  $(window).on("load resize", () => {
    var lengthElement = $("#" + elementID + "_length");
    var filterElement = $("#" + elementID + "_filter");
    var tableElement = $("#" + elementID);
    var tableParentElement = tableElement.parent();
    var infoElement = $("#" + elementID + "_paginate");
    var paginateElement = $("#" + elementID + "_paginate");

    // show entries and length row
    if (window.matchMedia("(max-width: 769px)").matches) {
      lengthElement.addClass("d-flex justify-content-left mb-3");
      filterElement.addClass("d-flex justify-content-left mb-3");
    } else {
      lengthElement.removeClass("d-flex justify-content-left");
      filterElement.removeClass("d-flex justify-content-left");
    }

    // paginate
    if (window.matchMedia("(max-width: 361px)").matches) {
      paginateElement.children().addClass("d-flex flex-column");
    } else {
      paginateElement.children().removeClass("d-flex flex-column");
    }

    // table
    if (!tableParentElement.parent().hasClass("table-responsive"))
      tableParentElement.parent().addClass("table-responsive");

    if (!tableParentElement.hasClass("p-0")) tableParentElement.addClass("p-0");

    // info
    if (!infoElement.hasClass("mb-3")) infoElement.addClass("mb-3 pb-3");

    // paginate
    if (!paginateElement.hasClass("mb-3"))
      paginateElement.addClass("mb-3 pb-3");
  });
}

function keepDataTableControlsVisible(elementID) {
  $(window).on("load resize", () => {
    var tableElement = $("#" + elementID);
    if (!tableElement.length) {
      return;
    }

    var wrapperElement = tableElement.closest(
      ".dataTables_wrapper, .dt-container, #" + elementID + "_wrapper",
    );
    if (!wrapperElement.length) {
      wrapperElement = $("#" + elementID + "_wrapper");
    }

    if (!wrapperElement.length) {
      return;
    }

    var scrollWrap = tableElement.parent(".datatable-scroll-wrap");
    if (!scrollWrap.length) {
      tableElement.wrap(
        '<div class="datatable-scroll-wrap table-responsive"></div>',
      );
      scrollWrap = tableElement.parent(".datatable-scroll-wrap");
    }

    if (
      wrapperElement.hasClass("table-responsive") &&
      !wrapperElement.hasClass("datatable-scroll-wrap")
    ) {
      wrapperElement.removeClass("table-responsive");
    }

    var outerResponsiveWrap = wrapperElement.parent(".table-responsive");
    if (
      outerResponsiveWrap.length &&
      !outerResponsiveWrap.hasClass("datatable-scroll-wrap")
    ) {
      outerResponsiveWrap.removeClass("table-responsive");
    }

    wrapperElement
      .parents(".table-responsive")
      .not(".datatable-scroll-wrap")
      .removeClass("table-responsive");

    if (window.matchMedia("(max-width: 769px)").matches) {
      wrapperElement.addClass("datatable-mobile-controls");
    } else {
      wrapperElement.removeClass("datatable-mobile-controls");
    }
  });
}

function centerAlignment(elementID) {
  $(window).on("load resize", () => {
    var form = $("#" + elementID);

    if (window.matchMedia("(max-height: 1250px)").matches) {
      if (form.hasClass("centered")) form.removeClass("centered");

      form.css("overflow", "auto");
    } else {
      form.addClass("centered");

      form.css("overflow", "visible");
    }
  });
}

function floatInput(element) {
  $(element).on("input", function () {
    let actualValue = $(this).val().replace(".", "");
    console.log(actualValue);
    $(this).val((parseInt(actualValue) / 100).toFixed(2));

    if ($(this).val() == "0" || $(this).val() == "0.00") $(this).val("");
  });
}

function previewImage(input, output) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();

    reader.onload = function (e) {
      $("#" + output).attr("src", e.target.result);
    };

    reader.readAsDataURL(input.files[0]);
  }
}

async function confirmationDialog(id, msg, pagename, path, pathreturn, act) {
  function cleanupConfirmationModal(modalInstance, modalNode) {
    try {
      if (modalInstance) {
        modalInstance.hide();
        modalInstance.dispose();
      }
    } catch (error) {}

    document.querySelectorAll(".modal-backdrop").forEach((backdrop) => {
      backdrop.remove();
    });

    if (modalNode && modalNode.remove) {
      modalNode.remove();
    }

    document.body.classList.remove("modal-open");
    document.body.style.removeProperty("overflow");
    document.body.style.removeProperty("padding-right");
  }

  switch (act) {
    case "I":
      var title = "Successful Insert " + pagename;
      var title2 = "Are you sure want to insert?";
      var btn = "Insert";
      break;
    case "E":
      var title = "Successful Edit " + pagename;
      var title2 = "Are you sure want to edit?";
      var btn = "Edit";
      break;
    case "D":
      var title = "Successful Delete " + pagename;
      var title2 = "Are You Sure Want To Delete This " + pagename + " ?";
      var btn = "Delete";
      break;
    case "F":
      var title = "Error Occurred,Please Try Again Later";
      break;
    case "MO":
      var title = msg + " Successful Place";
      break;
    case "ErrMO":
      var title = msg;
      break;
    case "NC":
      var title = "No changes were made.";
      break;
    case "PC":
      var title = "Successful Change " + pagename;
      break;
    case "LA":
    case "LD":
    case "LC":
      var action;
      if (act === "LA") {
        action = "approval";
      } else if (act === "LD") {
        action = "declined";
      } else if (act === "LC") {
        action = "Cancel";
      }

      var title = `Leave transaction ${action}`;
      var title2 = `<span style="color:#FF9B44" class="mdi mdi-alert-circle-outline"></span> Confirm Action`;
      var msg = [
        `This leave transaction cannot modify once it has been ${action}. Do you still want to proceed ?`,
      ];
      var btn = "Confirm";
      break;
    default:
      var title = "Error";
  }

  if (act !== "ErrMO") {
    clearLocalStoragePreservingCustomerRecordFilters();
  }

  var message = "";
  if (msg.length >= 1) {
    for (let i = 0; i < msg.length; i++)
      message += `<p class="mt-n3" style="text-align:center; font-weight:bold;">${msg[i]}</p>`;
  }

  if (act == "D" || act == "LD" || act == "LA" || act == "LC") {
    var firstContent = title2;
  } else {
    var firstContent = title;
  }

  const modalElem = document.createElement("div");
  modalElem.id = "modal-confirm";
  modalElem.className = "modal fade";
  modalElem.innerHTML = `
  <div class="modal-dialog modal-dialog-centered" style="font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
      <div class="modal-content">             
          <div class="modal-body fs-6 mt-3">
              <p style="text-align:center; font-weight:bold; font-size:25px;">${firstContent} </p>
              ${message}
          </div>
          <div class="modal-footer d-flex justify-content-center mt-n3" style="border-top:0px">             
              <button id="acceptBtn" type="button" class="btn" 
                  style="border:1px solid #FF9B44; background-color:#FFFFFF; color:#FF9B44; box-shadow: 0 0 !important; border-radius: 24px; text-transform:none;">
                  ${btn}
              </button>
              <button id="rejectBtn" type="button" class="btn" 
                  style="border: 1px solid #FF9B44; background-color:#FFFFFF; color:#FF9B44; box-shadow: 0 0 !important; border-radius: 24px; text-transform:none;">
                  Cancel
              </button>
          </div>
      </div>
  </div>
`;

  const modelResult = document.createElement("div");
  modelResult.id = "modal-confirm";
  modelResult.className = "modal fade";
  modelResult.innerHTML = `
        <div class="modal-dialog modal-dialog-centered " style="font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
        <div class="modal-content">             
            <div class="modal-body fs-6 mt-3">
            <p style="text-align:center; font-weight:bold; font-size:25px;">${title}</p>
        </div>
        <div class="modal-footer d-flex justify-content-center mt-n3" style="border-top:0px">             
            <button id="contBtn" type="button" class="btn" 
            style="border:1px solid #FF9B44; background-color:#FFFFFF; color:#FF9B44; box-shadow:0 0 !important; border-radius: 24px; text-transform:none;">Continue</button>
        </div>
        </div>
    </div>
    `;
  if (act == "D" || act == "LD" || act == "LA" || act == "LC") {
    const myModal = new bootstrap.Modal(modalElem, {
      keyboard: false,
      backdrop: "static",
    });
    myModal.show();

    const result = await new Promise((resolve, reject) => {
      document.body.addEventListener("click", response);

      function response(e) {
        let bool = false;
        if (e.target.id == "rejectBtn") bool = false;
        else if (e.target.id == "acceptBtn") bool = true;
        else return;
        document.body.removeEventListener("click", response);
        cleanupConfirmationModal(myModal, modalElem);
        resolve(bool);
      }
    });

    if (result) {
      $.ajax({
        type: "POST",
        url: path,
        data: {
          id: id,
          act: act,
        },

        cache: false,
        success: (result) => {
          console.log(path);
          const myModal2 = new bootstrap.Modal(modelResult, {
            keyboard: false,
            backdrop: "static",
          });
          myModal2.show();

          return new Promise((resolve, reject) => {
            document.body.addEventListener("click", response);

            var myTimeout = setTimeout(() => {
              document.body.removeEventListener("click", response);
              cleanupConfirmationModal(myModal2, modelResult);
              resolve(true);
              location.reload();
            }, 5000);

            function response(e) {
              let bool = false;
              let timeOut = false;

              if (e.target.id == "contBtn") {
                bool = true;
                clearTimeout(myTimeout);
              } else return;

              document.body.removeEventListener("click", response);
              cleanupConfirmationModal(myModal2, modelResult);
              resolve(bool);
              location.reload();
            }
          });
        },
      });
    } else console.log("Operation Cancelled.");
  }

  if (
    act == "I" ||
    act == "E" ||
    act == "MO" ||
    act == "NC" ||
    act == "PC" ||
    act == "F" ||
    act == "ErrMO"
  ) {
    const myModal2 = new bootstrap.Modal(modelResult, {
      keyboard: false,
      backdrop: "static",
    });
    myModal2.show();

    return new Promise((resolve, reject) => {
      document.body.addEventListener("click", response);

      var myTimeout = setTimeout(() => {
        document.body.removeEventListener("click", response);
        cleanupConfirmationModal(myModal2, modelResult);
        resolve(true);
        window.location.href = pathreturn;
      }, 5000);

      function response(e) {
        let bool = false;
        let timeOut = false;

        if (e.target.id == "contBtn") {
          bool = true;
          clearTimeout(myTimeout);
        } else return;

        document.body.removeEventListener("click", response);
        cleanupConfirmationModal(myModal2, modelResult);
        resolve(bool);
        window.location.href = pathreturn;
      }
    });
  }
}

/* Rate Checking */
var getUrlParameter = function getUrlParameter(sParam) {
  var sPageURL = window.location.search.substring(1),
    sURLVariables = sPageURL.split("&"),
    sParameterName,
    i;

  for (i = 0; i < sURLVariables.length; i++) {
    sParameterName = sURLVariables[i].split("=");

    if (sParameterName[0] === sParam) {
      return sParameterName[1] === undefined
        ? true
        : decodeURIComponent(sParameterName[1]).replace(/\+/g, " ");
    }
  }
  return false;
};

/* fix issue of dropdown menu display inside table responsive */
function dropdownMenuDispFix() {
  const dropdowns = document.querySelectorAll(".dropdown-toggle");
  const dropdown = [...dropdowns].map(
    (dropdownToggleEl) =>
      new bootstrap.Dropdown(dropdownToggleEl, {
        popperConfig(defaultBsPopperConfig) {
          return { ...defaultBsPopperConfig, strategy: "fixed" };
        },
      }),
  );
}

//autocomplete
function ensureAutocompleteResultShell(elementID) {
  var $input = $("#" + elementID);
  if (!$input.length) {
    return;
  }

  var resultId = "searchResult_" + elementID;
  var clearId = "clear_" + elementID;
  var $wrapper = $input.closest(".autocomplete");

  if (!$wrapper.length) {
    $wrapper = $input.parent();
  }

  if (!$("#" + resultId).length) {
    $wrapper.append(
      '<ul class="searchResult" id="' + resultId + '"></ul>',
      '<div id="' + clearId + '" class="clear"></div>',
    );
  } else if ($("#" + resultId).parent().get(0) !== $wrapper.get(0)) {
    $("#" + resultId).appendTo($wrapper);
    $("#" + clearId).appendTo($wrapper);
  }
}

function positionAutocompleteResult(elementID) {
  var input = document.getElementById(elementID);
  var result = document.getElementById("searchResult_" + elementID);
  if (!input || !result) {
    return;
  }

  result.style.left = input.offsetLeft + "px";
  result.style.top = input.offsetTop + input.offsetHeight + 4 + "px";
  result.style.width = input.offsetWidth + "px";
}

function searchInput(param, siteURL) {
  var elementID = param["elementID"];
  var hiddenElementID = param["hiddenElementID"];
  var search = param["search"];
  var type = param["searchType"];
  var dbTable = param["dbTable"];
  if (param["addSelection"]) {
    var addSelection = param["addSelection"];
  }

  if (search != "") {
    console.log(siteURL);
    $.ajax({
      url: siteURL + "/getSearch.php",
      type: "post",
      data: {
        searchText: search,
        searchType: type,
        tblname: dbTable,
      },
      dataType: "json",
      success: (result) => {
        // console.log(result);
        ensureAutocompleteResultShell(elementID);

        // set width same as input
        setWidth(elementID, "searchResult_" + elementID);
        positionAutocompleteResult(elementID);

        var dataArr = [];

        // loop result
        var len = result.length;
        $("#searchResult_" + elementID).empty();
        for (var i = 0; i < len; i++) {
          if (result[i]["desc"] != undefined) {
            var desc = result[i]["desc"];
            var value = result[i]["val"];
            $("#searchResult_" + elementID).append(
              "<li value='" + value + "'>" + desc + "</li>",
            );
          } else {
            var id = result[i]["id"];
            var name = result[i][type];
            $("#searchResult_" + elementID).append(
              "<li value='" + id + "'>" + name + "</li>",
            );
            dataArr[id] = result[i];
          }
        }

        if (addSelection) {
          $("#searchResult_" + elementID).append(
            "<li value='" + addSelection + "'>" + addSelection + "</li>",
          );
        }

        // binding click event to li
        $("#searchResult_" + elementID + " li").bind("click", function () {
          setText(this, "#" + elementID, "#" + hiddenElementID);
          $("#" + elementID).change();
          $("#searchResult_" + elementID).empty();
          $("#searchResult_" + elementID).remove();
          $("#clear_" + elementID).remove();
        });
      },
    });
  } else {
    $("#searchResult_" + elementID).empty();
    $("#searchResult_" + elementID).remove();
    $("#clear_" + elementID).remove();
  }
}
function searchInput2(param, siteURL) {
  var elementID = param["elementID"];
  var hiddenElementID = param["hiddenElementID"];
  var search = param["search"];
  var type = param["searchTypes"];
  var pkg = param["pkgID"];
  var usr = param["usrID"];
  var whse = param["whseID"];

  if (param["addSelection"]) {
    var addSelection = param["addSelection"];
  }

  if (search != "") {
    $.ajax({
      url: siteURL + "/getSearch2.php",
      type: "post",
      data: {
        searchText: search,
        searchType: type,
        tblname: "orderid",
        pkg: pkg,
        usr: usr,
        whse: whse,
      },
      dataType: "json",
      success: (result) => {
        // create div
        ensureAutocompleteResultShell(elementID);

        // set width same as input
        setWidth(elementID, "searchResult_" + elementID);
        positionAutocompleteResult(elementID);

        var dataArr = [];

        // loop result
        var len = result.length;
        $("#searchResult_" + elementID).empty();
        for (var i = 0; i < len; i++) {
          if (result[i]["desc"] != undefined) {
            var desc = result[i]["desc"];
            var value = result[i]["val"];
            $("#searchResult_" + elementID).append(
              "<li value='" + value + "'>" + desc + "</li>",
            );
          } else {
            var id = result[i]["id"];
            var name = result[i][type];
            $("#searchResult_" + elementID).append(
              "<li value='" + id + "'>" + name + "</li>",
            );
            dataArr[id] = result[i];
          }
        }

        if (addSelection) {
          $("#searchResult_" + elementID).append(
            "<li value='" + addSelection + "'>" + addSelection + "</li>",
          );
        }

        // binding click event to li
        $("#searchResult_" + elementID + " li").bind("click", function () {
          setText(this, "#" + elementID, "#" + hiddenElementID);
          $("#" + elementID).change();
          $("#searchResult_" + elementID).empty();
          $("#searchResult_" + elementID).remove();
          $("#clear_" + elementID).remove();
        });
      },
    });
  } else {
    $("#searchResult_" + elementID).empty();
    $("#searchResult_" + elementID).remove();
    $("#clear_" + elementID).remove();
  }
}
function retrieveDBData(param, siteURL, callback) {
  var search = param["search"];
  var type = param["searchType"];
  var dbTable = param["dbTable"];
  var col = param["searchCol"];
  var fin = param["isFin"];

  if (search != "") {
    $.ajax({
      url: siteURL + "/searchData.php",
      type: "post",
      data: {
        searchText: search,
        searchType: type,
        tblname: dbTable,
        searchCol: col,
        isFin: fin,
      },
      dataType: "json",
      success: (result) => {
        callback(result);
      },
      error: function (xhr, status, error) {
        console.error("Error fetching data:", error);
        console.log("XHR Status:", status);
        console.log("XHR Response Text:", xhr.responseText);
        console.log("XHR Response JSON:", xhr.responseJSON);
      },
    });
  }
}

function retrieveJSONData(search, type, tblname) {
  return $.ajax({
    url: "getSearch.php",
    type: "post",
    data: {
      searchText: search,
      searchType: type,
      tblname: tblname,
    },
    dataType: "json",
    success: (result) => {
      /* console.log(result[0]); */
      /* return result; */
    },
  });
}

function setText(element, val, val2) {
  var text = $(element).text();
  var value = $(element).attr("value");

  if (value != "emptyValue") {
    $(val).val(text);
    $(val2).val(value).trigger("input"); // to trigger input event from package page
  } else {
    $(val).val("");
    $(val2).val("").trigger("input"); // to trigger input event from package page
  }
}

document.addEventListener("DOMContentLoaded", function () {
  var actionBtn = document.getElementById("actionBtn");
  retrieveDataFromLocalStorage();

  // Attach input event listener to each input field
  var inputFields = document.querySelectorAll("input, textarea ,select");
  inputFields.forEach(function (input) {
    if (!input.readOnly) {
      input.addEventListener("input", function () {
        // Save form data to localStorage when user types
        saveFormDataToLocalStorage();
      });
    }
  });
  if (actionBtn) {
    actionBtn.addEventListener("click", function (event) {
      if (!validateForm()) {
        event.preventDefault();
        displayPreviousData();
      } else {
        // Save form data to localStorage when validation passes
        saveFormDataToLocalStorage();
      }
    });
  }

  function retrieveDataFromLocalStorage() {
    var inputFields = document.querySelectorAll("input, textarea ,select");
    var page = localStorage.getItem("page");

    if (page !== "invalid") {
      inputFields.forEach(function (input) {
        // Check if the input is not readonly and has stored data
        if (
          !input.readOnly &&
          localStorage.getItem(input.id) &&
          input.id &&
          input.type !== "file"
        ) {
          input.value = localStorage.getItem(input.id);
        }
      });
    }
  }

  function saveFormDataToLocalStorage() {
    var inputFields = document.querySelectorAll("input, textarea ,select");
    var page = localStorage.getItem("page");

    if (page !== "invalid") {
      inputFields.forEach(function (input) {
        if (
          !input.readOnly &&
          input.id &&
          !input.multiple &&
          input.type !== "file"
        ) {
          localStorage.setItem(input.id, input.value);
        }
      });
    }
  }

  function displayPreviousData() {
    // Loop through input fields and restore previous data
    var inputFields = document.querySelectorAll("input, textarea,select");
    inputFields.forEach(function (input) {
      // Check if the input is not readonly and has previous data
      if (
        !input.readOnly &&
        localStorage.getItem(input.id) &&
        input.type !== "file"
      ) {
        input.value = localStorage.getItem(input.id);
      }
    });
  }

  function validateForm() {
    var alertMessages = document.querySelectorAll('span[role="alert"]');
    alertMessages.forEach(function (alert) {
      alert.parentNode.removeChild(alert);
    });

    checkRequiredInputs();

    return document.querySelectorAll('span[role="alert"]').length === 0;
  }

  function checkRequiredInputs() {
    var requiredInputs = document.querySelectorAll(
      "input[required], select[required]",
    );

    requiredInputs.forEach(function (input) {
      if (input.value.trim() === "") {
        var labelContent = document.querySelector(
          'label[for="' + input.id + '"]',
        ).textContent;

        labelContent = labelContent.replace(/\*/g, "");

        var alertMessage = document.createElement("span");
        alertMessage.textContent = labelContent + " is required!";
        alertMessage.style.color = "red";
        alertMessage.setAttribute("role", "alert");

        input.parentNode.appendChild(alertMessage);

        // Save the current value as the previous value
        input.setAttribute("data-previous-value", input.value);
      }
    });
  }
});

// Wait for the DOM to be ready
document.addEventListener("DOMContentLoaded", function () {
  // Get the input field and error message elements
  var currentDataNameInput = document.getElementById("currentDataName");
  var errorSpan = document.getElementById("errorSpan");

  if (currentDataNameInput) {
    // Function to toggle error message visibility
    function toggleErrorMessage() {
      var inputValue = currentDataNameInput.value.trim();
      errorSpan.style.display =
        inputValue !== "" &&
        inputValue !== localStorage.getItem("currentDataName")
          ? "none"
          : "block";
    }

    // Attach an input event listener to the input field
    currentDataNameInput.addEventListener("input", toggleErrorMessage);

    // Initial toggle to set the initial state
    toggleErrorMessage();
  }
});

function setCookie(cname, cvalue, exMins) {
  var d = new Date();
  d.setTime(d.getTime() + exMins * 60 * 1000);
  var expires = "expires=" + d.toUTCString();
  document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

function toggleCustomerLabelVisibility(toggleButton) {
  if (!toggleButton) {
    return;
  }

  var labelWrap = toggleButton.closest(".js-customer-label-wrap");
  if (!labelWrap) {
    return;
  }

  var extraLabels = labelWrap.querySelectorAll(".customer-label-extra");
  var isExpanded = labelWrap.getAttribute("data-expanded") === "1";

  for (var i = 0; i < extraLabels.length; i++) {
    extraLabels[i].classList.toggle("d-none", isExpanded);
  }

  labelWrap.setAttribute("data-expanded", isExpanded ? "0" : "1");
  toggleButton.textContent = isExpanded ? "Show More" : "Show Less";
}

document.addEventListener("click", function (event) {
  var toggleButton = event.target.closest(".js-toggle-customer-labels");
  if (!toggleButton) {
    return;
  }

  event.preventDefault();
  toggleCustomerLabelVisibility(toggleButton);
});

function getCustomerRecordFilterStorageSnapshot() {
  var preservedEntries = {};

  for (var i = 0; i < localStorage.length; i++) {
    var key = localStorage.key(i);
    if (!key) {
      continue;
    }

    if (/_filters$/.test(key) || /_filter_panel_open$/.test(key)) {
      preservedEntries[key] = localStorage.getItem(key);
    }
  }

  return preservedEntries;
}

function restoreCustomerRecordFilterStorageSnapshot(entries) {
  if (!entries) {
    return;
  }

  Object.keys(entries).forEach(function (key) {
    localStorage.setItem(key, entries[key]);
  });
}

function clearLocalStoragePreservingCustomerRecordFilters() {
  var preservedEntries = getCustomerRecordFilterStorageSnapshot();
  localStorage.clear();
  restoreCustomerRecordFilterStorageSnapshot(preservedEntries);
}

function checkCurrentPage(page, action) {
  var previousPage = localStorage.getItem("page");
  var perviousAction = localStorage.getItem("action");

  if (previousPage != page || perviousAction != action) {
    clearLocalStoragePreservingCustomerRecordFilters();
    localStorage.setItem("page", page);
    localStorage.setItem("action", action);
  }
}

function preloader(additionalDelay, action) {
  function releasePageLoader() {
    setTimeout(function () {
      var preloaders = document.querySelectorAll(".preloader");
      var preloadCenters = document.querySelectorAll(".pre-load-center");
      var pageCovers = document.querySelectorAll(".page-load-cover");

      for (var i = 0; i < preloaders.length; i++) {
        preloaders[i].style.display = "none";
      }
      for (var j = 0; j < preloadCenters.length; j++) {
        preloadCenters[j].style.display = "none";
      }
      for (var k = 0; k < pageCovers.length; k++) {
        pageCovers[k].style.display = "block";
      }

      setAutofocus(action);

      if (typeof commonInitMobileActionEnhancements === "function") {
        commonInitMobileActionEnhancements();
      }
    }, additionalDelay || 0);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", releasePageLoader);
  } else {
    releasePageLoader();
  }

  window.addEventListener("load", function () {
    releasePageLoader();
  });
}

function setAutofocus(action) {
  if (action === "I" || action === "E") {
    var firstInput = $(
      "input[type='text']:visible:enabled:not(:checkbox,:radio,:hidden,[readonly]), textarea:visible:enabled:not(:hidden,[readonly]), input[type='number']:visible:enabled:not(:hidden,[readonly])",
    )
      .filter(function () {
        return $.trim($(this).val()) === "";
      })
      .first();

    if (firstInput.length > 0) {
      firstInput.focus();

      var inputValue = firstInput.val();
      if (inputValue) {
        var lastSpaceIndex = inputValue.lastIndexOf(" ");

        if (lastSpaceIndex !== -1) {
          var input = firstInput.get(0);
          var lastWordIndex = inputValue.indexOf(" ", lastSpaceIndex + 1);
          var cursorPosition =
            lastWordIndex !== -1 ? lastWordIndex : inputValue.length;
          input.setSelectionRange(cursorPosition, cursorPosition);
        } else {
          firstInput.get(0).selectionStart = firstInput.get(0).selectionEnd =
            inputValue.length;
        }
      }
    }
  }
}

//export notification
function exportData() {
  var checkboxes = document.querySelectorAll(".export:checked");
  if (checkboxes.length === 0) {
    alert("Please select data to export.");
    return false;
  }
  return true;
}

function showExportNotification() {
  alert("Export successful!");
}

function commonMobileActionIsVisible(element) {
  if (!element) {
    return false;
  }

  if (element.offsetParent === null) {
    return false;
  }

  var style = window.getComputedStyle(element);
  return style.display !== "none" && style.visibility !== "hidden";
}

function commonNormalizeButtonText(text) {
  return (text || "").replace(/\s+/g, " ").trim();
}

function commonResolvePageTitle() {
  var path = (window.location.pathname || "").toLowerCase();

  if (
    /\/shopee\/(shopee_order_req_table|shopee_processing_order|shopee_verify)\.php$/.test(
      path,
    )
  ) {
    return "Shopee Order Request";
  }

  return commonNormalizeButtonText(document.title || "");
}

function commonResolveButtonTitle(element, pageTitle) {
  if (!element) {
    return "";
  }

  var text = commonNormalizeButtonText(element.textContent || element.value || "");
  var value = commonNormalizeButtonText(element.value || "");
  var href = (element.getAttribute("href") || "").toLowerCase();
  var existingTitle = commonNormalizeButtonText(element.getAttribute("title") || "");
  var iconClass = "";
  var iconElement = element.querySelector("i");

  if (iconElement) {
    iconClass = iconElement.className || "";
  }

  var hasGenericTitle =
    existingTitle === "" ||
    /^(add|edit|view|delete|back|add record|edit record)$/i.test(existingTitle);

  if (!hasGenericTitle) {
    return existingTitle;
  }

  if (
    element.id === "addBtn" ||
    element.getAttribute("name") === "addBtn" ||
    value === "addRecord" ||
    /^Add Record$/i.test(text)
  ) {
    return pageTitle ? "Add " + pageTitle : "Add Record";
  }

  if (
    /^upd/i.test(value) ||
    ((element.id === "actionBtn" || element.classList.contains("submitBtn")) &&
      /^Edit\b/i.test(text))
  ) {
    return pageTitle ? "Edit " + pageTitle : "Edit Record";
  }

  if (value === "back" || /^Back$/i.test(text)) {
    return pageTitle ? "Back to " + pageTitle : "Back";
  }

  if (/fa-eye/.test(iconClass) || (/id=/.test(href) && !/act=/.test(href) && text === "")) {
    return pageTitle ? "View " + pageTitle : "View";
  }

  if (/fa-edit/.test(iconClass) || /act=e/i.test(href)) {
    return pageTitle ? "Edit " + pageTitle : "Edit";
  }

  if (/fa-trash/.test(iconClass)) {
    return pageTitle ? "Delete " + pageTitle : "Delete";
  }

  if (text !== "") {
    return text;
  }

  return existingTitle;
}

function commonApplyButtonTitles() {
  var pageTitle = commonResolvePageTitle();
  var buttons = document.querySelectorAll("a.btn, button.btn");

  for (var i = 0; i < buttons.length; i++) {
    var title = commonResolveButtonTitle(buttons[i], pageTitle);
    if (title !== "") {
      buttons[i].setAttribute("title", title);
      if (!buttons[i].getAttribute("aria-label")) {
        buttons[i].setAttribute("aria-label", title);
      }
    }
  }
}

function commonApplyVisibleActionLabels() {
  var pageTitle = commonResolvePageTitle();
  if (!pageTitle) {
    return;
  }

  var buttons = document.querySelectorAll("a.btn, button.btn");
  for (var i = 0; i < buttons.length; i++) {
    var button = buttons[i];
    var text = commonNormalizeButtonText(button.textContent || "");
    var value = commonNormalizeButtonText(button.value || "");

    if (
      /^upd/i.test(value) ||
      ((button.id === "actionBtn" || button.classList.contains("submitBtn")) &&
        /^Edit\b/i.test(text))
    ) {
      button.textContent = "Edit " + pageTitle;
      continue;
    }
  }
}

function commonSyncButtonVisualStyle(sourceButton, targetButton) {
  if (!sourceButton || !targetButton) {
    return;
  }

  var computedStyle = window.getComputedStyle(sourceButton);
  var properties = [
    "background",
    "backgroundColor",
    "border",
    "borderColor",
    "borderRadius",
    "boxShadow",
    "color",
    "font",
    "fontFamily",
    "fontSize",
    "fontWeight",
    "height",
    "letterSpacing",
    "lineHeight",
    "minHeight",
    "padding",
    "textTransform",
  ];

  for (var i = 0; i < properties.length; i++) {
    var property = properties[i];
    targetButton.style[property] = computedStyle[property];
  }
}

function commonBuildMobileFloatingAddButton() {
  if (document.querySelector(".mobile-floating-action-bar")) {
    return;
  }

  var sourceButton = document.querySelector(
    "a#addBtn.btn, button#addBtn.btn, a[name='addBtn'].btn, button[name='addBtn'].btn",
  );

  if (!sourceButton || !commonMobileActionIsVisible(sourceButton)) {
    return;
  }

  var stickyBar = document.createElement("div");
  stickyBar.className = "mobile-floating-action-bar mobile-floating-action-bar--single";

  var buttonClone = sourceButton.cloneNode(true);
  buttonClone.removeAttribute("id");
  buttonClone.classList.add("mobile-floating-primary-action");
  commonSyncButtonVisualStyle(sourceButton, buttonClone);

  stickyBar.appendChild(buttonClone);
  document.body.appendChild(stickyBar);

  sourceButton.classList.add("mobile-floating-source-btn");
  document.body.classList.add("has-mobile-floating-add-btn");
}

function commonBuildMobileStickyFormActions() {
  var preferredContainer = document.querySelector(".mobile-sticky-form-actions-target");
  if (preferredContainer) {
    preferredContainer.classList.add("mobile-sticky-form-actions");
    document.body.classList.add("has-mobile-sticky-form-actions");

    var preferredButtons = Array.prototype.slice
      .call(
        preferredContainer.querySelectorAll(
          "button.submitBtn, button.cancel, button#actionBtn, button#backBtn, button[name='actionBtn'], button[name='updateStatusBtn'], a.submitBtn, a.cancel, a#actionBtn, a#backBtn",
        ),
      )
      .filter(function (button) {
        return !button.closest("td") && !button.closest(".mobile-floating-action-bar");
      });

    preferredButtons.sort(function (a, b) {
      var aText = commonNormalizeButtonText(a.textContent || a.value || "").toLowerCase();
      var bText = commonNormalizeButtonText(b.textContent || b.value || "").toLowerCase();

      var aIsBack =
        a.id === "backBtn" ||
        a.classList.contains("backBtn") ||
        a.classList.contains("cancel") ||
        a.value === "back" ||
        aText === "back";

      var bIsBack =
        b.id === "backBtn" ||
        b.classList.contains("backBtn") ||
        b.classList.contains("cancel") ||
        b.value === "back" ||
        bText === "back";

      if (aIsBack && !bIsBack) {
        return -1;
      }

      if (!aIsBack && bIsBack) {
        return 1;
      }

      return 0;
    });

    for (var p = 0; p < preferredButtons.length; p++) {
      preferredButtons[p].classList.add("mobile-sticky-form-button");
      preferredContainer.appendChild(preferredButtons[p]);
    }

    return;
  }

  var selector =
  "button.submitBtn, button.cancel, button#actionBtn, button#backBtn, button[name='actionBtn'], button[name='updateStatusBtn'], a.submitBtn, a.cancel, a#actionBtn, a#backBtn";
  var buttons = Array.prototype.slice.call(document.querySelectorAll(selector)).filter(function (
    button,
  ) {
    var parentForm = button.closest("form");
    var isUploadAnalyzeButton =
      parentForm &&
      parentForm.querySelector("input[type='file']") &&
      !button.hasAttribute("formnovalidate");

    return (
      !isUploadAnalyzeButton &&
      !button.closest("td") &&
      !button.closest(".mobile-floating-action-bar") &&
      !button.closest(".mobile-sticky-form-actions")
    );
  });

  if (buttons.length === 0) {
    return;
  }

  var groupedParents = [];

  for (var i = 0; i < buttons.length; i++) {
    var parent = buttons[i].parentElement;
    if (!parent) {
      continue;
    }

    var existingGroup = null;
    for (var j = 0; j < groupedParents.length; j++) {
      if (groupedParents[j].element === parent) {
        existingGroup = groupedParents[j];
        break;
      }
    }

    if (existingGroup) {
      existingGroup.buttons.push(buttons[i]);
    } else {
      groupedParents.push({
        element: parent,
        buttons: [buttons[i]],
      });
    }
  }

  if (groupedParents.length === 0) {
    return;
  }

  groupedParents.sort(function (a, b) {
    if (b.buttons.length !== a.buttons.length) {
      return b.buttons.length - a.buttons.length;
    }

    return (
      b.element.getBoundingClientRect().top - a.element.getBoundingClientRect().top
    );
  });

  var actionContainer = groupedParents[0].element;
    actionContainer.classList.add("mobile-sticky-form-actions");
    document.body.classList.add("has-mobile-sticky-form-actions");

    var stickyButtons = groupedParents[0].buttons;

    stickyButtons.sort(function (a, b) {
      var aText = commonNormalizeButtonText(a.textContent || a.value || "").toLowerCase();
      var bText = commonNormalizeButtonText(b.textContent || b.value || "").toLowerCase();

      var aIsBack =
        a.id === "backBtn" ||
        a.classList.contains("backBtn") ||
        a.classList.contains("cancel") ||
        a.value === "back" ||
        aText === "back";

      var bIsBack =
        b.id === "backBtn" ||
        b.classList.contains("backBtn") ||
        b.classList.contains("cancel") ||
        b.value === "back" ||
        bText === "back";

      if (aIsBack && !bIsBack) {
        return -1;
      }

      if (!aIsBack && bIsBack) {
        return 1;
      }

      return 0;
    });

    for (var k = 0; k < stickyButtons.length; k++) {
      stickyButtons[k].classList.add("mobile-sticky-form-button");
      actionContainer.appendChild(stickyButtons[k]);
    }
}

function commonInitMobileActionEnhancements() {
  commonApplyVisibleActionLabels();
  commonApplyButtonTitles();
  commonBuildMobileFloatingAddButton();
  commonBuildMobileStickyFormActions();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", commonInitMobileActionEnhancements);
} else {
  commonInitMobileActionEnhancements();
}

window.addEventListener("load", commonInitMobileActionEnhancements);
