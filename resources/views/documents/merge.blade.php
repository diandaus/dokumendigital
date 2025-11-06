<!DOCTYPE html>
<html>
<head>
    <title>Gabung PDF - Manajemen Berkas Digital</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <style>
        .file-list {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .file-item {
            cursor: move;
            user-select: none;
            margin: 5px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 3px;
            transition: all 0.3s ease;
            border: 1px solid #dee2e6;
        }
        .file-item:hover {
            background: #e9ecef;
        }
        .file-item.sortable-ghost {
            opacity: 0.4;
            background: #198754;
        }
        .file-item.sortable-chosen {
            background: #e9ecef;
            border: 1px dashed #198754;
        }
        .file-item .drag-handle {
            cursor: move;
            padding: 0 10px;
            color: #6c757d;
        }
        .file-number {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            text-align: center;
            background: #198754;
            color: white;
            border-radius: 50%;
            margin-right: 10px;
        }
        .drag-area {
            border: 2px dashed #ccc;
            padding: 2rem;
            text-align: center;
            border-radius: 5px;
            background: #f8f9fa;
            cursor: pointer;
        }
        .drag-area.active {
            border-color: #198754;
            background: #f0f9f2;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Gabung PDF</h2>
            <a href="/documents" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <form id="mergeForm">
                    <div class="mb-4">
                        <div class="drag-area" id="dragArea">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                            <h4>Drag & Drop file PDF di sini</h4>
                            <p>atau</p>
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('pdfInput').click()">
                                    <i class="fas fa-file-upload"></i> Pilih Multiple File
                                </button>
                                <button type="button" class="btn btn-success" onclick="addSingleFile()">
                                    <i class="fas fa-plus"></i> Tambah File Satu Persatu
                                </button>
                            </div>
                            <input type="file" id="pdfInput" name="pdfs[]" multiple accept=".pdf" class="d-none">
                        </div>
                    </div>

                    <div id="fileList" class="mb-4 d-none">
                        <h5>File yang akan digabung:</h5>
                        <p class="text-muted small"><i class="fas fa-info-circle"></i> Drag file untuk mengatur urutan penggabungan</p>
                        <div id="selectedFiles" class="file-list">
                            <!-- File items will be added here -->
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-success btn-lg" id="mergeButton" disabled>
                            <i class="fas fa-object-group"></i> Gabung PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const dragArea = document.getElementById('dragArea');
        const fileInput = document.getElementById('pdfInput');
        const fileList = document.getElementById('selectedFiles');
        const mergeButton = document.getElementById('mergeButton');

        // Drag and drop handlers
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dragArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dragArea.addEventListener(eventName, () => {
                dragArea.classList.add('active');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dragArea.addEventListener(eventName, () => {
                dragArea.classList.remove('active');
            });
        });

        dragArea.addEventListener('drop', handleDrop);
        fileInput.addEventListener('change', handleFiles);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles({ target: { files: files } });
        }

        function handleFiles(e) {
            const files = Array.from(e.target.files).filter(file => file.type === 'application/pdf');
            
            if (files.length > 0) {
                document.getElementById('fileList').classList.remove('d-none');
                mergeButton.disabled = files.length < 2;
                updateFileList(files);
            }
        }

        function updateFileList(files) {
            const fileList = document.getElementById('selectedFiles');
            fileList.innerHTML = '';
            
            files.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.dataset.fileName = file.name;
                fileItem.innerHTML = `
                    <div class="d-flex align-items-center justify-content-between w-100">
                        <div class="d-flex align-items-center">
                            <span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>
                            <span class="file-number">${index + 1}</span>
                            <div>
                                <i class="fas fa-file-pdf text-danger"></i>
                                <span class="ms-2">${file.name}</span>
                                <small class="text-muted ms-2">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                fileList.appendChild(fileItem);
            });

            // Reinitialize Sortable
            initSortable();
        }

        function removeFile(index) {
            const dt = new DataTransfer();
            const files = fileInput.files;

            for (let i = 0; i < files.length; i++) {
                if (i !== index) dt.items.add(files[i]);
            }

            fileInput.files = dt.files;
            
            if (fileInput.files.length === 0) {
                document.getElementById('fileList').classList.add('d-none');
            }
            
            mergeButton.disabled = fileInput.files.length < 2;
            
            if (fileInput.files.length === 1) {
                Swal.fire({
                    title: 'Info',
                    text: 'Silakan tambah minimal 1 file PDF lagi untuk bisa menggabungkan',
                    icon: 'info'
                });
            }
            
            updateFileList(Array.from(fileInput.files));
        }

        // Tambahkan fungsi untuk validasi file
        function validateFiles(files) {
            const maxSize = 20 * 1024 * 1024; // 20MB in bytes
            const errors = [];

            if (files.length < 2) {
                errors.push('Pilih minimal 2 file PDF');
            }

            Array.from(files).forEach(file => {
                if (file.type !== 'application/pdf') {
                    errors.push(`File ${file.name} bukan file PDF`);
                }
                if (file.size > maxSize) {
                    errors.push(`File ${file.name} melebihi batas ukuran 20MB`);
                }
            });

            return errors;
        }

        // Update event listener untuk file input
        fileInput.addEventListener('change', function(e) {
            const errors = validateFiles(this.files);
            if (errors.length > 0) {
                Swal.fire('Error', errors.join('<br>'), 'error');
                this.value = ''; // Reset input
                fileList.innerHTML = '';
                document.getElementById('fileList').classList.add('d-none');
                mergeButton.disabled = true;
                return;
            }
            handleFiles(e);
            // Initialize sortable after files are added
            initSortable();
        });

        // Update submit handler
        document.getElementById('mergeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (fileInput.files.length < 2) {
                Swal.fire('Error', 'Pilih minimal 2 file PDF untuk digabung', 'error');
                return;
            }

            try {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Sedang menggabungkan dokumen PDF',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                const formData = new FormData();
                Array.from(fileInput.files).forEach(file => {
                    formData.append('pdfs[]', file);
                });
                
                const response = await fetch('/documents/merge', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                // Check content type dari response
                const contentType = response.headers.get('content-type');
                
                if (contentType && contentType.includes('application/json')) {
                    // Jika response adalah JSON, berarti ada error
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Gagal menggabungkan PDF');
                } else if (contentType && contentType.includes('application/pdf')) {
                    // Jika response adalah PDF
                    const blob = await response.blob();
                    if (blob.size === 0) {
                        throw new Error('File hasil gabungan kosong');
                    }
                    
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'dokumen-gabungan.pdf';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    Swal.fire('Berhasil', 'File PDF berhasil digabungkan', 'success');
                    
                    // Reset form
                    this.reset();
                    fileList.innerHTML = '';
                    document.getElementById('fileList').classList.add('d-none');
                    mergeButton.disabled = true;
                } else {
                    throw new Error('Response type tidak valid');
                }
                
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', error.message || 'Gagal menggabungkan PDF', 'error');
            }
        });

        function addSingleFile() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.pdf';
            input.multiple = false;
            
            input.onchange = function(e) {
                if (e.target.files.length > 0) {
                    const newFile = e.target.files[0];
                    
                    if (newFile.type !== 'application/pdf') {
                        Swal.fire('Error', 'File harus berformat PDF', 'error');
                        return;
                    }
                    
                    const dt = new DataTransfer();
                    let existingFiles = [];
                    
                    if (fileInput.files && fileInput.files.length > 0) {
                        existingFiles = Array.from(fileInput.files);
                    }
                    
                    const isDuplicate = existingFiles.some(existing => 
                        existing.name === newFile.name && 
                        existing.size === newFile.size
                    );
                    
                    if (isDuplicate) {
                        Swal.fire('Perhatian', 'File ini sudah dipilih', 'warning');
                        return;
                    }
                    
                    existingFiles.forEach(file => dt.items.add(file));
                    dt.items.add(newFile);
                    
                    fileInput.files = dt.files;
                    
                    document.getElementById('fileList').classList.remove('d-none');
                    
                    updateFileList(Array.from(fileInput.files));
                    
                    mergeButton.disabled = fileInput.files.length < 2;
                    
                    if (fileInput.files.length === 1) {
                        Swal.fire({
                            title: 'Info',
                            text: 'Silakan tambah 1 file PDF lagi untuk bisa menggabungkan',
                            icon: 'info'
                        });
                    } else if (fileInput.files.length >= 2) {
                        Swal.fire({
                            title: 'Berhasil',
                            text: 'File berhasil ditambahkan! Anda bisa menambah file lagi atau mulai menggabungkan',
                            icon: 'success'
                        });
                    }
                }
            };
            
            input.click();
        }

        // Tambahkan fungsi initSortable
        function initSortable() {
            const fileList = document.getElementById('selectedFiles');
            if (fileList) {
                new Sortable(fileList, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    onEnd: function(evt) {
                        // Reorder files after drag
                        const dt = new DataTransfer();
                        const items = fileList.querySelectorAll('.file-item');
                        const files = Array.from(fileInput.files);
                        
                        items.forEach(item => {
                            const fileName = item.dataset.fileName;
                            const file = files.find(f => f.name === fileName);
                            if (file) dt.items.add(file);
                        });
                        
                        fileInput.files = dt.files;
                        updateFileList(Array.from(fileInput.files));
                    }
                });
            }
        }
    </script>
</body>
</html> 