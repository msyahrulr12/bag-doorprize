<!DOCTYPE html>
<html>

<head>
    <title>Participants Report</title>
    <style>
        body {
            font-family: sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .header {
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Participants Report</h1>
        <p>Generated on: {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Customer Name</th>
                <th>Account Number</th>
                <th>CIF</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Points Snapshot</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
                <tr>
                    <td>{{ $record->account->customer->name }}</td>
                    <td>{{ $record->account->account_number }}</td>
                    <td>{{ $record->participant_cif }}</td>
                    <td>{{ $record->participant_email }}</td>
                    <td>{{ $record->participant_phone_number }}</td>
                    <td>{{ $record->total_points_snapshot }}</td>
                    <td>{{ $record->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>