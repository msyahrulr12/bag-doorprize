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
use App\Models\Participant;
use App\Models\LotteryTicket;
use App\Models\Event;
use App\Models\Winner;
use App\Utils\TicketHelper;
use Illuminate\Support\Facades\DB;
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
                    ->description('Adjust points for a customer account. This will automatically generate or subtract lottery tickets.')
                    ->schema([
                        Select::make('account_id')
                            ->label('Select Account')
                            ->getSearchResultsUsing(
                                fn(string $search): array => Account::query()
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
                                    $account = Account::findOrFail($state);
                                    $totalPoints = $account
                                        ->participants
                                        ->flatMap(fn($participant) => $participant->lotteryTickets)
                                        ->where('status', LotteryTicket::STATUS_ACTIVE)
                                        ->sum('total_points');

                                    $set('current_points', $totalPoints);
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
                            ->label('Total Points in this Customer Account')
                            ->helperText('This value will be converted into lottery tickets.')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->readOnly(true)
                            ->saved(false),

                        TextInput::make('points')
                            ->label('Total Points to Adjust')
                            ->helperText('This value will be converted into lottery tickets.')
                            ->numeric()
                            ->minValue(1)
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
