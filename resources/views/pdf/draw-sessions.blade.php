<!DOCTYPE html>
<html>

<head>
    <title>Draw Sessions Report</title>
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
        <h1>Draw Sessions Report</h1>
        <p>Generated on: {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Session Name</th>
                <th>Status</th>
                <th>Started At</th>
                <th>Ended At</th>
                <th>Total Winners</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
                <tr>
                    <td>{{ $record->name }}</td>
                    <td>{{ $record->status }}</td>
                    <td>{{ $record->started_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $record->ended_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $record->winners_count + $record->temporary_winners_count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>