<!DOCTYPE html>
<html>
<head>
    <title>Editor Halaman PDF - Manajemen Berkas Digital</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        .page-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .page-item {
            position: relative;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            background: #fff;
            cursor: move;
            transition: all 0.3s ease;
        }

        .page-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-color: #198754;
        }

        .page-item.sortable-ghost {
            opacity: 0.4;
            background: #198754;
        }

        .page-item.sortable-chosen {
            border: 2px dashed #198754;
        }

        .page-preview {
            width: 100%;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: #f8f9fa;
            min-height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-preview canvas {
            max-width: 100%;
            height: auto;
        }

        .page-number {
            position: absolute;
            top: 5px;
            left: 5px;
            background: #198754;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            z-index: 10;
        }

        .page-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
            justify-content: center;
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

        .rotate-icon {
            transition: transform 0.3s;
        }

        .page-item[data-rotation="90"] .page-preview canvas {
            transform: rotate(90deg);
        }

        .page-item[data-rotation="180"] .page-preview canvas {
            transform: rotate(180deg);
        }

        .page-item[data-rotation="270"] .page-preview canvas {
            transform: rotate(270deg);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Editor Halaman PDF</h2>
                @if($noRawat)
                    <p class="text-muted mb-0">
                        <i class="fas fa-hospital-user"></i> No. Rawat: <strong>{{ $noRawat }}</strong>
                        @if(count($pdfFiles) > 0)
                            <span class="badge bg-success ms-2">{{ count($pdfFiles) }} file PDF</span>
                        @endif
                    </p>
                @endif
            </div>
            <a href="/documents" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="drag-area" id="dragArea">
                    <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                    <h4>Upload File PDF</h4>
                    <p class="text-muted">Pilih satu atau beberapa file PDF sekaligus</p>
                    <p class="text-muted small">
                        <i class="fas fa-info-circle"></i>
                        Multiple PDF akan otomatis digabungkan
                    </p>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('pdfInput').click()">
                        <i class="fas fa-file-upload"></i> Pilih File PDF
                    </button>
                    <input type="file" id="pdfInput" accept=".pdf" multiple class="d-none">
                </div>
            </div>
        </div>

        <div id="pagesSection" class="d-none">
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt"></i> Halaman PDF
                        <span id="pageCount" class="badge bg-light text-dark ms-2">0</span>
                    </h5>
                    <div>
                        <button type="button" class="btn btn-light btn-sm me-2" onclick="addNewPage()">
                            <i class="fas fa-plus"></i> Tambah Halaman
                        </button>
                        <button type="button" class="btn btn-warning btn-sm" onclick="savePDF()">
                            <i class="fas fa-save"></i> Simpan PDF
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Drag halaman untuk mengubah urutan. Gunakan tombol aksi untuk memutar atau menghapus halaman.
                    </p>
                    <div id="pagesContainer" class="page-container">
                        <!-- Pages will be rendered here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Set worker path untuk PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let pages = [];
        let currentFile = null;

        // Data dari Laravel
        const pdfFilesFromServer = @json($pdfFiles ?? []);

        const dragArea = document.getElementById('dragArea');
        const fileInput = document.getElementById('pdfInput');
        const pagesContainer = document.getElementById('pagesContainer');

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
        fileInput.addEventListener('change', handleFileSelect);

        async function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = Array.from(dt.files).filter(f => f.type === 'application/pdf');

            if (files.length === 0) {
                Swal.fire('Error', 'File harus berformat PDF', 'error');
                return;
            }

            // Jika hanya 1 file, langsung load
            if (files.length === 1) {
                loadPDF(files[0]);
                return;
            }

            // Jika multiple files, gabungkan dulu
            try {
                Swal.fire({
                    title: 'Memproses...',
                    text: `Menggabungkan ${files.length} file PDF`,
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData();
                files.forEach(file => {
                    formData.append('pdfs[]', file);
                });

                const response = await fetch('/documents/merge-multiple-pdfs', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const contentType = response.headers.get('content-type');

                // Cek jika response adalah JSON (kemungkinan error)
                if (contentType && contentType.includes('application/json')) {
                    const result = await response.json();
                    throw new Error(result.message || 'Gagal menggabungkan PDF');
                }

                // Cek status response
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error('HTTP ' + response.status + ': ' + errorText);
                }

                // Jika response adalah PDF
                if (contentType && contentType.includes('application/pdf')) {
                    const blob = await response.blob();
                    const mergedFile = new File([blob], 'merged-pdfs.pdf', { type: 'application/pdf' });

                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: `${files.length} file PDF berhasil digabungkan`,
                        timer: 2000
                    });

                    await loadPDF(mergedFile);
                }

            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', 'Gagal memproses file: ' + error.message, 'error');
            }
        }

        async function handleFileSelect(e) {
            if (e.target.files.length === 0) {
                return;
            }

            // Jika hanya 1 file, langsung load
            if (e.target.files.length === 1) {
                loadPDF(e.target.files[0]);
                return;
            }

            // Jika multiple files, gabungkan dulu
            try {
                Swal.fire({
                    title: 'Memproses...',
                    text: `Menggabungkan ${e.target.files.length} file PDF`,
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Validasi semua file adalah PDF
                const files = Array.from(e.target.files);
                const invalidFiles = files.filter(f => f.type !== 'application/pdf');

                if (invalidFiles.length > 0) {
                    Swal.fire('Error', 'Semua file harus berformat PDF', 'error');
                    return;
                }

                // Gabungkan multiple PDF
                const formData = new FormData();
                files.forEach((file, index) => {
                    console.log(`Appending file ${index}:`, file.name, file.type, file.size);
                    formData.append('pdfs[]', file);
                });

                console.log('Sending FormData with', files.length, 'files');

                const response = await fetch('/documents/merge-multiple-pdfs', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const contentType = response.headers.get('content-type');

                // Cek jika response adalah JSON (kemungkinan error)
                if (contentType && contentType.includes('application/json')) {
                    const result = await response.json();
                    throw new Error(result.message || 'Gagal menggabungkan PDF');
                }

                // Cek status response
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error('HTTP ' + response.status + ': ' + errorText);
                }

                // Jika response adalah PDF
                if (contentType && contentType.includes('application/pdf')) {
                    const blob = await response.blob();
                    const mergedFile = new File([blob], 'merged-pdfs.pdf', { type: 'application/pdf' });

                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: `${files.length} file PDF berhasil digabungkan`,
                        timer: 2000
                    });

                    // Load PDF hasil gabungan
                    await loadPDF(mergedFile);
                }

            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', 'Gagal memproses file: ' + error.message, 'error');
            }
        }

        async function loadPDF(file) {
            try {
                Swal.fire({
                    title: 'Memuat PDF...',
                    text: 'Mohon tunggu',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                currentFile = file;
                const arrayBuffer = await file.arrayBuffer();
                pdfDoc = await pdfjsLib.getDocument({data: arrayBuffer}).promise;

                pages = [];
                for (let i = 1; i <= pdfDoc.numPages; i++) {
                    pages.push({
                        pageNum: i,
                        rotation: 0,
                        deleted: false
                    });
                }

                await renderPages();

                document.getElementById('pagesSection').classList.remove('d-none');
                updatePageCount();

                Swal.close();

                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: `PDF berhasil dimuat dengan ${pdfDoc.numPages} halaman`,
                    timer: 2000
                });

            } catch (error) {
                console.error('Error loading PDF:', error);
                Swal.fire('Error', 'Gagal memuat PDF: ' + error.message, 'error');
            }
        }

        async function renderPages(preserveScroll = false) {
            // Simpan posisi scroll sebelum re-render
            const scrollPosition = preserveScroll ? window.scrollY : 0;

            pagesContainer.innerHTML = '';

            let displayIndex = 1;
            for (let i = 0; i < pages.length; i++) {
                if (pages[i].deleted) continue;

                const page = await pdfDoc.getPage(pages[i].pageNum);
                // Scale 0.4 untuk performa lebih baik pada PDF besar
                // Bisa diubah ke 0.5 jika butuh kualitas lebih tinggi
                const scale = 0.4;
                const viewport = page.getViewport({scale: scale, rotation: pages[i].rotation});

                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                await page.render({
                    canvasContext: context,
                    viewport: viewport
                }).promise;

                const pageItem = document.createElement('div');
                pageItem.className = 'page-item';
                pageItem.dataset.index = i;
                pageItem.dataset.rotation = pages[i].rotation;

                pageItem.innerHTML = `
                    <div class="page-number">${displayIndex}</div>
                    <div class="page-preview">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-sm btn-outline-primary" onclick="rotatePage(${i})" title="Putar 90°">
                            <i class="fas fa-redo rotate-icon"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deletePage(${i})" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;

                pagesContainer.appendChild(pageItem);
                pageItem.querySelector('.page-preview').innerHTML = '';
                pageItem.querySelector('.page-preview').appendChild(canvas);

                displayIndex++;
            }

            initSortable();
            updatePageCount();

            // Kembalikan posisi scroll setelah re-render
            if (preserveScroll && scrollPosition > 0) {
                // Gunakan requestAnimationFrame untuk memastikan DOM sudah selesai update
                requestAnimationFrame(() => {
                    window.scrollTo(0, scrollPosition);
                });
            }
        }

        function initSortable() {
            if (pagesContainer.querySelector('.page-item')) {
                new Sortable(pagesContainer, {
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    onEnd: function(evt) {
                        reorderPages(evt.oldIndex, evt.newIndex);
                    }
                });
            }
        }

        function reorderPages(oldIndex, newIndex) {
            // Update internal pages array
            const activePages = pages.filter(p => !p.deleted);
            const [movedPage] = activePages.splice(oldIndex, 1);
            activePages.splice(newIndex, 0, movedPage);

            // Rebuild pages array
            let newPages = [];
            let activeIndex = 0;
            for (let page of pages) {
                if (!page.deleted) {
                    newPages.push(activePages[activeIndex]);
                    activeIndex++;
                } else {
                    newPages.push(page);
                }
            }
            pages = newPages;

            // Update hanya nomor halaman, TIDAK re-render canvas
            // Ini membuat drag & drop sangat smooth seperti Sejda
            updatePageNumbers();
        }

        function rotatePage(index) {
            pages[index].rotation = (pages[index].rotation + 90) % 360;
            // Preserve scroll position saat rotate
            renderPages(true);
        }

        function deletePage(index) {
            const activePages = pages.filter(p => !p.deleted).length;

            if (activePages <= 1) {
                Swal.fire('Perhatian', 'Tidak dapat menghapus halaman terakhir', 'warning');
                return;
            }

            Swal.fire({
                title: 'Konfirmasi',
                text: 'Yakin ingin menghapus halaman ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    pages[index].deleted = true;
                    // Preserve scroll position saat delete
                    renderPages(true);
                    Swal.fire('Berhasil', 'Halaman telah dihapus', 'success');
                }
            });
        }

        function updatePageCount() {
            const activePages = pages.filter(p => !p.deleted).length;
            document.getElementById('pageCount').textContent = activePages;
        }

        function updatePageNumbers() {
            // Update nomor halaman pada setiap page-item tanpa re-render canvas
            // Ini membuat drag & drop sangat smooth
            const pageItems = pagesContainer.querySelectorAll('.page-item');
            let displayIndex = 1;

            pageItems.forEach((item, index) => {
                const pageNumberEl = item.querySelector('.page-number');
                if (pageNumberEl) {
                    pageNumberEl.textContent = displayIndex;
                    displayIndex++;
                }
            });
        }

        async function addNewPage() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.pdf';
            input.multiple = true; // Support multiple files

            input.onchange = async function(e) {
                if (e.target.files.length === 0) {
                    return;
                }

                try {
                    const newFiles = Array.from(e.target.files);

                    // Validasi semua file adalah PDF
                    const invalidFiles = newFiles.filter(f => f.type !== 'application/pdf');
                    if (invalidFiles.length > 0) {
                        Swal.fire('Error', 'Semua file harus berformat PDF', 'error');
                        return;
                    }

                    Swal.fire({
                        title: 'Memproses...',
                        text: `Menambahkan ${newFiles.length} file PDF`,
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Jika multiple files, gabungkan dulu PDF baru
                    let finalNewFile;
                    let totalNewPages = 0;

                    if (newFiles.length === 1) {
                        finalNewFile = newFiles[0];
                        const arrayBuffer = await finalNewFile.arrayBuffer();
                        const newPdfDoc = await pdfjsLib.getDocument({data: arrayBuffer}).promise;
                        totalNewPages = newPdfDoc.numPages;
                    } else {
                        // Gabungkan multiple PDF baru terlebih dahulu
                        const mergeFormData = new FormData();
                        newFiles.forEach(file => {
                            mergeFormData.append('pdfs[]', file);
                        });

                        const mergeResponse = await fetch('/documents/merge-multiple-pdfs', {
                            method: 'POST',
                            body: mergeFormData,
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });

                        const mergeContentType = mergeResponse.headers.get('content-type');

                        if (mergeContentType && mergeContentType.includes('application/json')) {
                            const result = await mergeResponse.json();
                            throw new Error(result.message || 'Gagal menggabungkan PDF baru');
                        }

                        if (!mergeResponse.ok) {
                            throw new Error('HTTP Error ' + mergeResponse.status);
                        }

                        const blob = await mergeResponse.blob();
                        finalNewFile = new File([blob], 'merged-new-pdfs.pdf', { type: 'application/pdf' });

                        // Hitung total halaman
                        const arrayBuffer = await finalNewFile.arrayBuffer();
                        const newPdfDoc = await pdfjsLib.getDocument({data: arrayBuffer}).promise;
                        totalNewPages = newPdfDoc.numPages;
                    }

                    // Gabungkan dengan PDF yang ada
                    const formData = new FormData();

                    // Kirim PDF asli
                    formData.append('original_pdf', currentFile);

                    // Kirim halaman yang sudah diedit
                    const activePages = pages.map((page, index) => ({
                        originalPage: page.pageNum,
                        rotation: page.rotation,
                        deleted: page.deleted,
                        order: index
                    })).filter(p => !p.deleted);

                    formData.append('pages', JSON.stringify(activePages));

                    // Kirim PDF baru yang akan ditambahkan (sudah gabungan jika multiple)
                    formData.append('new_pdf', finalNewFile);

                    const response = await fetch('/documents/merge-additional-pdf', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    const contentType = response.headers.get('content-type');

                    if (contentType && contentType.includes('application/json')) {
                        const result = await response.json();
                        throw new Error(result.message || 'Gagal menggabungkan PDF');
                    }

                    if (!response.ok) {
                        throw new Error('HTTP Error ' + response.status);
                    }

                    if (contentType && contentType.includes('application/pdf')) {
                        // Buat blob dari hasil merge
                        const blob = await response.blob();

                        // Konversi blob ke File object dan reload
                        const mergedFile = new File([blob], 'merged-temp.pdf', { type: 'application/pdf' });

                        const successMessage = newFiles.length === 1
                            ? `${totalNewPages} halaman berhasil ditambahkan`
                            : `${newFiles.length} file PDF (${totalNewPages} halaman) berhasil ditambahkan`;

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: successMessage + '. Memuat ulang preview...',
                            timer: 2000
                        });

                        // Reload dengan file yang sudah digabung
                        await loadPDF(mergedFile);
                    }

                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Gagal menambahkan halaman: ' + error.message, 'error');
                }
            };

            input.click();
        }

        async function savePDF() {
            try {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Menyimpan perubahan PDF',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData();
                formData.append('pdf', currentFile);

                // Kirim informasi halaman yang aktif dan rotasinya
                const activePages = pages.map((page, index) => ({
                    originalPage: page.pageNum,
                    rotation: page.rotation,
                    deleted: page.deleted,
                    order: index
                })).filter(p => !p.deleted);

                formData.append('pages', JSON.stringify(activePages));

                const response = await fetch('/documents/save-edited-pdf', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                // Cek status response
                if (!response.ok) {
                    if (response.status === 404) {
                        throw new Error('Endpoint tidak ditemukan. Silakan refresh halaman dan coba lagi.');
                    }
                    const errorText = await response.text();
                    throw new Error('HTTP Error ' + response.status + ': ' + errorText);
                }

                const contentType = response.headers.get('content-type');

                if (contentType && contentType.includes('application/json')) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Gagal menyimpan PDF');
                } else if (contentType && contentType.includes('application/pdf')) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'dokumen-edited.pdf';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);

                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: 'PDF berhasil disimpan',
                        confirmButtonText: 'OK'
                    });
                }

            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', error.message || 'Gagal menyimpan PDF', 'error');
            }
        }

        // Auto-load PDF files dari server saat halaman dibuka
        async function loadPDFsFromServer() {
            if (!pdfFilesFromServer || pdfFilesFromServer.length === 0) {
                return;
            }

            try {
                Swal.fire({
                    title: 'Memuat File PDF...',
                    text: `Mengambil ${pdfFilesFromServer.length} file dari server`,
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Download semua PDF dari server
                const pdfBlobs = [];
                for (const fileInfo of pdfFilesFromServer) {
                    try {
                        const response = await fetch(fileInfo.url);
                        if (!response.ok) {
                            console.error('Failed to load:', fileInfo.url);
                            continue;
                        }
                        const blob = await response.blob();
                        const file = new File([blob], fileInfo.name, { type: 'application/pdf' });
                        pdfBlobs.push(file);
                    } catch (error) {
                        console.error('Error loading file:', fileInfo.url, error);
                    }
                }

                if (pdfBlobs.length === 0) {
                    throw new Error('Tidak ada file PDF yang berhasil dimuat');
                }

                // Jika hanya 1 file, langsung load
                if (pdfBlobs.length === 1) {
                    await loadPDF(pdfBlobs[0]);
                    return;
                }

                // Jika multiple files, gabungkan dulu
                Swal.fire({
                    title: 'Menggabungkan PDF...',
                    text: `Menggabungkan ${pdfBlobs.length} file PDF`,
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData();
                pdfBlobs.forEach(file => {
                    formData.append('pdfs[]', file);
                });

                // Add timeout menggunakan AbortController
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 120000); // 2 minutes timeout

                try {
                    const response = await fetch('/documents/merge-multiple-pdfs', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        signal: controller.signal
                    });

                    clearTimeout(timeoutId);

                    console.log('Merge response status:', response.status);
                    console.log('Merge response headers:', {
                        contentType: response.headers.get('content-type'),
                        contentLength: response.headers.get('content-length')
                    });

                    const contentType = response.headers.get('content-type');

                    // Check for JSON error response
                    if (contentType && contentType.includes('application/json')) {
                        const result = await response.json();
                        throw new Error(result.message || 'Gagal menggabungkan PDF');
                    }

                    if (!response.ok) {
                        const text = await response.text();
                        console.error('Response text:', text);
                        throw new Error('HTTP Error ' + response.status + ': ' + text.substring(0, 200));
                    }

                    // Get blob (regardless of content-type header)
                    const blob = await response.blob();
                    console.log('Merged blob size:', blob.size, 'bytes');

                    if (blob.size < 1000) {
                        throw new Error('File hasil merge terlalu kecil (' + blob.size + ' bytes), kemungkinan error');
                    }

                    const mergedFile = new File([blob], 'merged-pdfs.pdf', { type: 'application/pdf' });

                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: `${pdfBlobs.length} file PDF berhasil digabungkan`,
                        timer: 2000
                    });

                    await loadPDF(mergedFile);

                } catch (fetchError) {
                    clearTimeout(timeoutId);
                    if (fetchError.name === 'AbortError') {
                        throw new Error('Timeout: Proses merge memakan waktu lebih dari 2 menit');
                    }
                    throw fetchError;
                }

            } catch (error) {
                console.error('Error loading PDFs from server:', error);
                Swal.fire('Error', 'Gagal memuat file PDF: ' + error.message, 'error');
            }
        }

        // Jalankan auto-load saat halaman selesai dimuat
        window.addEventListener('DOMContentLoaded', () => {
            if (pdfFilesFromServer && pdfFilesFromServer.length > 0) {
                loadPDFsFromServer();
            }
        });
    </script>
</body>
</html>
