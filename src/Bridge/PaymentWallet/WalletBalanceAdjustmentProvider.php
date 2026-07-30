<?php

declare(strict_types=1);

namespace App\Bridge\PaymentWallet;

use App\Core\Security\IdentityUserIdResolverInterface;
use App\Payment\DTO\PaymentAdjustmentContext;
use App\Payment\DTO\PaymentAdjustmentResult;
use App\Payment\Entity\Invoice;
use App\Payment\Service\Adjustment\PaymentAdjustmentProviderInterface;
use App\Wallet\DTO\WalletPaymentReference;
use App\Wallet\Entity\WalletPaymentDeduction;
use App\Wallet\Repository\WalletPaymentDeductionRepository;
use App\Wallet\Service\Payment\WalletPaymentDeductionService;

final class WalletBalanceAdjustmentProvider implements PaymentAdjustmentProviderInterface
{
    public function __construct(
        private readonly WalletPaymentDeductionService $deductionService,
        private readonly WalletPaymentDeductionRepository $deductionRepository,
        private readonly IdentityUserIdResolverInterface $identityUserIdResolver,
    ) {}

    public static function getName(): string { return WalletPaymentDeduction::TYPE_WALLET_BALANCE; }

    public function supports(Invoice $invoice, string $payment, array $options): bool
    {
        return $this->deductionService->createRequestFromOptions($invoice->getCurrency(), $options) !== null;
    }

    public function apply(PaymentAdjustmentContext $context): PaymentAdjustmentResult
    {
        $deduction = $this->deductionService->applyFromOptions($this->reference($context->invoice), $context->options);
        if (!$deduction instanceof WalletPaymentDeduction) {
            throw new \RuntimeException('Wallet balance deduction request is missing.');
        }

        return self::resultFromDeduction($deduction);
    }

    public function applied(Invoice $invoice): array
    {
        $deduction = $this->deductionService->findApplied($invoice->getUuid());
        return $deduction instanceof WalletPaymentDeduction ? [self::resultFromDeduction($deduction)] : [];
    }

    public function release(Invoice $invoice, PaymentAdjustmentResult $adjustment, string $reason): PaymentAdjustmentResult
    {
        $deduction = $this->deductionFromResult($adjustment);
        return self::resultFromDeduction($this->deductionService->release($invoice->getUuid(), $reason) ?? $deduction);
    }

    public function refund(Invoice $invoice, PaymentAdjustmentResult $adjustment, string $reason): PaymentAdjustmentResult
    {
        $deduction = $this->deductionFromResult($adjustment);
        return self::resultFromDeduction($this->deductionService->refund($invoice->getUuid(), $reason) ?? $deduction);
    }

    private function reference(Invoice $invoice): WalletPaymentReference
    {
        $payerUuid = $invoice->getPayerUuid();
        $payerId = $payerUuid === null ? null : $this->identityUserIdResolver->resolveIdentityUserId($payerUuid);
        if ($payerId === null) {
            throw new \RuntimeException('Invoice has no payer for wallet deduction.');
        }
        \assert($payerUuid !== null);

        return new WalletPaymentReference($invoice->getUuid(), $invoice->getOutTradeNo(), $payerId, $payerUuid, $invoice->getAmount(), $invoice->getCurrency(), $invoice->getSubject() ?? ('Deduction for invoice ' . $invoice->getOutTradeNo()));
    }

    private function deductionFromResult(PaymentAdjustmentResult $adjustment): WalletPaymentDeduction
    {
        $deduction = $this->deductionRepository->findOneBy(['referenceId' => $adjustment->referenceId]);
        if (!$deduction instanceof WalletPaymentDeduction) {
            throw new \RuntimeException(sprintf('Wallet balance deduction "%s" not found.', $adjustment->referenceId));
        }

        return $deduction;
    }

    private static function resultFromDeduction(WalletPaymentDeduction $deduction): PaymentAdjustmentResult
    {
        return new PaymentAdjustmentResult(self::getName(), $deduction->getAmount(), $deduction->getCurrency(), $deduction->getReferenceId(), array_filter([
            'deductionId' => $deduction->getUuid(),
            'transactionId' => $deduction->getWalletTransactionId(),
            'reversalTransactionId' => $deduction->getReversalTransactionId(),
            'status' => $deduction->getStatus(),
        ], static fn (mixed $value): bool => $value !== null));
    }
}
