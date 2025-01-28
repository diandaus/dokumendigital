<!DOCTYPE html>
<html>
<head>
    <title>Dokumen Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Tambahkan CSS Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .file-list {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .file-item {
            margin: 5px 0;
            padding: 5px;
            background: #f8f9fa;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Manajemen Dokumen</h2>
        
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        
        <!-- Form Upload -->
        <form id="uploadForm" action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="mb-4">
            @csrf
            <div class="mb-3">
                <label>Pilih Dokter/Pegawai</label>
                <select name="pegawai_id" class="form-control select2" required>
                    <option value="">-- Pilih Dokter/Pegawai --</option>
                    @foreach($pegawai as $p)
                        <option value="{{ $p->id }}">{{ $p->nama }} - {{ $p->email }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label>File Dokumen (PDF) - Max 10 File</label>
                <input type="file" name="documents[]" class="form-control" accept=".pdf" multiple required>
                <div id="fileList" class="file-list d-none">
                    <h6>File yang akan dikirim:</h6>
                    <div id="fileItems"></div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Kirim ke Peruri</button>
        </form>

        <!-- Tabel Dokumen -->
        <table class="table">
            <thead>
                <tr>
                    <th>No Rawat</th>
                    <th>Nama Dokumen</th>
                    <th>Tanggal Kirim</th>
                    <th>Status</th>
                    <th>Email TTD</th>
                    <th>Keterangan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($documents as $doc)
                <tr>
                    <td>{{ $doc->no_rawat }}</td>
                    <td>{{ $doc->nama_dokumen }}</td>
                    <td>{{ $doc->tgl_kirim }}</td>
                    <td>
                        <span class="badge bg-{{ $doc->status_ttd == 'Selesai' ? 'success' : ($doc->status_ttd == 'Proses' ? 'warning' : 'danger') }}">
                            {{ $doc->status_ttd }}
                        </span>
                    </td>
                    <td>{{ $doc->email_ttd }}</td>
                    <td>{{ $doc->keterangan }}</td>
                    <td>
                        @if($doc->status_ttd != 'Selesai')
                            <button type="button" 
                                    class="btn btn-primary btn-sm"
                                    onclick="checkAndSignDocument({{ $doc->id_tracking }}, '{{ $doc->email_ttd }}', '{{ $doc->token_session }}', '{{ $doc->token_expired_at }}')">
                                Tanda Tangan
                            </button>
                        @endif
                        
                        <!-- Tombol Download -->
                        @if($doc->status_ttd == 'Selesai')
                            <a href="{{ route('documents.download', $doc->id_tracking) }}" 
                               class="btn btn-success btn-sm">
                                Download
                            </a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Tambahkan JavaScript di bagian bawah body -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5',
                placeholder: "Cari Dokter/Pegawai...",
                allowClear: true,
                width: '100%'
            });
        });

        function checkAndSignDocument(id, email, tokenSession, tokenExpiredAt) {
            // Cek apakah token masih valid
            if (tokenSession && new Date(tokenExpiredAt) > new Date()) {
                // Token masih valid, langsung tanda tangan
                proceedToSign(id);
            } else {
                // Token expired atau belum ada, kirim OTP baru
                sendOTP(id, email);
            }
        }

        function sendOTP(id, email) {
            $.ajax({
                url: `/documents/${id}/send-otp`,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        promptOTP(id);
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        }

        function promptOTP(id) {
            Swal.fire({
                title: 'Masukkan Kode OTP',
                input: 'text',
                inputAttributes: {
                    autocapitalize: 'off'
                },
                showCancelButton: true,
                confirmButtonText: 'Verifikasi',
                cancelButtonText: 'Batal',
                showLoaderOnConfirm: true,
                preConfirm: (otp) => {
                    return $.ajax({
                        url: `/documents/${id}/validate-otp`,
                        type: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            otp_code: otp
                        }
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    if (result.value.success) {
                        window.location.href = result.value.redirect_url;
                    } else {
                        Swal.fire('Error', result.value.message, 'error');
                    }
                }
            });
        }

        function proceedToSign(id) {
            // Langsung ke proses tanda tangan
            $(`form[action="/documents/${id}/sign"]`).submit();
        }

        document.querySelector('input[type="file"]').addEventListener('change', function(e) {
            const fileList = document.getElementById('fileList');
            const fileItems = document.getElementById('fileItems');
            fileItems.innerHTML = '';
            
            if (this.files.length > 0) {
                fileList.classList.remove('d-none');
                Array.from(this.files).forEach((file, index) => {
                    fileItems.innerHTML += `
                        <div class="file-item">
                            ${index + 1}. ${file.name}
                        </div>
                    `;
                });
            } else {
                fileList.classList.add('d-none');
            }
        });

        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const files = formData.getAll('documents[]');
            const pegawaiId = formData.get('pegawai_id');
            
            if (files.length > 10) {
                Swal.fire('Error', 'Maksimal 10 file yang dapat dikirim sekaligus', 'error');
                return;
            }

            Swal.fire({
                title: 'Memulai Pengiriman',
                html: 'Menyiapkan file...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            for (let i = 0; i < files.length; i++) {
                const singleFormData = new FormData();
                singleFormData.append('document', files[i]);
                singleFormData.append('pegawai_id', pegawaiId);
                
                try {
                    const response = await fetch('{{ route('documents.store') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: singleFormData
                    });
                    
                    const result = await response.json();
                    
                    await Swal.fire({
                        title: `File ${i + 1} dari ${files.length}`,
                        html: `${files[i].name}<br>${result.message}`,
                        icon: result.success ? 'success' : 'error',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                    
                } catch (error) {
                    await Swal.fire({
                        title: `Error pada file ${i + 1}`,
                        text: error.message,
                        icon: 'error'
                    });
                }
            }

            await Swal.fire({
                title: 'Selesai',
                text: 'Semua file telah diproses',
                icon: 'success'
            });

            window.location.reload();
        });
    </script>
</body>
</html> 