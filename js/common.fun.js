function obj(str) {
  return document.getElementById(str) || null;
}

function objValue(str) {
  let element = obj(str);
  return element ? element.value : "";
}

function normalizeNotificationType(type) {
  let resolvedType = String(type || "info").toLowerCase();

  if (
    resolvedType !== "success" &&
    resolvedType !== "error" &&
    resolvedType !== "warning" &&
    resolvedType !== "info"
  ) {
    resolvedType = "info";
  }

  return resolvedType;
}

function showNotification(message, type) {
  let text = String(message == null ? "" : message).trim();
  if (!text) {
    return;
  }

  let resolvedType = normalizeNotificationType(type);
  let palette = {
    success: {
      background: "#d1e7dd",
      border: "#badbcc",
      color: "#0f5132",
    },
    error: {
      background: "#f8d7da",
      border: "#f5c2c7",
      color: "#842029",
    },
    warning: {
      background: "#fff3cd",
      border: "#ffecb5",
      color: "#664d03",
    },
    info: {
      background: "#cff4fc",
      border: "#b6effb",
      color: "#055160",
    },
  };

  let host = document.getElementById("global-notification-host");
  if (!host) {
    host = document.createElement("div");
    host.id = "global-notification-host";
    host.setAttribute("aria-live", "polite");
    host.style.position = "fixed";
    host.style.top = "16px";
    host.style.right = "16px";
    host.style.zIndex = "1080";
    host.style.display = "flex";
    host.style.flexDirection = "column";
    host.style.gap = "10px";
    host.style.maxWidth = "min(360px, calc(100vw - 32px))";
    (document.body || document.documentElement).appendChild(host);
  }

  let toast = document.createElement("div");
  toast.setAttribute("role", "status");
  toast.style.background = palette[resolvedType].background;
  toast.style.border = "1px solid " + palette[resolvedType].border;
  toast.style.borderRadius = "10px";
  toast.style.boxShadow = "0 10px 24px rgba(15, 23, 42, 0.14)";
  toast.style.color = palette[resolvedType].color;
  toast.style.fontSize = "14px";
  toast.style.fontWeight = "600";
  toast.style.lineHeight = "1.4";
  toast.style.padding = "12px 14px";
  toast.style.opacity = "0";
  toast.style.transform = "translateY(-8px)";
  toast.style.transition = "opacity 0.2s ease, transform 0.2s ease";
  toast.textContent = text;

  host.appendChild(toast);

  window.requestAnimationFrame(function () {
    toast.style.opacity = "1";
    toast.style.transform = "translateY(0)";
  });

  window.setTimeout(function () {
    toast.style.opacity = "0";
    toast.style.transform = "translateY(-8px)";
    window.setTimeout(function () {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 220);
  }, 3200);
}

function toggle(str) {
  let element = obj(str);
  if (!element) {
    return false;
  }

  if (element.style.display == "none") {
    element.style.display = "block";
    return true;
  } else if (element.style.display == "block") {
    element.style.display = "none";
    return false;
  }
}

function isEmail(str) {
  const filter =
    /^[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,})$/;
  return filter.test(str);
}

function isNumber(str) {
  const filter = /^[0-9]+$/;
  return filter.test(str);
}

function MM_findObj(n, d) {
  let p, i, x;
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
  let i,
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
  let i,
    x,
    a = document.MM_sr;
  for (i = 0; a && i < a.length && (x = a[i]) && x.oSrc; i++) x.src = x.oSrc;
}

function isNumberKey(evt) {
  let keyEvent = evt || window.event;
  if (!keyEvent) {
    return true;
  }

  let charCode = keyEvent.which ? keyEvent.which : keyEvent.keyCode;
  if (charCode > 31 && (charCode < 48 || charCode > 57)) {
    return false;
  }

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
  const features =
    "toolbars=0, scrollbars=1, location=0, statusbars=0, menubars=0, resizable=0, width=" +
    x +
    ", height=" +
    y +
    ", left = 168, top = 118";
  const popupWindow = window.open(webaddy, title, features);
  return popupWindow;
}

function limitText(limitField, limitCount, limitNum) {
  if (limitField.value.length > limitNum)
    limitField.value = limitField.value.substring(0, limitNum);
  else limitCount.value = limitNum - limitField.value.length;
}

function colorInputValidationCheck(ob, ob_des, msg) {
  const inputElement = obj(ob);
  const descriptionElement = obj(ob_des);
  inputElement.className = "redthickborder";
  descriptionElement.innerHTML = '<span class="font_red">' + msg + "</span>";
}

function removeColorInput(ob, ob_des) {
  const inputElement = obj(ob);
  const descriptionElement = obj(ob_des);
  inputElement.className = "";
  descriptionElement.innerHTML = "";
}

function convertSpecialChars() {
  const chars = [
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
  const codes = [
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

  for (let i = 0; i < arguments.length; i++) {
    for (let x = 0; x < chars.length; x++) {
      arguments[i].value = arguments[i].value.replace(
        new RegExp(chars[x], "g"),
        codes[x],
      );
    }
  }
}

function isScrolledVisible(elem) {
  const docViewTop = jQuery(window).scrollTop();
  const elemTop = jQuery(elem).offset().top + jQuery(elem).height();
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

function tooltipsfun(sensorele, tooltipID) {
  let timer = null;
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
}

function vmoreHLnews(boxwidth, totalitems, nodata) {
  let n = jQuery(".hlitem").length,
    width = boxwidth,
    newwidth = width * n;

  jQuery("#hlstage, .hlitem").css("width", width);
  jQuery("#hlslide-holder").css({
    width: newwidth,
  });

  jQuery(".hlitem").each(function (i) {
    let thiswid = 730;
    jQuery(this).css({
      left: thiswid * i,
    });
  });

  jQuery("#hlprev").click(function () {
    let hlprev = jQuery("#hlslide-holder .active").prev();
    let curIndex =
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
    let hlnext = jQuery("#hlslide-holder .active").next();
    let curIndex =
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
    let scrollLeft = jQuery(this).scrollLeft();
    jQuery(".hlitem").each(function (i) {
      let posLeft = jQuery(this).position().left;
      let w = jQuery(this).width();

      if (scrollLeft >= posLeft && scrollLeft < posLeft + w) {
        jQuery(this).addClass("active").siblings().removeClass("active");
      }
    });
  });
}

function getHLpaging(curIndex, totalitems, totaldivitems, totaldata) {
  jQuery("a.hlnavleft").removeClass("inactiveleft");
  jQuery("a.hlnavright").removeClass("inactiveright");
  const pagingIndex =
    curIndex == totaldivitems - 1 ? totaldata : (curIndex + 1) * totalitems;
  const pagingText =
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
  let v = grecaptcha.getResponse();
  if (v.length == 0) {
    showNotification("Please Complete The Captcha", "error");
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
  let today = new Date();
  let todaydd = today.getDate();
  let todaymm = today.getMonth() + 1; //January is 0!
  let todayyyyy = today.getFullYear();
  todayyyyy = todayyyyy.toString();

  if (todaydd < 10) todaydd = pad(todaydd, 2);
  if (todaymm < 10) todaymm = pad(todaymm, 2);

  if (inDate == "") return true;
  let d = "312831303130313130313031";

  /* For invalid dates, return false */
  if (inDate.length > 0 && inDate.length < 8) return false;

  // Expected inDate format: dd.mm.yyyy
  const dd = inDate.substring(0, 2);
  const mm = inDate.substring(2, 4);
  let yy = inDate.substring(4, 8);

  /* Now, convert the string yr1 into a numeric and test for leap year.
  If it is, change the end of month day string for Feb to 29  */

  let isLeap = false;
  yy = yy * 1;
  if (yy % 400 == 0) isLeap = true;
  else if (yy % 100 == 0) isLeap = false;
  else if (yy % 4 == 0) isLeap = true;
  if (isLeap) d = d.substring(0, 2) + "29" + d.substring(4, d.length);

  /* Pick the end of month day from the d string for this month. */
  const pos = mm * 2 - 2;
  const ld = d.substring(pos, pos + 2) + 0;
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
  let key;
  let keychar;
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
  let oldonload = window.onload;
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
    let closelink =
      document.getElementById("closeme"); /* find the 'close this' link */
    closelink.onclick =
      gDestroycatfish; /* attach the destroy function to it's 'onclick' */
  }
}

function gSetCookie(cname, cvalue, exdays) {
  let d = new Date();
  d.setTime(d.getTime() + exdays * 24 * 60 * 60 * 1000);
  let expires = "expires=" + d.toUTCString();
  document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

function convertDate(date, current_Date) {
  // format: DD/MM/YYYY (Check valid date and get date)
  let valid_date = "";

  let todayDate = new Date();
  let todayDD = todayDate.getDate().toString();
  let todayMM = (todayDate.getMonth() + 1).toString();
  let todayYY = todayDate.getFullYear().toString();

  if (todayDD < 10) todayDD = pad(todayDD, 2);
  if (todayMM < 10) todayMM = pad(todayMM, 2);

  if (date) {
    let splitDate = date.split(/[-\/.]/);
    let inputDD = splitDate[0] ? splitDate[0] : "";
    inputDD = inputDD.toString();
    let inputMM = splitDate[1] ? splitDate[1] : "";
    inputMM = inputMM.toString();
    let inputYY = splitDate[2] ? splitDate[2] : "";
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

  let style = document.createElement("style");
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

  let settings = dataTableApi.settings()[0];
  if (!settings || !settings.sTableId) {
    return;
  }

  let tableId = settings.sTableId;
  let wrapperId = tableId + "_wrapper";
  let emptyStateId = tableId + "_global_no_result";
  let wrapper = document.getElementById(wrapperId);

  if (!wrapper || !wrapper.parentNode) {
    return;
  }

  let recordsTotal = 0;
  if (
    typeof dataTableApi.page === "function" &&
    typeof dataTableApi.page.info === "function"
  ) {
    let pageInfo = dataTableApi.page.info();
    if (pageInfo && typeof pageInfo.recordsTotal === "number") {
      recordsTotal = pageInfo.recordsTotal;
    }
  }

  if (recordsTotal === 0 && typeof dataTableApi.rows === "function") {
    recordsTotal = dataTableApi.rows().count();
  }

  let isEmpty = recordsTotal === 0;
  let emptyStateNode = document.getElementById(emptyStateId);

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
    let tableApi = getDataTableApiFromSettings(settings);
    if (tableApi) {
      syncGlobalDataTableEmptyState(tableApi);
    }
  });

  let existingTables = $.fn.dataTable.tables({ api: true });
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
  let titleAttr = (element.getAttribute("title") || "").trim();
  if (titleAttr !== "") {
    return titleAttr;
  }

  let textLabel = (element.textContent || "").trim();
  if (textLabel !== "") {
    return textLabel;
  }

  let icon = element.querySelector("i");
  let iconClass = icon ? icon.className : "";
  if (iconClass.indexOf("fa-eye") !== -1) return "View";
  if (iconClass.indexOf("fa-edit") !== -1 || iconClass.indexOf("fa-pen") !== -1)
    return "Edit";
  if (iconClass.indexOf("fa-trash") !== -1) return "Delete";
  if (iconClass.indexOf("fa-address-card") !== -1) return "Urbanism Member";
  if (iconClass.indexOf("fa-user-plus") !== -1) return "Register Member";
  return "Action";
}

function resolveActionType(element) {
  let icon = element.querySelector("i");
  let iconClass = icon ? icon.className : "";

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
  let node = actionNode.cloneNode(true);
  let actionLabel = resolveActionLabel(actionNode);
  let actionType = resolveActionType(actionNode);
  let iconNode = actionNode.querySelector("i");
  let iconHtml = iconNode
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
  let actionCells = document.querySelectorAll("td.btn-container");
  let isMobileView = window.matchMedia("(max-width: 768px)").matches;

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

    let tempWrapper = document.createElement("div");
    tempWrapper.innerHTML = cell.dataset.originalActionHtml;

    let actionNodes = Array.prototype.slice
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

    let wrapper = document.createElement("div");
    wrapper.className = "mobile-action-wrapper";

    let trigger = document.createElement("button");
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

    let menu = document.createElement("div");
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
  let currentHoveredNode = null;
  let hoverSyncIntervalId = null;

  let setHoveredNode = function (node) {
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

  let clearMobileHoverState = function () {
    setHoveredNode(null);
  };

  let applyMobileHoverFromPoint = function (clientX, clientY) {
    if (
      typeof clientX !== "number" ||
      typeof clientY !== "number" ||
      clientX < 0 ||
      clientY < 0
    ) {
      return;
    }

    let findHoverCandidateByRect = function () {
      let candidates = document.querySelectorAll(
        "td.btn-container.mobile-action-open .mobile-action-item, td.btn-container.mobile-action-open .mobile-action-trigger",
      );

      for (let i = 0; i < candidates.length; i++) {
        let candidate = candidates[i];
        let rect = candidate.getBoundingClientRect();
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

    let rectCandidate = findHoverCandidateByRect();
    if (rectCandidate) {
      setHoveredNode(rectCandidate);
      return;
    }

    let target = document.elementFromPoint(clientX, clientY);
    if (!target) {
      return;
    }

    let hoveredItem = target.closest(".mobile-action-item");
    if (hoveredItem) {
      setHoveredNode(hoveredItem);
      return;
    }

    let hoveredTrigger = target.closest(".mobile-action-trigger");
    if (hoveredTrigger) {
      setHoveredNode(hoveredTrigger);
      return;
    }

    setHoveredNode(null);
  };

  let applyHoverByTarget = function (target) {
    if (!target) {
      setHoveredNode(null);
      return;
    }

    let hoveredItem = target.closest(".mobile-action-item");
    if (hoveredItem) {
      setHoveredNode(hoveredItem);
      return;
    }

    let hoveredTrigger = target.closest(".mobile-action-trigger");
    if (hoveredTrigger) {
      setHoveredNode(hoveredTrigger);
      return;
    }

    setHoveredNode(null);
  };

  let isInsideMobileActionUi = function (node) {
    if (!node || !(node instanceof Element)) {
      return false;
    }
    return !!node.closest(
      ".mobile-action-trigger, .mobile-action-menu, .mobile-action-item, td.btn-container.mobile-action-open",
    );
  };

  let closeAllMenus = function () {
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

  let syncHoverFromCssState = function () {
    if (!window.matchMedia("(max-width: 768px)").matches) {
      return;
    }

    let openMenuExists = document.querySelector(
      "td.btn-container.mobile-action-open",
    );
    if (!openMenuExists) {
      return;
    }

    try {
      let hoveredChain = document.querySelectorAll(":hover");
      let hoveredTarget = hoveredChain.length
        ? hoveredChain[hoveredChain.length - 1]
        : null;

      if (!hoveredTarget) {
        setHoveredNode(null);
        return;
      }

      let actionHoverTarget = hoveredTarget.closest(
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
    let trigger = event.target.closest(".mobile-action-trigger");
    let insideMenu = event.target.closest(".mobile-action-menu");

    if (trigger) {
      let actionCell = trigger.closest("td.btn-container");
      let shouldOpen =
        actionCell && !actionCell.classList.contains("mobile-action-open");
      closeAllMenus();
      if (actionCell && shouldOpen) {
        let menu = actionCell.querySelector(".mobile-action-menu");
        let menuWidth = menu ? Math.max(menu.offsetWidth || 0, 160) : 160;
        let triggerRect = trigger.getBoundingClientRect();
        let spaceRight = window.innerWidth - triggerRect.right;

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
    let toNode = event && event.relatedTarget ? event.relatedTarget : null;
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
    let toNode = event && event.relatedTarget ? event.relatedTarget : null;
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

function updateCheckboxesOnOtherPages(isChecked, tableId) {
  if (typeof window.jQuery === "undefined") {
    return;
  }

  let $ = window.jQuery;
  let checkedValue = !!isChecked;

  function updateExportCheckboxesInTable(tableElement) {
    if (!tableElement) {
      return false;
    }

    let $table = $(tableElement);

    try {
      if ($.fn && $.fn.DataTable && $.fn.DataTable.isDataTable(tableElement)) {
        let cells = $table.DataTable().cells().nodes();
        $(cells).find(".export").prop("checked", checkedValue);
        return true;
      }
    } catch (error) {
      // Fallback to normal DOM checkboxes below.
    }

    $table.find(".export").prop("checked", checkedValue);
    return true;
  }

  if (typeof tableId === "string" && tableId.trim() !== "") {
    let normalizedTableId = tableId.trim();

    if (normalizedTableId.charAt(0) !== "#") {
      normalizedTableId = "#" + normalizedTableId;
    }

    updateExportCheckboxesInTable($(normalizedTableId).get(0));
    return;
  }

  let updated = false;

  $("table").each(function () {
    if ($(this).find(".export").length === 0) {
      return;
    }

    if (updateExportCheckboxesInTable(this)) {
      updated = true;
    }
  });

  if (!updated) {
    $(".export").prop("checked", checkedValue);
  }
}

function toggleFilters(sectionId) {
  let section = document.getElementById(sectionId);

  if (!section) {
    return;
  }

  section.style.display = section.style.display === "none" ? "flex" : "none";
}

function autoToggleSections(config) {
  config = config || {};

  let urlParams = new URLSearchParams(window.location.search);
  let filterFields = config.filterFields || ["month", "status", "brand", "pkg", "acc"];
  let groupFields = config.groupFields || ["month_gb", "status_gb", "brand_gb", "pkg_gb", "acc_gb"];
  let filterSectionId = config.filterSectionId || "filterSection";
  let groupBySectionId = config.groupBySectionId || "groupBySection";

  let filterActive = filterFields.some(function (key) {
    let value = urlParams.get(key);
    return value && value !== "" && value !== "All";
  });

  let groupActive = groupFields.some(function (key) {
    let value = urlParams.get(key);
    return value && value !== "";
  });

  let filterSection = document.getElementById(filterSectionId);
  let groupBySection = document.getElementById(groupBySectionId);

  if (filterActive && filterSection) {
    filterSection.style.display = "flex";
  }

  if (groupActive && groupBySection) {
    groupBySection.style.display = "flex";
  }
}

function applyFilterOrGroup(param, element) {
  if (!param || !element) {
    return;
  }

  let value = element.value;
  let url = new URL(window.location.href);
  url.searchParams.set(param, value);
  window.location.href = url.toString();
}

function activatePlatformTab(platformKey, hiddenInputs) {
  document.querySelectorAll("[data-platform-tab]").forEach(function (button) {
    button.classList.toggle(
      "is-active",
      button.getAttribute("data-platform-tab") === platformKey,
    );
  });

  document.querySelectorAll("[data-platform-panel]").forEach(function (panel) {
    panel.classList.toggle(
      "is-active",
      panel.getAttribute("data-platform-panel") === platformKey,
    );
  });

  let inputs = [];

  if (hiddenInputs) {
    if (
      typeof NodeList !== "undefined" &&
      hiddenInputs instanceof NodeList
    ) {
      inputs = Array.prototype.slice.call(hiddenInputs);
    } else if (Array.isArray(hiddenInputs)) {
      inputs = hiddenInputs;
    } else {
      inputs = [hiddenInputs];
    }
  } else {
    if (typeof hiddenPlatformInput !== "undefined" && hiddenPlatformInput) {
      inputs.push(hiddenPlatformInput);
    }

    if (typeof hiddenPlatformInputs !== "undefined" && hiddenPlatformInputs) {
      inputs = inputs.concat(Array.prototype.slice.call(hiddenPlatformInputs));
    }

    inputs = inputs.concat(Array.prototype.slice.call(
      document.querySelectorAll("[data-platform-hidden-input]"),
    ));
  }

  inputs.forEach(function (input) {
    if (input) {
      input.value = platformKey;
    }
  });
}

function getValidDataTableRowCount(tableElement) {
  if (!tableElement) {
    return 0;
  }

  let headerCount = tableElement.querySelectorAll("thead th").length;
  let validRows = 0;

  tableElement.querySelectorAll("tbody tr").forEach(function (rowElement) {
    let cellCount = rowElement.querySelectorAll("td, th").length;
    let hasColspan = rowElement.querySelector("[colspan]");

    if (!hasColspan && cellCount === headerCount) {
      validRows += 1;
    }
  });

  return validRows;
}

function clearNewCustomerInlineError(field) {
  if (!field) {
    return;
  }

  field.classList.remove("shopee-inline-invalid");

  if (
    field.nextElementSibling &&
    field.nextElementSibling.classList.contains("shopee-inline-error")
  ) {
    field.nextElementSibling.remove();
  }

  let wrapper = field.parentElement;
  if (!wrapper) {
    return;
  }

  wrapper.querySelectorAll(".shopee-inline-error").forEach(function (node) {
    node.remove();
  });
}

function showNewCustomerInlineError(field, message) {
  if (!field) {
    return;
  }

  clearNewCustomerInlineError(field);

  field.classList.add("shopee-inline-invalid");

  let errorNode = document.createElement("span");
  errorNode.className = "shopee-inline-error";
  errorNode.textContent = message;

  field.insertAdjacentElement("afterend", errorNode);
}

function togglePassword(inputId) {
  let input = document.getElementById(inputId);
  let icon = document.getElementById(
    "show" + inputId.charAt(0).toUpperCase() + inputId.slice(1),
  );

  if (!input || !icon) {
    return;
  }

  if (input.getAttribute("type") === "password") {
    input.setAttribute("type", "text");
    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");
    return;
  }

  input.setAttribute("type", "password");
  icon.classList.remove("fa-eye");
  icon.classList.add("fa-eye-slash");
}

function setStatus(message, isError, targetStatusNode) {
  let statusElement = targetStatusNode || null;

  if (!statusElement && typeof statusNode !== "undefined") {
    statusElement = statusNode;
  }

  if (!statusElement) {
    return;
  }

  statusElement.textContent = message;
  statusElement.classList.toggle("text-danger", !!isError);
  statusElement.classList.toggle("text-muted", !isError);
}

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
    orderFixed: options.orderFixed || null,
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
  let values = [];
  let uniqueMap = {};
  let sourceValue = String(rawValue == null ? "" : rawValue);

  sourceValue.split("||").forEach(function (value) {
    let trimmedValue = String(value == null ? "" : value)
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
    let rowApi = tableApi.row(dataIndex);
    if (rowApi && typeof rowApi.node === "function") {
      return rowApi.node();
    }
  }

  return null;
}

function getCustomerRecordFilterSourceRows(tableElement, tableApi) {
  let rowNodes = [];

  if (tableApi && typeof tableApi.rows === "function") {
    let apiNodes = tableApi.rows().nodes();

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
    let rawValue = localStorage.getItem(storageKey);
    if (!rawValue) {
      return {};
    }

    let parsedValue = JSON.parse(rawValue);
    return parsedValue && typeof parsedValue === "object" ? parsedValue : {};
  } catch (error) {
    return {};
  }
}

function normalizeCustomerRecordFilterValuesList(values) {
  let normalizedValues = [];
  let uniqueMap = {};

  (Array.isArray(values) ? values : [values]).forEach(function (value) {
    let trimmedValue = String(value == null ? "" : value)
      .replace(/\s+/g, " ")
      .trim();

    if (trimmedValue === "" || uniqueMap[trimmedValue]) {
      return;
    }

    uniqueMap[trimmedValue] = true;
    normalizedValues.push(trimmedValue);
  });

  return normalizedValues;
}

function normalizeCustomerRecordFieldStoredValue(field, rawValue) {
  if (field && field.multiple) {
    if (Array.isArray(rawValue)) {
      return normalizeCustomerRecordFilterValuesList(rawValue);
    }

    return normalizeCustomerRecordFilterValuesList(
      splitCustomerRecordFilterValues(rawValue),
    );
  }

  return String(rawValue == null ? "" : rawValue)
    .replace(/\s+/g, " ")
    .trim();
}

function cloneCustomerRecordFilterState(fields, rawState) {
  let normalizedState = {};
  let sourceState =
    rawState && typeof rawState === "object" ? rawState : {};

  fields.forEach(function (field) {
    normalizedState[field.key] = normalizeCustomerRecordFieldStoredValue(
      field,
      sourceState[field.key],
    );
  });

  return normalizedState;
}

function customerRecordFilterStateHasValues(fields, filterState) {
  return fields.some(function (field) {
    let fieldValue = filterState ? filterState[field.key] : "";

    if (field.multiple) {
      return Array.isArray(fieldValue) && fieldValue.length > 0;
    }

    return normalizeCustomerRecordFilterValue(fieldValue) !== "";
  });
}

function getCustomerRecordSelectedNormalizedValues(selectedValues) {
  return normalizeCustomerRecordFilterValuesList(selectedValues).map(function (
    value,
  ) {
    return normalizeCustomerRecordFilterValue(value);
  });
}

function customerRecordRowMatchesSelectedValues(rowNode, attrName, selectedValues) {
  let normalizedSelections =
    getCustomerRecordSelectedNormalizedValues(selectedValues);

  if (!normalizedSelections.length) {
    return true;
  }

  let rowValues = getCustomerRecordRowFilterNormalizedValues(rowNode, attrName);
  return normalizedSelections.some(function (selectedValue) {
    return rowValues.indexOf(selectedValue) !== -1;
  });
}

function normalizeCustomerRecordFilterPath(path) {
  return String(path == null ? "" : path)
    .split("?")[0]
    .replace(/\\/g, "/")
    .replace(/\/+/g, "/")
    .replace(/\/$/, "")
    .toLowerCase();
}

function customerRecordFilterPathMatches(expectedPath, actualPath) {
  let normalizedExpected = normalizeCustomerRecordFilterPath(expectedPath);
  let normalizedActual = normalizeCustomerRecordFilterPath(actualPath);

  if (!normalizedExpected || !normalizedActual) {
    return false;
  }

  if (normalizedExpected === normalizedActual) {
    return true;
  }

  if (normalizedActual.slice(-normalizedExpected.length) !== normalizedExpected) {
    return false;
  }

  let boundaryIndex = normalizedActual.length - normalizedExpected.length - 1;
  return boundaryIndex < 0 || normalizedActual.charAt(boundaryIndex) === "/";
}

function getCustomerRecordReferrerPath() {
  if (!document.referrer) {
    return "";
  }

  try {
    let parser = document.createElement("a");
    parser.href = document.referrer;
    return normalizeCustomerRecordFilterPath(parser.pathname || "");
  } catch (error) {
    return "";
  }
}

function shouldResetCustomerRecordFilterState(config) {
  let scopePaths = Array.isArray(config && config.scopePaths)
    ? config.scopePaths
    : [];

  if (!scopePaths.length) {
    return false;
  }

  let referrerPath = getCustomerRecordReferrerPath();
  if (!referrerPath) {
    return false;
  }

  let allowedPaths = scopePaths
    .map(normalizeCustomerRecordFilterPath)
    .filter(function (path) {
      return path !== "";
    });
  let currentPath = normalizeCustomerRecordFilterPath(window.location.pathname);

  if (
    currentPath !== "" &&
    !allowedPaths.some(function (path) {
      return customerRecordFilterPathMatches(path, currentPath);
    })
  ) {
    allowedPaths.push(currentPath);
  }

  return !allowedPaths.some(function (path) {
    return customerRecordFilterPathMatches(path, referrerPath);
  });
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

  $(document)
    .off("click.customerTagActivity", ".customer-tag-assignment-view-more")
    .on("click.customerTagActivity", ".customer-tag-assignment-view-more", function () {
      let button = $(this);
      let activityList = button.closest(".customer-tag-assignment-activity-list");
      let isExpanded = button.attr("data-expanded") === "1";
      let hiddenCount = button.attr("data-hidden-count") || "0";

      activityList.find(".customer-tag-assignment-activity-extra").toggle(!isExpanded);
      button.attr("data-expanded", isExpanded ? "0" : "1");
      button.text(isExpanded ? "Show More (" + hiddenCount + ")" : "Show Less");
    });

  let tableElement = document.getElementById(config.tableId);
  if (!tableElement || !$.fn.DataTable.isDataTable(tableElement)) {
    return null;
  }

  let $tableElement = $(tableElement);
  let tableApi = $tableElement.DataTable();
  let wrapper = $("#" + config.tableId + "_wrapper");

  if (!wrapper.length) {
    wrapper = $tableElement.closest(".dataTables_wrapper, .dt-container");
  }

  if (!wrapper.length) {
    return null;
  }

  let fields = Array.isArray(config.filters) ? config.filters : [];
  if (!fields.length) {
    return null;
  }

  fields = fields.map(function (field) {
    let normalizedField = field || {};
    if (
      normalizedField.type === "select" &&
      config.selectFieldsMultiple === true &&
      normalizedField.multiple !== false
    ) {
      normalizedField.multiple = true;
    }

    return normalizedField;
  });

  let storageKey = config.storageKey || "";
  let panelStorageKey = config.panelStorageKey || "";
  let deferredApply = config.deferApply === true;

  if (shouldResetCustomerRecordFilterState(config)) {
    if (storageKey) {
      localStorage.removeItem(storageKey);
    }
    if (panelStorageKey) {
      localStorage.removeItem(panelStorageKey);
    }
  }

  let activeFilters = cloneCustomerRecordFilterState(
    fields,
    readCustomerRecordFilterStorage(storageKey),
  );
  let pendingFilters = cloneCustomerRecordFilterState(fields, activeFilters);
  let urlFilterParams = config.urlFilterParams && typeof config.urlFilterParams === "object"
    ? config.urlFilterParams
    : {};
  let currentUrlParams = new URLSearchParams(window.location.search);

  fields.forEach(function (field) {
    let urlParamName = urlFilterParams[field.key];
    if (!urlParamName || !currentUrlParams.has(urlParamName)) {
      return;
    }

    let urlValues = currentUrlParams.getAll(urlParamName);
    if (!urlValues.length) {
      return;
    }

    let urlFilterValue = field.multiple
      ? normalizeCustomerRecordFilterValuesList(urlValues)
      : String(urlValues[0] == null ? "" : urlValues[0]).trim();
    activeFilters[field.key] = urlFilterValue;
    pendingFilters[field.key] = urlFilterValue;
  });
  let tableSearchFn = tableElement.__customerRecordFilterSearch || null;

  if (!tableSearchFn) {
    tableSearchFn = function (settings, rowData, dataIndex) {
      if (!settings || settings.nTable !== tableElement) {
        return true;
      }

      let rowNode = getCustomerRecordFilterRowNode(settings, dataIndex, tableApi);
      if (!rowNode) {
        return true;
      }

      for (let i = 0; i < fields.length; i++) {
        let field = fields[i];
        let rawFilterValue = activeFilters[field.key];

        if (field.multiple) {
          if (
            !customerRecordRowMatchesSelectedValues(
              rowNode,
              field.attr,
              rawFilterValue,
            )
          ) {
            return false;
          }

          continue;
        }

        let filterValue = normalizeCustomerRecordFilterValue(rawFilterValue);

        if (!filterValue) {
          continue;
        }

        let rowValues = getCustomerRecordRowFilterNormalizedValues(rowNode, field.attr);

        if (field.type === "text") {
          let textMatched = rowValues.some(function (rowValue) {
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

  let toolbarRow = wrapper.parent().find(
    ".customer-record-filter-toolbar-row[data-table-id='" + config.tableId + "']",
  );
  if (!toolbarRow.length) {
    toolbarRow = $('<div class="col-md-12 mb-3 customer-record-filter-toolbar-row"></div>');
    toolbarRow.attr("data-table-id", config.tableId);
    wrapper.before(toolbarRow);
  }

  let toolbar = toolbarRow.find(".customer-record-filter-toolbar");
  if (!toolbar.length) {
    toolbar = $('<div class="customer-record-filter-toolbar"></div>');
    toolbar.attr("data-table-id", config.tableId);
    toolbarRow.append(toolbar);
  }

  let filterButton = toolbar.find(".customer-record-filter-toggle");
  if (!filterButton.length) {
    filterButton = $(
      '<button type="button" class="btn btn-info customer-record-filter-toggle">Show/Hide Filters</button>',
    );
    toolbar.append(filterButton);
  }

  let panel = wrapper.find(
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

  let fieldNodes = {};
  let searchButton = null;

  let updateDropdownButtonLabel = function (field) {
    if (!field || !field.multiple || !fieldNodes[field.key]) {
      return;
    }

    let fieldNode = fieldNodes[field.key];
    let selectedValues = normalizeCustomerRecordFilterValuesList(
      fieldNode.inputs
        .filter(":checked")
        .map(function () {
          return $(this).val();
        })
        .get(),
    );
    selectedValues = selectedValues.filter(function (value) {
      return value !== "";
    });

    if (!selectedValues.length) {
      fieldNode.button.text(field.placeholder || "All");
    } else if (selectedValues.length === 1) {
      fieldNode.button.text(selectedValues[0]);
    } else {
      fieldNode.button.text(selectedValues.length + " selected");
    }
  };

  let getFieldNodeValue = function (field) {
    let fieldNode = fieldNodes[field.key];
    if (!fieldNode) {
      return field.multiple ? [] : "";
    }

    if (field.multiple) {
      return normalizeCustomerRecordFilterValuesList(
        fieldNode.inputs
          .filter(":checked")
          .map(function () {
            return $(this).val();
          })
          .get(),
      ).filter(function (value) {
        return value !== "";
      });
    }

    return String(fieldNode.input.val() == null ? "" : fieldNode.input.val());
  };

  let syncMultiSelectPlaceholderState = function (field) {
    if (!field || !field.multiple || !fieldNodes[field.key]) {
      return;
    }

    let fieldNode = fieldNodes[field.key];
    let selectedValues = normalizeCustomerRecordFilterValuesList(
      fieldNode.inputs
        .filter(":checked")
        .map(function () {
          return $(this).val();
        })
        .get(),
    ).filter(function (value) {
      return value !== "";
    });

    fieldNode.inputs.each(function () {
      let input = $(this);
      if (String(input.val()) === "") {
        input.prop("checked", selectedValues.length === 0);
      }
    });
  };

  let setFieldNodeValue = function (field, value) {
    let fieldNode = fieldNodes[field.key];
    if (!fieldNode) {
      return;
    }

    if (field.multiple) {
      let selectedValues = normalizeCustomerRecordFilterValuesList(value);
      let selectedMap = {};
      selectedValues.forEach(function (selectedValue) {
        selectedMap[selectedValue] = true;
      });

      fieldNode.inputs.each(function () {
        let input = $(this);
        input.prop("checked", !!selectedMap[String(input.val()).trim()]);
      });

      syncMultiSelectPlaceholderState(field);
      updateDropdownButtonLabel(field);
      return;
    }

    fieldNode.input.val(value || "");
  };

  let getCurrentFieldValues = function () {
    let values = {};

    fields.forEach(function (field) {
      values[field.key] = getFieldNodeValue(field);
    });

    return values;
  };

  let saveActiveFilters = function () {
    if (!storageKey) {
      return;
    }

    if (customerRecordFilterStateHasValues(fields, activeFilters)) {
      localStorage.setItem(storageKey, JSON.stringify(activeFilters));
    } else {
      localStorage.removeItem(storageKey);
    }
  };

  let setPanelOpenState = function (shouldOpen) {
    panel.toggleClass("is-open", shouldOpen);
    panel.css("display", shouldOpen ? "flex" : "none");
    filterButton.toggleClass("active", shouldOpen);

    if (panelStorageKey) {
      localStorage.setItem(panelStorageKey, shouldOpen ? "1" : "0");
    }
  };

  let applyFilters = function () {
    activeFilters = cloneCustomerRecordFilterState(fields, getCurrentFieldValues());
    pendingFilters = cloneCustomerRecordFilterState(fields, activeFilters);
    saveActiveFilters();
    setPanelOpenState(true);
    tableApi.draw(false);
  };

  let syncPendingFiltersFromInputs = function () {
    pendingFilters = cloneCustomerRecordFilterState(fields, getCurrentFieldValues());
  };

  let handleFieldValueChange = function (field) {
    if (field.multiple) {
      updateDropdownButtonLabel(field);
    }

    if (deferredApply) {
      syncPendingFiltersFromInputs();
      return;
    }

    applyFilters();
  };

  fields.forEach(function (field) {
    let columnClass = field.columnClass || "col-md-3 mb-3";
    let fieldWrap = $('<div class="' + columnClass + '"></div>');
    let fieldId = config.tableId + "_" + field.key;
    let labelNode;

    if (field.type === "text") {
      labelNode = $(
        '<label class="form-label customer-record-filter-label" for="' +
          fieldId +
          '">' +
          "Filter by " +
          field.label +
          "</label>",
      );

      let inputNode = $(
        '<input type="text" class="form-control customer-record-filter-input" id="' +
          fieldId +
          '" placeholder="' +
          (field.placeholder || "") +
          '">',
      );

      fieldWrap.append(labelNode);
      fieldWrap.append(inputNode);
      panel.append(fieldWrap);

      fieldNodes[field.key] = {
        input: inputNode,
      };

      setFieldNodeValue(field, pendingFilters[field.key]);
      inputNode
        .off("input.customerRecordFilter change.customerRecordFilter")
        .on("input.customerRecordFilter change.customerRecordFilter", function () {
          handleFieldValueChange(field);
        });
      return;
    }

    let optionValues = {};
    getCustomerRecordFilterSourceRows(tableElement, tableApi).forEach(function (rowNode) {
      getCustomerRecordRowFilterValues(rowNode, field.attr).forEach(function (value) {
        optionValues[value] = true;
      });
    });

    let sortedOptionValues = normalizeCustomerRecordFilterValuesList(
      Object.keys(optionValues),
    ).sort(function (leftValue, rightValue) {
      return leftValue.localeCompare(rightValue);
    });

    if (field.multiple) {
      labelNode = $(
        '<label class="form-label customer-record-filter-label" for="' +
          fieldId +
          '">' +
          "Filter by " +
          field.label +
          "</label>",
      );

      let dropdownWrap = $(
        '<div class="dropdown customer-record-filter-dropdown"></div>',
      );
      let dropdownButton = $(
        '<button class="customer-record-filter-dropdown-toggle" type="button" id="' +
          fieldId +
          '" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"></button>',
      );
      let dropdownMenu = $(
        '<div class="dropdown-menu" aria-labelledby="' + fieldId + '"></div>',
      );

      let checkboxNodes = $();
      let placeholderCheckboxId = fieldId + "_all";
      let placeholderCheckboxWrap = $('<div class="form-check"></div>');
      let placeholderCheckboxNode = $(
        '<input class="form-check-input customer-record-filter-checkbox" type="checkbox" value="" id="' +
          placeholderCheckboxId +
          '" data-filter-key="' +
          field.key +
          '" data-placeholder="' +
          $("<div></div>").text(field.placeholder || "All").html() +
          '">',
      );
      let placeholderCheckboxLabel = $(
        '<label class="form-check-label" for="' +
          placeholderCheckboxId +
          '">' +
          $("<div></div>").text(field.placeholder || "All").html() +
          "</label>",
      );

      placeholderCheckboxWrap.append(placeholderCheckboxNode);
      placeholderCheckboxWrap.append(placeholderCheckboxLabel);
      dropdownMenu.append(placeholderCheckboxWrap);
      checkboxNodes = checkboxNodes.add(placeholderCheckboxNode);

      sortedOptionValues.forEach(function (value, index) {
        let checkboxId = fieldId + "_" + index;
        let checkboxWrap = $('<div class="form-check"></div>');
        let checkboxNode = $(
          '<input class="form-check-input customer-record-filter-checkbox" type="checkbox" value="' +
            $("<div></div>").text(value).html() +
            '" id="' +
            checkboxId +
            '" data-filter-key="' +
            field.key +
            '" data-placeholder="' +
            $("<div></div>").text(field.placeholder || "All").html() +
            '">',
        );
        let checkboxLabel = $(
          '<label class="form-check-label" for="' +
            checkboxId +
            '">' +
            $("<div></div>").text(value).html() +
            "</label>",
        );

        checkboxWrap.append(checkboxNode);
        checkboxWrap.append(checkboxLabel);
        dropdownMenu.append(checkboxWrap);
        checkboxNodes = checkboxNodes.add(checkboxNode);
      });

      dropdownWrap.append(dropdownButton);
      dropdownWrap.append(dropdownMenu);
      fieldWrap.append(labelNode);
      fieldWrap.append(dropdownWrap);
      panel.append(fieldWrap);

      fieldNodes[field.key] = {
        inputs: checkboxNodes,
        button: dropdownButton,
      };

      setFieldNodeValue(field, pendingFilters[field.key]);
      checkboxNodes
        .off("change.customerRecordFilter")
        .on("change.customerRecordFilter", function () {
          let currentCheckbox = $(this);
          let currentValue = String(currentCheckbox.val()).trim();

          if (currentValue === "" && currentCheckbox.is(":checked")) {
            checkboxNodes.each(function () {
              let checkbox = $(this);
              if (checkbox.get(0) !== currentCheckbox.get(0)) {
                checkbox.prop("checked", false);
              }
            });
          } else if (currentValue !== "" && currentCheckbox.is(":checked")) {
            checkboxNodes.each(function () {
              let checkbox = $(this);
              if (String(checkbox.val()).trim() === "") {
                checkbox.prop("checked", false);
              }
            });
          }

          syncMultiSelectPlaceholderState(field);
          handleFieldValueChange(field);
        });

      return;
    }

    labelNode = $(
      '<label class="form-label customer-record-filter-label" for="' +
        fieldId +
        '">' +
        "Filter by " +
        field.label +
        "</label>",
    );

    let selectNode = $(
      '<select class="form-select customer-record-filter-input" id="' +
        fieldId +
        '"></select>',
    );

    selectNode.append(
      $("<option></option>")
        .attr("value", "")
        .text(field.placeholder || "All"),
    );

    sortedOptionValues.forEach(function (value) {
      selectNode.append(
        $("<option></option>")
          .attr("value", value)
          .text(value),
      );
    });

    fieldWrap.append(labelNode);
    fieldWrap.append(selectNode);
    panel.append(fieldWrap);

    fieldNodes[field.key] = {
      input: selectNode,
    };

    setFieldNodeValue(field, pendingFilters[field.key]);
    selectNode
      .off("change.customerRecordFilter")
      .on("change.customerRecordFilter", function () {
        handleFieldValueChange(field);
      });
  });

  if (deferredApply) {
    let actionWrap = $(
      '<div class="col-md-3 mb-3 d-flex align-items-end customer-record-filter-action-wrap"></div>',
    );
    searchButton = $(
      '<button class="btn btn-outline-primary filter-reset me-2 customer-record-filter-search" type="button">Search</button>',
    );
    let resetButton = $(
      '<button class="btn btn-outline-danger filter-reset customer-record-filter-reset" type="button">Reset</button>',
    );

    actionWrap.append(searchButton);
    actionWrap.append(resetButton);
    panel.append(actionWrap);
  } else {
    let resetWrap = $('<div class="col-md-2 mb-3"></div>');
    resetWrap.append('<label class="form-label d-block invisible">Reset</label>');
    let resetButton = $(
      '<a href="#" class="btn btn-outline-danger filter-reset customer-record-filter-reset">Reset</a>',
    );
    resetWrap.append(resetButton);
    panel.append(resetWrap);
  }

  let resetFilters = function () {
    activeFilters = cloneCustomerRecordFilterState(fields, {});
    pendingFilters = cloneCustomerRecordFilterState(fields, {});

    fields.forEach(function (field) {
      setFieldNodeValue(field, pendingFilters[field.key]);
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

  if (searchButton) {
    searchButton
      .off("click.customerRecordFilter")
      .on("click.customerRecordFilter", function () {
        applyFilters();
      });
  }

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

  fields.forEach(function (field) {
    if (field.multiple) {
      updateDropdownButtonLabel(field);
    }
  });

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
  let one = document.getElementById(id);
  let two = document.getElementById(id2);

  if (!one || !two) {
    return;
  }

  let style = window.getComputedStyle(one);
  let width = style.getPropertyValue("width");
  two.style.width = width;
}

function datatableAlignment(elementID) {
  $(window).on("load resize", () => {
    let lengthElement = $("#" + elementID + "_length");
    let filterElement = $("#" + elementID + "_filter");
    let tableElement = $("#" + elementID);
    let tableParentElement = tableElement.parent();
    let infoElement = $("#" + elementID + "_paginate");
    let paginateElement = $("#" + elementID + "_paginate");

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
    let tableElement = $("#" + elementID);
    if (!tableElement.length) {
      return;
    }

    let wrapperElement = tableElement.closest(
      ".dataTables_wrapper, .dt-container, #" + elementID + "_wrapper",
    );
    if (!wrapperElement.length) {
      wrapperElement = $("#" + elementID + "_wrapper");
    }

    if (!wrapperElement.length) {
      return;
    }

    let scrollWrap = tableElement.parent(".datatable-scroll-wrap");
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

    let outerResponsiveWrap = wrapperElement.parent(".table-responsive");
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

function centerAlignment(elementID, options) {
  options = options || {};
  let disableAbsoluteCentering = options.disableAbsoluteCentering === true;

  $(window).on("load resize", () => {
    let form = $("#" + elementID);

    if (
      disableAbsoluteCentering ||
      window.matchMedia("(max-height: 1250px)").matches
    ) {
      if (form.hasClass("centered")) form.removeClass("centered");

      form.css({
        position: "static",
        top: "auto",
        left: "auto",
        transform: "none",
        overflow: disableAbsoluteCentering ? "visible" : "auto",
      });
    } else {
      form.addClass("centered");

      form.css({
        position: "",
        top: "",
        left: "",
        transform: "",
        overflow: "visible",
      });
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
    let reader = new FileReader();

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

  function extractNotificationResponseMeta(responsePayload) {
    let meta = {
      message: "",
      type: "info",
      redirectUrl: "",
      useReplace: false,
      reload: false,
    };

    if (responsePayload == null) {
      return meta;
    }

    if (typeof responsePayload === "object") {
      if (typeof responsePayload.message === "string") {
        meta.message = responsePayload.message;
      }
      if (typeof responsePayload.type === "string") {
        meta.type = responsePayload.type;
      }
      if (typeof responsePayload.redirectUrl === "string") {
        meta.redirectUrl = responsePayload.redirectUrl;
      }
      if (typeof responsePayload.redirect_url === "string") {
        meta.redirectUrl = responsePayload.redirect_url;
      }
      meta.useReplace =
        responsePayload.useReplace === true || responsePayload.use_replace === true;
      meta.reload =
        responsePayload.reload === true || responsePayload.shouldReload === true;
      return meta;
    }

    let responseText = String(responsePayload).trim();
    if (!responseText) {
      return meta;
    }

    let messageMatch = responseText.match(/var message=(.*?);var type=/s);
    let typeMatch = responseText.match(/var type=(.*?);var redirectUrl=/s);
    let redirectMatch = responseText.match(/var redirectUrl=(.*?);var delayMs=/s);
    let useReplaceMatch = responseText.match(/var useReplace=(true|false);/);
    let reloadMatch = responseText.match(/var shouldReload=(true|false);/);

    try {
      if (messageMatch && messageMatch[1]) {
        meta.message = JSON.parse(messageMatch[1]);
      }
    } catch (error) {}

    try {
      if (typeMatch && typeMatch[1]) {
        meta.type = JSON.parse(typeMatch[1]);
      }
    } catch (error) {}

    try {
      if (redirectMatch && redirectMatch[1]) {
        meta.redirectUrl = JSON.parse(redirectMatch[1]);
      }
    } catch (error) {}

    if (useReplaceMatch && useReplaceMatch[1]) {
      meta.useReplace = useReplaceMatch[1] === "true";
    }

    if (reloadMatch && reloadMatch[1]) {
      meta.reload = reloadMatch[1] === "true";
    }

    return meta;
  }

  let title = "";
  let title2 = "";
  let btn = "";

  switch (act) {
    case "I":
      title = "Successful Insert " + pagename;
      title2 = "Are you sure want to insert?";
      btn = "Insert";
      break;
    case "E":
      title = "Successful Edit " + pagename;
      title2 = "Are you sure want to edit?";
      btn = "Edit";
      break;
    case "D":
      title = "Successful Delete " + pagename;
      title2 = "Are You Sure Want To Delete This " + pagename + " ?";
      btn = "Delete";
      break;
    case "F":
      title = "Error Occurred,Please Try Again Later";
      break;
    case "MO":
      title = msg + " Successful Place";
      break;
    case "ErrMO":
      title = msg;
      break;
    case "CONFIRM":
      title = "Confirm Action";
      title2 = "Are You Sure Want To " + pagename + " ?";
      btn = "Confirm";
      break;
    case "NC":
      title = "No changes were made.";
      break;
    case "PC":
      title = "Successful Change " + pagename;
      break;
    case "LA":
    case "LD":
    case "LC":
      let action;
      if (act === "LA") {
        action = "approval";
      } else if (act === "LD") {
        action = "declined";
      } else if (act === "LC") {
        action = "Cancel";
      }

      title = `Leave transaction ${action}`;
      title2 = `<span style="color:#FF9B44" class="mdi mdi-alert-circle-outline"></span> Confirm Action`;
      msg = [
        `This leave transaction cannot modify once it has been ${action}. Do you still want to proceed ?`,
      ];
      btn = "Confirm";
      break;
    default:
      title = "Error";
  }

  if (act !== "ErrMO" && act !== "CONFIRM") {
    clearLocalStoragePreservingCustomerRecordFilters();
  }

  let message = "";
  let messageItems = [];
  if (Array.isArray(msg)) {
    messageItems = msg;
  } else if (typeof msg === "string" && msg.trim() !== "" && act !== "ErrMO") {
    messageItems = [msg];
  }

  if (messageItems.length >= 1) {
    for (let i = 0; i < messageItems.length; i++)
      message += `<p class="mt-n3" style="text-align:center; font-weight:bold;">${messageItems[i]}</p>`;
  }

  let firstContent =
    act == "D" || act == "LD" || act == "LA" || act == "LC" || act == "CONFIRM" ? title2 : title;

  const modalElem = document.createElement("div");
  modalElem.id = "modal-confirm-action";
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
  modelResult.id = "modal-confirm-result";
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
  if (act == "CONFIRM") {
    document.body.appendChild(modalElem);
    const myModal = new bootstrap.Modal(modalElem, {
      keyboard: false,
      backdrop: "static",
    });
    myModal.show();

    return new Promise((resolve) => {
      document.body.addEventListener("click", response);

      function response(e) {
        let confirmed = false;
        if (e.target.id == "rejectBtn") confirmed = false;
        else if (e.target.id == "acceptBtn") confirmed = true;
        else return;

        document.body.removeEventListener("click", response);
        cleanupConfirmationModal(myModal, modalElem);
        resolve(confirmed);
      }
    });
  }

  if (act == "D" || act == "LD" || act == "LA" || act == "LC") {
    document.body.appendChild(modalElem);
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
          let responseMeta = extractNotificationResponseMeta(result);
          let responseMessageText = String(
            responseMeta.message == null ? "" : responseMeta.message
          ).trim();
          let resultTitle = title;

          if (
            responseMessageText &&
            !/deleted successfully/i.test(responseMessageText) &&
            !/order deleted successfully/i.test(responseMessageText)
          ) {
            resultTitle = responseMessageText;
          }

          document.body.appendChild(modelResult);
          let resultTitleNode = modelResult.querySelector("p");
          if (resultTitleNode) {
            resultTitleNode.textContent = resultTitle;
          }
          const myModal2 = new bootstrap.Modal(modelResult, {
            keyboard: false,
            backdrop: "static",
          });
          myModal2.show();

          return new Promise((resolve, reject) => {
            document.body.addEventListener("click", response);

            let myTimeout = setTimeout(() => {
              document.body.removeEventListener("click", response);
              cleanupConfirmationModal(myModal2, modelResult);
              resolve(true);
              if (responseMeta.reload) {
                location.reload();
              } else if (responseMeta.redirectUrl) {
                if (responseMeta.useReplace) {
                  window.location.replace(responseMeta.redirectUrl);
                } else {
                  window.location.href = responseMeta.redirectUrl;
                }
              } else if (pathreturn) {
                window.location.href = pathreturn;
              } else {
                location.reload();
              }
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
              if (responseMeta.reload) {
                location.reload();
              } else if (responseMeta.redirectUrl) {
                if (responseMeta.useReplace) {
                  window.location.replace(responseMeta.redirectUrl);
                } else {
                  window.location.href = responseMeta.redirectUrl;
                }
              } else if (pathreturn) {
                window.location.href = pathreturn;
              } else {
                location.reload();
              }
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
    document.body.appendChild(modelResult);
    const myModal2 = new bootstrap.Modal(modelResult, {
      keyboard: false,
      backdrop: "static",
    });
    myModal2.show();

    return new Promise((resolve, reject) => {
      document.body.addEventListener("click", response);

      let shouldAutoClose = !(act == "ErrMO" && !pathreturn);
      let myTimeout = shouldAutoClose
        ? setTimeout(() => {
            document.body.removeEventListener("click", response);
            cleanupConfirmationModal(myModal2, modelResult);
            resolve(true);
            if (pathreturn) {
              window.location.href = pathreturn;
            }
          }, 5000)
        : null;

      function response(e) {
        let bool = false;
        let timeOut = false;

        if (e.target.id == "contBtn") {
          bool = true;
          if (myTimeout) {
            clearTimeout(myTimeout);
          }
        } else return;

        document.body.removeEventListener("click", response);
        cleanupConfirmationModal(myModal2, modelResult);
        resolve(bool);
        if (pathreturn) {
          window.location.href = pathreturn;
        }
      }
    });
  }
}

function commonConfirmFormSubmit(form, actionLabel) {
  if (!form) {
    return false;
  }

  confirmationDialog("", "", actionLabel || "Confirm Action", "", "", "CONFIRM").then(function (confirmed) {
    if (confirmed) {
      HTMLFormElement.prototype.submit.call(form);
    }
  });

  return false;
}

/* Rate Checking */
function getUrlParameter(sParam) {
  let sPageURL = window.location.search.substring(1),
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
}

/* fix issue of dropdown menu display inside table responsive */
function dropdownMenuDispFix() {
  if (typeof bootstrap === "undefined" || !bootstrap.Dropdown) {
    return;
  }

  let dropdowns = document.querySelectorAll(".dropdown-toggle");
  dropdowns.forEach(function (dropdownToggleEl) {
    new bootstrap.Dropdown(dropdownToggleEl, {
      popperConfig(defaultBsPopperConfig) {
        return { ...defaultBsPopperConfig, strategy: "fixed" };
      },
    });
  });
}

//autocomplete
function ensureAutocompleteResultShell(elementID) {
  let $input = $("#" + elementID);
  if (!$input.length) {
    return;
  }

  let resultId = "searchResult_" + elementID;
  let clearId = "clear_" + elementID;
  let $wrapper = $input.closest(".autocomplete");

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
  let input = document.getElementById(elementID);
  let result = document.getElementById("searchResult_" + elementID);

  if (!input || !result) {
    return;
  }

  result.style.left = input.offsetLeft + "px";
  result.style.top = input.offsetTop + input.offsetHeight + 4 + "px";
  result.style.width = input.offsetWidth + "px";
  result.style.display = "block";
}

function searchInput(param, siteURL) {
  param = param || {};

  let elementID = param["elementID"];
  let hiddenElementID = param["hiddenElementID"];
  let search = param["search"] == null ? "" : String(param["search"]);
  let type = param["searchType"];
  let dbTable = param["dbTable"];
  let addSelection = param["addSelection"] ? String(param["addSelection"]) : "";
  let onSelect = typeof param["onSelect"] === "function" ? param["onSelect"] : null;

  if (!elementID || !hiddenElementID) {
    return;
  }

  if (search !== "") {
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
        ensureAutocompleteResultShell(elementID);

        setWidth(elementID, "searchResult_" + elementID);
        positionAutocompleteResult(elementID);

        let resultList = $("#searchResult_" + elementID);
        let resultRows = Array.isArray(result) ? result : [];

        resultList.empty();

        for (let i = 0; i < resultRows.length; i++) {
          let row = resultRows[i] || {};

          if (row["desc"] != undefined) {
            let desc = row["desc"];
            let value = row["val"];
            let selectText = row["select_text"];

            let resultItem = $("<li></li>")
              .attr("value", String(value == null ? "" : value))
              .text(String(desc == null ? "" : desc))
              .data("searchRow", row);
            if (selectText != undefined) {
              resultItem.attr("data-select-text", String(selectText));
            }
            resultList.append(resultItem);
          } else {
            let id = row["id"];
            let name = row[type];

            resultList.append(
              $("<li></li>")
                .attr("value", String(id == null ? "" : id))
                .text(String(name == null ? "" : name))
                .data("searchRow", row),
            );
          }
        }

        if (addSelection !== "") {
          resultList.append(
            $("<li></li>")
              .attr("value", addSelection)
              .text(addSelection),
          );
        }

        resultList
          .find("li")
          .off("click.searchInput")
          .on("click.searchInput", function () {
            setText(this, "#" + elementID, "#" + hiddenElementID);
            if (onSelect && $(this).attr("value") !== "emptyValue") {
              onSelect($(this).data("searchRow") || {});
            }
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
  param = param || {};

  let elementID = param["elementID"];
  let hiddenElementID = param["hiddenElementID"];
  let search = param["search"] == null ? "" : String(param["search"]);
  let type = param["searchTypes"];
  let pkg = param["pkgID"];
  let usr = param["usrID"];
  let whse = param["whseID"];
  let addSelection = param["addSelection"] ? String(param["addSelection"]) : "";

  if (!elementID || !hiddenElementID) {
    return;
  }

  if (search !== "") {
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
        ensureAutocompleteResultShell(elementID);

        setWidth(elementID, "searchResult_" + elementID);
        positionAutocompleteResult(elementID);

        let resultList = $("#searchResult_" + elementID);
        let resultRows = Array.isArray(result) ? result : [];

        resultList.empty();

        for (let i = 0; i < resultRows.length; i++) {
          let row = resultRows[i] || {};

          if (row["desc"] != undefined) {
            let desc = row["desc"];
            let value = row["val"];

            resultList.append(
              $("<li></li>")
                .attr("value", String(value == null ? "" : value))
                .text(String(desc == null ? "" : desc)),
            );
          } else {
            let id = row["id"];
            let name = row[type];

            resultList.append(
              $("<li></li>")
                .attr("value", String(id == null ? "" : id))
                .text(String(name == null ? "" : name)),
            );
          }
        }

        if (addSelection !== "") {
          resultList.append(
            $("<li></li>")
              .attr("value", addSelection)
              .text(addSelection),
          );
        }

        resultList
          .find("li")
          .off("click.searchInput2")
          .on("click.searchInput2", function () {
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
  let search = param["search"];
  let type = param["searchType"];
  let dbTable = param["dbTable"];
  let col = param["searchCol"];
  let fin = param["isFin"];

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
        if (typeof callback === "function") {
          callback(result);
        }
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
  let text = $(element).attr("data-select-text");
  if (text == undefined) {
    text = $(element).text();
  }
  let value = $(element).attr("value");

  if (value != "emptyValue") {
    $(val).val(text);
    $(val2).val(value).trigger("input"); // to trigger input event from package page
  } else {
    $(val).val("");
    $(val2).val("").trigger("input"); // to trigger input event from package page
  }
}

document.addEventListener("DOMContentLoaded", function () {
  let actionBtn = document.getElementById("actionBtn");
  retrieveDataFromLocalStorage();

  // Attach input event listener to each input field
  let inputFields = document.querySelectorAll("input, textarea ,select");
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
    let inputFields = document.querySelectorAll("input, textarea ,select");
    let page = localStorage.getItem("page");

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
    let inputFields = document.querySelectorAll("input, textarea ,select");
    let page = localStorage.getItem("page");

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
    let inputFields = document.querySelectorAll("input, textarea,select");
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
    let alertMessages = document.querySelectorAll('span[role="alert"]');
    alertMessages.forEach(function (alert) {
      alert.parentNode.removeChild(alert);
    });

    checkRequiredInputs();

    return document.querySelectorAll('span[role="alert"]').length === 0;
  }

  function checkRequiredInputs() {
    let requiredInputs = document.querySelectorAll(
      "input[required], select[required]",
    );

    requiredInputs.forEach(function (input) {
      if (String(input.value == null ? "" : input.value).trim() === "") {
        let labelNode = null;
        let labels = document.querySelectorAll("label");

        for (let i = 0; i < labels.length; i++) {
          if (labels[i].getAttribute("for") === input.id) {
            labelNode = labels[i];
            break;
          }
        }

        let labelContent = labelNode
          ? labelNode.textContent
          : input.getAttribute("name") || input.id || "This field";

        labelContent = labelContent.replace(/\*/g, "");

        let alertMessage = document.createElement("span");
        alertMessage.textContent = labelContent + " is required!";
        alertMessage.style.color = "red";
        alertMessage.setAttribute("role", "alert");

        if (input.parentNode) {
          input.parentNode.appendChild(alertMessage);
        }

        input.setAttribute("data-previous-value", input.value);
      }
    });
  }
});

// Wait for the DOM to be ready
document.addEventListener("DOMContentLoaded", function () {
  // Get the input field and error message elements
  let currentDataNameInput = document.getElementById("currentDataName");
  let errorSpan = document.getElementById("errorSpan");

  if (currentDataNameInput && errorSpan) {
    // Function to toggle error message visibility
    function toggleErrorMessage() {
      let inputValue = currentDataNameInput.value.trim();
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
  let d = new Date();
  d.setTime(d.getTime() + exMins * 60 * 1000);
  let expires = "expires=" + d.toUTCString();
  document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

function toggleCustomerLabelVisibility(toggleButton) {
  if (!toggleButton) {
    return;
  }

  let labelWrap = toggleButton.closest(".js-customer-label-wrap");
  if (!labelWrap) {
    return;
  }

  let extraLabels = labelWrap.querySelectorAll(".customer-label-extra");
  let isExpanded = labelWrap.getAttribute("data-expanded") === "1";

  for (let i = 0; i < extraLabels.length; i++) {
    extraLabels[i].classList.toggle("d-none", isExpanded);
  }

  labelWrap.setAttribute("data-expanded", isExpanded ? "0" : "1");
  toggleButton.textContent = isExpanded ? "Show More" : "Show Less";
}

document.addEventListener("click", function (event) {
  let toggleButton = event.target.closest(".js-toggle-customer-labels");
  if (!toggleButton) {
    return;
  }

  event.preventDefault();
  toggleCustomerLabelVisibility(toggleButton);
});

function getCustomerRecordFilterStorageSnapshot() {
  let preservedEntries = {};

  for (let i = 0; i < localStorage.length; i++) {
    let key = localStorage.key(i);
    if (!key) {
      continue;
    }

    if (
      /_filters$/.test(key) ||
      /_filter_panel_open$/.test(key) ||
      /^task_board_worklog_timer_(?:v1_|v2_|active_v1_)/.test(key)
    ) {
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
  let preservedEntries = getCustomerRecordFilterStorageSnapshot();
  localStorage.clear();
  restoreCustomerRecordFilterStorageSnapshot(preservedEntries);
}

function checkCurrentPage(page, action) {
  let previousPage = localStorage.getItem("page");
  let perviousAction = localStorage.getItem("action");

  if (previousPage != page || perviousAction != action) {
    clearLocalStoragePreservingCustomerRecordFilters();
    localStorage.setItem("page", page);
    localStorage.setItem("action", action);
  }
}

function preloader(additionalDelay, action) {
  function releasePageLoader() {
    setTimeout(function () {
      let preloaders = document.querySelectorAll(".preloader");
      let preloadCenters = document.querySelectorAll(".pre-load-center");
      let pageCovers = document.querySelectorAll(".page-load-cover");

      for (let i = 0; i < preloaders.length; i++) {
        preloaders[i].style.display = "none";
      }
      for (let j = 0; j < preloadCenters.length; j++) {
        preloadCenters[j].style.display = "none";
      }
      for (let k = 0; k < pageCovers.length; k++) {
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
    let firstInput = $(
      "input[type='text']:visible:enabled:not(:checkbox,:radio,:hidden,[readonly]), textarea:visible:enabled:not(:hidden,[readonly]), input[type='number']:visible:enabled:not(:hidden,[readonly])",
    )
      .filter(function () {
        return $.trim($(this).val()) === "";
      })
      .first();

    if (firstInput.length > 0) {
      firstInput.focus();

      let inputValue = firstInput.val();
      if (inputValue) {
        let lastSpaceIndex = inputValue.lastIndexOf(" ");

        if (lastSpaceIndex !== -1) {
          let input = firstInput.get(0);
          let lastWordIndex = inputValue.indexOf(" ", lastSpaceIndex + 1);
          let cursorPosition =
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
  let checkboxes = document.querySelectorAll(".export:checked");
  if (checkboxes.length === 0) {
    showNotification("Please select data to export.", "warning");
    return false;
  }
  return true;
}

function showExportNotification() {
  showNotification("Export successful!", "success");
}

function auditExport(ids, tblName) {
  let xhr = new XMLHttpRequest();
  xhr.open("POST", "../export.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  xhr.send("ids=" + ids.join(",") + "&tblName=" + tblName);
}

function captureAndExport(tblName) {
  let selectedIds = [];
  document.querySelectorAll("input.export:checked").forEach(function (checkbox) {
    selectedIds.push(checkbox.value);
  });

  auditExport(selectedIds, tblName);

  if (exportData()) {
    showExportNotification();
  }
}

function getParameterByName(name) {
  let urlParams = new URLSearchParams(window.location.search);
  return urlParams.get(name);
}

function commonMobileActionIsVisible(element) {
  if (!element) {
    return false;
  }

  if (element.offsetParent === null) {
    return false;
  }

  let style = window.getComputedStyle(element);
  return style.display !== "none" && style.visibility !== "hidden";
}

function commonNormalizeButtonText(text) {
  return (text || "").replace(/\s+/g, " ").trim();
}

function commonResolvePageTitle() {
  let path = (window.location.pathname || "").toLowerCase();

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

  let text = commonNormalizeButtonText(element.textContent || element.value || "");
  let value = commonNormalizeButtonText(element.value || "");
  let href = (element.getAttribute("href") || "").toLowerCase();
  let existingTitle = commonNormalizeButtonText(element.getAttribute("title") || "");
  let iconClass = "";
  let iconElement = element.querySelector("i");

  if (iconElement) {
    iconClass = iconElement.className || "";
  }

  let hasGenericTitle =
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
  let pageTitle = commonResolvePageTitle();
  let buttons = document.querySelectorAll("a.btn, button.btn");

  for (let i = 0; i < buttons.length; i++) {
    let title = commonResolveButtonTitle(buttons[i], pageTitle);
    if (title !== "") {
      buttons[i].setAttribute("title", title);
      if (!buttons[i].getAttribute("aria-label")) {
        buttons[i].setAttribute("aria-label", title);
      }
    }
  }
}

function commonApplyVisibleActionLabels() {
  let pageTitle = commonResolvePageTitle();
  if (!pageTitle) {
    return;
  }

  let buttons = document.querySelectorAll("a.btn, button.btn");
  for (let i = 0; i < buttons.length; i++) {
    let button = buttons[i];
    let text = commonNormalizeButtonText(button.textContent || "");
    let value = commonNormalizeButtonText(button.value || "");

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

  let computedStyle = window.getComputedStyle(sourceButton);
  let properties = [
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

  for (let i = 0; i < properties.length; i++) {
    let property = properties[i];
    targetButton.style[property] = computedStyle[property];
  }
}

function commonBuildMobileFloatingAddButton() {
  if (document.querySelector(".mobile-floating-action-bar")) {
    return;
  }

  let sourceButton = document.querySelector(
    "a#addBtn.btn, button#addBtn.btn, a[name='addBtn'].btn, button[name='addBtn'].btn",
  );

  if (!sourceButton || !commonMobileActionIsVisible(sourceButton)) {
    return;
  }

  let stickyBar = document.createElement("div");
  stickyBar.className = "mobile-floating-action-bar mobile-floating-action-bar--single";

  let buttonClone = sourceButton.cloneNode(true);
  buttonClone.removeAttribute("id");
  buttonClone.classList.add("mobile-floating-primary-action");
  commonSyncButtonVisualStyle(sourceButton, buttonClone);

  stickyBar.appendChild(buttonClone);
  document.body.appendChild(stickyBar);

  sourceButton.classList.add("mobile-floating-source-btn");
  document.body.classList.add("has-mobile-floating-add-btn");
}

let commonMobileStickyFormActionsState = null;

function commonMobileStickyActionSelector() {
  return "button.submitBtn, button.cancel, button#actionBtn, button#backBtn, button[name='actionBtn'], button[name='updateStatusBtn'], a.submitBtn, a.cancel, a#actionBtn, a#backBtn";
}

function commonIsBackActionButton(button) {
  if (!button) {
    return false;
  }

  let buttonText = commonNormalizeButtonText(button.textContent || "").toLowerCase();
  let buttonValue = commonNormalizeButtonText(button.value || "").toLowerCase();

  return (
    button.id === "backBtn" ||
    button.classList.contains("backBtn") ||
    button.classList.contains("cancel") ||
    buttonValue === "back" ||
    buttonText === "back"
  );
}

function commonGetMobileStickyActionPriority(button) {
  if (!button) {
    return 99;
  }

  if (commonIsBackActionButton(button)) {
    return 3;
  }

  let buttonName = commonNormalizeButtonText(button.getAttribute("name") || "").toLowerCase();
  if (buttonName === "updatestatusbtn") {
    return 1;
  }

  return 2;
}

function commonCollectMobileStickyActionButtons(container) {
  if (!container) {
    return [];
  }

  return Array.prototype.slice
    .call(container.querySelectorAll(commonMobileStickyActionSelector()))
    .filter(function (button) {
      return (
        commonMobileActionIsVisible(button) &&
        !button.disabled &&
        !button.closest("td") &&
        !button.closest(".modal") &&
        !button.closest(".mobile-floating-action-bar") &&
        !button.closest(".mobile-sticky-form-actions")
      );
    });
}

function commonSortMobileStickyActionButtons(buttons) {
  return (buttons || [])
    .map(function (button, index) {
      return {
        button: button,
        index: index,
      };
    })
    .sort(function (a, b) {
      let aPriority = commonGetMobileStickyActionPriority(a.button);
      let bPriority = commonGetMobileStickyActionPriority(b.button);

      if (aPriority !== bPriority) {
        return aPriority - bPriority;
      }

      return a.index - b.index;
    })
    .map(function (entry) {
      return entry.button;
    });
}

function commonFindMobileStickyActionGroup() {
  let preferredContainer = document.querySelector(".mobile-sticky-form-actions-target");
  if (preferredContainer) {
    let preferredButtons = commonCollectMobileStickyActionButtons(preferredContainer);
    if (preferredButtons.length > 0) {
      return {
        container: preferredContainer,
        buttons: commonSortMobileStickyActionButtons(preferredButtons),
      };
    }
  }

  let buttons = Array.prototype.slice
    .call(document.querySelectorAll(commonMobileStickyActionSelector()))
    .filter(function (button) {
      let parentForm = button.closest("form");
      let isUploadAnalyzeButton =
        parentForm &&
        parentForm.querySelector("input[type='file']") &&
        !button.hasAttribute("formnovalidate");

      return (
        commonMobileActionIsVisible(button) &&
        !button.disabled &&
        !isUploadAnalyzeButton &&
        !button.closest("td") &&
        !button.closest(".modal") &&
        !button.closest(".mobile-floating-action-bar") &&
        !button.closest(".mobile-sticky-form-actions")
      );
    });

  if (buttons.length === 0) {
    return null;
  }

  let groupedParents = [];

  for (let i = 0; i < buttons.length; i++) {
    let parent = buttons[i].parentElement;
    let sourcePanel = buttons[i].closest(".mobile-sticky-form-actions-source-panel");
    if (
      sourcePanel &&
      sourcePanel.parentElement &&
      sourcePanel.parentElement.classList.contains("mobile-sticky-form-actions-source")
    ) {
      parent = sourcePanel.parentElement;
    }

    if (!parent) {
      continue;
    }

    let existingGroup = null;
    for (let j = 0; j < groupedParents.length; j++) {
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
    return null;
  }

  groupedParents.sort(function (a, b) {
    if (b.buttons.length !== a.buttons.length) {
      return b.buttons.length - a.buttons.length;
    }

    return b.element.getBoundingClientRect().top - a.element.getBoundingClientRect().top;
  });

  return {
    container: groupedParents[0].element,
    buttons: commonSortMobileStickyActionButtons(groupedParents[0].buttons),
  };
}

function commonMeasureMobileStickyActionWidth(button) {
  if (!button) {
    return 0;
  }

  let measurementNode = button.cloneNode(true);
  measurementNode.removeAttribute("id");
  measurementNode.removeAttribute("name");
  measurementNode.removeAttribute("form");
  measurementNode.setAttribute("aria-hidden", "true");
  if (measurementNode.tagName === "BUTTON") {
    measurementNode.setAttribute("type", "button");
  }
  if (measurementNode.tagName === "A") {
    measurementNode.setAttribute("href", "#");
  }

  measurementNode.style.position = "absolute";
  measurementNode.style.left = "-9999px";
  measurementNode.style.top = "-9999px";
  measurementNode.style.visibility = "hidden";
  measurementNode.style.width = "auto";
  measurementNode.style.maxWidth = "none";
  measurementNode.style.whiteSpace = "nowrap";
  measurementNode.style.fontSize = "12px";
  measurementNode.style.lineHeight = "1.2";
  measurementNode.style.padding = "8px 14px";
  measurementNode.style.minHeight = "40px";

  document.body.appendChild(measurementNode);
  let measuredWidth = Math.ceil(measurementNode.getBoundingClientRect().width);
  measurementNode.remove();

  return Math.max(measuredWidth + 6, 108);
}

function commonShouldCollapseSplitMobileStickyActionLayout(sourceContainer, buttons) {
  if (!sourceContainer || !buttons || buttons.length !== 3) {
    return false;
  }

  if (!window.matchMedia("(max-width: 768px)").matches) {
    return false;
  }

  let viewportWidth = Math.max(
    document.documentElement.clientWidth || 0,
    window.innerWidth || 0,
  );
  let sourceWidth = Math.ceil(sourceContainer.getBoundingClientRect().width || 0);
  let availableWidth = Math.max(Math.min(sourceWidth || viewportWidth, viewportWidth) - 4, 240);
  let secondRowColumnWidth = Math.floor((availableWidth - 8) / 2);

  if (secondRowColumnWidth <= 0) {
    return true;
  }

  for (let i = 1; i < buttons.length; i++) {
    let button = buttons[i];
    let computedStyle = window.getComputedStyle(button);
    let measurementNode = document.createElement("span");
    measurementNode.textContent = commonNormalizeButtonText(button.textContent || "");
    measurementNode.style.position = "absolute";
    measurementNode.style.left = "-9999px";
    measurementNode.style.top = "-9999px";
    measurementNode.style.visibility = "hidden";
    measurementNode.style.whiteSpace = "nowrap";
    measurementNode.style.fontFamily = computedStyle.fontFamily;
    measurementNode.style.fontSize = computedStyle.fontSize;
    measurementNode.style.fontWeight = computedStyle.fontWeight;
    measurementNode.style.fontStyle = computedStyle.fontStyle;
    measurementNode.style.letterSpacing = computedStyle.letterSpacing;
    measurementNode.style.textTransform = computedStyle.textTransform;

    document.body.appendChild(measurementNode);
    let textWidth = Math.ceil(measurementNode.getBoundingClientRect().width);
    measurementNode.remove();

    let horizontalChrome =
      (parseFloat(computedStyle.paddingLeft) || 0) +
      (parseFloat(computedStyle.paddingRight) || 0) +
      (parseFloat(computedStyle.borderLeftWidth) || 0) +
      (parseFloat(computedStyle.borderRightWidth) || 0) +
      20;

    if ((textWidth + horizontalChrome) > secondRowColumnWidth) {
      return true;
    }
  }

  return false;
}

function commonResolveMobileStickyActionLayout(sourceContainerOrButtons, maybeButtons) {
  let sourceContainer = Array.isArray(sourceContainerOrButtons)
    ? null
    : sourceContainerOrButtons;
  let buttons = Array.isArray(sourceContainerOrButtons)
    ? sourceContainerOrButtons
    : (maybeButtons || []);
  let buttonCount = (buttons || []).length;

  if (buttonCount <= 1) {
    return { mode: "inline", columns: 1 };
  }

  if (buttonCount === 2) {
    return { mode: "inline", columns: 2 };
  }

  if (buttonCount === 3) {
    if (commonShouldCollapseSplitMobileStickyActionLayout(sourceContainer, buttons)) {
      return { mode: "collapsed", columns: 1 };
    }

    return { mode: "split", columns: 2 };
  }

  if (buttonCount > 3) {
    return { mode: "collapsed", columns: 1 };
  }

  let viewportWidth = Math.max(
    document.documentElement.clientWidth || 0,
    window.innerWidth || 0,
  );
  let availableWidth = Math.max(viewportWidth - 24, 240);
  let gapWidth = 8;
  let requiredWidth = 0;

  for (let i = 0; i < buttons.length; i++) {
    requiredWidth += commonMeasureMobileStickyActionWidth(buttons[i]);
  }

  requiredWidth += gapWidth * (buttons.length - 1);

  if (requiredWidth <= availableWidth) {
    return { mode: "inline", columns: buttons.length };
  }

  return { mode: "split", columns: 2 };
}

function commonEnsureMobileStickyActionSourceStructure(sourceContainer) {
  if (!sourceContainer) {
    return {
      toggleButton: null,
      panel: null,
    };
  }

  let panel = sourceContainer.querySelector(".mobile-sticky-form-actions-source-panel");
  if (!panel) {
    panel = document.createElement("div");
    panel.className = "mobile-sticky-form-actions-source-panel";
    sourceContainer.appendChild(panel);
  }

  let directChildren = Array.prototype.slice.call(sourceContainer.children);
  for (let i = 0; i < directChildren.length; i++) {
    let child = directChildren[i];
    if (
      child !== panel &&
      child.matches &&
      child.matches(commonMobileStickyActionSelector())
    ) {
      panel.appendChild(child);
    }
  }

  let toggleButton = sourceContainer.querySelector(".mobile-sticky-form-actions-source-toggle");
  if (!toggleButton) {
    toggleButton = document.createElement("button");
    toggleButton.type = "button";
    toggleButton.className =
      "btn btn-lg btn-rounded btn-primary mobile-sticky-form-actions-source-toggle";
    toggleButton.textContent = "Show More";
    sourceContainer.insertBefore(toggleButton, panel);
  }

  if (!toggleButton.dataset.bound) {
    toggleButton.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopPropagation();

      let shouldExpand = !sourceContainer.classList.contains("is-expanded");
      sourceContainer.classList.toggle("is-expanded", shouldExpand);
      toggleButton.textContent = shouldExpand ? "Hide Actions" : "Show More";
      toggleButton.setAttribute("aria-expanded", shouldExpand ? "true" : "false");
    });
    toggleButton.dataset.bound = "1";
  }

  return {
    toggleButton: toggleButton,
    panel: panel,
  };
}

let commonMobileStickySourceOutsideBound = false;

function commonBindMobileStickySourceOutsideCollapse() {
  if (commonMobileStickySourceOutsideBound) {
    return;
  }

  document.addEventListener("pointerdown", function (event) {
    let expandedSources = document.querySelectorAll(
      '.mobile-sticky-form-actions-source[data-mobile-sticky-source-mode="collapsed"].is-expanded',
    );

    for (let i = 0; i < expandedSources.length; i++) {
      let sourceContainer = expandedSources[i];
      if (sourceContainer.contains(event.target)) {
        continue;
      }

      sourceContainer.classList.remove("is-expanded");
      let toggleButton = sourceContainer.querySelector(
        ".mobile-sticky-form-actions-source-toggle",
      );
      if (toggleButton) {
        toggleButton.textContent = "Show More";
        toggleButton.setAttribute("aria-expanded", "false");
      }
    }
  });

  commonMobileStickySourceOutsideBound = true;
}

function commonApplyMobileStickySourceLayout(sourceContainer, sourceButtons, layout) {
  if (!sourceContainer) {
    return;
  }

  let sourceStructure = commonEnsureMobileStickyActionSourceStructure(sourceContainer);
  let toggleButton = sourceStructure.toggleButton;

  sourceContainer.classList.add("mobile-sticky-form-actions-source");
  sourceContainer.setAttribute(
    "data-mobile-sticky-source-mode",
    layout && layout.mode ? layout.mode : "inline",
  );
  if (layout && layout.mode !== "collapsed") {
    sourceContainer.classList.remove("is-expanded");
  }

  if (toggleButton) {
    toggleButton.textContent = "Show More";
    toggleButton.setAttribute(
      "aria-expanded",
      sourceContainer.classList.contains("is-expanded") ? "true" : "false",
    );
  }

  let allActionButtons = sourceContainer.querySelectorAll(commonMobileStickyActionSelector());
  for (let i = 0; i < allActionButtons.length; i++) {
    allActionButtons[i].classList.remove(
      "mobile-sticky-form-source-btn",
      "mobile-sticky-form-source-btn-primary",
    );
  }

  for (let j = 0; j < sourceButtons.length; j++) {
    sourceButtons[j].classList.add("mobile-sticky-form-source-btn");
    if (layout && layout.mode === "split" && j === 0) {
      sourceButtons[j].classList.add("mobile-sticky-form-source-btn-primary");
    }
  }

  commonBindMobileStickySourceOutsideCollapse();
}

function commonIsMobileStickyActionViewport() {
  return window.matchMedia("(max-width: 768px)").matches;
}

function commonIsMobileStickyActionSourceVisible(sourceContainer) {
  if (!sourceContainer || !commonMobileActionIsVisible(sourceContainer)) {
    return false;
  }

  let sourceRect = sourceContainer.getBoundingClientRect();
  let viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;

  return sourceRect.top < viewportHeight - 16 && sourceRect.bottom > 0;
}

function commonEnsureMobileStickyFormActionsOverlay() {
  if (
    commonMobileStickyFormActionsState &&
    commonMobileStickyFormActionsState.overlay &&
    commonMobileStickyFormActionsState.overlay.isConnected
  ) {
    return commonMobileStickyFormActionsState;
  }

  let overlay = document.createElement("div");
  overlay.className = "mobile-sticky-form-actions";
  overlay.hidden = true;
  overlay.setAttribute("aria-hidden", "true");
  overlay.innerHTML =
    '<div class="mobile-sticky-form-actions-shell">' +
      '<div class="mobile-sticky-form-actions-panel"></div>' +
      '<button type="button" class="btn btn-lg btn-rounded btn-primary mobile-sticky-form-actions-toggle" aria-expanded="false">Show More</button>' +
    "</div>";

  document.body.appendChild(overlay);

  let state = {
    overlay: overlay,
    shell: overlay.querySelector(".mobile-sticky-form-actions-shell"),
    panel: overlay.querySelector(".mobile-sticky-form-actions-panel"),
    toggleButton: overlay.querySelector(".mobile-sticky-form-actions-toggle"),
    sourceContainer: null,
    sourceButtons: [],
    mutationObserver: null,
    visibilityObserver: null,
    rafHandle: 0,
    mode: "inline",
  };

  state.toggleButton.addEventListener("click", function (event) {
    event.preventDefault();
    if (state.mode !== "collapsed") {
      return;
    }

    let shouldExpand = !state.overlay.classList.contains("is-expanded");
    state.overlay.classList.toggle("is-expanded", shouldExpand);
    state.toggleButton.textContent = shouldExpand ? "Hide Actions" : "Show More";
    state.toggleButton.setAttribute("aria-expanded", shouldExpand ? "true" : "false");
  });

  document.addEventListener("pointerdown", function (event) {
    if (
      !commonMobileStickyFormActionsState ||
      commonMobileStickyFormActionsState.mode !== "collapsed" ||
      !commonMobileStickyFormActionsState.overlay.classList.contains("is-expanded") ||
      !commonMobileStickyFormActionsState.overlay.contains(event.target)
    ) {
      commonCloseMobileStickyFormActions();
    }
  });

  window.addEventListener("resize", function () {
    if (commonMobileStickyFormActionsState) {
      commonQueueMobileStickyFormActionRefresh();
    }
  });

  window.addEventListener(
    "scroll",
    function () {
      if (commonMobileStickyFormActionsState) {
        commonQueueMobileStickyFormActionRefresh();
      }
    },
    { passive: true },
  );

  commonMobileStickyFormActionsState = state;
  return state;
}

function commonCloseMobileStickyFormActions() {
  if (!commonMobileStickyFormActionsState) {
    return;
  }

  commonMobileStickyFormActionsState.overlay.classList.remove("is-expanded");
  commonMobileStickyFormActionsState.toggleButton.textContent = "Show More";
  commonMobileStickyFormActionsState.toggleButton.setAttribute("aria-expanded", "false");
}

function commonScheduleMobileStickySourceObserver(state) {
  if (!state || !state.sourceContainer) {
    return;
  }

  if (state.visibilityObserver) {
    state.visibilityObserver.disconnect();
    state.visibilityObserver = null;
  }

  if ("IntersectionObserver" in window) {
    state.visibilityObserver = new IntersectionObserver(
      function () {
        commonQueueMobileStickyFormActionRefresh();
      },
      {
        root: null,
        threshold: [0, 0.1, 0.5, 1],
      },
    );
    state.visibilityObserver.observe(state.sourceContainer);
  }
}

function commonBindMobileStickySourceMutations(state) {
  if (!state || !state.sourceContainer || !("MutationObserver" in window)) {
    return;
  }

  if (state.mutationObserver) {
    state.mutationObserver.disconnect();
  }

  state.mutationObserver = new MutationObserver(function () {
    commonQueueMobileStickyFormActionRefresh(true);
  });

  state.mutationObserver.observe(state.sourceContainer, {
    childList: true,
    subtree: true,
    characterData: true,
    attributes: true,
    attributeFilter: [
      "class",
      "style",
      "disabled",
      "hidden",
      "value",
      "title",
      "aria-label",
      "data-bs-original-title",
    ],
  });
}

function commonCreateMobileStickyActionProxy(sourceButton) {
  let proxyButton = sourceButton.cloneNode(true);
  proxyButton.removeAttribute("id");
  proxyButton.removeAttribute("name");
  proxyButton.removeAttribute("form");
  proxyButton.removeAttribute("onclick");
  proxyButton.classList.add("mobile-sticky-form-overlay-btn");

  if (proxyButton.tagName === "BUTTON") {
    proxyButton.setAttribute("type", "button");
  }

  if (proxyButton.tagName === "A") {
    proxyButton.setAttribute("href", "#");
  }

  proxyButton.addEventListener("click", function (event) {
    event.preventDefault();
    event.stopPropagation();

    if (sourceButton.disabled) {
      return;
    }

    sourceButton.click();
  });

  return proxyButton;
}

function commonRenderMobileStickyFormActions(state) {
  if (!state || !state.sourceContainer) {
    return;
  }

  let sourceButtons = commonSortMobileStickyActionButtons(
    commonCollectMobileStickyActionButtons(state.sourceContainer),
  );

  state.sourceButtons = sourceButtons;
  state.panel.innerHTML = "";

  if (sourceButtons.length === 0) {
    state.overlay.hidden = true;
    state.overlay.setAttribute("aria-hidden", "true");
    document.body.classList.remove("has-mobile-sticky-form-actions");
    return;
  }

  let layout = commonResolveMobileStickyActionLayout(sourceButtons);
  state.mode = layout.mode;
  state.overlay.dataset.mode = layout.mode;
  state.overlay.style.setProperty("--mobile-sticky-cols", String(layout.columns || 1));
  document.body.classList.add("has-mobile-sticky-form-actions");
  commonApplyMobileStickySourceLayout(state.sourceContainer, sourceButtons, layout);

  for (let i = 0; i < sourceButtons.length; i++) {
    let proxyButton = commonCreateMobileStickyActionProxy(sourceButtons[i]);
    if (layout.mode === "split" && i === 0) {
      proxyButton.classList.add("mobile-sticky-form-overlay-btn-primary");
    }
    state.panel.appendChild(proxyButton);
  }

  if (layout.mode !== "collapsed") {
    commonCloseMobileStickyFormActions();
  }
}

function commonRefreshMobileStickyFormActions(forceRerender) {
  let state = commonEnsureMobileStickyFormActionsOverlay();
  if (!state || !state.sourceContainer) {
    return;
  }

  if (forceRerender) {
    commonRenderMobileStickyFormActions(state);
  }

  let shouldShowOverlay =
    commonIsMobileStickyActionViewport() &&
    state.sourceButtons.length > 0 &&
    !commonIsMobileStickyActionSourceVisible(state.sourceContainer);

  if (!shouldShowOverlay) {
    state.overlay.hidden = true;
    state.overlay.setAttribute("aria-hidden", "true");
    commonCloseMobileStickyFormActions();
    return;
  }

  state.overlay.hidden = false;
  state.overlay.setAttribute("aria-hidden", "false");
}

function commonQueueMobileStickyFormActionRefresh(forceRerender) {
  let state = commonEnsureMobileStickyFormActionsOverlay();
  if (!state) {
    return;
  }

  if (state.rafHandle) {
    window.cancelAnimationFrame(state.rafHandle);
  }

  state.rafHandle = window.requestAnimationFrame(function () {
    state.rafHandle = 0;
    commonRefreshMobileStickyFormActions(!!forceRerender);
  });
}

function commonDestroyMobileStickyFormActionsOverlay() {
  if (!commonMobileStickyFormActionsState) {
    document.body.classList.remove("has-mobile-sticky-form-actions");
    return;
  }

  if (commonMobileStickyFormActionsState.visibilityObserver) {
    commonMobileStickyFormActionsState.visibilityObserver.disconnect();
  }

  if (commonMobileStickyFormActionsState.mutationObserver) {
    commonMobileStickyFormActionsState.mutationObserver.disconnect();
  }

  if (commonMobileStickyFormActionsState.rafHandle) {
    window.cancelAnimationFrame(commonMobileStickyFormActionsState.rafHandle);
  }

  if (
    commonMobileStickyFormActionsState.overlay &&
    commonMobileStickyFormActionsState.overlay.parentNode
  ) {
    commonMobileStickyFormActionsState.overlay.parentNode.removeChild(
      commonMobileStickyFormActionsState.overlay,
    );
  }

  commonMobileStickyFormActionsState = null;
  document.body.classList.remove("has-mobile-sticky-form-actions");
}

function commonBuildMobileStickyFormActions() {
  let actionGroup = commonFindMobileStickyActionGroup();
  if (!actionGroup || !actionGroup.container || actionGroup.buttons.length === 0) {
    commonDestroyMobileStickyFormActionsOverlay();
    return;
  }

  let layout = commonResolveMobileStickyActionLayout(
    actionGroup.container,
    actionGroup.buttons,
  );
  commonApplyMobileStickySourceLayout(
    actionGroup.container,
    actionGroup.buttons,
    layout,
  );
  commonDestroyMobileStickyFormActionsOverlay();
}

let commonGlobalScrollTopButtonState = null;

function commonGetGlobalScrollTopDocumentHeight() {
  let body = document.body;
  let docElement = document.documentElement;
  return Math.max(
    body ? body.scrollHeight : 0,
    body ? body.offsetHeight : 0,
    docElement ? docElement.scrollHeight : 0,
    docElement ? docElement.offsetHeight : 0,
    docElement ? docElement.clientHeight : 0,
  );
}

function commonShouldShowGlobalScrollTopButton() {
  let viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
  if (viewportHeight <= 0) {
    return false;
  }

  let documentHeight = commonGetGlobalScrollTopDocumentHeight();
  if (documentHeight < viewportHeight * 2) {
    return false;
  }

  return (window.scrollY || window.pageYOffset || 0) > viewportHeight;
}

function commonEnsureGlobalScrollTopButton() {
  let buttons = document.querySelectorAll("#globalScrollTopBtn");
  let button = buttons.length > 0 ? buttons[0] : null;

  for (let i = 1; i < buttons.length; i++) {
    if (buttons[i] && buttons[i].parentNode) {
      buttons[i].parentNode.removeChild(buttons[i]);
    }
  }

  if (!button) {
    button = document.createElement("button");
    button.type = "button";
    button.id = "globalScrollTopBtn";
    button.setAttribute("aria-label", "Back to Top");
    button.setAttribute("data-tooltip", "Back to Top");
    button.setAttribute("title", "Back to Top");
    button.hidden = true;
    button.innerHTML = '<i class="fas fa-chevron-up" aria-hidden="true"></i>';

    if (document.body) {
      document.body.appendChild(button);
    }
  }

  if (!button) {
    return null;
  }

  if (!button.getAttribute("aria-label")) {
    button.setAttribute("aria-label", "Back to Top");
  }

  button.setAttribute("data-tooltip", "Back to Top");
  button.setAttribute("title", "Back to Top");

  if (button.dataset.globalScrollTopBound !== "1") {
    button.addEventListener("click", function () {
      window.scrollTo({
        top: 0,
        behavior: "smooth",
      });
    });
    button.dataset.globalScrollTopBound = "1";
  }

  return button;
}

function commonUpdateGlobalScrollTopButton() {
  let button = commonEnsureGlobalScrollTopButton();
  if (!button) {
    return;
  }

  let shouldShowButton = commonShouldShowGlobalScrollTopButton();
  button.hidden = !shouldShowButton;
  button.classList.toggle("is-visible", shouldShowButton);
  button.setAttribute("aria-hidden", shouldShowButton ? "false" : "true");
}

function commonQueueGlobalScrollTopButtonUpdate() {
  let button = commonEnsureGlobalScrollTopButton();
  if (!button) {
    return;
  }

  if (!commonGlobalScrollTopButtonState) {
    commonUpdateGlobalScrollTopButton();
    return;
  }

  if (commonGlobalScrollTopButtonState.rafHandle) {
    window.cancelAnimationFrame(commonGlobalScrollTopButtonState.rafHandle);
  }

  commonGlobalScrollTopButtonState.rafHandle = window.requestAnimationFrame(function () {
    commonGlobalScrollTopButtonState.rafHandle = 0;
    commonUpdateGlobalScrollTopButton();
  });
}

function commonInitGlobalScrollTopButton() {
  let button = commonEnsureGlobalScrollTopButton();
  if (!button) {
    return;
  }

  if (!commonGlobalScrollTopButtonState) {
    let state = {
      rafHandle: 0,
      resizeObserver: null,
    };

    window.addEventListener(
      "scroll",
      function () {
        commonQueueGlobalScrollTopButtonUpdate();
      },
      { passive: true },
    );
    window.addEventListener("resize", commonQueueGlobalScrollTopButtonUpdate);
    window.addEventListener("orientationchange", commonQueueGlobalScrollTopButtonUpdate);
    window.addEventListener("load", commonQueueGlobalScrollTopButtonUpdate);

    if ("ResizeObserver" in window) {
      state.resizeObserver = new ResizeObserver(function () {
        commonQueueGlobalScrollTopButtonUpdate();
      });

      if (document.documentElement) {
        state.resizeObserver.observe(document.documentElement);
      }

      if (document.body) {
        state.resizeObserver.observe(document.body);
      }
    }

    commonGlobalScrollTopButtonState = state;
  }

  commonQueueGlobalScrollTopButtonUpdate();
}

function commonInitMobileActionEnhancements() {
  commonInitGlobalScrollTopButton();
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
window.addEventListener("resize", commonBuildMobileStickyFormActions);

// Import preview forms are rendered again after every file analysis. Some
// browsers restore form controls from the previous response after that render,
// which can make the new PDF look like the previous one and submit stale data.
// Keep the server-rendered values authoritative for every page in /import/.
function commonIsImportPage() {
  return /(^|\/)import(?:\/|$)/i.test(window.location.pathname || "");
}

function commonGetImportPreviewForms() {
  if (!commonIsImportPage()) {
    return [];
  }

  return Array.prototype.filter.call(document.forms, function (form) {
    return !form.querySelector('input[type="file"][name="import_file"]');
  });
}

function commonResetImportPreviewFormsToServerValues() {
  commonGetImportPreviewForms().forEach(function (form) {
    form.setAttribute("autocomplete", "off");
    form.reset();
  });
}

function commonHideStaleImportPreviews() {
  commonGetImportPreviewForms().forEach(function (form) {
    var previewCard = form.closest(".card");
    if (previewCard) {
      previewCard.hidden = true;
    }

    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
      button.disabled = true;
      button.title = "Load and analyze the selected file first.";
    });
  });
}

function commonInitImportPreviewFreshness() {
  if (!commonIsImportPage()) {
    return;
  }

  document.querySelectorAll('form input[type="file"][name="import_file"]').forEach(function (input) {
    var uploadForm = input.form;
    if (uploadForm) {
      uploadForm.setAttribute("autocomplete", "off");
    }

    if (input.dataset.importPreviewFreshnessBound === "1") {
      return;
    }

    input.addEventListener("change", function () {
      if (input.files && input.files.length > 0) {
        commonHideStaleImportPreviews();
      }
    });
    input.dataset.importPreviewFreshnessBound = "1";
  });

  commonResetImportPreviewFormsToServerValues();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", commonInitImportPreviewFreshness);
} else {
  commonInitImportPreviewFreshness();
}

window.addEventListener("pageshow", function (event) {
  if (event.persisted && commonIsImportPage()) {
    window.location.replace(window.location.pathname + window.location.search + window.location.hash);
  }
});
