<table class="table">
    <thead>
        <tr>
            <th>No</th>
            <th>Nama Dokumen</th>
            <th>Order ID</th>
            <th>Tanggal</th>
            <th>Email TTD</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        @foreach($documents as $index => $doc)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $doc->nama_dokumen }}</td>
                <td>{{ $doc->order_id }}</td>
                <td>{{ $doc->tgl_kirim }}</td>
                <td>{{ $doc->email_ttd }}</td>
                <td>
                    <span class="badge bg-{{ 
                        $doc->status_ttd == 'Sudah' ? 'success' : 'warning'
                    }}">
                        {{ $doc->status_ttd }}
                    </span>
                </td>
                <td>
                    @if($doc->status_ttd == 'Belum')
                        <button type="button" 
                                class="btn btn-primary btn-sm"
                                onclick="signDocument({{ $doc->id_tracking }})">
                            Tanda Tangan
                        </button>
                    @elseif($doc->status_ttd == 'Sudah')
                        <button type="button"
                                class="btn btn-success btn-sm"
                                onclick="downloadDocument('{{ $doc->order_id }}')">
                            Download
                        </button>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table> 