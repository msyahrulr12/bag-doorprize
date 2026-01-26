<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sk_produk')
                    ->required(),
                TextInput::make('kode_group_produk')
                    ->required()
                    ->numeric(),
                TextInput::make('group_produk')
                    ->required(),
                TextInput::make('kode_produk')
                    ->required(),
                TextInput::make('nama_produk')
                    ->required(),
                TextInput::make('nama_singkat_produk')
                    ->required(),
                TextInput::make('kode_sub_produk'),
                TextInput::make('nama_sub_produk')
                    ->required(),
                TextInput::make('gol_mas'),
                DatePicker::make('date_time'),
                DatePicker::make('batch_date'),
                DatePicker::make('insert_date'),
            ]);
    }
}
