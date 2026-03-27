<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Models\Account;
use App\Models\PointHistory;
use App\Models\LotteryTicket;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class PointCorrection extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static string|UnitEnum|null $navigationGroup = 'Point Management';
    protected static ?string $title = 'Point & Ticket Correction';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.point-correction';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer & Point Adjustment')
                    ->description('Adjust points for a customer account for a specific period. These corrections are cumulative and will be added to or subtracted from existing points.')
                    ->schema([
                        Select::make('account_id')
                            ->label('Account')
                            ->getSearchResultsUsing(
                                fn(string $search): array => Account::query()
                                    ->with(['customer', 'branch'])
                                    ->when(!auth()->user()->hasRole('super_admin'), function ($query) {
                                        $query->whereIn('branch_id', auth()->user()->branches->pluck('id'));
                                    })
                                    ->where(function ($q) use ($search) {
                                        $q->where('account_number', 'ilike', "%{$search}%")
                                            ->orWhereHas('customer', fn($query) => $query->where('name', 'ilike', "%{$search}%"));
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($account) => [$account->id => "{$account->account_number} - " . ($account->customer?->name ?? 'N/A')])
                                    ->toArray()
                            )
                            ->afterStateUpdated(
                                function ($state, $set) {
                                    if (!$state) {
                                        $set('current_points', 0);
                                        return;
                                    }

                                    $totalPoints = PointHistory::where('account_id', $state)->sum('points');

                                    $set('current_points', (int) $totalPoints);
                                }
                            )
                            ->getOptionLabelUsing(function ($value): ?string {
                                $account = Account::with('customer')->find($value);
                                return $account ? "{$account->account_number} - " . ($account->customer?->name ?? 'N/A') : null;
                            })
                            ->searchable()
                            ->required()
                            ->live(),
                        Select::make('type')
                            ->label('Adjustment Type')
                            ->options([
                                PointHistory::POINT_TYPE_EARN => 'EARN (Add Points)',
                                PointHistory::POINT_TYPE_EXPIRED => 'EXPIRED (Subtract Points)',
                            ])
                            ->default(PointHistory::POINT_TYPE_EARN)
                            ->required(),

                        TextInput::make('current_points')
                            ->label('Current Points')
                            ->helperText('Points found in this account.')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->readOnly(true)
                            ->saved(false),

                        TextInput::make('points')
                            ->label('Total Points to Adjust')
                            ->helperText('Use 0 in Subtract mode to clear ALL active tickets.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Textarea::make('description')
                            ->label('Reason / Description')
                            ->placeholder('e.g. Fraud correction, Manual reward, etc.')
                            ->required(),
                    ])
            ])
            ->statePath('data');
    }


    public function submit()
    {
        $data = $this->form->getState();
        $resourceName = 'PointCorrection';
        $action = 'submit';

        // Check if approval is required
        if (\App\Models\ApprovalConfig::isRequired($resourceName, $action)) {
            \App\Services\ApprovalService::createRequest(
                $resourceName,
                $action,
                null,
                newData: $data,
                originalData: [
                    'account' => Account::find($data['account_id'])?->account_number,
                    'month' => $data['month'] ?? now()->month,
                    'year' => $data['year'] ?? now()->year,
                    'type' => $data['type'],
                    'points' => $data['points'],
                    'description' => $data['description']
                ]
            );

            Notification::make()
                ->title('Correction request sent for approval.')
                ->success()
                ->send();

            $this->form->fill();
            return;
        }

        try {
            $service = new \App\Services\PointService();
            $service->executeCorrection($data);

            Notification::make()
                ->title('Points and tickets corrected successfully')
                ->success()
                ->send();

            $this->form->fill();

        } catch (\Exception $e) {
            Log::error("Point Correction Error: " . $e->getMessage());

            Notification::make()
                ->title('Correction Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
