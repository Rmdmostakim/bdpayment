<?php


namespace RmdMostakim\BdPayment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;


class Bdpayment extends Model
{
    use SoftDeletes;
    protected $fillable = [
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
     * Generate a unique invoice ID in the format: INV-YYYYMMDD-XXXXXX
     */
    protected static function generateUniqueInvoiceId(): string
    {
        do {
            $invoiceId = 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (self::where('invoice', $invoiceId)->exists());


        return $invoiceId;
    }


    /**
     * Define the polymorphic relation.
     */
    public function payable()
    {
        return $this->morphTo();
    }


    /**
     * Define the user relation.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
