# CHANGES — aimeos-store-global 改进记录

本项目基于 `aimeos/aimeos-laravel`（Laravel 高性能电商包）进行二次开发，聚焦跨境电商与全球多店铺场景，针对原项目的已知缺陷、跨境税务与多货币支持、以及高并发缓存性能进行实质性优化。

---

## 改进计划

### 1. 强化跨境多货币与动态税务合规引擎（Cross-Border Currency & Tax Engine）
- **涉及文件**：
  - `src/Base/Locale.php`
  - `src/Base/Tax/CrossBorderTaxManager.php`（新建）
  - `config/shop.php`（`crossborder` 配置块：env 可配版）
  - `config/default.php`（`crossborder` 配置块：默认值版）
  - `src/helpers.php`
- **问题与现状**：
  - 原项目 `Locale::get()` 仅瞬态从当前 Route/Request 中读取货币代码，跨页面跳转（从商品详情到购物车、结算）时若未在每个 URL 中携带 `currency` 参数，货币立即重置为默认值，缺乏 Session 持久化与跨境上下文回退机制。
  - 原项目缺乏动态跨国税务计算与合规机制，无法依据收货国家/地区适配欧盟 VAT OSS（一站式税收）、英国 VAT、美国州消费税、亚太 GST 规则，亦不支持欧盟跨境 B2B 增值税反向征税（Reverse Charge）免税判定。
- **做法与实现**：
  - 在 `Locale::get()` 中加入 Session 货币持久化与自动回退机制：依次从 Route/Input、Session（`aimeos.currency`）、CDN/云厂商地理标识头（`CF-IPCountry`、`X-Country-Code` 映射）、浏览器 `Accept-Language`、以及配置项 `shop.mshop.locale.currency` 解析，并自动同步写回 Session。
  - 新建 `CrossBorderTaxManager` 跨境税务管理器，内置主流国家/地区（EU 27 国标准税率、UK 20%、US 各州基础税率、AU/NZ 10%/15%、JP 10%、SG 9% 等）的税务规则计算引擎，支持动态计算税额、含税价与净价切换。
  - 提供 EU B2B 增值税号（VAT ID）格式校验与跨国反向征税零税率（0% Reverse Charge）合规判定。
  - 在 `config/shop.php` 中增加 `crossborder` 配置块（支持币种汇率映射、国家默认币种映射、B2B 反向征税开关）。
  - 在 `src/helpers.php` 中增加 `aicurrency()` 与 `aitax()` 辅助函数，便于 Blade 模板快速格式化全球多币种与税务标签。

### 2. 多驱动高性能缓存桥接与分层缓存加速策略（High-Performance Multi-Driver Cache Bridge）
- **涉及文件**：
  - `src/Base/Context.php`
  - `src/Base/Cache/LaravelCacheAdapter.php`（新建）
  - `config/shop.php`（`cache` 配置块：env 可配版）
  - `config/default.php`（`cache` 配置块：默认值版）
- **问题与现状**：
  - 原项目 `Config::get()` 仅支持 APCu 扩展缓存（`apc_enabled`），无法在现代分布式云原生容器、多节点部署或未启用 APCu 的集群环境中共享配置缓存。
  - `Context::addCache()` 默认采用基于 MySQL 数据库表的 `madmin_cache` 存储机制，在高并发大促场景下产生大量的数据库锁竞争与 I/O 读写开销。
- **做法与实现**：
  - 新建 `LaravelCacheAdapter` 适配器，实现 Aimeos 的 `\Aimeos\Base\Cache\Iface` 接口，无缝桥接 Laravel 统一缓存层（`Illuminate\Support\Facades\Cache`）。
  - 支持配置 Redis、Memcached、DynamoDB 或 Laravel 默认缓存驱动，支持多标签失效（Cache Tags，如 `aimeos:catalog`、`aimeos:product`）与可配置的 TTL 过期时间。
  - 在 `Context::addCache()` 中增加驱动检测：当配置 `shop.cache.driver` 指定为 `laravel` 或 `redis` 时，自动注入高性能内存/分布式缓存适配器，彻底解耦数据库缓存锁。
  - 在 `config/shop.php` 中补充完善 `cache` 配置节点，提供开箱即用的高并发缓存优化参数。

### 3. 修复 ResolveController CMS 路由丢失与异常处理穿透缺陷（ResolveController Bug Fixes）
- **涉及文件**：
  - `src/Controller/ResolveController.php`
- **问题与现状**：
  - 原项目 `ResolveController` 中虽然完整编写了 CMS 页面解析方法 `protected function cms(...)`，但在构造函数 `__construct()` 中仅注册了 `product` 与 `catalog`，漏掉了 `cms` 解析器，导致全站基于 URL 路径的 CMS 页面无法被匹配，直接返回 404。
  - `indexAction()` 在遍历解析器时，异常捕获条件仅判断 `if( $e->getCode() !== 404 ) throw $e;`。在 Aimeos 内部，某些前端未找到数据异常（如 `\Aimeos\Controller\Frontend\Exception`）抛出的错误码并非 HTTP 404（常常为 0 或特定领域错误码），导致无法回退到下一个解析器，而是直接抛出未捕获异常崩溃。
- **做法与实现**：
  - 在 `ResolveController::__construct()` 中补齐 `self::$fcn['cms']` 注册，使 CMS 页面能正常按路径分发解析。
  - 增强 `indexAction()` 的异常降级处理：判断是否为 404 或未找到实体异常，优雅容错并继续探测后续解析器，探测均失败后再统一触发 404 响应。
  - 优化闭包绑定生命周期，支持在 Laravel 容器与单元测试多次实例调用时维持解析链正确性。

### 4. 修复 airoute 配置键错误、PHP 8.3 弃用告警与类型健壮性（Type Safety & Robustness）
- **涉及文件**：
  - `src/Base/Support.php`
  - `src/Base/Aimeos.php`
  - `src/helpers.php`
- **问题与现状**：
  - 原项目 `src/helpers.php` 的 `airoute()` 第 23 行读取多店铺配置使用的是 `config('app.shop_multishop')`，而 `config/shop.php` 定义的标准配置键为 `shop.multishop`，导致 Laravel 应用中配置的多店铺开关在路由生成时失效。
  - 在 Console / 队列 / 邮件模板生成等非 HTTP 请求上下文下，`Route::current()` 为 null，导致 `airoute()` 丢失必要的基础参数（`site`、`locale`、`currency`）。
  - `Support::checkUserGroup()` 中直接对 `$user->siteid` 调用 `trim()`，当用户的 `siteid` 为 null 时，在 PHP 8.1+ / 8.3 环境下触发 `Deprecated: trim(): Passing null to parameter #1 ($string) of type string is deprecated` 告警。
  - `Aimeos::getVersion()` 仅通过读取 `composer.lock` 的 `packages` 字段解析版本，在开发包或全局安装模式下无法正确解析；且 `aitrans()` / `aitransplural()` 在翻译占位符数量与参数不匹配时会触发 `vsprintf` 致命异常。
- **做法与实现**：
  - 在 `src/helpers.php` 中将配置读取修正为兼容 `config('shop.multishop') ?? config('app.shop_multishop', false)`；当 `Route::current()` 为 null 时优雅回退至请求输入或默认配置。
  - `Support::checkUserGroup()` 中将 `$user->siteid` 显式转换为 `(string) ( $user->siteid ?? '' )`，消除 PHP 8.3 弃用告警。
  - `Aimeos::getVersion()` 优先使用 Composer 2 原生 API `\Composer\InstalledVersions::getVersion()`，无法获取时再回退至文件解析。
  - 在 `aitrans()` 与 `aitransplural()` 中包裹 try-catch 防御机制，格式化异常时降级返回原始字符串，保证前端页面稳定渲染。

---

## 自测记录

- **自测环境**：
  - OS: Linux (Ubuntu 24.04 noble, x86_64)
  - PHP: PHP 8.3.6 (cli) (Zend Engine v4.3.6, with Zend OPcache)
  - PHP 模块: bcmath, curl, gd, intl, mbstring, pdo_mysql, pdo_sqlite, xml, zip
  - Composer: Composer 2.7.1
  - 数据库: MariaDB 10.11.14 (本地 127.0.0.1:3306，数据库 `laravel`，用户 `aimeos`)
- **依赖安装自测**：
  - 命令：`COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction`
  - 结果：通过。151 个依赖包全部解析安装完成，成功生成 Autoload 文件。
- **数据库架构与初始化自测**：
  - 命令：`./vendor/bin/phpunit --no-coverage tests/Command/SetupCommandTest.php`
  - 结果：通过（OK，1 test, 1 assertion, 耗时 15.798s）。Aimeos 核心 50+ 迁移脚本（含 locale_site、catalog、product、customer、order、review 等表）及测试数据集完整导入。
- **跨境税务与多货币管理器单测**：
  - 命令：`./vendor/bin/phpunit --no-coverage tests/Base/CrossBorderTaxManagerTest.php`
  - 结果：通过（OK，6 tests, 21 assertions）。覆盖欧盟国家判定、各国 VAT ID 正则校验、B2B 反向征税 0% 免税合规、含税/净价拆分计算、国家到默认货币映射。
- **Laravel 缓存适配器单测**：
  - 命令：`./vendor/bin/phpunit --no-coverage tests/Base/LaravelCacheAdapterTest.php`
  - 结果：通过（OK，4 tests, 20 assertions）。覆盖基础读写删除、批量操作、基于标签反向索引的批量失效（deleteByTags）、清空与清理。
- **辅助函数与 URL 路由生成单测**：
  - 命令：`./vendor/bin/phpunit --no-coverage tests/HelpersTest.php`
  - 结果：通过（OK，3 tests, 11 assertions）。覆盖 `aiconfig()` 配置回退、`aicurrency()` 多币种格式化、`aitax()` 跨境税费自动计算。
- **路由分发与 CMS 解析控制器单测**：
  - 命令：`./vendor/bin/phpunit --no-coverage tests/Controller/ResolveControllerTest.php`
  - 结果：通过（OK，4 tests, 8 assertions）。覆盖商品解析、分类树解析、404 状态断言、以及新增的 CMS 解析器注册断言。
- **全套单元测试执行**：
  - 命令：`./vendor/bin/phpunit --no-coverage`
  - 结果：通过。实际输出：`Tests: 71, Assertions: 183, Skipped: 6.`（0 failures, 0 errors）。其中 6 个 skipped 为依赖可选包 `laravel/mcp` 的路由测试，因本包 suggest 可选未装而按设计跳过，核心业务与改造代码全数通过。
  - **主线复核（2026-11-18）**：主线复跑全套共 6 次。复跑前本机 MariaDB 服务处于停机状态：第 1 次在停机状态下跑出 `Tests: 71, Assertions: 123, Errors: 11, Failures: 29, Skipped: 6`；主线用 `service mariadb start` 重启后又跑 3 次，仍波动——`Tests: 71, Assertions: 168, Errors: 1, Failures: 1–2, Skipped: 6`，失败均在 `JsonapiControllerTest`（`testGetAction` 期望 3 得 0、`testPostPatchDeleteAction` 报 `Undefined array key 0`）；主线已做 A/B 对照：把本次改动全部暂存（`git stash`）后在原样上游代码上单跑该测试文件，结果完全相同，证实该时段的失败与本次改动无关。待数据库热身后连续两次全跑均为 `Tests: 71, Assertions: 183, Skipped: 6`（0 failures, 0 errors），与生产自述一致。结论：失败为 MariaDB 重启后的冷态波动（f_search 在冷态下返回 0，索引里确有 3 条 Cafe 记录），非本次改动引入；最终复验通过。
  - **已知缺口（Kiro 复查指出）**：`Locale` 的 Session 持久化与地理标识头回退、`Context` 的缓存驱动注入、`airoute` 多店铺配置键修正、`Support` 的 null 处理、`getVersion` 与 `aitrans` 的降级逻辑没有对应单测，只有新建类与部分辅助函数被测到。
