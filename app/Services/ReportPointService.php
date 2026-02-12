<?php

namespace App\Services;

use App\Models\LotteryTicket;
use App\Models\PointHistory;
use DB;
use Illuminate\Support\Facades\Storage;

class ReportPointService
{
    public function __construct(
    ) {
    }

    /**
     * @param int $month
     * @param int $year
     * @return array
     */
    public function reportPoint(int $year, int $month)
    {
        $data = $this->getLotteryTickets($month, $year);

        if (empty($data)) {
            return [];
        }

        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $filename = env('REPORT_POINT_PREFIX_FILENAME', 'Report_Ticket_') . "{$year}{$month}.csv";
        $directory = env('PATH_DATA_SOURCE', 'Prodev/Aplikasi_Undian');
        $path = "{$directory}/{$filename}";

        $handle = fopen('php://temp', 'r+');

        // Header
        fputcsv($handle, array_keys($data[0]), '|');

        // Data
        foreach ($data as $row) {
            fputcsv($handle, $row, '|');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $uploadFile = Storage::disk('s3')->put($path, $content);

        return [
            'path' => $path,
            'content' => $content,
            'upload_file' => $uploadFile,
        ];
    }

    /**
     * @param int $month
     * @param int $year
     * @return array
     */
    private function getLotteryTickets(int $month, int $year)
    {
        $data = [];

        $lotteryTickets = DB::table('lottery_tickets')
            ->join('participants', 'participants.id', '=', 'lottery_tickets.participant_id')
            ->join('accounts', 'accounts.id', '=', 'participants.account_id')
            ->join('customers', 'customers.id', '=', 'accounts.customer_id')
            ->join('branches as account_branch', 'account_branch.id', '=', 'accounts.branch_id')
            ->join('branches as customer_branch', 'customer_branch.id', '=', 'customers.branch_id')
            ->join('point_histories', 'point_histories.account_id', '=', 'accounts.id')
            ->select([
                'lottery_tickets.id',
                'lottery_tickets.total_points',
                'lottery_tickets.range_start',
                'lottery_tickets.range_end',
                'lottery_tickets.participant_id',
                'lottery_tickets.status as lottery_ticket_status',
                'customers.cif',
                'accounts.account_type',
                'customer_branch.company_book as cif_branch_code',
                'account_branch.company_book as account_branch_code',
                'participants.participant_name',
                'accounts.account_number',
                'point_histories.description'
            ])
            ->where('lottery_tickets.month', $month)
            ->where('lottery_tickets.year', $year)
            ->whereNull('lottery_tickets.deleted_at')
            ->whereNull('participants.deleted_at')
            ->get();

        foreach ($lotteryTickets as $lotteryTicket) {

            $data[] = [
                'cif' => $lotteryTicket->cif ?? '',
                'ac_id' => $lotteryTicket->account_number ?? '',
                'name' => $lotteryTicket->participant_name ?? '',
                'range_start' => $lotteryTicket->range_start ?? '',
                'range_end' => $lotteryTicket->range_end ?? '',
                'ticket_status' => $lotteryTicket->lottery_ticket_status ?? '',
                'points' => $lotteryTicket->total_points ?? '',
                'jenis_rekening' => $lotteryTicket->account_type ?? '',
                'keterangan' => $lotteryTicket->description ?? '',
                'cus_open_branch' => $lotteryTicket->cif_branch_code ?? '',
                'acc_open_branch' => $lotteryTicket->account_branch_code ?? '',
                'month' => $month ?? '',
                'year' => $year ?? '',
            ];
        }
        return $data;
    }
}