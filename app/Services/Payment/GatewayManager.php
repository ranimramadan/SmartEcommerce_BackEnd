<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentGateway;
use InvalidArgumentException;

class GatewayManager
{
    /** @var PaymentGateway[] */
    protected array $drivers = [];

    public function register(PaymentGateway $gateway): void
    {
        $this->drivers[$gateway->code()] = $gateway;
    }

    public function driver(string $code): PaymentGateway
    {
        if (! isset($this->drivers[$code])) {
            throw new InvalidArgumentException("Payment gateway [$code] not registered.");
        }
        return $this->drivers[$code];
    }

    public function all(): array
    {
        return $this->drivers;
    }
}
