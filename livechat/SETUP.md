# Live Chat 系统 - 快速设置指南

## 📋 前置条件

- PHP 7.4+
- MySQL 5.7+
- 现代浏览器（Chrome, Firefox, Safari, Edge）

## 🚀 快速设置

### 步骤 1: 创建数据库表

在 phpMyAdmin 或你的数据库管理工具中执行以下 SQL：

```sql
-- Chat messages table
CREATE TABLE IF NOT EXISTS `livechat_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sender_id` INT NOT NULL,
    `recipient_id` INT NOT NULL,
    `message` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_read` TINYINT DEFAULT 0,
    INDEX `idx_conversation` (`sender_id`, `recipient_id`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat attachments table
CREATE TABLE IF NOT EXISTS `livechat_attachments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `message_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` INT NOT NULL,
    `file_type` VARCHAR(50),
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`message_id`) REFERENCES `livechat_messages`(`id`) ON DELETE CASCADE,
    INDEX `idx_message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User online status tracking
CREATE TABLE IF NOT EXISTS `livechat_user_status` (
    `user_id` INT PRIMARY KEY,
    `is_online` TINYINT DEFAULT 0,
    `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `usr_user`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 步骤 2: 验证目录权限

确保上传目录可写：

```bash
# Linux/Mac
chmod 755 livechat/uploads

# Windows (通常不需要，但检查文件夹属性)
# - 右键 > 属性 > 安全 > 编辑 > 允许"修改"
```

### 步骤 3: 访问 Live Chat

在浏览器打开：
```
http://yoursite.com/livechat/
```

你应该看到登录会话所属用户的用户列表。

## ✅ 验证安装

1. **用户列表加载** ✓
   - 左侧应显示所有用户（除当前用户外）
   - 在线状态应显示为绿色点（在线）或灰色点（离线）

2. **发送消息** ✓
   - 点击用户，输入消息并按 Enter 或点击发送
   - 消息应立即出现

3. **文件上传** ✓
   - 点击附件按钮选择图片
   - 消息发送时，图片应与消息一起上传
   - 接收方应立即看到图片

4. **实时通知** ✓
   - 打开两个浏览器标签页
   - 用 A 账户在标签页1发送消息
   - 消息应在标签页2（B 账户）立即出现

## 🔧 常见问题排查

### Q: 消息没有实时显示

**A:** 检查以下几点：
1. 服务器是否支持 Server-Sent Events（SSE）
2. 防火墙/代理是否允许长连接
3. PHP 配置：
   ```php
   // 检查 php.ini
   max_execution_time = 600  // 至少600秒
   output_buffering = Off    // 关闭输出缓冲
   ```

### Q: 文件上传失败

**A:** 检查以下配置：
1. PHP 配置（php.ini）：
   ```ini
   upload_max_filesize = 50M
   post_max_size = 50M
   ```

2. 目录权限：
   ```bash
   ls -la livechat/uploads  # 应显示 drwxr-xr-x
   ```

3. 文件类型：仅支持 JPEG、PNG、GIF、WebP、BMP

### Q: 在线状态不更新

**A:** 
1. 确保 `livechat_user_status` 表存在
2. 关闭 Live Chat 标签页时，用户状态会自动更新为离线（5分钟内）
3. 用户列表每30秒自动刷新一次

### Q: 看不到其他用户

**A:** 可能原因：
1. 数据库中没有其他用户
2. 检查用户权限（需要登录到系统）
3. 检查 SQL 查询是否正确：
   ```sql
   SELECT COUNT(*) FROM usr_user;  -- 应显示多个用户
   ```

## 📱 浏览器开发者工具调试

### 查看 Network 标签
- 查看 `api_stream_messages.php` 连接
- 应显示 `Event Stream` 类型的长连接

### 查看 Console 标签
- 查看是否有 JavaScript 错误
- 查看消息日志：
  ```javascript
  console.log('Message received:', message);
  ```

## 🔐 安全建议

1. **确保 HTTPS**：Live Chat 应在 HTTPS 环境中运行
2. **认证检查**：确保 `menuHeader.php` 正确验证用户
3. **文件验证**：已实现文件类型和大小检查
4. **SQL 注入防护**：使用了 `mysqli_real_escape_string`
5. **日志记录**：考虑添加审计日志记录重要操作

## 📊 性能优化

### 对于大量用户（1000+）

1. **增加数据库索引**：
   ```sql
   CREATE INDEX idx_messages_lookup ON livechat_messages(sender_id, recipient_id, created_at);
   CREATE INDEX idx_unread ON livechat_messages(recipient_id, is_read);
   ```

2. **调整 SSE 心跳间隔**：在 `api_stream_messages.php` 中修改
   ```php
   sleep(2);  // 改为 2 秒以减少服务器负载
   ```

3. **使用消息分页**：减少加载历史消息数量

4. **定期清理旧消息**：
   ```sql
   DELETE FROM livechat_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
   ```

## 🎨 自定义

### 修改界面样式

编辑 `index.php` 中的 `<style>` 部分：

```css
/* 修改消息气泡颜色 */
.message-group.sent .message-bubble {
    background-color: #2196F3;  /* 改为你想要的颜色 */
}

/* 修改在线指示器大小 */
.online-badge {
    width: 12px;  /* 改为12px */
    height: 12px;
}
```

### 修改文件大小限制

在 `api_send_message.php` 中：
```php
$maxFileSize = 10242880;  // 改为10MB
```

## 📞 获取帮助

- 查看 `README.md` 了解详细文档
- 检查浏览器控制台错误消息
- 查看 PHP 错误日志：`tail -f /var/log/php-errors.log`

---

**安装完成！** 🎉 现在你可以开始使用 Live Chat 进行实时员工通讯了。
