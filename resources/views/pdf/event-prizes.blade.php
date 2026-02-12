<!DOCTYPE html>
<html>

<head>
    <title>Event Prizes Report</title>
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
        <h1>Event Prizes Report</h1>
        <p>Generated on: {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Prize Name</th>
                <th>Code</th>
                <th>Total Qty</th>
                <th>Remaining Qty</th>
                <th>Min Points</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
                <tr>
                    <td>{{ $record->prize->prize_name }}</td>
                    <td>{{ $record->prize->prize_code }}</td>
                    <td>{{ $record->total_quantity }}</td>
                    <td>{{ $record->remaining_quantity }}</td>
                    <td>{{ number_format($record->min_points_required) }}</td>
                    <td>{{ number_format($record->prize->value) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>