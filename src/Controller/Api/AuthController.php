<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Account\Service\AccountNumberGenerator;
use App\Entity\Account;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        AccountNumberGenerator $accountNumberGenerator,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = $request->getPayload()->all();
        if (empty($data)) {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'A valid email address is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 6) {
            return $this->json(['error' => 'Password must be at least 6 characters.'], Response::HTTP_BAD_REQUEST);
        }

        if (null !== $userRepository->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'A user with this email already exists.'], Response::HTTP_CONFLICT);
        }

        $user = new User($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $accountNumber = $accountNumberGenerator->generateUnique();
        $account = new Account($user, $accountNumber->getValue());
        $user->setAccount($account);

        $em->persist($user);
        $em->persist($account);
        $em->flush();

        return $this->json([
            'message' => 'User registered successfully.',
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'accountNumber' => $account->getAccountNumber(),
                'balance' => $account->getBalance(),
                'currency' => $account->getCurrency(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        $data = $request->getPayload()->all();
        if (empty($data)) {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $user = $userRepository->findOneBy(['email' => $email]);
        if (null === $user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid email or password.'], Response::HTTP_UNAUTHORIZED);
        }

        $account = $user->getAccount();

        return $this->json([
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'accountNumber' => $account?->getAccountNumber(),
                'balance' => $account?->getBalance() ?? 0,
                'balanceFormatted' => sprintf('$%.2f', ($account?->getBalance() ?? 0) / 100.0),
                'currency' => $account?->getCurrency() ?? 'USD',
            ],
        ]);
    }
}
