<?php

declare(strict_types=1);

namespace App\Wallet\Service;

use App\Wallet\Repository\WalletRepository;

class WalletPaymentService
{
    public function __construct(
        private readonly WalletRepository $walletRepository,
        private readonly TransferServiceInterface $transferService,
    ) {}

    public function pay(int $payerId, string $currency, int $systemWalletId, int $amount, string $referenceId, string $description): TransferResult
    {
        return $this->transfer($payerId, $currency, $systemWalletId, $amount, $referenceId, $description, false);
    }

    public function refund(int $payerId, string $currency, int $systemWalletId, int $amount, string $referenceId, string $description): TransferResult
    {
        return $this->transfer($payerId, $currency, $systemWalletId, $amount, $referenceId, $description, true);
    }

    private function transfer(int $payerId, string $currency, int $systemWalletId, int $amount, string $referenceId, string $description, bool $refund): TransferResult
    {
        $wallet = $this->walletRepository->findByUserAndCurrency($payerId, $currency);
        if ($wallet === null || $wallet->getId() === null) {
            throw new \RuntimeException(sprintf('No %s wallet found for payer.', $currency));
        }

        return $refund
            ? $this->transferService->transfer($systemWalletId, $wallet->getId(), $amount, $referenceId, $description)
            : $this->transferService->transfer($wallet->getId(), $systemWalletId, $amount, $referenceId, $description);
    }
}
