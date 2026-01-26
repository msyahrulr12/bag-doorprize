<?php

namespace App\Filament\Resources\Branches\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Branch Information')
                    ->schema([
                        TextInput::make('branch_code')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('sk_branch', $state);
                            }),
                        TextInput::make('branch_name')
                            ->required(),
                        Select::make('region')
                            ->options(\App\Models\Branch::REGIONS)
                            ->required(),
                        Textarea::make('address')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make('Aditional Information')
                    ->schema([
                        TextInput::make('sk_branch'),
                        TextInput::make('sandi_pelapor_kantor_lbu'),
                        TextInput::make('nama_sandi_pelapor'),
                        TextInput::make('company_book'),
                        TextInput::make('company_name'),
                        Textarea::make('name_address')
                            ->columnSpanFull(),
                        DatePicker::make('date_from'),
                        DatePicker::make('date_to'),
                        TextInput::make('version'),
                        TextInput::make('wib'),
                        DatePicker::make('update_date'),
                        TextInput::make('update_regional1'),
                        DatePicker::make('update_date1'),
                        TextInput::make('new_regional_head'),
                    ])->columns(2),
            ]);
    }
}
