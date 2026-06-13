<?php
// bmi.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BMI Calculator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="bmi-style.css">
</head>

<body>

<div class="container">
    <h1 id="title">BMI Calculator</h1>

    <!-- Language Selection -->
    <label id="langLabel">Language</label>
    <select id="language" onchange="switchLanguage()">
        <option value="en">English</option>
        <option value="zh">中文（简体）</option>
    </select>

    <!-- Height -->
    <label id="heightLabel">Height</label>
    <div class="input-group">
        <input type="number" id="height" step="any" min="0" oninput="validateInput()">
        <span class="unit-label">cm</span>
    </div>

    <!-- Weight -->
    <label id="weightLabel">Weight</label>
    <div class="input-group">
        <input type="number" id="weight" step="any" min="0" oninput="validateInput()">
        <span class="unit-label">kg</span>
    </div>

    <!-- Buttons Container -->
    <div class="button-group">
        <button type="button" class="btn-reset" onclick="resetForm()" id="resetBtn">Reset</button>
        <button type="button" class="btn-calc" onclick="calculateBMI()" id="calcBtn">Calculate BMI</button>
    </div>

    <!-- Error -->
    <div class="error" id="error"></div>

    <!-- Visual Divider -->
    <div class="divider" id="resultDivider"></div>

    <!-- Result Box -->
    <div class="result" id="resultBox">
        <div id="bmiCategory" class="result-header"></div>
        <div id="bmiComparison" class="result-comparison"></div>
        <div id="bmiValue" class="result-big-number"></div>
        <div id="bmiMessage" class="result-footer"></div>
    </div>

    <!-- Save Result Button -->
    <div class="result-actions" id="resultActions">
        <button type="button" class="btn-save" onclick="downloadBMIResult()" id="saveBtn">Save Result</button>
    </div>
</div>

<script src="../header/js/html2canvas.min.js"></script>
<script src="bmi-script.js"></script>

</body>
</html>