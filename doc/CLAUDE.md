# CLAUDE.md — IMS 项目工作约束

> 给 Claude Code / developer 改这个项目时遵守的规则。
> 详见 [COMMIT_CHECKLIST.md](COMMIT_CHECKLIST.md) 与 [CODE_DUPLICATION_REPORT.md](CODE_DUPLICATION_REPORT.md)。

## 项目概况
- 传统 PHP IMS(库存/订单/财务管理),无框架。入口配置在 `init.php`。
- 第三方库都在 `header/`(bootstrap / dompdf / tinymce / fpdf / phpqrcode 等)——**不要改、不要扫、不要在这里加业务代码**。
- 共享代码归属:
  - PHP 工具函数 → `include/common.php`(几乎所有页面已 include)
  - JS 工具函数 → `js/common.fun.js`(已被 ~101 个页面加载,含 84 个函数)
  - 全局常量 → `init.php`(`SITEURL`、DB 等)、`include/common_variable.php`(链接、图片路径等)
- 环境/域名/DB 由 `init.php` 按 host 自动判断,统一用常量 `SITEURL`。

## 硬性规则(写代码必须遵守)

1. **不复制函数**。新增函数前先搜:`grep -rn "function 名" --include=*.php --include=*.js . | grep -v header/`。
   - 工具函数放共享文件,**不要**放在页面里。
   - 同一逻辑出现第 3 次必须抽成共享函数。
   - 已知反例:`deleteDir`/`addDirToZip`(复制进 50 文件)、`exportData`/`setCookie`(共享库已有却重复 18 次)。

2. **不写死 URL / 域名 / 路径**。站点用 `SITEURL`;外链/图片/第三方 API base 用常量(缺的就在 `common_variable.php` 新增),**绝不**内联 `https://cms.beyourdiary.com` 或 `localhost/cms`。

3. **绝不提交密钥/密码/token** 到代码或仓库(现存 `init.php` 明文 DB 密码属遗留问题,新代码不沿用)。

4. **UI 重复块用 `include` 共享**(弹窗 modal、列表页头、preloader、网络失败 alert);通用 CSS 放 `css/main.css`,不要每页内联 `<style>`。

5. **改共享文件**(`common.php` / `common.fun.js`)影响 100+ 页面:列出受影响页面并回归测试。
   - PHP 集中函数用 `if (!function_exists('xxx')) { ... }` 包裹,避免 Fatal error。
   - JS 删本地副本前确认共享版是最新正确版(后加载会覆盖先加载)。

## 提交前
- 跑 hook(已通过 `core.hooksPath=.githooks` 启用),或手动:`powershell -ExecutionPolicy Bypass -File scripts\precommit_check.ps1`。
- Commit message 类型:`feat | fix | refactor | dup-remove | hardcode-fix | ui | chore`;改共享文件要在正文写影响范围/已测页面。
- 目标:不让 `CODE_DUPLICATION_REPORT.md` 的问题数量增加。

## 环境
- Windows + PowerShell(本地 WAMP,`c:\wamp64\www\ims`)。
- Git for Windows;hook 经 Git 自带 bash 运行。
