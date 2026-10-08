<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:retry-refunds')]
#[Description('Vuelve a intentar los reembolsos de pedidos cancelados que la pasarela no pudo devolver')]
class RetryRefunds extends Command
{
    public function handle(PaymentService $payments): int
    {
        $done = $payments->retryPendingRefunds();

        $this->components->info("Pedidos reembolsados por completo: {$done}.");

        return self::SUCCESS;
    }
}
