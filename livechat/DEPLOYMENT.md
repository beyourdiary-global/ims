# Live Chat 系统 - 部署清单

## 📦 交付内容清单

✅ **后端组件**
- [x] 通用函数库 (`livechat_common.php`) - 消息、用户状态、文件验证
- [x] 消息发送 API (`api_send_message.php`) - 支持文件上传
- [x] 实时推流 API (`api_stream_messages.php`) - Server-Sent Events
- [x] 用户列表 API (`api_user_list.php`) - 在线状态和未读计数
- [x] 消息历史 API (`api_get_messages.php`) - 对话加载

✅ **前端组件**
- [x] 完整的 UI 界面 (`index.php`)
- [x] 响应式设计（桌面端优化）
- [x] 实时消息显示
- [x] 文件预览和上传
- [x] 在线状态指示器
- [x] 消息时间戳

✅ **数据库**
- [x] 消息表 (`livechat_messages`)
- [x] 附件表 (`livechat_attachments`)
- [x] 用户状态表 (`livechat_user_status`)
- [x] 性能索引

✅ **文档**
- [x] README - 完整文档
- [x] SETUP - 快速设置指南
- [x] FEATURES - 功能详细说明
- [x] DEPLOYMENT - 本文档

---

## 🎯 部署步骤

### 第 1 步：准备环境

**检查清单**:
- [ ] PHP 版本 ≥ 7.4
- [ ] MySQL 版本 ≥ 5.7
- [ ] MySQLi 扩展已启用
- [ ] Session 已配置
- [ ] 文件上传目录权限正确

**验证命令**:
```bash
# 检查 PHP 版本
php -v

# 检查 MySQLi 是否启用
php -m | grep -i mysqli

# 检查目录权限
ls -la livechat/uploads
```

### 第 2 步：导入数据库表

**方法 A - 使用 phpMyAdmin**:
1. 打开 phpMyAdmin
2. 选择你的数据库
3. 点击"SQL"标签
4. 打开 `livechat/schema.sql`
5. 复制内容并粘贴
6. 点击"执行"

**方法 B - 使用 MySQL 命令行**:
```bash
mysql -u username -p database_name < livechat/schema.sql
```

**方法 C - 使用 PHP**:
```php
<?php
session_start();
include 'menuHeader.php';

$sqlFile = file_get_contents(__DIR__ . '/livechat/schema.sql');
$queries = array_filter(array_map('trim', explode(';', $sqlFile)));

foreach ($queries as $query) {
    if (!empty($query)) {
        if (!mysqli_query($connect, $query)) {
            echo "Error: " . mysqli_error($connect);
        }
    }
}
echo "Database tables created successfully!";
?>
```

### 第 3 步：验证数据库表

```sql
-- 在 MySQL 中执行以下查询

-- 检查表是否存在
SHOW TABLES LIKE 'livechat%';

-- 检查表结构
DESCRIBE livechat_messages;
DESCRIBE livechat_attachments;
DESCRIBE livechat_user_status;

-- 检查索引
SHOW INDEX FROM livechat_messages;
```

### 第 4 步：配置文件权限

```bash
# 确保上传目录可写
mkdir -p livechat/uploads
chmod 755 livechat/uploads

# 确保所有 PHP 文件可读
chmod 644 livechat/*.php
chmod 644 livechat/*.md
```

### 第 5 步：测试安装

#### 5.1 测试数据库连接
```php
// 在 livechat/test_db.php 中
<?php
session_start();
include '../menuHeader.php';

echo "Database Connection: ";
echo $connect ? "✓ OK" : "✗ FAILED";

echo "<br>Tables: ";
$result = mysqli_query($connect, "SHOW TABLES LIKE 'livechat%'");
echo $result->num_rows . " found";
?>
```

#### 5.2 访问 Live Chat
在浏览器中打开：
```
http://yoursite.com/livechat/
```

#### 5.3 测试功能

| 测试项 | 步骤 | 预期结果 |
|--------|------|---------|
| 加载用户列表 | 页面打开 | 显示所有用户 + 在线状态 |
| 选择用户 | 点击用户 | 打开对话窗口 |
| 发送消息 | 输入文本 + 回车 | 消息立即显示 |
| 上传图片 | 点击附件选择图片 | 显示预览 + 发送 |
| 实时接收 | 另一浏览器标签页发送 | 立即看到消息 |
| 在线状态 | 打开/关闭标签页 | 状态改变 |

---

## 🔍 验证清单

### 后端验证

```bash
# 1. 检查 API 端点响应
curl -X GET "http://yoursite.com/livechat/api_user_list.php"

# 2. 检查文件权限
ls -la livechat/uploads

# 3. 检查 PHP 日志
tail -f /var/log/php-fpm/error.log

# 4. 检查 MySQL 日志
tail -f /var/log/mysql/error.log
```

### 前端验证

**浏览器控制台（F12 打开）**:
1. 检查 Console 是否有红色错误
2. 查看 Network 标签：
   - `api_user_list.php` - 应返回 JSON
   - `api_stream_messages.php` - 应显示 EventStream
   - `api_get_messages.php` - 应返回消息数组

**测试用例**:
```javascript
// 在控制台运行
console.log('Current User ID:', CURRENT_USER_ID);
console.log('Users Loaded:', document.querySelectorAll('.user-item').length);
console.log('SSE Connected:', eventSource ? 'Yes' : 'No');
```

---

## ⚠️ 常见问题排查

### 问题 1：访问 `/livechat/` 显示白页面

**可能原因**:
1. PHP 错误（查看错误日志）
2. Session 不正确
3. 数据库连接失败

**解决**:
```php
// 在 index.php 顶部添加临时调试代码
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### 问题 2：消息不实时显示

**可能原因**:
1. SSE 连接不成功
2. 防火墙阻止长连接
3. PHP 输出缓冲未禁用

**解决**:
- 检查 PHP 日志
- 在浏览器 Network 标签查看 SSE 连接
- 增加 `max_execution_time` 到 600 秒

### 问题 3：文件上传失败

**可能原因**:
1. 目录权限不足
2. PHP 文件大小限制
3. 文件类型不支持

**解决**:
```bash
# 修改权限
sudo chmod 777 livechat/uploads

# 修改 PHP 配置（php.ini）
upload_max_filesize = 50M
post_max_size = 50M
```

### 问题 4：看不到其他用户

**可能原因**:
1. 数据库中没有其他用户
2. 查询权限问题
3. 认证失败

**解决**:
```sql
-- 检查用户数量
SELECT COUNT(*) FROM usr_user;

-- 验证当前用户
SELECT * FROM usr_user WHERE id = 1;
```

---

## 📊 数据库备份

### 备份脚本

```bash
#!/bin/bash
# 每天备份数据库

BACKUP_DIR="/backup/livechat"
DB_NAME="your_database_name"
DB_USER="username"
DB_PASS="password"

mkdir -p $BACKUP_DIR
DATE=$(date +"%Y%m%d_%H%M%S")

mysqldump -u $DB_USER -p$DB_PASS $DB_NAME \
    livechat_messages \
    livechat_attachments \
    livechat_user_status \
    > $BACKUP_DIR/livechat_backup_$DATE.sql

# 保留最近 30 天的备份
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
```

### 恢复备份

```bash
mysql -u username -p database_name < backup_file.sql
```

---

## 🚀 性能优化建议

### 1. 启用查询缓存
```sql
SET SESSION query_cache_type = ON;
```

### 2. 调整数据库连接
```php
// 增加连接超时
mysqli_query($connect, "SET SESSION wait_timeout=600");
```

### 3. 实现消息分页
```php
// 在 api_get_messages.php 中减少初始加载数量
$limit = 30;  // 改为 30 条而不是 50 条
```

### 4. 压缩 JavaScript
```bash
# 使用工具压缩 JS 文件
npx uglify-js index.php -o index.min.php
```

---

## 🔐 安全部署清单

- [ ] HTTPS 已启用
- [ ] SQL 注入防护已验证
- [ ] XSS 防护已验证
- [ ] CSRF Token 检查（如需要）
- [ ] 文件上传验证已启用
- [ ] 用户认证已验证
- [ ] 访问控制已配置
- [ ] 错误消息不暴露敏感信息
- [ ] 日志记录已配置
- [ ] 定期备份已计划

---

## 📈 监控建议

### 关键指标

1. **消息延迟** - SSE 推送延迟 < 1秒
2. **在线用户** - 活跃连接数
3. **消息吞吐量** - 每分钟消息数
4. **错误率** - API 错误百分比
5. **存储占用** - 数据库大小增长

### 监控查询

```sql
-- 消息统计
SELECT 
    DATE(created_at) as date,
    COUNT(*) as message_count,
    COUNT(DISTINCT sender_id) as active_users
FROM livechat_messages
GROUP BY DATE(created_at)
ORDER BY date DESC
LIMIT 7;

-- 存储占用
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
FROM information_schema.TABLES
WHERE table_schema = DATABASE()
AND table_name LIKE 'livechat%';
```

---

## 🎓 用户培训

### 基本操作说明

1. **启动聊天**
   - 打开 `/livechat/` URL
   - 从左侧列表选择同事

2. **发送消息**
   - 在底部输入框输入消息
   - 按 Enter 或点击发送按钮

3. **上传图片**
   - 点击附件图标（📎）
   - 选择 1-5 张图片
   - 图片会在消息中显示

4. **查看状态**
   - 🟢 绿色点 = 在线
   - ⚪ 灰色点 = 离线
   - 点击用户刷新状态

---

## ✅ 部署后清单

- [ ] 所有表已创建
- [ ] 文件权限已设置
- [ ] 所有 API 端点已测试
- [ ] 前端显示正常
- [ ] 实时消息工作
- [ ] 文件上传工作
- [ ] 在线状态更新
- [ ] 备份计划已配置
- [ ] 监控已启用
- [ ] 用户已培训
- [ ] 文档已更新

---

## 📞 支持联系

如遇问题：
1. 查看 `README.md` 和 `FEATURES.md`
2. 检查 `SETUP.md` 的故障排查部分
3. 查看浏览器控制台错误
4. 查看 PHP 和 MySQL 错误日志

---

**部署日期**: ___________
**部署人员**: ___________
**确认时间**: ___________

---

🎉 **部署完成！Live Chat 系统已准备好投入使用。**
