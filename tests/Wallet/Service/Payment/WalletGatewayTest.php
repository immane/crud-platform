<?php

declare(strict_types=1);

namespace App\Tests\Wallet\Service\Payment;

use App\Bridge\PaymentWallet\WalletGateway;
use App\Core\Security\IdentityUserIdResolverInterface;
use App\Payment\DTO\PaymentNotifyResult;
use App\Payment\Entity\Invoice;
use App\Payment\Exception\PaymentVerificationException;
use App\Wallet\Entity\WalletTransaction;
use App\Wallet\Service\TransferResult;
use App\Wallet\Service\WalletPaymentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class WalletGatewayTest extends TestCase
{
    public function testGetNameAndNotifyResponse(): void
    {
        $gateway = $this->gateway();
        self::assertSame(Invoice::PAYMENT_WALLET, $gateway::getName());
        self::assertSame('SUCCESS', $gateway->getNotifySuccessResponse(new PaymentNotifyResult(Invoice::PAYMENT_WALLET, 'TEST', Invoice::STATUS_PAID, 100, 'OK'))->getContent());
    }

    public function testNotifyRejectsExternalCallbacks(): void
    {
        $this->expectException(PaymentVerificationException::class);
        $this->gateway()->notify(new Request());
    }

    public function testPayRequiresPayer(): void
    {
        $this->expectExceptionMessage('Invoice has no payer for wallet payment.');
        $this->gateway()->pay(new Invoice(), 100);
    }

    public function testRefundRequiresSystemWallet(): void
    {
        $invoice = (new Invoice())->setPayerUuid('payer')->setCurrency('CNY');
        $this->expectExceptionMessage('systemWalletId is required for wallet refund.');
        $this->gateway(1, null)->refund($invoice, 100, 100, 'test');
    }

    public function testPayDelegatesToWalletService(): void
    {
        $invoice = (new Invoice())->setPayerUuid('payer')->setCurrency('CNY')->setOutTradeNo('ORDER-1');
        $service = $this->createMock(WalletPaymentService::class);
        $service->expects(self::once())->method('pay')->with('payer', 'CNY', 2, 100, 'invoice-pay-ORDER-1', 'Payment for invoice ORDER-1')->willReturn(new TransferResult(new WalletTransaction('transaction-1', 100, 'transfer'), 0, 0));

        $result = $this->gateway(1, 2, $service)->pay($invoice, 100);
        self::assertSame(Invoice::STATUS_PAID, $result->status);
        self::assertSame('transaction-1', $result->payload['transactionId']);
    }

    private function gateway(?int $payerId = null, ?int $systemWalletId = 2, ?WalletPaymentService $service = null): WalletGateway
    {
        return new WalletGateway($service ?? $this->createMock(WalletPaymentService::class), $systemWalletId);
    }
}
