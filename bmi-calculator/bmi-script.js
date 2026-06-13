/**
 * BMI Calculator Logic
 * Handles language switching, input validation, BMI calculation, and result image download.
 */

const texts = {
  en: {
    title: "BMI Calculator",
    language: "Language",
    height: "Height",
    weight: "Weight",
    calc: "Calculate BMI",
    reset: "Reset",
    save: "Save Result",
    invalidError: "Please enter valid numeric values.",
    emptyError: "Please enter both height and weight.",
    saveNoResult: "Please calculate BMI before saving.",
    saveFail: "Unable to save the result image. Please try again.",
    underweight: "Underweight",
    normal: "Normal weight",
    overweight: "Overweight",
    obese: "Obese",
    msgUnder:
      "Your weight is below the normal range. Remember to eat a balanced diet and stay healthy!",
    msgNormal:
      "Great job! You are within a healthy weight range. Keep up the good lifestyle!",
    msgOver: "You are above the normal weight range.",
    msgObese:
      "Your BMI is in the obese range. Consider consulting a professional for a personalized health plan.",
  },
  zh: {
    title: "BMI计算器",
    language: "语言",
    height: "身高",
    weight: "体重",
    calc: "计算BMI",
    reset: "重置",
    save: "保存结果",
    invalidError: "请输入有效的数字。",
    emptyError: "请输入身高和体重。",
    saveNoResult: "请先计算BMI后再保存。",
    saveFail: "无法保存结果图片，请再试一次。",
    underweight: "体重过轻",
    normal: "正常体重",
    overweight: "超重",
    obese: "肥胖",
    msgUnder: "您的体重低于正常范围。请记得保持均衡饮食，照顾好身体哦！",
    msgNormal: "太棒了！您的体重在正常范围内。请继续保持健康的生活方式！",
    msgOver: "您的体重高于正常体重，尝试多运动。",
    msgObese: "您的BMI属于肥胖范围。建议咨询专业人士，制定适合您的健康计划。",
  },
};

/**
 * Updates UI text based on the selected language.
 */
function switchLanguage() {
  const lang = document.getElementById("language").value;

  document.getElementById("title").innerText = texts[lang].title;
  document.getElementById("langLabel").innerText = texts[lang].language;
  document.getElementById("heightLabel").innerText = texts[lang].height;
  document.getElementById("weightLabel").innerText = texts[lang].weight;
  document.getElementById("calcBtn").innerText = texts[lang].calc;
  document.getElementById("resetBtn").innerText = texts[lang].reset;

  const saveBtn = document.getElementById("saveBtn");
  if (saveBtn) {
    saveBtn.innerText = texts[lang].save;
  }

  document.getElementById("error").innerText = "";

  const resultBox = document.getElementById("resultBox");
  if (resultBox.style.display === "block") {
    calculateBMI();
  }
}

/**
 * Core calculation function.
 * Validates inputs and updates the result section.
 */
function calculateBMI() {
  const lang = document.getElementById("language").value;
  const heightVal = document.getElementById("height").value;
  const weightVal = document.getElementById("weight").value;
  const heightInput = parseFloat(heightVal);
  const weightInput = parseFloat(weightVal);
  const errorDiv = document.getElementById("error");
  const resultBox = document.getElementById("resultBox");
  const divider = document.getElementById("resultDivider");
  const resultActions = document.getElementById("resultActions");

  if (!heightVal || !weightVal) {
    errorDiv.innerText = texts[lang].emptyError;
    resultBox.style.display = "none";
    divider.style.display = "none";

    if (resultActions) {
      resultActions.style.display = "none";
    }

    return;
  }

  if (
    isNaN(heightInput) ||
    isNaN(weightInput) ||
    heightInput <= 0 ||
    weightInput <= 0
  ) {
    errorDiv.innerText = texts[lang].invalidError;
    resultBox.style.display = "none";
    divider.style.display = "none";

    if (resultActions) {
      resultActions.style.display = "none";
    }

    return;
  }

  errorDiv.innerText = "";

  let height = heightInput > 3 ? heightInput / 100 : heightInput;
  const bmi = weightInput / (height * height);
  const bmiRounded = bmi.toFixed(1);

  let category, message, cssClass, comparisonText;

  if (bmi < 18.5) {
    category = texts[lang].underweight;
    message = texts[lang].msgUnder;
    cssClass = "underweight";
    comparisonText = `${bmiRounded} < 18.5`;
  } else if (bmi < 25) {
    category = texts[lang].normal;
    message = texts[lang].msgNormal;
    cssClass = "normal";
    comparisonText = `18.5 < ${bmiRounded} < 25`;
  } else if (bmi < 30) {
    category = texts[lang].overweight;
    message = texts[lang].msgOver;
    cssClass = "overweight";
    comparisonText = `25 < ${bmiRounded} < 30`;
  } else {
    category = texts[lang].obese;
    message = texts[lang].msgObese;
    cssClass = "obese";
    comparisonText = `${bmiRounded} ≥ 30`;
  }

  document.getElementById("bmiCategory").innerText = category;
  document.getElementById("bmiComparison").innerText = comparisonText;
  document.getElementById("bmiValue").innerText = bmiRounded;
  document.getElementById("bmiMessage").innerText = message;

  resultBox.className = "result " + cssClass;
  resultBox.style.display = "block";
  divider.style.display = "block";

  if (resultActions) {
    resultActions.style.display = "block";
  }
}

/**
 * Resets the form inputs and hides result sections.
 */
function resetForm() {
  document.getElementById("height").value = "";
  document.getElementById("weight").value = "";
  document.getElementById("resultBox").style.display = "none";
  document.getElementById("resultDivider").style.display = "none";
  document.getElementById("error").innerText = "";

  const resultActions = document.getElementById("resultActions");
  if (resultActions) {
    resultActions.style.display = "none";
  }
}

/**
 * Real-time validation as the user types.
 */
function validateInput() {
  const lang = document.getElementById("language").value;
  const h = document.getElementById("height").value;
  const w = document.getElementById("weight").value;
  const errorDiv = document.getElementById("error");

  if (
    (h !== "" && (parseFloat(h) <= 0 || isNaN(h))) ||
    (w !== "" && (parseFloat(w) <= 0 || isNaN(w)))
  ) {
    errorDiv.innerText = texts[lang].invalidError;
  } else {
    errorDiv.innerText = "";
  }
}

/**
 * Downloads the BMI result box as a PNG image.
 */
function downloadBMIResult() {
  const lang = document.getElementById("language").value;
  const resultBox = document.getElementById("resultBox");
  const errorDiv = document.getElementById("error");
  const saveBtn = document.getElementById("saveBtn");

  if (!resultBox || resultBox.style.display !== "block") {
    errorDiv.innerText = texts[lang].saveNoResult;
    return;
  }

  if (typeof html2canvas === "undefined") {
    errorDiv.innerText = texts[lang].saveFail;
    return;
  }

  if (saveBtn) {
    saveBtn.disabled = true;
  }

  html2canvas(resultBox, {
    backgroundColor: null,
    scale: 2,
  })
    .then(function (canvas) {
      const link = document.createElement("a");
      const timestamp = new Date().toISOString().replace(/[:.]/g, "-");

      link.download = "bmi-result-" + timestamp + ".png";
      link.href = canvas.toDataURL("image/png");
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    })
    .catch(function () {
      errorDiv.innerText = texts[lang].saveFail;
    })
    .finally(function () {
      if (saveBtn) {
        saveBtn.disabled = false;
      }
    });
}