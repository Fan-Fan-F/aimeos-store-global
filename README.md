# Aimeos Store Global

> 面向跨境电商与全球多店铺场景的高性能 Laravel 电商包 / High-performance Laravel eCommerce package enhanced for cross-border multi-store and global trade.

---

## 简体中文

### 简介
`aimeos-store-global` 是基于官方 `aimeos/aimeos-laravel` 二次开发的高性能全球化电商包。原项目具备极高的并发吞吐量与灵活的多租户/多店铺能力，本项目在此基础上进一步强化了跨境贸易场景的税务与货币合规支持、分布式高并发缓存策略、以及核心路由与类型安全优化。

### 功能与改进点
本项目在保留 Aimeos 原生多店铺、多语言、GraphQL/JSON:API 与 AI MCP 集成特性的同时，实现了以下实质性增强（对应 [CHANGES.md](CHANGES.md)）：

1. **跨境多货币与动态税务合规引擎（Cross-Border Tax & Multi-Currency Engine）**
   - **Session 币种持久化与智能回退**：重构 `Locale::get()`，解决原项目币种在跨页面跳转时频繁丢失的缺陷；支持从 Request、Session、CDN/云厂商地理标识头（`CF-IPCountry`、`X-Country-Code`）及配置项层级解析并自动回写 Session。
   - **全球标准与欧盟 VAT OSS 规则库**：内置 `CrossBorderTaxManager`，提供欧盟 27 国 OSS 税率、英美加澳日新等主流贸易市场的税率计算与净价/含税价换算。
   - **欧盟跨境 B2B 反向征税合规**：支持成员国企业 VAT ID 格式语法校验，自动执行跨国 B2B 零税率（0% Reverse Charge）合规免税。
   - **全球化模板辅助函数**：提供 `aicurrency()` 与 `aitax()` 辅助函数，便于 Blade 模板快速格式化全球多币种与税务标签。

2. **多驱动分布式缓存与分层加速策略（High-Performance Cache Bridge）**
   - **Laravel Cache 统一桥接**：实现 `LaravelCacheAdapter`，完整实现 Aimeos `\Aimeos\Base\Cache\Iface` 接口，无缝对接 Redis、Memcached、DynamoDB 或 Laravel 默认缓存存储。
   - **解耦数据库表锁**：在 `Context::addCache()` 中支持配置化驱动切换（`shop.cache.driver`），在高并发大促场景下以高速内存/分布式缓存替代原有的 `madmin_cache` MySQL 表，规避行级锁与 I/O 争用。
   - **全局标签失效索引**：针对不支持原生 Tag 的驱动维护高效的反向标签索引，确保 `deleteByTags()` 在所有存储介质中均能稳定执行。

3. **修复 ResolveController CMS 路由丢失与异常处理穿透缺陷**
   - **补齐 CMS 解析路由注册**：修复 `ResolveController` 构造函数漏注 `cms` 解析器的 Bug，确保全站基于 URL 路径的 CMS 页面可被正常解析与分发。
   - **健壮的 404 容错降级**：重构 `indexAction()` 的异常捕获逻辑，避免非 404 错误码的“未找到”异常阻断后续解析器，实现平滑降级。

4. **PHP 8.3 强类型兼容、辅助函数健壮性与配置名统一**
   - **配置键命名修正**：修正 `airoute()` 中将 `shop.multishop` 误读为 `app.shop_multishop` 的缺陷，并补齐在 CLI/队列非 HTTP 场景下的默认参数回退。
   - **消除 PHP 8.1+ 弃用警告**：修复 `Support::checkUserGroup()` 中对 `null` 调用 `trim()` 触发的 Deprecated 告警。
   - **安全翻译与 Composer 2 原生版本获取**：在 `aitrans()` / `aitransplural()` 中增加对 `vsprintf` 参数不匹配的异常防御；`Aimeos::getVersion()` 优先调用 `\Composer\InstalledVersions` 原生 API。

### 快速开始

#### 1. 环境要求
- PHP >= 8.1（推荐 PHP 8.2 或 8.3）
- 扩展：`bcmath`, `curl`, `gd`, `intl`, `mbstring`, `pdo_mysql` (或 `pdo_pgsql`), `xml`, `zip`
- Laravel 10.x, 11.x, 12.x 或 13.x
- Composer 2.x

#### 2. 安装与配置发布
在 Laravel 项目根目录下引入包并发布配置文件：
```bash
composer require aimeos/aimeos-laravel
php artisan vendor:publish --tag=config
```

#### 3. 跨境与缓存配置
编辑发布的 `config/shop.php` 配置文件：
```php
return [
    // 启用高性能 Redis / Laravel 缓存驱动（替代默认数据库缓存表）
    'cache' => [
        'driver' => env('SHOP_CACHE_DRIVER', 'laravel'),
        'store' => env('SHOP_CACHE_STORE', 'redis'),
        'ttl' => 3600,
    ],

    // 跨境税务与多货币配置
    'crossborder' => [
        'enabled' => true,
        'seller_country' => 'DE', // 商家注册国代码
        'price_includes_tax' => true, // 目录售价是否含税
        'b2b_reverse_charge' => true, // 启用欧盟跨国 B2B 免税反向征税
        'currencies' => ['EUR', 'USD', 'GBP', 'JPY', 'CNY', 'AUD'],
    ],
];
```

#### 4. 数据库初始化
运行 Aimeos 安装与数据迁移：
```bash
php artisan aimeos:setup
```

#### 5. 常用测试与管理命令
```bash
# 执行单元测试
./vendor/bin/phpunit --no-coverage

# 清除缓存
php artisan aimeos:clear
```

### 来源与致谢
本项目基于 [aimeos-laravel](https://github.com/aimeos/aimeos-laravel) 二次开发，原项目由 aimeos 以 MIT 许可证开源。

### 许可证
沿用原许可证，全文见根目录 LICENSE。

---

## English

### Overview
`aimeos-store-global` is a secondary development based on the official `aimeos/aimeos-laravel` package. While maintaining ultra-fast execution, headless GraphQL/JSON:API support, and multi-tenant capabilities, this project significantly enhances cross-border taxation compliance (EU VAT OSS, B2B Reverse Charge), persistent multi-currency detection, high-performance distributed caching (Redis/Laravel unified cache), and route resolver stability.

### Key Features & Improvements
Corresponding to [CHANGES.md](CHANGES.md):

1. **Cross-Border Tax & Multi-Currency Engine**
   - **Persistent Currency Resolution**: Solves the ephemeral currency issue in `Locale::get()` by adding session persistence, GeoIP / Cloudflare header detection (`CF-IPCountry`, `X-Country-Code`), and fallback chains.
   - **Global & EU VAT OSS Rules**: Built-in `CrossBorderTaxManager` covering EU 27 member states, UK VAT, US sales tax, Australian/NZ GST, Japanese JCT, and net/gross calculations.
   - **Intra-Community B2B Reverse Charge**: Automated syntax validation for EU VAT identification numbers and zero-rate reverse charge compliance.
   - **Global Blade Helpers**: Adds `aicurrency()` and `aitax()` for easy multi-currency formatting and tax calculation in templates.

2. **High-Performance Multi-Driver Cache Bridge**
   - **Laravel Cache Integration**: Implements `LaravelCacheAdapter` fulfilling `\Aimeos\Base\Cache\Iface`, allowing seamless use of Redis, Memcached, DynamoDB, or Octane.
   - **Decoupling Database Locks**: In `Context::addCache()`, bypasses the default database table `madmin_cache` to eliminate row locks and I/O contention during traffic spikes.
   - **Tag-based Invalidation Index**: Employs reverse tag indexing for stores lacking native tagging support.

3. **ResolveController Bug Fixes & Resilient Fallbacks**
   - **CMS Route Dispatching**: Fixes the omission of `cms` resolver in `ResolveController::__construct()`, enabling proper path-based CMS page resolution.
   - **Graceful 404 Fallback**: Hardens `indexAction()` exception handling to allow chained resolvers to continue when non-404 not-found exceptions occur.

4. **PHP 8.3 Compatibility & Type Safety**
   - **Multi-shop Config Key Fix**: Rectifies `shop.multishop` configuration retrieval in `airoute()`, and adds fallback values when running in CLI/queue contexts.
   - **Eliminates PHP 8.1+ Deprecation Warnings**: Safely casts `$user->siteid` before trimming in `Support::checkUserGroup()`.
   - **Safe Formatting & Composer 2 API**: Wraps `vsprintf` calls in `aitrans()` and `aitransplural()` to prevent argument count crashes, and adopts `\Composer\InstalledVersions` in `Aimeos::getVersion()`.

### Quick Start

#### Requirements
- PHP >= 8.1 (PHP 8.2 or 8.3 recommended)
- Extensions: `bcmath`, `curl`, `gd`, `intl`, `mbstring`, `pdo_mysql` (or `pdo_pgsql`), `xml`, `zip`
- Laravel 10.x, 11.x, 12.x, or 13.x
- Composer 2.x

#### Installation
```bash
composer require aimeos/aimeos-laravel
php artisan vendor:publish --tag=config
```

#### Configuration
Update `config/shop.php` to customize cross-border and caching options:
```php
'cache' => [
    'driver' => env('SHOP_CACHE_DRIVER', 'laravel'),
    'store' => env('SHOP_CACHE_STORE', 'redis'),
    'ttl' => 3600,
],
'crossborder' => [
    'enabled' => true,
    'seller_country' => 'DE',
    'price_includes_tax' => true,
    'b2b_reverse_charge' => true,
    'currencies' => ['EUR', 'USD', 'GBP', 'JPY', 'CNY', 'AUD'],
],
```

#### Database Setup
```bash
php artisan aimeos:setup
```

#### Testing
```bash
./vendor/bin/phpunit --no-coverage
```

### Sources & Acknowledgements
This project is a secondary development based on [aimeos-laravel](https://github.com/aimeos/aimeos-laravel), originally developed by [aimeos](https://github.com/aimeos) and open-sourced under the MIT License.

### License
Licensed under the original MIT License. See LICENSE for details.
