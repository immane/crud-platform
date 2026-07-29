<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Inventory\Command\BackfillOutboxCorrelationCommand as InventoryBackfillCommand;
use App\Inventory\Entity\InventoryOutboxMessage;
use App\Inventory\Repository\InventoryOutboxMessageRepository;
use App\Store\Command\BackfillOutboxCorrelationCommand as StoreBackfillCommand;
use App\Store\Entity\StoreOutboxMessage;
use App\Store\Repository\StoreOutboxMessageRepository;
use App\Tests\Integration\DatabaseBootstrapTrait;
use App\Tests\Integration\IntegrationWebTestCase;
use App\Trade\Command\BackfillOutboxCorrelationCommand as TradeBackfillCommand;
use App\Trade\Entity\TradeOutboxMessage;
use App\Trade\Repository\TradeOutboxMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class OutboxCorrelationBackfillCommandTest extends IntegrationWebTestCase
{
    use DatabaseBootstrapTrait;

    protected function setUp(): void
    {
        $this->bootTestDatabase();
        self::ensureKernelShutdown();

        $client = static::createClient();
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        foreach ([
            'App\\Trade\\Entity\\TradeOutboxMessage',
            'App\\Store\\Entity\\StoreOutboxMessage',
            'App\\Inventory\\Entity\\InventoryOutboxMessage',
        ] as $entity) {
            $entityManager->createQuery(sprintf('DELETE FROM %s message', $entity))->execute();
        }
        self::ensureKernelShutdown();
    }

    public function testCommandsDryRunThenBackfillOnlyUnpublishedRows(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $trade = new TradeOutboxMessage('trade.order.created.v1', 'trade_order', 'trade-order', []);
        $publishedTrade = new TradeOutboxMessage('trade.order.cancelled.v1', 'trade_order', 'published-trade-order', []);
        $store = new StoreOutboxMessage('store.order.accepted.v1', 'store_order', 'store-order', []);
        $inventory = new InventoryOutboxMessage('inventory.reservation.confirmed.v1', 'inventory_reservation', 'reservation', []);
        foreach ([$trade, $publishedTrade, $store, $inventory] as $message) {
            $entityManager->persist($message);
        }
        $entityManager->flush();
        $publishedTrade->markPublished();
        $entityManager->flush();

        $ids = [
            TradeOutboxMessage::class => $trade->getId(),
            StoreOutboxMessage::class => $store->getId(),
            InventoryOutboxMessage::class => $inventory->getId(),
        ];
        foreach ($ids as $entity => $id) {
            self::assertNotNull($id);
            $entityManager->createQuery(sprintf('UPDATE %s message SET message.correlationId = NULL WHERE message.id = :id', $entity))
                ->setParameter('id', $id)
                ->execute();
        }
        $entityManager->createQuery('UPDATE App\\Trade\\Entity\\TradeOutboxMessage message SET message.correlationId = NULL WHERE message.id = :id')
            ->setParameter('id', $publishedTrade->getId())
            ->execute();
        $entityManager->clear();

        $commands = [
            [new TradeBackfillCommand($container->get(TradeOutboxMessageRepository::class)), TradeOutboxMessage::class, $ids[TradeOutboxMessage::class]],
            [new StoreBackfillCommand($container->get(StoreOutboxMessageRepository::class)), StoreOutboxMessage::class, $ids[StoreOutboxMessage::class]],
            [new InventoryBackfillCommand($container->get(InventoryOutboxMessageRepository::class)), InventoryOutboxMessage::class, $ids[InventoryOutboxMessage::class]],
        ];

        foreach ($commands as [$command, $entity, $id]) {
            $tester = new CommandTester($command);
            self::assertSame(0, $tester->execute(['--limit' => '1']));
            self::assertStringContainsString('Would backfill 1 unpublished', $tester->getDisplay());

            self::assertSame(0, $tester->execute(['--limit' => '1', '--apply' => true]));
            self::assertStringContainsString('Backfilled 1 unpublished', $tester->getDisplay());

            $entityManager->clear();
            $message = $container->get(match ($entity) {
                TradeOutboxMessage::class => TradeOutboxMessageRepository::class,
                StoreOutboxMessage::class => StoreOutboxMessageRepository::class,
                InventoryOutboxMessage::class => InventoryOutboxMessageRepository::class,
            })->find($id);
            self::assertNotNull($message);
            self::assertSame($message->getEventId(), $message->getCorrelationId());
            self::assertNull($message->getCausationId());
        }
    }
}
