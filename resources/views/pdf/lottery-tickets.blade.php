<!DOCTYPE html>
<html>

<head>
    <title>Lottery Tickets Report</title>
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
        <h1>Lottery Tickets Report</h1>
        <p>Generated on: {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Event</th>
                <th>Participant</th>
                <th>Points</th>
                <th>Range Start</th>
                <th>Range End</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
                <tr>
                    <td>{{ $record->event->event_name }}</td>
                    <td>{{ $record->participant->participant_name }}</td>
                    <td>{{ $record->total_points }}</td>
                    <td>{{ $record->range_start }}</td>
                    <td>{{ $record->range_end }}</td>
                    <td>{{ $record->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>