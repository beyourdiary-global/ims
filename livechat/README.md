# Live Chat System

实时员工通讯系统，支持消息传递、在线状态检测和照片附件。

## 功能特性

- ✅ 实时消息传递（Server-Sent Events）
- ✅ 在线状态检测和显示
- ✅ 支持图片附件（最多5张，单张5MB）
- ✅ 消息已读标记
- ✅ 对话历史记录
- ✅ 未读消息计数器
- ✅ 用户列表排序

## 安装步骤

### 1. 创建数据库表

运行 `schema.sql` 中的 SQL 语句来创建所需的表：

```sql
-- 在你的数据库管理工具中执行 livechat/schema.sql 文件
```

或者通过 PHP：

```php
$sqlFile = file_get_contents(__DIR__ . '/livechat/schema.sql');
$queries = array_filter(array_map('trim', explode(';', $sqlFile)));
foreach ($queries as $query) {
    if (!empty($query)) {
        mysqli_query($connect, $query);
    }
}
```

### 2. 设置文件上传目录权限

确保 `livechat/uploads/` 目录存在并有写入权限：

```bash
mkdir -p livechat/uploads
chmod 755 livechat/uploads
```

### 3. 访问 Live Chat

在浏览器中访问：`/livechat/index.php`

## API 端点

### GET /livechat/api_user_list.php

获取所有用户及其在线状态。

**返回：**
```json
{
    "success": true,
    "users": [
        {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "is_online": true,
            "unread_count": 2,
            "last_seen": "2024-01-15 10:30:45"
        }
    ]
}
```

### GET /livechat/api_get_messages.php?user_id=2&limit=50&offset=0

获取与特定用户的对话历史。

**参数：**
- `user_id` (required): 对方用户 ID
- `limit` (optional): 每页消息数（默认50，最多100）
- `offset` (optional): 分页偏移

**返回：**
```json
{
    "success": true,
    "messages": [
        {
            "id": 1,
            "sender_id": 1,
            "recipient_id": 2,
            "message": "Hello!",
            "created_at": "2024-01-15 10:30:45",
            "is_read": 1,
            "attachments": [
                {
                    "id": 1,
                    "file_name": "photo.jpg",
                    "file_path": "livechat/uploads/2024/01/15/msg_123.jpg",
                    "file_size": 2048,
                    "file_type": "image/jpeg"
                }
            ]
        }
    ]
}
```

### POST /livechat/api_send_message.php

发送消息和附件。

**参数（multipart/form-data）：**
- `recipient_id`: 接收者 ID
- `message`: 消息内容（可选，但必须有附件）
- `files[]`: 文件数组（可选）

**返回：**
```json
{
    "success": true,
    "message_id": 1,
    "attachments": []
}
```

### GET /livechat/api_stream_messages.php?user_id=2

Server-Sent Events 端点，用于实时接收消息。

**事件类型：**
- `connected`: 连接成功
- `message`: 新消息
- `heartbeat`: 保活心跳

## 数据库表结构

### livechat_messages
- `id`: 消息 ID
- `sender_id`: 发送者 ID
- `recipient_id`: 接收者 ID
- `message`: 消息内容
- `created_at`: 创建时间
- `is_read`: 是否已读
- `updated_at`: 更新时间

### livechat_attachments
- `id`: 附件 ID
- `message_id`: 关联消息 ID
- `file_name`: 原始文件名
- `file_path`: 存储路径
- `file_size`: 文件大小（字节）
- `file_type`: MIME 类型
- `uploaded_at`: 上传时间

### livechat_user_status
- `user_id`: 用户 ID
- `is_online`: 是否在线
- `last_seen`: 最后在线时间
- `last_activity`: 最后活动时间

## 配置选项

在 `api_send_message.php` 中调整：

```php
// 文件大小限制（字节）
$maxFileSize = 5242880; // 5MB
```

在 `api_stream_messages.php` 中调整：

```php
// 连接超时时间（秒）
$maxInactivity = 300; // 5分钟
```

## 安全考虑

- ✅ 要求用户登录（通过 SESSION）
- ✅ 支持的文件类型限制（仅图片）
- ✅ 文件大小限制
- ✅ SQL 转义处理
- ✅ 用户只能看到自己的对话

## 性能优化

- 使用 Server-Sent Events 而不是长轮询
- 数据库索引优化对话查询
- 消息流式传输，不缓冲所有消息
- 用户状态每 30 秒刷新一次

## 浏览器兼容性

- Chrome 64+
- Firefox 55+
- Safari 11.1+
- Edge 79+

*注意：不支持 IE 11*

## 故障排查

### 消息没有实时到达

1. 检查服务器是否支持 Server-Sent Events
2. 检查防火墙/代理是否阻止长连接
3. 查看浏览器控制台是否有错误

### 文件上传失败

1. 检查 `livechat/uploads/` 目录权限
2. 检查 PHP 配置中的 `upload_max_filesize` 和 `post_max_size`
3. 确保文件是图片格式

### 在线状态不更新

1. 检查数据库中 `livechat_user_status` 表是否存在
2. 用户离线时会自动更新状态（连接关闭时）
3. 状态每 30 秒从服务器刷新一次

## 许可证

内部使用
