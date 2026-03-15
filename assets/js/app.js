/* ============================================================
   MASJID ERP - Main Application JavaScript
   ============================================================ */

$(document).ready(function () {

    // ---- Sidebar Toggle -----------------------------------
    $('#sidebarToggle').on('click', function () {
        const sidebar = $('#sidebar');
        if (window.innerWidth < 992) {
            sidebar.toggleClass('show');
        } else {
            sidebar.toggleClass('collapsed');
            $('.main-content').toggleClass('full-width');
        }
    });

    // Close sidebar on mobile when clicking outside
    $(document).on('click', function (e) {
        if (window.innerWidth < 992) {
            if (!$(e.target).closest('#sidebar, #sidebarToggle').length) {
                $('#sidebar').removeClass('show');
            }
        }
    });

    // ---- Initialize Select2 -------------------------------
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%',
        });
    }

    // ---- Initialize Flatpickr dates -----------------------
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.date-picker', {
            dateFormat: 'Y-m-d',
            allowInput: true,
        });
        flatpickr('.date-picker-display', {
            dateFormat: 'd/m/Y',
            altInput: true,
            altFormat: 'd/m/Y',
            dateFormat: 'Y-m-d',
            allowInput: true,
        });
        flatpickr('.month-picker', {
            plugins: [new monthSelectPlugin({ shorthand: false, dateFormat: 'Y-m', altFormat: 'F Y' })],
        });
    }

    // ---- Initialize DataTables ----------------------------
    if ($.fn.DataTable) {
        $('.datatable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [],
            language: {
                search: '',
                searchPlaceholder: 'Search...',
                lengthMenu: 'Show _MENU_ entries',
                emptyTable: 'No records found',
                zeroRecords: 'No matching records',
            },
            dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        });

        // DataTable with export buttons
        $('.datatable-export').DataTable({
            responsive: true,
            pageLength: 25,
            order: [],
            dom: "<'row'<'col-sm-12 col-md-4'l><'col-sm-12 col-md-4'B><'col-sm-12 col-md-4'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            buttons: [
                { extend: 'excelHtml5', text: '<i class="bi bi-file-earmark-excel me-1"></i>Excel', className: 'btn btn-sm btn-success' },
                { extend: 'pdfHtml5', text: '<i class="bi bi-file-earmark-pdf me-1"></i>PDF', className: 'btn btn-sm btn-danger' },
                { extend: 'print', text: '<i class="bi bi-printer me-1"></i>Print', className: 'btn btn-sm btn-secondary' },
            ],
            language: {
                search: '',
                searchPlaceholder: 'Search...',
            },
        });
    }

    // ---- Auto-dismiss alerts ------------------------------
    setTimeout(function () {
        $('.alert-auto').fadeOut('slow');
    }, 4000);

    // ---- Amount formatting --------------------------------
    $(document).on('blur', '.amount-input', function () {
        const val = parseFloat($(this).val().replace(/,/g, ''));
        if (!isNaN(val)) {
            $(this).val(val.toFixed(2));
        }
    });

    // ---- Confirm delete -----------------------------------
    $(document).on('click', '.btn-confirm-delete', function (e) {
        if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
            e.preventDefault();
        }
    });

    // ---- Print functionality ------------------------------
    $(document).on('click', '.btn-print', function () {
        window.print();
    });

    // ---- Load notifications -------------------------------
    function loadNotifications() {
        $.get(BASE_PATH + '/pages/notifications.php', { ajax: 1 }, function (data) {
            try {
                const res = JSON.parse(data);
                if (res.count > 0) {
                    $('.notification-count').text(res.count).removeClass('d-none');
                }
                $('#notificationList').html(res.html || '<p class="mb-0">No new notifications</p>');
            } catch (e) {
                $('#notificationList').html('<p class="mb-0 text-muted">Unable to load notifications</p>');
            }
        });
    }

    if ($('#notificationList').length) {
        loadNotifications();
    }

    // ---- CSRF token for AJAX ------------------------------
    $.ajaxSetup({
        data: { csrf_token: $('meta[name="csrf-token"]').attr('content') }
    });

    // ---- Tooltip initialization ---------------------------
    const tooltipEls = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipEls.forEach(el => new bootstrap.Tooltip(el));

    // ---- Receipt number preview ---------------------------
    $(document).on('change', '#payment_mode', function () {
        const mode = $(this).val();
        if (mode === 'cheque') {
            $('#cheque_fields').removeClass('d-none');
        } else {
            $('#cheque_fields').addClass('d-none');
        }
    });
});

// Global base path (set in layout)
var BASE_PATH = BASE_PATH || '';
