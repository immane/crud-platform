# CRUD Platform

CRUD Platform 是從 `crud-skeleton` 演進而來的 Symfony 8.1 後端專案：以模組化 CRUD
基礎為起點，逐步整理為多應用程式微服務架構。

> English: [README.md](README.md) · 简体中文: [README.zh-cn.md](README.zh-cn.md) · 日本語: [README.ja.md](README.ja.md)

## 專案目標

目標是建立一個**單一儲存庫、多個可獨立部署 Symfony 應用程式**的平台。每個服務將獨立
擁有 Kernel、設定、資料庫與遷移、訊息佇列、worker、排程工作、Docker 映像、測試與 CI。

目前儲存庫仍是**模組化單體**，不是已完成的微服務系統：它共用一個 Kernel、Composer
專案、容器、資料庫、遷移鏈、Messenger 佇列、worker、scheduler 與 Docker 映像。
`Trade -> Store -> Inventory` 的 Outbox/Inbox 流程是第一個提取邊界。

目標目錄、邊界規則與提取準入條件請見
[Microservice Transition Contract](docs/design/microservice-transition.md)。

## 目前能力

- Symfony 8.1、PHP 8.4+、Doctrine ORM、MySQL 8，以及 SQLite 測試環境。
- 身分與存取：RS256 JWT、Refresh Token 輪換、OTP、密碼登入與微信登入介接器。
- CMS、商品/訂單工作流、門市營運、庫存保留、發票、錢包帳本、促銷 DSL 與媒體儲存模組。
- Trade、Store、Inventory 已具備版本化事件與 Outbox/Inbox 模式。
- `/api/doc` OpenAPI、PHPUnit、PHPStan Level 8、Rector 型別檢查與 Docker Compose 開發環境。

Inventory 已實作，但預設關閉且尚未達到正式環境可用標準。安全限制請見
[Inventory design](docs/design/bundles/inventory.md)。

## 快速開始

Docker 是建議的本機執行方式。在儲存庫根目錄執行，主機只需要 Docker：

```bash
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec store-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec inventory-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec payment-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:identity:user:create admin@example.com admin 'P@ssw0rd' --admin

curl -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"identifier":"admin@example.com","password":"P@ssw0rd"}'
```

- API：`http://localhost:8080`
- Store API：`http://localhost:8081`
- Inventory API：`http://localhost:8082`
- Payment runtime 冒煙：`http://localhost:8083`（尚未可切流）
- OpenAPI：`http://localhost:8080/api/doc`
- worker/scheduler 日誌：`docker compose logs -f worker scheduler`

本機 PHP 執行與排錯請參閱 [QUICKSTART.md](QUICKSTART.md)。

## 架構方向

| 目標上下文 | 目前來源 | 目前定位 |
|---|---|---|
| Platform Kernel | `Core` | 共用框架函式庫，不是服務 |
| Commerce | `Trade`、`Promotion` | 過渡服務候選 |
| Store Operations | `Store` → `apps/store` | 已提取；單體在過渡期間託管 |
| Inventory | `Inventory` → `apps/inventory` | 已提取；單體在過渡期間託管，受安全條件限制 |
| Payments | `Payment` → `apps/payment`、微信支付介接器 | 已提取；單體在過渡期間託管 |
| Wallet/Ledger | `Wallet` | 在 Payment 契約解耦後提取 |
| Identity & Access | `Identity`、微信登入介接器 | 後期提取 |
| Content/Media | `Common`、`Storage` | 後期；須先分離 Settings 所有權 |

服務提取前必須具備純量跨服務契約、沒有跨服務 Doctrine 關聯、所需的 Outbox/Inbox，
並獨立擁有佇列、執行環境與部署產物。

## 開發

```bash
./vendor/bin/phpunit
composer deptrac
composer phpstan
composer rector:types:check
mkdocs build --strict
```

本機命令需要 PHP 8.4+。微服務遷移期間，現有完整測試套件會保留為特徵測試。

## 文件

- [Microservice Transition Contract](docs/design/microservice-transition.md)
- [System Architecture](docs/design/system-architecture.md)
- [System Contracts](docs/design/system-contracts.md)
- [Module Design](docs/design/module-design.md)
- [AI Context](docs/ai/context.md)
- [文件站點](https://immane.github.io/crud-skeleton)

## 授權

Apache-2.0。請見 [LICENSE](LICENSE)。
