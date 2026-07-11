# xlchClassbook 绚丽彩虹同学录

绚丽彩虹同学录是一个早期 PHP 同学录程序，包含个人主页、同学录、相册、留言板、后台管理等功能。本仓库为可运行源码版本，已做 PHP 8.x 兼容整理，并新增 SQLite 与 S3 兼容对象存储支持。

原项目地址：https://github.com/xlch88/xlchClassbook

## 当前特性

- 支持 PHP 8.x，按 PHP 8.5 向上兼容方向清理旧语法。
- 支持 MySQL / MariaDB。
- 支持 SQLite；安装时选择 SQLite 会自动创建 `data/` 目录，并生成随机文件名数据库。
- 相册照片支持本地上传、URL 导入、S3 兼容对象存储。
- 上传附件不再绑定七牛，后台改为通用 S3 兼容配置。
- 保留原有模板、用户资料字段、用户组、侧边栏等配置方式。

## 环境要求

必需：

- PHP 7.0 或更高版本，建议 PHP 8.1+。
- `file_get_contents`
- `file_put_contents`
- `curl`
- GD 图像处理扩展，例如 `imagecreatefromjpeg`
- 使用 MySQL 时需要 `mysqli`
- 使用 SQLite 时需要 `PDO` 和 `pdo_sqlite`

推荐：

- `ZipArchive`
- `fsockopen`
- Web 服务器开启伪静态支持，或按安装器提示跳过伪静态

## 安装

1. 将项目放到网站根目录。
2. 确保以下目录可写：
   - 项目根目录
   - `Upload/`
   - 使用 SQLite 时还需要允许程序创建 `data/`
3. 浏览器访问 `/Install`。
4. 按安装向导完成环境检测、伪静态配置、站点信息配置和管理员账号创建。
5. 安装完成后会生成 `Install/Install.lock`，用于阻止重复安装。

如需重新安装，删除：

```text
Install/Install.lock
```

## 数据库配置

### MySQL / MariaDB

安装时选择 `MySQL / MariaDB`，填写数据库地址、端口、用户名、密码和数据库名。

配置文件位置：

```text
Core/WebApp/Config/Database/Database.php
```

### SQLite

安装时选择 `SQLite`，无需填写数据库地址、用户名或密码。安装器会自动：

- 创建 `data/` 目录
- 生成随机数据库文件名，例如 `data/classbook_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.sqlite`
- 写入基础访问保护文件
- 将数据库路径保存到 `Core/WebApp/Config/Database/Database.php`

请不要把 SQLite 数据库移动到可公开下载的位置。

## 图片与附件上传

后台路径：

```text
后台管理 -> 站点配置 -> 功能设置 -> 上传照片到
```

可选方式：

- 本地服务器
- S3 兼容存储

### S3 兼容存储配置

选择 `S3 兼容存储` 后填写：

- `Endpoint`：对象存储接口地址，例如 `https://s3.amazonaws.com`、MinIO 地址、R2 地址等
- `Region`：区域，例如 `us-east-1`；不确定时可填 `auto`
- `Access Key`
- `Secret Key`
- `Bucket`
- `访问域名`：可选，填写 CDN 或公开访问域名；留空时使用上传接口地址拼接访问 URL
- `使用 path-style 地址`：MinIO 或部分兼容服务可能需要开启

上传实现使用 S3 Signature V4 的单文件 `PUT` 请求，不依赖 Composer SDK。

## 主要配置文件

```text
Core/WebApp/Config/SysConfig/Config.php
```

站点主配置，包含网站名称、注册开关、上传方式、S3 配置、频率限制等。

```text
Core/WebApp/Config/SysConfig/DefaultUserData.php
```

新注册用户的默认资料模板。

```text
Core/WebApp/Config/SysConfig/Info.php
```

个人资料字段配置。

```text
Core/WebApp/Config/SysConfig/Sidebar.php
```

侧边栏菜单配置。

```text
Core/WebApp/Config/SysConfig/UserGroup.php
```

用户组和权限配置。

## 升级注意

从旧版本升级前请备份：

```text
Core/WebApp/Config/
Upload/
data/
```

如果旧站点使用七牛配置，升级后需要进入后台重新配置为 S3 兼容存储。旧配置中的 `Option.Qiniu` 不再作为上传入口使用。

## 问题反馈

这是开源免费项目，不提供一对一技术支持。请先阅读本文档和源码；如仍有问题，建议通过 GitHub Issues 反馈。

## 原作者

- 程序：悦咚
- 策划：华梦

请尊重原作者劳动成果。若从 GitHub 以外渠道下载源码，请自行确认文件安全性。
