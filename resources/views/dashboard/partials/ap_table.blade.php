<table class="table table-striped">
    <thead>
        <tr>
            <th>Access Point</th>
            <th>SSID</th>
            <th>Sessions</th>
            <th>Unique Users</th>
            <th>Avg. Duration (s)</th>
            <th>Avg. RSSI (dBm)</th>
        </tr>
    </thead>
    <tbody>
        @dump($data)
        @foreach ($data as $row)
            
            <tr>
                <td>{{ $row->ap_name }}</td>
                <td>{{ $row->ssid }}</td>
                <td>{{ $row->sessions }}</td>
                <td>{{ $row->unique_users }}</td>
                <td>{{ number_format($row->avg_duration, 2) }}</td>
                <td>{{ number_format($row->avg_rssi, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
