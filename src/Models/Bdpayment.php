<?php

/**
 * Bdpayment Model
 *
 * Represents a payment transaction for Bkash, Nagad, or other gateways.
 * Handles invoice generation, user and payable relations.
 *
 * @package RmdMostakim\BdPayment\Models
 */

namespace RmdMostakim\BdPayment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Class Bdpayment
 *
 * @property int|null $product_id
 * @property int|null $user_id
 * @property string $invoice
 * @property string $mode
 * @property string|null $transaction_id
 * @property float $amount
 * @property string $currency
 * @property string|null $status
 * @property string|null $note
 * @property string|null $sender_name
 * @property string|null $sender_phone
 * @property string|null $receiver_account
 * @property string|null $bank_transaction_id
 * @property string|null $card_type
 * @property string|null $card_no
 * @property string|null $bank_name
 * @property string|null $account_number
 * @property string|null $branch_name
 * @property int|null $payable_id
 * @property string|null $payable_type
 * @property string|null $paid_at
 * @property-read \App\Models\User|null $user
 * @method static string generateUniqueInvoiceId()
 */
class Bdpayment extends Model
{
    use SoftDeletes;

    /**
     * Fillable attributes for mass assignment.
     * @var array
     */
    protected $fillable = [
        'product_id',
        'user_id',
        'invoice',
        'mode',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'note',
        'sender_name',
        'sender_phone',
        'receiver_account',
        'bank_transaction_id',
        'card_type',
        'card_no',
        'bank_name',
        'account_number',
        'branch_name',
        'payable_id',
        'payable_type',
        'paid_at',
    ];

    /**
     * Default attribute values.
     * @var array
     */
    protected $attributes = [
        'product_id' => null,
        'user_id' => null,
    ];

    /**
     * Booted event for model.
     * Generates invoice if not set.
     */
    protected static function booted()
    {
        static::creating(function ($payment) {
            // If invoice is not set, generate a unique one
            if (empty($payment->invoice)) {
                $payment->invoice = self::generateUniqueInvoiceId();
            }
        });
    }

    /**
     * Generate a unique invoice ID in the format: INVYYYYMMDDXXXXXX
     *
     * @return string
     */
    protected static function generateUniqueInvoiceId(): string
    {
        do {
            $invoiceId = 'INV' . now()->format('Ymd') . strtoupper(Str::random(6));
        } while (self::where('invoice', $invoiceId)->exists());

        return $invoiceId;
    }

    /**
     * Polymorphic relation to payable entity.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function payable()
    {
        return $this->morphTo();
    }

    /**
     * Relation to User model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for filtering by multiple fields.
     */
    public function scopeFilter($query, array $filters)
    {
        return $query
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['mode'] ?? null, fn($q, $mode) => $q->where('mode', $mode))
            ->when($filters['user_id'] ?? null, fn($q, $user) => $q->where('user_id', $user))
            ->when($filters['min_amount'] ?? null, fn($q, $min) => $q->where('amount', '>=', $min))
            ->when($filters['max_amount'] ?? null, fn($q, $max) => $q->where('amount', '<=', $max))
            ->when($filters['from'] ?? null, fn($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn($q, $to) => $q->whereDate('created_at', '<=', $to));
    }

    /**
     * Get all payments with filtering, sorting, and pagination.
     */
    public static function getAll(array $filters = [], string $sortBy = 'created_at', string $sortDir = 'desc', int $perPage = 15)
    {
        return self::query()
            ->filter($filters)
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    /**
     * Find payment by invoice.
     */
    public static function findByInvoice(string $invoice): ?self
    {
        return self::where('invoice', $invoice)->first();
    }
}
