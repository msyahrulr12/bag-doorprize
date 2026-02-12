<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Product extends Model implements Auditable
{
    use SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'sk_produk',
        'kode_group_produk',
        'group_produk',
        'kode_produk',
        'nama_produk',
        'nama_singkat_produk',
        'kode_sub_produk',
        'nama_sub_produk',
        'gol_mas',
        'date_time',
        'batch_date',
        'insert_date',
    ];
}
