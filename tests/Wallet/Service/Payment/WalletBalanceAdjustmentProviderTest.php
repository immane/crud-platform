<?php

declare(strict_types=1);

namespace App\Tests\Wallet\Service\Payment;

use App\Bridge\PaymentWallet\WalletBalanceAdjustmentProvider;
use App\Core\Security\IdentityUserIdResolverInterface;
use App\Payment\DTO\PaymentAdjustmentContext;
use App\Payment\Entity\Invoice;
use App\Wallet\Entity\WalletPaymentDeduction;
use App\Wallet\Repository\WalletPaymentDeductionRepository;
use App\Wallet\Service\Payment\WalletPaymentDeductionService;
use PHPUnit\Framework\TestCase;

final class WalletBalanceAdjustmentProviderTest extends TestCase
{
    public function testSupportsUsesScalarCurrency(): void
    {
        $service = $this->createMock(WalletPaymentDeductionService::class);
        $service->expects(self::once())->method('createRequestFromOptions')->with('CNY', ['walletAmount' => 300])->willReturn(null);

        self::assertFalse($this->provider($service)->supports((new Invoice())->setCurrency('CNY'), 'mock', ['walletAmount' => 300]));
    }

    public function testApplyMapsInvoiceToWalletReference(): void
    {
        $invoice = (new Invoice())->setPayerUuid('payer')->setCurrency('CNY')->setAmount(1000)->setOutTradeNo('ORDER-1');
        $deduction = $this->createMock(WalletPaymentDeduction::class);
        $deduction->method('getAmount')->willReturn(300);
        $deduction->method('getCurrency')->willReturn('CNY');
        $deduction->method('getReferenceId')->willReturn('deduction-1');
        $deduction->method('getUuid')->willReturn('uuid');
        $deduction->method('getStatus')->willReturn(WalletPaymentDeduction::STATUS_APPLIED);

        $service = $this->createMock(WalletPaymentDeductionService::class);
        $service->expects(self::once())->method('applyFromOptions')->willReturn($deduction);
        $result = $this->provider($service, 1)->apply(new PaymentAdjustmentContext($invoice, 'mock', 1000, 'CNY', ['walletAmount' => 300]));

        self::assertSame('deduction-1', $result->referenceId);
        self::assertSame(300, $result->amount);
    }

    public function testApplyRequiresPayer(): void
    {
        $this->expectExceptionMessage('Invoice has no payer for wallet deduction.');
        $this->provider($this->createMock(WalletPaymentDeductionService::class))->apply(new PaymentAdjustmentContext(new Invoice(), 'mock', 1000, 'CNY'));
    }

    private function provider(WalletPaymentDeductionService $service, ?int $payerId = null): WalletBalanceAdjustmentProvider
    {
        $resolver = $this->createMock(IdentityUserIdResolverInterface::class);
        $resolver->method('resolveIdentityUserId')->willReturn($payerId);

        return new WalletBalanceAdjustmentProvider($service, $this->createMock(WalletPaymentDeductionRepository::class), $resolver);
    }
}
