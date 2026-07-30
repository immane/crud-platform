<?php

declare(strict_types=1);

namespace App\Bridge\PaymentWallet;

use App\Core\Security\IdentityUserIdResolverInterface;
use App\Payment\DTO\PaymentNotifyResult;
use App\Payment\DTO\PaymentRefundResult;
use App\Payment\DTO\PaymentResult;
use App\Payment\Entity\Invoice;
use App\Payment\Exception\PaymentVerificationException;
use App\Payment\Service\PaymentGatewayInterface;
use App\Wallet\Service\WalletPaymentService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WalletGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly WalletPaymentService $walletPaymentService,
        private readonly IdentityUserIdResolverInterface $identityUserIdResolver,
        #[Autowire('%payment.system_wallet_id%')]
        private readonly ?int $systemWalletId = null,
    ) {}

    public static function getName(): string { return Invoice::PAYMENT_WALLET; }

    public function pay(Invoice $invoice, int $amount, array $options = []): PaymentResult
    {
        $payerId = $this->payerId($invoice, 'payment');
        $systemWalletId = $this->systemWalletId($options, 'payment');
        $transfer = $this->walletPaymentService->pay($payerId, $invoice->getCurrency(), $systemWalletId, $amount, 'invoice-pay-' . $invoice->getOutTradeNo(), $invoice->getSubject() ?? ('Payment for invoice ' . $invoice->getOutTradeNo()));

        return new PaymentResult(
            invoice: $invoice,
            status: Invoice::STATUS_PAID,
            payload: [
                'transactionId' => $transfer->transaction->getUuid(),
                'fromWalletId' => $transfer->transaction->getFromWallet()?->getId(),
                'toWalletId' => $transfer->transaction->getToWallet()?->getId(),
            ],
            message: 'Wallet payment completed',
        );
    }

    public function notify(Request $request): PaymentNotifyResult
    {
        throw new PaymentVerificationException('Wallet gateway does not accept external notify callbacks.');
    }

    public function refund(Invoice $invoice, int $amount, int $paidAmount, string $reason, array $options = []): PaymentRefundResult
    {
        $payerId = $this->payerId($invoice, 'refund');
        $systemWalletId = $this->systemWalletId($options, 'refund');
        $transfer = $this->walletPaymentService->refund($payerId, $invoice->getCurrency(), $systemWalletId, $amount, 'invoice-refund-' . $invoice->getOutTradeNo() . '-' . ($invoice->getRefundedAmount() + $amount), $reason);

        return new PaymentRefundResult($invoice, $amount, $amount >= ($paidAmount - $invoice->getRefundedAmount()) ? Invoice::STATUS_REFUNDED : Invoice::STATUS_PARTIAL_REFUNDED, $transfer->transaction->getUuid(), ['reason' => $reason, 'transactionId' => $transfer->transaction->getUuid()]);
    }

    public function getNotifySuccessResponse(PaymentNotifyResult $result): Response
    {
        return new Response($result->responseBody, 200, ['Content-Type' => 'text/plain']);
    }

    /** @param array<string, mixed> $options */
    private function systemWalletId(array $options, string $operation): int
    {
        $systemWalletId = (int) ($options['systemWalletId'] ?? $this->systemWalletId ?? 0);
        if ($systemWalletId <= 0) {
            throw new \InvalidArgumentException(sprintf('systemWalletId is required for wallet %s.', $operation));
        }

        return $systemWalletId;
    }

    private function payerId(Invoice $invoice, string $operation): int
    {
        $payerUuid = $invoice->getPayerUuid();
        $payerId = $payerUuid === null ? null : $this->identityUserIdResolver->resolveIdentityUserId($payerUuid);
        if ($payerId === null) {
            throw new \RuntimeException(sprintf('Invoice has no payer for wallet %s.', $operation));
        }

        return $payerId;
    }
}
