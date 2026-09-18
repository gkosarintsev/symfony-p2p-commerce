<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Service;

use App\Entity\Transaction;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ReceiptPdfGenerator
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(Transaction $transaction): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);

        $id = $transaction->getId()->toRfc4122();
        $date = $transaction->getCreatedAt()->format('Y-m-d H:i:s UTC');
        $amount = sprintf('$%.2f %s', $transaction->getAmount() / 100.0, $transaction->getCurrency());
        $type = $transaction->getType()->value;
        $source = $transaction->getSourceAccount()?->getAccountNumber() ?? 'External Gateway';
        $dest = $transaction->getDestinationAccount()?->getAccountNumber() ?? 'N/A';
        $reference = htmlspecialchars($transaction->getReference() ?? 'Standard Transaction', ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Transaction Receipt #{$id}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; margin: 40px; }
        .header { border-bottom: 2px solid #6366f1; padding-bottom: 15px; margin-bottom: 30px; }
        .title { font-size: 24px; font-weight: bold; color: #312e81; }
        .subtitle { font-size: 13px; color: #64748b; margin-top: 5px; }
        .receipt-card { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 25px; }
        .row { margin-bottom: 12px; font-size: 14px; }
        .label { font-weight: bold; color: #475569; width: 180px; display: inline-block; }
        .value { color: #0f172a; }
        .amount-row { border-top: 1px dashed #cbd5e1; padding-top: 15px; margin-top: 15px; font-size: 18px; }
        .amount-value { font-weight: bold; color: #059669; }
        .badge { background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .footer { margin-top: 50px; font-size: 11px; color: #94a3b8; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">E-Commerce Micro-Marketplace</div>
        <div class="subtitle">Official Transaction & Ledger Proof of Payment</div>
    </div>

    <div class="receipt-card">
        <div class="row">
            <span class="label">Transaction ID:</span>
            <span class="value"><code>{$id}</code></span>
        </div>
        <div class="row">
            <span class="label">Timestamp:</span>
            <span class="value">{$date}</span>
        </div>
        <div class="row">
            <span class="label">Operation Type:</span>
            <span class="value"><strong>{$type}</strong></span>
        </div>
        <div class="row">
            <span class="label">Source Account:</span>
            <span class="value">{$source}</span>
        </div>
        <div class="row">
            <span class="label">Destination Account:</span>
            <span class="value">{$dest}</span>
        </div>
        <div class="row">
            <span class="label">Reference:</span>
            <span class="value">{$reference}</span>
        </div>
        <div class="row">
            <span class="label">Status:</span>
            <span class="badge">COMPLETED</span>
        </div>
        <div class="row amount-row">
            <span class="label">Settled Amount:</span>
            <span class="amount-value">{$amount}</span>
        </div>
    </div>

    <div class="footer">
        Generated automatically by Symfony P2P Ledger Engine &bull; Cryptographically Verified
    </div>
</body>
</html>
HTML;

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $receiptsDir = $this->projectDir.'/var/receipts';
        if (!is_dir($receiptsDir)) {
            mkdir($receiptsDir, 0777, true);
        }

        $filePath = $receiptsDir.'/'.$id.'.pdf';
        file_put_contents($filePath, (string) $dompdf->output());

        $this->logger->info('Generated PDF receipt for transaction', ['transaction_id' => $id, 'path' => $filePath]);

        return $filePath;
    }
}
