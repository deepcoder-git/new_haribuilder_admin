<style>
    .cursor-pointer {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .cursor-pointer:hover {
        background-color: #ffffff !important;
    }
    tbody tr:hover {
        background-color: #ffffff !important;
    }
    .form-control:focus, .form-select:focus {
        border-color: #1e3a8a;
        box-shadow: 0 0 0 0.2rem rgba(30, 58, 138, 0.1);
    }
    .btn-icon {
        transition: all 0.2s;
    }
    .btn-icon:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .btn-icon:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: 0.15em;
    }
    .pagination {
        gap: 0.5rem;
        margin-top: 0.25rem;
        margin-bottom: 0.25rem;
    }
    .pagination .page-link {
        color: #6b7280;
        border-color: #e5e7eb;
        transition: none;
        padding: 0.5rem 0.75rem;
        border-radius: 0.5rem;
        font-weight: 500;
        min-width: 40px;
        text-align: center;
        background: transparent;
    }
    .pagination .page-link:hover:not(.disabled):not([aria-disabled="true"]) {
        background: #1e3a8a;
        color: #ffffff !important;
        border-color: #1e3a8a;
        box-shadow: none;
    }
    .pagination .page-item.active .page-link {
        background: #1e3a8a;
        border-color: #1e3a8a;
        color: #ffffff !important;
        box-shadow: none;
        font-weight: 600;
    }
    .pagination .page-item.disabled .page-link {
        opacity: 0.4;
        cursor: not-allowed;
        background-color: transparent;
    }
    .pagination .page-link:focus {
        box-shadow: none;
        outline: none;
    }

    /* Common Table Styles - Equal Column Spacing & Center Alignment */
    /* Override common.css for equal column spacing */
    .table.align-middle {
        table-layout: fixed !important;
        width: 100% !important;
    }

    /* Table Headers - Consistent Styling with Center Alignment */
    .table.align-middle thead th {
        padding: 0.75rem 0.5rem !important;
        color: #1e3a8a !important;
        font-size: 0.8125rem !important;
        letter-spacing: 0.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        text-align: center !important;
        vertical-align: middle !important;
        border-bottom: 2px solid #1e3a8a !important;
        background: #ffffff !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    /* Force center alignment for all header content - Override common.css */
    .table.align-middle thead th > div {
        justify-content: center !important;
        text-align: center !important;
    }

    .table.align-middle thead th > div > span,
    .table.align-middle thead th span {
        text-align: center !important;
        display: inline-block !important;
    }

    .table.align-middle thead th > * {
        text-align: center !important;
    }

    /* Table Body Cells - Center Alignment & Consistent Padding */
    .table.align-middle tbody td {
        padding: 0.75rem 0.5rem !important;
        text-align: center !important;
        vertical-align: middle !important;
        color: #374151 !important;
        font-size: 0.9375rem !important;
        border: none !important;
        line-height: 1.5 !important;
        background: transparent !important;
    }

    /* Force center alignment for all cell content - Override common.css */
    .table.align-middle tbody td > div {
        justify-content: center !important;
        text-align: center !important;
    }

    .table.align-middle tbody td span,
    .table.align-middle tbody td > span {
        text-align: center !important;
    }

    .table.align-middle tbody td > * {
        text-align: center !important;
    }

    /* Table Body Rows */
    .table.align-middle tbody tr {
        border-bottom: 1px solid #e5e7eb !important;
        transition: background-color 0.2s !important;
        min-height: 60px !important;
    }

    .table.align-middle tbody tr:hover {
        background-color: #f9fafb !important;
    }

    /* Action Column - Slightly wider but still equal spacing */
    .table.align-middle thead th:last-child,
    .table.align-middle tbody td:last-child {
        min-width: 150px !important;
    }

    /* Image Column - Keep consistent with others */
    .table.align-middle tbody td img {
        max-width: 50px;
        max-height: 50px;
        object-fit: cover;
        border-radius: 0.375rem;
    }

    /* Status Column - Center align switch/toggle */
    .table.align-middle tbody td .form-check,
    .table.align-middle tbody td .form-switch {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
    }

    /* Action Buttons - Center aligned */
    .table.align-middle tbody td .d-flex.justify-content-center {
        justify-content: center !important;
    }

    /* Ensure sortable column headers center their content - Override common.css */
    .table.align-middle thead th.cursor-pointer > div {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
    }

    /* Force all inner elements in headers to center - Override common.css */
    .table.align-middle thead th .d-flex {
        justify-content: center !important;
        align-items: center !important;
    }


    @media (max-width: 992px) {
        .card-header .d-flex {
            flex-wrap: wrap !important;
        }
        .card-header .d-flex > div:first-child {
            width: 100%;
            margin-bottom: 0.75rem;
        }
        .card-header .d-flex > div:last-child {
            width: 100%;
            justify-content: flex-start;
        }
    }
    @media (max-width: 768px) {
        .table-responsive {
            font-size: 0.875rem;
        }
        .btn-icon {
            width: 32px !important;
            height: 32px !important;
        }
        .card-header .d-flex > div:last-child {
            flex-wrap: wrap;
        }
        .card-header .d-flex > div:last-child > * {
            flex: 1 1 auto;
            min-width: 140px;
        }
        .table.align-middle {
            table-layout: auto;
        }
        .table.align-middle thead th,
        .table.align-middle tbody td {
            padding: 0.5rem 0.375rem !important;
            font-size: 0.8125rem !important;
        }
    }
</style>

