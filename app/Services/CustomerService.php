<?php

namespace App\Services;

use App\Models\Customer;

class CustomerService
{
    public static function getEmptyNikAndNpwp(): array
    {
        $customers = Customer::where(function ($query) {
                $query->whereNull('nik')
                    ->orWhere('nik', '');
            })
            ->orWhere(function ($query) {
                $query->whereNull('npwp')
                    ->orWhere('npwp', '');
            })
            ->pluck('id', 'cif')
            ->toArray();

        return $customers;
    }
}
