<?php

declare(strict_types=1);

namespace App\Domain\Notification\MessageHandler;

use App\Domain\Notification\Message\SendPurchaseNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendPurchaseNotificationHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(SendPurchaseNotificationMessage $message): void
    {
        $amountFormatted = sprintf('$%.2f', $message->amountCents / 100.0);

        // Simulate sending email to buyer
        $this->logger->info(sprintf(
            '[NOTIFICATION SENT] Email to BUYER <%s>: Your purchase of "%s" for %s was confirmed! Order ID: %s',
            $message->buyerEmail,
            $message->productTitle,
            $amountFormatted,
            $message->orderId
        ));

        // Simulate sending email to seller
        $this->logger->info(sprintf(
            '[NOTIFICATION SENT] Email to SELLER <%s>: Your item "%s" was sold for %s! Funds credited to your wallet. Order ID: %s',
            $message->sellerEmail,
            $message->productTitle,
            $amountFormatted,
            $message->orderId
        ));
    }
}
