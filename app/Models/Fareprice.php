<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Fareprice extends Model
{
    use HasFactory;

    protected $table = 'fareprices';
    protected $fillable = [
        'tdms_product_id',
        'json',
        'agent_branch',
        'version'
    ];

    protected $casts = ['json' => 'array'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($fareprice) {
            DB::transaction(function () use ($fareprice) {
                // Fetch the latest fareprice based on the 'tdms_product_id'
                $latestFareprice = static::where('tdms_product_id', $fareprice->tdms_product_id)
                    ->orderBy('version', 'desc')
                    ->first();

                if ($latestFareprice) {
                    // Set the new fareprice's version to the latest + 1
                    $fareprice['version'] = $latestFareprice->version + 1;
                } else {
                    // If this is the first fare$fareprice for the given 'tdms_product_id'
                    $fareprice['version'] = 1;
                }
            });
        });
    }
}
