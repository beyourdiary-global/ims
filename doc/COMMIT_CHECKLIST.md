# 提交检查清单 & 规则 / Commit Checklist & Rules

> 配套文档 / Companion to: [CODE_DUPLICATION_REPORT.md](CODE_DUPLICATION_REPORT.md)
> 目的 / Purpose: 防止再次引入重复代码 (duplication) 与硬编码 (hardcoded values)。**每次 commit 前过一遍。**
> 适用 / Applies to: `*.php`, `*.js`, `*.html`（不含 `header/` 第三方库）。

---

## ✅ 每次 Commit 前快速清单 / Quick Pre-Commit Checklist

复制下面到 PR 描述里逐项打勾：

```
[ ] 没有复制粘贴整段函数到新文件（先找共享库有没有）
[ ] 新工具函数放进 include/common.php 或 js/common.fun.js，不是页面内
[ ] 没有重复定义 common.fun.js 里已有的函数 (exportData / setCookie / showExportNotification ...)
[ ] 没有写死 URL / 域名 / API key / DB 凭据（用常量或 SITEURL）
[ ] 没有写死数据库密码、token、密钥进代码
[ ] UI 弹窗 / 列表页头 / preloader 用 include 共享 partial，不是复制
[ ] 内联 <style> 的 .btn 等通用样式放进 css/main.css
[ ] 改了共享文件 (common.php / common.fun.js) 已回归测试受影响页面
[ ] commit message 清楚说明改了什么、影响哪些页面
```

---

## 📌 核心规则 / Core Rules

### 规则 1 — 禁止复制函数,先找共享库
**Before writing a function, search first.**

- PHP 工具函数 → 一律放 `include/common.php`（几乎所有页面已 include）。
- JS 工具函数 → 一律放 `js/common.fun.js`（已被 101 个页面加载）。
- 提交前先搜一下函数名是否已存在：
  ```bash
  grep -rn "function 你的函数名" --include=*.php --include=*.js . | grep -v header/
  ```
- ❌ 反例:`deleteDir()` / `addDirToZip()` 被复制进 50 个文件。
- ❌ 反例:`exportData` / `setCookie` 共享库已有,还在 18 个文件重新定义。

> **规则**:同一段逻辑出现**第 2 次** = 警告;出现**第 3 次** = 必须抽成共享函数。

### 规则 2 — 不写死 URL / 域名 / 路径
- 站点地址用 `SITEURL`(已在 `init.php` 按环境自动判断),**不要**写死 `https://cms.beyourdiary.com` 或 `localhost/cms`。
- 社交/外链/图片路径用 `include/common_variable.php` 里的常量(`FB_LINK`、`COMPANY_LINK`、`img_server` 等);缺的就**新增常量**,不要内联。
- 第三方 API base(telegram / easyparcel / qrserver / chart.js CDN / jquery CDN)→ 定义常量或集中到公共 head partial。
- 检查命令:
  ```bash
  grep -rnE "https?://(cms|uatcms)?\.?beyourdiary|localhost/cms" --include=*.php --include=*.js . | grep -v header/
  ```

### 规则 3 — 绝不提交密钥 / 密码 / Token
- DB 密码、API key、bot token、auth secret **绝不**写进代码或仓库。
- 用环境变量 / 服务器配置 / 不进版本库的 config 文件。
- 提交前自检:
  ```bash
  git diff --cached | grep -iE "password|passwd|pwd|secret|api[_-]?key|token|Byd1234"
  ```
- ⚠️ 现存问题:`init.php` 里 DB 密码明文。新代码不要再沿用这种写法。

### 规则 4 — UI 重复块用 include 共享(comment share)
- 弹窗 modal、列表页顶部样板、preloader、网络失败 alert → 抽成 `include/xxx.php` 用 `include` 复用。
- 目标共享文件(建议,见报告):
  - `include/list_page_header.php` — 列表页 session 重置 + 网络检查 + head
  - `include/estimated_date_modal.php` — 预计到货弹窗
  - `js/pdf_airbill_parser.js` — PDF/airbill 解析
- 通用 CSS(如 `.btn` padding)放 `css/main.css`,不要每页内联 `<style>`。

### 规则 5 — 改共享文件要回归测试
改 `include/common.php` 或 `js/common.fun.js` 影响面极大(100+ 页面):
- 列出受影响页面,至少在 UAT 跑一遍主要流程。
- PHP 集中函数时用 `if (!function_exists('xxx')) { ... }` 包裹,避免 Fatal error。
- JS 删本地副本前,确认共享版本是最新正确版(后加载会覆盖先加载)。

---

## 🔍 一键自检脚本 / Self-Check Before Commit

把下面存成 `scripts/precommit_check.sh`,commit 前手动跑:

```bash
#!/usr/bin/env bash
# 重复 & 硬编码自检 / duplication & hardcode self-check
set -e
EXCL="--exclude-dir=header --exclude-dir=.git"

echo "== 1) 暂存区里有疑似密钥/密码吗? =="
git diff --cached | grep -inE "password|passwd|pwd|secret|api[_-]?key|token|Byd1234" || echo "  OK 无"

echo "== 2) 暂存区里有写死域名/localhost 吗? =="
git diff --cached | grep -inE "https?://(cms|uatcms)?\.?beyourdiary|localhost/cms" || echo "  OK 无"

echo "== 3) 新增的函数是否已存在于共享库? (人工核对) =="
for fn in $(git diff --cached -U0 | grep -oE "^\+\s*function [a-zA-Z_]+" | grep -oE "[a-zA-Z_]+$" | sort -u); do
  cnt=$(grep -rln "function $fn" --include=*.php --include=*.js . $EXCL 2>/dev/null | wc -l)
  if [ "$cnt" -gt 1 ]; then echo "  ⚠ $fn 已在 $cnt 个文件出现,确认是否该用共享版"; fi
done
echo "自检完成。"
```

> 想强制执行可做成 git `pre-commit` hook;如需要我可以帮你配置(注意:hook 不进仓库,需团队各自启用或用 husky 类工具)。

---

## 📝 Commit Message 规范 / Convention

```
<类型>: <简述>

类型: feat | fix | refactor | dup-remove | hardcode-fix | ui | chore
```
- 抽取重复代码 → 用 `dup-remove:`,并在正文列出**删了哪些文件的副本、移到哪个共享文件**。
- 消除硬编码 → 用 `hardcode-fix:`,说明换成了哪个常量。
- 改了共享文件 → 在正文写 **影响范围 / 已测页面**。

示例:
```
dup-remove: 抽 deleteDir/addDirToZip 到 include/common.php

- 删除 finance/ shopee/ 下 50 个 *_table.php 的本地副本
- 集中到 include/common.php,用 function_exists 包裹
- 已测: atome / shopee_withdrawal / lazada 导出 ZIP 正常
```

---

## 🎯 Definition of Done(合并前)
- [ ] 通过本清单全部勾选项
- [ ] `precommit_check.sh` 无红色告警(或已说明原因)
- [ ] 改动的共享文件已回归测试
- [ ] 没有让 [CODE_DUPLICATION_REPORT.md](CODE_DUPLICATION_REPORT.md) 里的问题数量增加

---

*本清单基于 2026-06-09 的重复扫描结果制定,随重构进度更新。*
