<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Helper\DateHelper;
use App\Models\Account;
use App\Models\Customer;
use App\Models\LotteryTicket;
use App\Models\Participant;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Filament\Schemas\Schema;
use Log;

class PdfBankStatement extends Widget implements HasForms
{
    use HasWidgetShield;
    use InteractsWithForms;

    protected string $view = 'filament.resources.customers.widgets.pdf-bank-statement';

    public ?Customer $customer = null;
    public ?array $data = [];

    public function mount($customer): void
    {
        $this->customer = $customer;
        $this->form->fill([
            'account_number' => $customer->accounts[0]->id ?? 'N/A',
            'month' => now()->month,
            'year' => now()->year
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('account_number')
                    ->label('Account')
                    ->options($this->customer->accounts->pluck('account_number', 'id'))
                    ->required()
                    ->reactive(),
                Select::make('month')
                    ->label('Month')
                    ->options([
                        1 => 'Januari',
                        2 => 'Februari',
                        3 => 'Maret',
                        4 => 'April',
                        5 => 'Mei',
                        6 => 'Juni',
                        7 => 'Juli',
                        8 => 'Agustus',
                        9 => 'September',
                        10 => 'Oktober',
                        11 => 'November',
                        12 => 'Desember'
                    ])
                    ->required()
                    ->reactive(),
                Select::make('year')
                    ->label('Year')
                    ->options(array_combine(range(2024, 2030), range(2024, 2030)))
                    ->required()
                    ->reactive(),
            ])
            ->statePath('data');
    }

    #[Computed]
    public function pdfExists(): bool
    {
        $formData = $this->form->getRawState();
        $accountNumber = $formData['account_number'] ?? 0;
        $month = $formData['month'] ?? now()->month;
        $year = $formData['year'] ?? now()->year;
        $filename = $this->getPdfFilename($accountNumber, $month, $year);

        return Storage::disk('s3')->exists($filename);
    }

    #[Computed]
    public function pdfUrl(): ?string
    {
        if (!$this->pdfExists) {
            return null;
        }

        $formData = $this->form->getRawState();
        $accountNumber = $formData['account_number'] ?? 0;
        $month = $formData['month'] ?? now()->month;
        $year = $formData['year'] ?? now()->year;
        $filename = $this->getPdfFilename($accountNumber, $month, $year);

        return Storage::disk('s3')->temporaryUrl($filename, now()->addMinutes(5));
    }

    private function getPdfFilename(string $accountNumber, int $month, int $year): string
    {
        return "bank-statements/{$accountNumber}_{$year}_{$month}.pdf";
    }

    public function generatePdf()
    {
        $this->authorize('View:PdfBankStatement');

        $formData = $this->form->getState();
        $accountNumber = (string) ($formData['account_number'] ?? 0);
        $month = (int) ($formData['month'] ?? now()->month);
        $year = (int) ($formData['year'] ?? now()->year);

        $monthNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];
        $monthName = $monthNames[$month];

        $tickets = LotteryTicket::whereHas('participant', function ($query) {
            $query->whereIn('account_id', $this->customer->accounts->pluck('id'));
        })
            ->where('month', '<=', $month)
            ->where('year', '<=', $year)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($tickets->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('No Data')
                ->body('No lottery tickets found for the selected period.')
                ->send();
            return;
        }

        $totalPoints = 0;
        $totalPointDescriptions = "";
        $totalPenambahan = 0;
        $totalPengurangan = 0;

        $coupons = $tickets->map(function ($ticket) use (&$totalPenambahan, &$totalPengurangan) {
            $winsCount = \DB::table('winners')->where('lottery_ticket_id', $ticket->id)->count();

            if ($ticket->status == LotteryTicket::STATUS_ACTIVE) {
                $penambahan = $ticket->total_points - $winsCount;
                $pengurangan = 0;
            } else if ($ticket->status == LotteryTicket::STATUS_COMPLETED) {
                $penambahan = 0;
                $pengurangan = 0;
            } else {
                $penambahan = 0;
                $pengurangan = $ticket->total_points;
            }

            $totalPenambahan += $penambahan;
            $totalPengurangan += $pengurangan;

            $saldo = $penambahan - $pengurangan;

            $desc = $ticket->description ?? "PENAMBAHAN {$ticket->total_points} KUPON";
            if ($winsCount > 0) {
                $desc .= " (DIPOTONG {$winsCount} KUPON MENANG PRIZE)";
            }

            return [
                'periode' => DateHelper::MONTHS[$ticket->month],
                'penambahan' => number_format($penambahan, 0, ',', '.'),
                'pengurangan' => number_format($pengurangan, 0, ',', '.'),
                'nomor' => "{$ticket->range_start} - {$ticket->range_end}",
                'saldo' => $saldo,
                'keterangan' => $desc,
            ];
        })->toArray();

        $totalPoints = max(0, $totalPenambahan - $totalPengurangan);
        if ($totalPenambahan > 0) {
            $totalPointDescriptions .= "REK {$accountNumber} BERTAMBAH {$totalPenambahan} KUPON<br>";
        }
        if ($totalPengurangan > 0) {
            $totalPointDescriptions .= "REK {$accountNumber} BERKURANG {$totalPengurangan} KUPON<br>";
        }

        $data = [
            'account_number' => $accountNumber ?? 'N/A',
            'branch' => $this->customer->branch->branch_name ?? 'CABANG KPO SUDIRMAN',
            'customer_name' => $this->customer->name,
            'period' => "01 {$monthName} s/d " . Carbon::create($year, $month)->endOfMonth()->format('d') . " {$monthName} {$year}",
            'cif_number' => $this->customer->cif,
            'coupons' => $coupons,
            'showSuccessMessage' => $tickets[count($tickets) - 1]->status == LotteryTicket::STATUS_RESET ? false : true,
            'monthName' => DateHelper::MONTHS[$month],
            'year' => $year,
            'totalPoints' => number_format($totalPoints, 0, ',', '.'),
            'totalPointDescriptions' => $totalPointDescriptions
        ];

        $pdf = DomPDF::loadView('pdf.bank-statement-1', $data)
            ->setPaper('A4', 'portrait');

        $filename = $this->getPdfFilename($accountNumber, $month, $year);
        Storage::disk('s3')->put($filename, $pdf->output());

        Notification::make()
            ->success()
            ->title('PDF Generated')
            ->body('Bank statement PDF has been generated successfully.')
            ->send();

        // Refresh the component
        $this->dispatch('$refresh');
    }

    public function downloadPdf()
    {
        if (!$this->pdfExists) {
            Notification::make()
                ->warning()
                ->title('File Not Found')
                ->body('Please generate the PDF first.')
                ->send();
            return;
        }

        $formData = $this->form->getState();
        $month = $formData['month'] ?? now()->month;
        $year = $formData['year'] ?? now()->year;
        $filename = $this->getPdfFilename($formData['account_number'], $month, $year);

        return Storage::disk('s3')->download($filename);
    }
}
