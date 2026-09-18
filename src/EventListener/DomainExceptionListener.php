<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Domain\Account\Exception\AccountNotFoundException;
use App\Domain\Account\Exception\InsufficientFundsException;
use App\Domain\Account\Exception\InvalidAmountException;
use App\Domain\Account\Exception\SelfTransferException;
use App\Domain\Idempotency\Exception\IdempotencyConflictException;
use App\Domain\Product\Exception\ProductAlreadySoldException;
use App\Domain\Product\Exception\ProductNotActiveException;
use App\Domain\Product\Exception\ProductNotFoundException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener(event: 'kernel.exception', priority: 10)]
final class DomainExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        // Unwrap messenger HandlerFailedException if applicable
        if ($throwable instanceof \Symfony\Component\Messenger\Exception\HandlerFailedException) {
            $previous = $throwable->getPrevious();
            if ($previous instanceof \Throwable) {
                $throwable = $previous;
            }
        }

        $statusCode = match (true) {
            $throwable instanceof InsufficientFundsException => Response::HTTP_UNPROCESSABLE_ENTITY,
            $throwable instanceof SelfTransferException => Response::HTTP_UNPROCESSABLE_ENTITY,
            $throwable instanceof InvalidAmountException => Response::HTTP_BAD_REQUEST,
            $throwable instanceof AccountNotFoundException => Response::HTTP_NOT_FOUND,
            $throwable instanceof ProductNotFoundException => Response::HTTP_NOT_FOUND,
            $throwable instanceof ProductAlreadySoldException => Response::HTTP_CONFLICT,
            $throwable instanceof ProductNotActiveException => Response::HTTP_UNPROCESSABLE_ENTITY,
            $throwable instanceof IdempotencyConflictException => Response::HTTP_CONFLICT,
            default => null,
        };

        if (null === $statusCode) {
            return;
        }

        $problem = [
            'type' => 'https://tools.ietf.org/html/rfc7807',
            'title' => (new \ReflectionClass($throwable))->getShortName(),
            'status' => $statusCode,
            'detail' => $throwable->getMessage(),
        ];

        $response = new JsonResponse($problem, $statusCode, [
            'Content-Type' => 'application/problem+json',
        ]);

        $event->setResponse($response);
    }
}
