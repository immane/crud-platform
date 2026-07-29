<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Core\Security\IdentityUserIdResolverInterface;
use App\Identity\Repository\UserRepository;
use App\Payment\Service\PayerDirectoryService;
use App\Payment\Service\PayerReferenceResolverInterface;

final class IdentityPayerDirectoryAdapter implements PayerReferenceResolverInterface, IdentityUserIdResolverInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PayerDirectoryService $payerDirectoryService,
    ) {}

    public function resolve(string $reference): ?string
    {
        if (!ctype_digit($reference)) {
            return null;
        }
        $userId = (int) $reference;
        $user = $this->userRepository->find($userId);
        if ($user === null || $user->getId() === null) {
            return null;
        }

        $this->payerDirectoryService->upsert($user->getId(), $user->getUuid());

        return $user->getUuid();
    }

    public function resolveIdentityUserId(string $userUuid): ?int
    {
        return $this->userRepository->findByUuid($userUuid)?->getId();
    }
}
