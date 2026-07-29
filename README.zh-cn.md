# CRUD Platform

CRUD Platform 是从 `crud-skeleton` 演进而来的 Symfony 8.1 后端项目：以模块化 CRUD
基础为起点，逐步整理为多应用微服务架构。

> English: [README.md](README.md) · 繁體中文: [README.zh-hant.md](README.zh-hant.md) · 日本語: [README.ja.md](README.ja.md)

## 项目目标

目标是构建一个**单仓库、多独立部署 Symfony 应用**的平台。每个服务将独立拥有
Kernel、配置、数据库和迁移、消息队列、worker、定时任务、Docker 镜像、测试和 CI。

当前仓库仍是**模块化单体**，不是已经完成的微服务系统：它共享一个 Kernel、Composer
项目、容器、数据库、迁移链、Messenger 队列、worker、scheduler 和 Docker 镜像。
`Trade -> Store -> Inventory` 的 Outbox/Inbox 链路是首个提取边界。

目标目录、边界规则与提取准入条件见
[微服务迁移契约](docs/design/microservice-transition.md)。

## 当前能力

- Symfony 8.1、PHP 8.4+、Doctrine ORM、MySQL 8，以及 SQLite 测试环境。
- 身份与访问：RS256 JWT、Refresh Token 轮换、OTP、密码登录和微信登录适配器。
- CMS、商品/订单工作流、门店运营、库存预留、发票、钱包账本、促销 DSL 和媒体存储模块。
- Trade、Store、Inventory 已具备版本化事件与 Outbox/Inbox 模式。
- `/api/doc` OpenAPI、PHPUnit、PHPStan Level 8、Rector 类型检查与 Docker Compose 开发环境。

Inventory 已实现，但默认关闭且尚未达到生产可用标准。安全限制见
[库存设计](docs/design/bundles/inventory.md)。

## 快速开始

Docker 是推荐的本地运行方式。在仓库根目录执行，主机只需要 Docker：

```bash
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec store-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec inventory-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:identity:user:create admin@example.com admin 'P@ssw0rd' --admin

curl -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"identifier":"admin@example.com","password":"P@ssw0rd"}'
```

- API：`http://localhost:8080`
- Store API：`http://localhost:8081`
- Inventory API：`http://localhost:8082`
- OpenAPI：`http://localhost:8080/api/doc`
- worker/scheduler 日志：`docker compose logs -f worker scheduler`

排障和本机 PHP 运行说明见 [QUICKSTART.zh-cn.md](QUICKSTART.zh-cn.md)。

## 架构方向

| 目标上下文 | 当前来源 | 当前定位 |
|---|---|---|
| Platform Kernel | `Core` | 共享框架库，不是服务 |
| Commerce | `Trade`、`Promotion` | 过渡服务候选 |
| Store Operations | `Store` → `apps/store` | 已提取；单体在过渡期间托管 |
| Inventory | `Inventory` → `apps/inventory` | 已提取；单体在过渡期间托管，受安全条件约束 |
| Payments | `Payment`、微信支付适配器 | 需要先建立持久化生命周期事件 |
| Wallet/Ledger | `Wallet` | 在 Payment 契约解耦后提取 |
| Identity & Access | `Identity`、微信登录适配器 | 后期提取 |
| Content/Media | `Common`、`Storage` | 后期；需先拆分 Settings 所有权 |

服务提取前必须具备标量跨服务契约、无跨服务 Doctrine 关系、所需的 Outbox/Inbox，
并独立拥有队列、运行时和部署产物。

## 开发

```bash
./vendor/bin/phpunit
composer deptrac
composer phpstan
composer rector:types:check
mkdocs build --strict
```

本机命令需使用 PHP 8.4+。微服务迁移期间，现有完整测试套件保留为特征测试。

## 文档

- [微服务迁移契约](docs/design/microservice-transition.md)
- [系统架构](docs/design/system-architecture.md)
- [系统契约](docs/design/system-contracts.md)
- [模块设计](docs/design/module-design.md)
- [AI 上下文](docs/ai/context.md)
- [文档站点](https://immane.github.io/crud-skeleton)

## 许可证

Apache-2.0。见 [LICENSE](LICENSE)。
