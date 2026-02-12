<!DOCTYPE html>
<html>

<head>
    <title>Point History Report</title>
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
        <h1>Point History Report</h1>
        <p>Generated on: {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Account</th>
                <th>Points</th>
                <th>Amount</th>
                <th>Type</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
                <tr>
                    <td>{{ $record->created_at->format('Y-m-d') }}</td>
                    <td>{{ $record->account->account_number }}</td>
                    <td>{{ $record->points }}</td>
                    <td>{{ number_format($record->amount, 2) }}</td>
                    <td>{{ $record->type }}</td>
                    <td>{{ $record->description }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>