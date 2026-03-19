<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * History of attempts (success or failure) of transactions with gateways.
 * Each gateway call (create customer, charge, pay with card) can be recorded
 * for tracking and auditing.
 */
class GatewayTransaction extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PENDING = 'pending';

    protected $table = 'gateway_transactions';

    protected $fillable = [
        'billing_id',
        'customer_id',
        'payment_id',
        'external_id',
        'credit_card_id',
        'card_display',
        'payload',
        'response',
        'message',
        'amount',
        'status',
        'gateway',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'amount' => 'decimal:2',
    ];

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class, 'credit_card_id');
    }

    /**
     * Record an attempt in history (success or failure).
     *
     * @param  array<string, mixed>|object|null  $payload
     * @param  array<string, mixed>|object|null  $response
     */
    public static function record(
        int $billingId,
        ?int $customerId,
        ?int $paymentId,
        ?string $externalId,
        ?int $creditCardId,
        ?string $cardDisplay,
        $payload,
        $response,
        ?string $message,
        $amount,
        string $status,
        string $gateway
    ): self {
        return self::create([
            'billing_id' => $billingId,
            'customer_id' => $customerId,
            'payment_id' => $paymentId,
            'external_id' => $externalId,
            'credit_card_id' => $creditCardId,
            'card_display' => $cardDisplay,
            'payload' => self::normalizeToArray($payload),
            'response' => self::normalizeToArray($response),
            'message' => $message,
            'amount' => $amount !== null && $amount !== '' ? (float) $amount : null,
            'status' => $status,
            'gateway' => $gateway,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function normalizeToArray($value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : ['_raw' => $value];
        }
        return ['_raw' => $value];
    }
}
