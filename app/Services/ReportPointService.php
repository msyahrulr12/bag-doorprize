<?php

namespace App\Services;

use App\Models\LotteryTicket;
use App\Models\PointHistory;
use Illuminate\Support\Facades\Storage;

class ReportPointService
{
    public function __construct(
    ) {
    }
    public function reportPoint(int $year, int $month)
    {
        $data = $this->getLotteryTickets($month, $year);

        if (empty($data)) {
            return null;
        }

        $filename = "report_point_{$year}_{$month}.csv";
        $directory = "reports/points";
        $path = "{$directory}/{$filename}";

        $handle = fopen('php://temp', 'r+');

        // Header
        fputcsv($handle, array_keys($data[0]));

        // Data
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('public')->put($path, $content);

        return $path;
    }
    public function getLotteryTickets(int $month, int $year)
    {
        $data = [];

        $lotteryTickets = LotteryTicket::with(['participant.account.customer'])
            ->where("month", $month)
            ->where('year', $year)
            ->orderBy('id', 'asc')
            ->get();

        foreach ($lotteryTickets as $lotteryTicket) {
            $participant = $lotteryTicket->participant;
            $account = $participant->account ?? null;

            $data[] = [
                'cif' => $participant->participant_cif ?? ($account->customer->cif ?? ''),
                'account_number' => $participant->participant_account_number ?? ($account->account_number ?? ''),
                'customer_name' => $participant->participant_name ?? ($account->customer->name ?? ''),
                'range_start' => $lotteryTicket->range_start,
                'range_end' => $lotteryTicket->range_end,
                'points' => $lotteryTicket->total_points,
                'jenis_rekening' => $account->account_type ?? '',
                'keterangan' => $lotteryTicket->description,
                'month' => $lotteryTicket->month,
                'year' => $lotteryTicket->year,
            ];
        }
        return $data;
    }
}