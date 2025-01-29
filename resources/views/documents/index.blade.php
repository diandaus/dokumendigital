<!DOCTYPE html>
<html>
<head>
    <title>Manajemen Berkas Digital</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Tambahkan CSS Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Tambahkan Font Awesome untuk ikon -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Tambahkan CSS custom -->
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
        @media (max-width: 768px) {
            .btn {
                margin-bottom: 5px;
            }
            
            .d-flex.gap-2 {
                margin-top: 1rem;
            }
        }
        
        .select2-container {
            width: 100% !important;
        }
        
        .card {
            margin-bottom: 1rem;
        }
        
        .form-label {
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .gap-2 {
            gap: 0.5rem !important;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manajemen Berkas Digital</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                Upload Dokumen
            </button>
        </div>

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
        
        <!-- Update form filter -->
        <div class="card mb-3">
            <div class="card-body">
                <form id="filterForm" class="row align-items-end g-3">
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" name="start_date" id="start_date">
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" class="form-control" name="end_date" id="end_date">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Email</label>
                        <select class="form-control select2" name="email" id="email">
                            <option value="">Semua Email</option>
                            @foreach($emails as $email)
                                <option value="{{ $email->email_ttd }}">{{ $email->email_ttd }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status" id="status">
                            <option value="">Semua Status</option>
                            <option value="Belum">Belum</option>
                            <option value="Sudah">Sudah</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <button type="button" class="btn btn-secondary flex-fill" onclick="resetFilter()">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Dokumen -->
        <div class="card">
            <div class="card-body">
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
            </div>
        </div>
    </div>

    <!-- Modal Upload -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Dokumen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="uploadForm">
                    <div class="modal-body">
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
                            <label>Upload Dokumen (PDF)</label>
                            <input type="file" name="documents[]" class="form-control" accept=".pdf" multiple required>
                            <small class="text-muted">Anda dapat memilih lebih dari satu file PDF</small>
                        </div>
                        <div id="selectedFiles" class="file-list d-none">
                            <strong>File yang dipilih:</strong>
                            <div id="fileList"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">Upload & Kirim</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#uploadModal')
            });
        });

        function signDocument(id) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Sedang menandatangani dokumen',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`/documents/${id}/sign-with-session`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Berhasil', data.message, 'success')
                    .then(() => window.location.reload());
                } else if (data.needOTP) {
                    sendOTP(id);
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Gagal menandatangani dokumen', 'error');
            });
        }

        function sendOTP(id) {
            fetch(`/documents/${id}/send-otp`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    promptOTP(id);
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }

        function promptOTP(id) {
            Swal.fire({
                title: 'Masukkan OTP',
                text: 'OTP telah dikirim ke email Anda',
                input: 'text',
                showCancelButton: true,
                confirmButtonText: 'Verifikasi',
                cancelButtonText: 'Batal',
                showLoaderOnConfirm: true,
                preConfirm: (otp) => {
                    return validateOTP(id, otp);
                }
            });
        }

        function validateOTP(id, otp) {
            return fetch(`/documents/${id}/validate-otp`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ otp: otp })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    throw new Error(data.message);
                }
            });
        }

        function sendDocument(id) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Sedang mengirim dokumen ke Peruri',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`/documents/${id}/send`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Berhasil', 'Dokumen berhasil dikirim ke Peruri', 'success')
                    .then(() => window.location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Gagal mengirim dokumen', 'error');
            });
        }

        function downloadDocument(orderId) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Sedang mengunduh dokumen',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Gunakan endpoint download yang benar
            fetch(`/documents/${orderId}/download`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.blob())
            .then(blob => {
                // Buat URL untuk blob
                const url = window.URL.createObjectURL(blob);
                // Buat link untuk download
                const a = document.createElement('a');
                a.href = url;
                a.download = `document-${orderId}_signed.pdf`;
                document.body.appendChild(a);
                a.click();
                // Cleanup
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                Swal.close();
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Gagal mengunduh dokumen', 'error');
            });
        }

        // Tambahkan script untuk preview file yang dipilih
        document.querySelector('input[name="documents[]"]').addEventListener('change', function(e) {
            const fileList = document.getElementById('fileList');
            const selectedFiles = document.getElementById('selectedFiles');
            fileList.innerHTML = '';
            
            if (this.files.length > 0) {
                selectedFiles.classList.remove('d-none');
                Array.from(this.files).forEach(file => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    fileItem.textContent = file.name;
                    fileList.appendChild(fileItem);
                });
            } else {
                selectedFiles.classList.add('d-none');
            }
        });

        // Handle form upload
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            try {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Sedang mengirim dokumen',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                const formData = new FormData(this);
                
                const response = await fetch('/documents', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire('Berhasil', result.message, 'success')
                    .then(() => {
                        $('#uploadModal').modal('hide');
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', result.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Gagal mengupload dokumen', 'error');
            }
        });

        // Update script filter
        document.getElementById('filterForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const email = document.getElementById('email').value;
            const status = document.getElementById('status').value;
            
            try {
                const response = await fetch(
                    `/documents?start_date=${startDate}&end_date=${endDate}&email=${email}&status=${status}`
                );
                const html = await response.text();
                
                document.querySelector('.card-body table').innerHTML = 
                    html.split('<table class="table">')[1].split('</table>')[0];
                
            } catch (error) {
                Swal.fire('Error', 'Gagal memuat data', 'error');
            }
        });

        function resetFilter() {
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            document.getElementById('email').value = '';
            $('#email').trigger('change'); // untuk reset select2
            document.getElementById('status').value = '';
            window.location.reload();
        }

        // Set default date (30 hari terakhir)
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const thirtyDaysAgo = new Date(today);
            thirtyDaysAgo.setDate(today.getDate() - 30);
            
            document.getElementById('end_date').value = today.toISOString().split('T')[0];
            document.getElementById('start_date').value = thirtyDaysAgo.toISOString().split('T')[0];
        });

        // Initialize select2 for email filter
        $(document).ready(function() {
            $('#email').select2({
                theme: 'bootstrap-5',
                placeholder: 'Pilih Email'
            });
        });
    </script>
</body>
</html> 