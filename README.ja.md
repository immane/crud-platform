# CRUD Platform

CRUD Platform は `crud-skeleton` から発展した Symfony 8.1 バックエンドです。
モジュール化された CRUD 基盤を出発点として、複数アプリケーションのマイクロサービス
アーキテクチャへ段階的に移行します。

> English: [README.md](README.md) · 简体中文: [README.zh-cn.md](README.zh-cn.md) · 繁體中文: [README.zh-hant.md](README.zh-hant.md)

## プロジェクトの目標

目標は、**単一リポジトリ内で独立してデプロイできる複数の Symfony アプリケーション**です。
各サービスは Kernel、設定、データベースとマイグレーション、キュー、worker、定期ジョブ、
Docker イメージ、テスト、CI を所有します。

現在は完成済みのマイクロサービスではなく、**モジュラーモノリス**です。Kernel、Composer
プロジェクト、コンテナ、データベース、マイグレーション履歴、Messenger キュー、worker、
scheduler、Docker イメージを共有しています。`Trade -> Store -> Inventory` の
Outbox/Inbox フローが最初の抽出境界です。

目標ディレクトリ、境界ルール、抽出条件は
[Microservice Transition Contract](docs/design/microservice-transition.md) を参照してください。

## 現在の機能

- Symfony 8.1、PHP 8.4+、Doctrine ORM、MySQL 8、SQLite テスト環境。
- ID とアクセス: RS256 JWT、Refresh Token ローテーション、OTP、パスワードログイン、
  WeChat ログインアダプタ。
- CMS、商品/注文ワークフロー、店舗運用、在庫予約、請求書、ウォレット台帳、
  プロモーション DSL、メディアストレージのモジュール。
- Trade、Store、Inventory のバージョン付きイベントと Outbox/Inbox パターン。
- `/api/doc` の OpenAPI、PHPUnit、PHPStan Level 8、Rector 型チェック、
  Docker Compose 開発環境（22 サービス）。

Inventory は実装済みですが、既定で無効であり、本番利用可能な状態ではありません。
制約は [Inventory design](docs/design/bundles/inventory.md) を参照してください。

## クイックスタート

Docker が推奨されるローカル実行方法です。リポジトリのルートで実行してください。
ホスト側に必要なのは Docker だけです。

```bash
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec store-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec inventory-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec payment-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec wallet-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec identity-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec common-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec trade-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:identity:user:create admin@example.com admin 'P@ssw0rd' --admin

curl -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"identifier":"admin@example.com","password":"P@ssw0rd"}'
```

- API: `http://localhost:8080`
- Store API: `http://localhost:8081`
- Inventory API: `http://localhost:8082`
- Payment runtime smoke: `http://localhost:8083`（トラフィック切替前）
- Wallet runtime smoke: `http://localhost:8084`（トラフィック切替前）
- Identity runtime smoke: `http://localhost:8085`（トラフィック切替前）
- Common runtime smoke: `http://localhost:8086`（トラフィック切替前）
- Trade runtime smoke: `http://localhost:8087`（トラフィック切替前）
- OpenAPI: `http://localhost:8080/api/doc`
- worker/scheduler ログ: `docker compose logs -f worker scheduler`

ネイティブ PHP の実行とトラブルシューティングは [QUICKSTART.md](QUICKSTART.md) を参照してください。

## アーキテクチャの方向性

| 対象コンテキスト | 現在のソース | 位置付け |
|---|---|---|
| Platform Kernel | `Core` | 共通フレームワークライブラリ。サービスではない |
| Commerce | `Trade`、`Promotion` → `apps/trade` | 抽出済み。移行中はモノリスがホスト |
| Store Operations | `Store` → `apps/store` | 抽出済み。移行中はモノリスがホスト |
| Inventory | `Inventory` → `apps/inventory` | 抽出済み。移行中はモノリスがホスト。安全条件付き |
| Payments | `Payment` → `apps/payment`、WeChat Pay アダプタ | 抽出済み。ゲートウェイは Payment が所有 |
| Wallet/Ledger | `Wallet` → `apps/wallet` | 抽出済み。移行中はモノリスがホスト |
| Identity & Access | `Identity` → `apps/identity`、WeChat ログインアダプタ | 抽出済み。移行中はモノリスがホスト |
| Content/Media | `Common`、`Storage` → `apps/common` | 抽出済み。移行中はモノリスがホスト |

サービスを抽出する前に、スカラーなサービス間契約、サービス間 Doctrine 関連の排除、
必要な Outbox/Inbox、キュー・実行環境・デプロイ成果物の独立所有が必要です。

## 開発

```bash
./vendor/bin/phpunit
composer deptrac
composer phpstan
composer rector:types:check
composer coverage                # 全スイート実行 + 集約カバレッジ >= 90%
mkdocs build --strict
```

ローカルコマンドには PHP 8.4+ が必要です。7 つの独立アプリテストスイート
（common、identity、inventory、payment、store、trade、wallet）が
ルート統合テストと共に独立して実行され、集約行カバレッジは 91.36% です。

## ドキュメント

- [Microservice Transition Contract](docs/design/microservice-transition.md)
- [System Architecture](docs/design/system-architecture.md)
- [System Contracts](docs/design/system-contracts.md)
- [Module Design](docs/design/module-design.md)
- [AI Context](docs/ai/context.md)
- [ドキュメントサイト](https://immane.github.io/crud-skeleton)

## ライセンス

Apache-2.0。詳細は [LICENSE](LICENSE) を参照してください。
