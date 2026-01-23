@props(['items', 'columns' => [], 'actions' => true, 'showCheckbox' => false, 'showEdit' => true, 'showView' => true, 'showDelete' => true])

@php
    // Ensure columns is always an array
    if (!is_array($columns)) {
        $columns = [];
    }
    $columns = $columns ?? [];
    
    // Ensure items is iterable
    if (!is_iterable($items) && !is_object($items)) {
        $items = [];
    }
@endphp

<div>
    <!-- Delete Confirmation Modal -->
    <x-confirm-modal 
        id="deleteConfirmModal"
        title="Delete Item"
        message="Are you sure you want to delete this item?"
        confirmText="Delete"
        cancelText="Cancel"
        type="danger"
    />

    <style>
        /* Enhanced Table Container */
        .table-responsive {
            border-radius: 0;
            overflow-x: auto;
            overflow-y: visible;
            box-shadow: none;
            border: none;
            background: transparent;
            padding: 0;
            margin: 0;
            max-width: 100%;
        }

        /* Table Styling */
        .table {
            border-collapse: separate;
            border-spacing: 0;
            border: none;
            border-radius: 0.5rem;
            overflow: hidden;
            margin-bottom: 0;
            background: transparent;
            table-layout: auto !important;
            width: 100% !important;
        }

        /* Table Body Row Styling */
        .table tbody tr {
            background: transparent;
            min-height: 36px;
        }
        
        .table tbody tr:hover {
            background: transparent !important;
        }
        
        .table tbody tr:nth-child(even) {
            background: transparent;
        }
        
        .table tbody tr:nth-child(odd) {
            background: transparent;
        }

        /* Table Cell Styling */
        .table tbody td {
            padding: 0.5rem 0.5rem;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
            font-size: 1.125rem;
            color: #374151;
            line-height: 1.5;
            white-space: nowrap;
        }

        .table tbody td:first-child {
            padding-left: 0.75rem;
        }

        .table tbody td:last-child {
            padding-right: 0.75rem;
        }
        
        /* Ensure proper alignment for table cells */
        .table tbody td > div {
            width: 100%;
            line-height: 1.3;
            min-height: 18px;
        }

        /* Ensure proper vertical alignment */
        .table td,
        .table th {
            vertical-align: middle !important;
        }

        /* Text alignment for different column types */
        .table td.text-start,
        .table th.text-start {
            text-align: left !important;
        }
        
        .table td.text-start > div,
        .table th.text-start > div {
            justify-content: flex-start !important;
            text-align: left !important;
        }

        .table td.text-center,
        .table th.text-center {
            text-align: center !important;
        }
        
        .table td.text-center > div,
        .table th.text-center > div {
            justify-content: center !important;
            text-align: center !important;
        }

        .table td.text-end,
        .table th.text-end {
            text-align: right !important;
        }
        
        .table td.text-end > div,
        .table th.text-end > div {
            justify-content: flex-end !important;
            text-align: right !important;
        }

        /* Center alignment for checkboxes */
        .table td .form-check {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }

        /* Center alignment for status switches */
        .table td .form-check-switch {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }

        /* Action buttons alignment */
        .table td .d-flex.justify-content-end {
            display: flex !important;
            justify-content: flex-end !important;
            align-items: center !important;
        }

        .table thead th:first-child {
            padding-left: 0.75rem;
        }

        .table thead th:last-child {
            padding-right: 0.75rem;
        }

        .table tbody td:first-child {
            padding-left: 0.75rem;
        }

        .table tbody td:last-child {
            padding-right: 0.75rem;
        }

        /* Action Buttons */
        .btn-icon {
            transition: all 0.2s ease;
            border-radius: 0.375rem;
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            cursor: pointer;
            background: transparent !important;
        }
        
        .btn-icon:hover {
            opacity: 0.7;
            transform: scale(1.1);
        }

        .btn-icon:hover i {
            opacity: 0.8;
        }

        .btn-icon:active {
            opacity: 0.6;
            transform: scale(0.95);
        }
        
        .btn-icon i {
            font-size: 1rem !important;
            display: inline-block;
            transition: all 0.2s ease;
        }
        
        /* Transparent Icon Buttons */
        .btn-icon-transparent {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            transition: all 0.2s ease;
        }
        
        .btn-icon-transparent:hover {
            background: transparent !important;
        }
        
        .btn-icon-transparent:hover i {
            opacity: 0.8;
            transform: scale(1.1);
        }
        
        .btn-icon-transparent:active {
            transform: scale(0.95);
        }
        
        .btn-icon-transparent i {
            font-size: 1.375rem !important;
            display: inline-block;
            transition: all 0.2s ease;
        }
        
        .btn-icon-view i {
            color: #1e3a8a !important;
        }
        
        .btn-icon-view:hover i {
            color: #1e40af !important;
        }
        
        .btn-icon-edit i {
            color: #60a5fa !important;
        }
        
        .btn-icon-edit:hover i {
            color: #3b82f6 !important;
        }
        
        .btn-icon-delete i {
            color: #ef4444 !important;
        }
        
        .btn-icon-delete:hover i {
            color: #dc2626 !important;
        }
        
        .btn-icon-approve i {
            color: #10b981 !important;
        }
        
        .btn-icon-approve:hover i {
            color: #059669 !important;
        }
        
        .btn-icon-cancel i {
            color: #f59e0b !important;
        }
        
        .btn-icon-cancel:hover i {
            color: #d97706 !important;
        }
        
        /* Table Header Enhanced Styling */
        table thead th {
            transition: none;
            padding: 0.625rem 0.5rem;
            font-weight: 700;
            font-size: 0.9375rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            white-space: nowrap !important;
            overflow: visible !important;
            text-overflow: clip !important;
            word-break: keep-all !important;
            word-wrap: normal !important;
            border-bottom: 2px solid #1e3a8a;
            border-right: none;
            border-top: none;
            border-left: none;
            background: transparent;
            color: #1e3a8a;
            vertical-align: middle;
            position: relative;
            text-align: left;
        }
        
        /* Ensure header text and divs don't wrap */
        table thead th > div,
        table thead th span {
            white-space: nowrap !important;
            overflow: visible !important;
            display: inline-block !important;
        }
        
        /* Prevent text wrapping in header content */
        table thead th > div > span {
            white-space: nowrap !important;
            display: inline-block !important;
            word-break: keep-all !important;
            word-wrap: normal !important;
            hyphens: none !important;
        }
        
        /* Replace spaces with non-breaking spaces in headers using CSS */
        table thead th {
            font-variant-numeric: normal;
        }

        table thead th:first-child {
            padding-left: 0.75rem;
        }

        table thead th:last-child {
            padding-right: 0.75rem;
        }
        
        /* Table Header Row */
        table thead tr {
            background: transparent;
            border-bottom: 2px solid #1e3a8a;
        }
        
        /* Improved spacing for better readability */
        .table tbody tr td {
            line-height: 1.3;
        }
        
        /* Table cell content spacing */
        .table tbody td > div {
            line-height: 1.3;
            min-height: 18px;
        }
        
        .table tbody tr td .text-gray-800 {
            color: #374151;
            font-weight: 500;
        }
        
        /* Fix alignment for all table headers */
        .table thead th > div {
            width: 100%;
            display: flex;
            align-items: center;
        }
        
        table thead th:hover {
            background: transparent !important;
        }
        
        table thead th.cursor-pointer {
            cursor: pointer;
            position: relative;
            user-select: none;
        }
        
        table thead th.cursor-pointer:hover {
            background: transparent !important;
        }
        
        table thead th.cursor-pointer:hover span {
            color: #1e3a8a !important;
        }
        
        /* Table Header Text Styling */
        table thead th span {
            color: #1e3a8a;
            font-weight: 700;
            font-size: 0.9375rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        /* Table Header Icons */
        table thead th i {
            font-size: 0.875rem;
            margin-left: 0.5rem;
        }
        
        /* Ensure proper spacing in header cells */
        table thead th .d-flex {
            gap: 0.5rem;
        }

        /* Checkbox Styling */
        .form-check-input {
            width: 1.375rem;
            height: 1.375rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid #d1d5db;
        }

        .form-check-input:hover {
            border-color: #1e3a8a;
        }
        
        .form-check-input:checked {
            background-color: #50cd89;
            border-color: #50cd89;
            box-shadow: none;
        }

        .form-check-input:focus {
            border-color: #1e3a8a;
            box-shadow: none;
        }

        /* Status Switch Enhanced */
        .form-check-input[type="checkbox"][style*="width: 40px"],
        .form-check-input[type="checkbox"][style*="width: 52px"],
        .form-check-input[type="checkbox"][style*="width: 48px"] {
            width: 40px !important;
            height: 22px !important;
            border-radius: 11px;
            transition: all 0.3s ease;
        }

        .form-check-input[type="checkbox"][style*="width: 40px"]:checked,
        .form-check-input[type="checkbox"][style*="width: 52px"]:checked,
        .form-check-input[type="checkbox"][style*="width: 48px"]:checked {
            background-color: #50cd89;
            border-color: #50cd89;
        }

        /* Empty State */
        .table tbody tr td[colspan] {
            padding: 3rem 1rem;
        }

        .table tbody tr td[colspan] .text-gray-500 {
            font-size: 1rem;
            color: #9ca3af;
        }

        /* Pagination Enhanced Styling */
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
            color: white;
            border-color: #1e3a8a;
            box-shadow: none;
        }
        
        .pagination .page-item.active .page-link {
            background: #1e3a8a;
            border-color: #1e3a8a;
            color: white;
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

        /* Per Page Selector */
        .form-select-sm {
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            transition: none;
            padding: 0.375rem 1.5rem 0.375rem 0.625rem;
        }

        .form-select-sm:hover {
            border-color: #1e3a8a;
        }

        .form-select-sm:focus {
            border-color: #1e3a8a;
            box-shadow: none;
        }

        /* Pagination Info Text */
        .text-gray-600 {
            color: #6b7280;
            font-size: 1.125rem;
        }

        /* Table Text Styling */
        .table tbody .text-gray-800 {
            color: #1f2937;
            font-weight: 500;
            font-size: 1.125rem;
        }

        /* Increase row height */
        .table tbody tr {
            min-height: 36px;
        }

        /* Increase icon sizes in buttons */
        .btn-icon i {
            font-size: 0.75rem !important;
        }

        /* Responsive Enhancements */
        @media (max-width: 768px) {
            .table thead th,
            .table tbody td {
                padding: 0.5rem 0.375rem;
                font-size: 1.125rem;
            }

            .btn-icon {
                width: 32px;
                height: 32px;
            }

            .pagination .page-link {
                padding: 0.5rem 0.625rem;
                min-width: 36px;
                font-size: 1.125rem;
            }
        }

        /* Loading State */
        .table tbody tr[wire\:loading] {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Smooth Scrolling */
        .table-responsive {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Table Header Styling */
        .table thead th {
            padding: 0.3rem 0.4rem;
            font-size: 0.8125rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        /* Dynamic column sizing - columns adjust based on content */
        .table thead th:not(:last-child),
        .table tbody td:not(:last-child) {
            width: auto !important;
        }
        
        /* Action column fixed width at the end */
        .table {
            table-layout: auto !important;
            width: 100% !important;
            border-right: none !important;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-right: none !important;
        }
        
        /* Remove right border from entire table structure */
        .table,
        .table thead,
        .table tbody,
        .table tr {
            border-right: none !important;
        }
        
        .table thead th:last-child,
        .table tbody td:last-child {
            width: auto !important;
            min-width: 120px !important;
            max-width: 150px !important;
            border-right: none !important;
            border-right-width: 0 !important;
            border-right-style: none !important;
            position: relative;
        }
        
        /* Remove any extra space or columns after the last column */
        .table thead th:last-child::after,
        .table tbody td:last-child::after {
            display: none !important;
            content: none !important;
        }
        
        /* Remove border from last cell in each row */
        .table tbody tr td:last-child,
        .table thead tr th:last-child {
            border-right: 0 !important;
            border-right-width: 0 !important;
            border-right-style: none !important;
        }
        
        /* Ensure action column background matches */
        .table tbody tr td:last-child {
            background: inherit !important;
            padding-right: 0.75rem !important;
            white-space: nowrap;
        }
        
        .table thead th:last-child {
            padding-right: 0.75rem !important;
        }
        
        .table tbody tr:hover td:last-child {
            background: inherit !important;
        }
        
        /* Prevent table from creating phantom columns */
        .table tbody tr {
            width: 100%;
        }
        
        /* Allow minimum widths for better readability */
        .table thead th.min-w-125px {
            min-width: 125px !important;
        }
        
        /* Compact table styling */
        .table {
            width: 100% !important;
            table-layout: auto !important;
        }
    </style>

    <script>
    function showDeleteConfirm(id, itemName, buttonElement) {
        const modal = document.getElementById('deleteConfirmModal');
        const messageEl = modal.querySelector('.modal-body p.text-gray-800');
        const confirmBtn = document.getElementById('deleteConfirmModalConfirmBtn');
        
        // Update message with item name
        messageEl.textContent = `Are you sure you want to delete "${itemName}"?`;
        
        // Remove previous event listeners by cloning the button
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        // Find the Livewire component from the button's closest wire:id
        const wireElement = buttonElement ? buttonElement.closest('[wire\\:id]') : document.querySelector('[wire\\:id]');
        const wireId = wireElement ? wireElement.getAttribute('wire:id') : null;
        
        // Add new event listener
        newConfirmBtn.addEventListener('click', function() {
            if (wireId && window.Livewire) {
                const component = window.Livewire.find(wireId);
                if (component) {
                    component.call('delete', id);
                }
            }
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        });
        
        // Show modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
    </script>

    <div class="table-responsive" style="background: transparent; border-radius: 0; overflow-x: auto; overflow-y: visible; max-width: 100%; padding: 0; margin: 0;">
    <table class="table align-middle table-row-dashed fs-6 mb-0" style="margin-bottom: 0 !important; background: transparent;">
        <thead>
            <tr class="fw-bold fs-7 text-uppercase gs-0" style="background: transparent; border-bottom: 2px solid #1e3a8a; border-top: none; border-left: none; border-right: none;">
                @if($showCheckbox)
                <th class="w-10px pe-2 text-center align-middle" style="width: 40px; padding: 0.4rem 0.5rem; border-top: none; border-left: none; border-right: none; vertical-align: middle; background: transparent;">
                    <div class="form-check form-check-sm form-check-custom form-check-solid d-flex justify-content-center align-items-center" style="margin: 0;">
                        <input class="form-check-input" 
                               type="checkbox" 
                               wire:model="selectAll"
                               style="cursor: pointer;"/>
                    </div>
                </th>
                @endif
                @if(!empty($columns) && is_array($columns))
                    @foreach($columns as $column)
                        @if(is_array($column))
                            @php
                                $field = $column['field'] ?? '';
                                $label = $column['label'] ?? '';
                                $colType = $column['type'] ?? null;
                                $colWidth = $column['width'] ?? null;
                                $headerAlign = 'left';
                                $headerJustify = 'justify-content-start';
                                $headerClass = 'text-start';
                                if ($colType === 'center' || $colType === 'status') {
                                    $headerAlign = 'center';
                                    $headerJustify = 'justify-content-center';
                                    $headerClass = 'text-center';
                                } elseif ($colType === 'right') {
                                    $headerAlign = 'right';
                                    $headerJustify = 'justify-content-end';
                                    $headerClass = 'text-end';
                                } elseif ($colType === 'left') {
                                    $headerAlign = 'left';
                                    $headerJustify = 'justify-content-start';
                                    $headerClass = 'text-start';
                                }
                                $widthStyle = $colWidth ? "width: {$colWidth};" : '';
                            @endphp
                            @if(!empty($field))
                            <th class="min-w-125px cursor-pointer align-middle {{ $headerClass }}" 
                                wire:click="sortBy('{{ $field }}')"
                                style="user-select: none; padding: 0.625rem 0.5rem; border-top: none; border-left: none; border-right: none; position: relative; vertical-align: middle; text-align: {{ $headerAlign }} !important; background: transparent; white-space: nowrap !important; overflow: visible !important; {{ $widthStyle }}">
                                <div class="d-flex align-items-center {{ $headerJustify }}" style="width: 100%; gap: 0.25rem; white-space: nowrap !important; overflow: visible !important;">
                                    <span style="color: #1e3a8a; font-weight: 700; font-size: 0.9375rem; letter-spacing: 0.5px; text-transform: uppercase; white-space: nowrap !important;">{!! strtoupper(str_replace(' ', '&nbsp;', $label)) !!}</span>
                                    @if(($sortField ?? '') === $field)
                                        @if(($sortDirection ?? 'asc') === 'asc')
                                            <i class="ki-duotone ki-up fs-5 text-primary ms-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        @else
                                            <i class="ki-duotone ki-down fs-5 text-primary ms-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        @endif
                                    @else
                                        <i class="ki-duotone ki-arrows-vertical fs-5 text-muted ms-2 opacity-50">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    @endif
                                </div>
                            </th>
                            @endif
                        @endif
                    @endforeach
                @endif
                @if($actions)
                <th class="text-center align-middle" style="min-width: 120px; max-width: 150px; width: auto; padding: 0.625rem 0.5rem; border-top: none; border-left: none; border-right: none; vertical-align: middle; background: transparent; white-space: nowrap; text-align: center !important;">
                    <div class="d-flex justify-content-center align-items-center" style="width: 100%; text-align: center;">
                        <span style="color: #1e3a8a; font-weight: 700; font-size: 0.9375rem; letter-spacing: 0.5px; text-transform: uppercase; text-align: center;">ACTION</span>
                    </div>
                </th>
                @endif
            </tr>
        </thead>
        <tbody class="text-gray-700 fw-semibold" style="background: transparent;">
            @forelse($items ?? [] as $item)
            <tr style="border-bottom: none;">
                @if($showCheckbox)
                <td class="text-center align-middle" style="width: 40px; vertical-align: middle; padding: 0.4rem 0.5rem;">
                    <div class="form-check form-check-sm form-check-custom form-check-solid d-flex justify-content-center align-items-center" style="margin: 0;">
                        <input class="form-check-input" 
                               type="checkbox" 
                               value="{{ $item->id ?? '' }}"
                               wire:model="selectedItems"/>
                    </div>
                </td>
                @endif
                @if(!empty($columns) && is_array($columns))
                    @foreach($columns as $column)
                        @if(is_array($column))
                            @php
                                $field = $column['field'] ?? '';
                                $type = $column['type'] ?? null;
                                $colWidth = $column['width'] ?? null;
                            @endphp
                            @if(!empty($field))
                            @php
                                $textAlign = 'left';
                                $textAlignClass = 'text-start';
                                if ($type === 'status' || $type === 'center') {
                                    $textAlign = 'center';
                                    $textAlignClass = 'text-center';
                                } elseif ($type === 'right') {
                                    $textAlign = 'right';
                                    $textAlignClass = 'text-end';
                                } elseif ($type === 'left') {
                                    $textAlign = 'left';
                                    $textAlignClass = 'text-start';
                                }
                                $widthStyle = $colWidth ? "width: {$colWidth};" : '';
                            @endphp
                            <td class="align-middle {{ $textAlignClass }}" style="vertical-align: middle; text-align: {{ $textAlign }} !important; padding: 0.5rem 0.5rem; line-height: 1.4; border-bottom: none; white-space: nowrap; {{ $widthStyle }}">
                                @if(isset($column['render']) && is_callable($column['render']))
                                    <div class="d-flex align-items-center {{ $textAlignClass === 'text-end' ? 'justify-content-end' : ($textAlignClass === 'text-center' ? 'justify-content-center' : 'justify-content-start') }}" style="width: 100%; text-align: {{ $textAlign }};">
                                        <span style="text-align: {{ $textAlign }};">{!! $column['render']($item) !!}</span>
                                    </div>
                                @elseif(isset($type) && $type === 'status')
                                    <div class="form-check form-switch form-check-custom form-check-solid form-check-success d-flex justify-content-center align-items-center" style="margin: 0 auto;">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               wire:change="toggleStatus({{ is_numeric($item->id ?? 0) ? ($item->id ?? 0) : json_encode($item->id ?? 0) }})"
                                               @if($item->status ?? false) checked @endif
                                               style="cursor: pointer; width: 40px; height: 22px;"/>
                                    </div>
                                @elseif(isset($type) && $type === 'center')
                                    <div class="d-flex align-items-center justify-content-center" style="min-height: 24px; width: 100%; text-align: center;">
                                        <span style="color: #374151; font-size: 1.125rem; text-align: center;">{{ data_get($item, $field, '') }}</span>
                                    </div>
                                @elseif(isset($type) && $type === 'right')
                                    <div class="d-flex align-items-center justify-content-end" style="min-height: 24px; width: 100%;">
                                        <span style="color: #374151; font-size: 1.125rem;">{{ data_get($item, $field, '') }}</span>
                                    </div>
                                @elseif(isset($type) && $type === 'left')
                                    <div class="d-flex align-items-center justify-content-start" style="min-height: 24px; width: 100%;">
                                        <span style="color: #374151; font-size: 1.125rem; font-weight: 500;">{{ data_get($item, $field, '') }}</span>
                                    </div>
                                @else
                                    <div class="d-flex align-items-center justify-content-start" style="min-height: 24px; width: 100%;">
                                        <span style="color: #374151; font-size: 1.125rem; font-weight: 500;">{{ data_get($item, $field, '') }}</span>
                                    </div>
                                @endif
                            </td>
                            @endif
                        @endif
                    @endforeach
                @endif
                @if($actions)
                <td class="text-center align-middle" style="min-width: 120px; max-width: 150px; width: auto; vertical-align: middle; padding: 0.5rem 0.5rem; line-height: 1.4; border-bottom: none; white-space: nowrap; text-align: center !important;">
                    <div class="d-flex flex-row justify-content-center align-items-center gap-2" style="min-height: 28px; flex-wrap: nowrap; width: 100%; text-align: center;">
                        @php
                            $itemName = $item->name ?? $item->code ?? 'this item';
                            $itemNameEscaped = addslashes($itemName);
                            $itemId = $item->id ?? 0;
                            $itemIdValue = is_numeric($itemId) ? $itemId : json_encode($itemId);
                        @endphp
                        @if($showView)
                        <a href="#" 
                           wire:click.prevent="openViewModal({{ $itemIdValue }})"
                           class="btn-icon-transparent btn-icon-view" 
                           data-bs-toggle="tooltip" 
                           title="View"
                           style="width: auto; height: auto; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0.625rem; background: transparent !important; border: none !important; cursor: pointer; border-radius: 0.375rem;">
                            <i class="fa-solid fa-eye" style="font-size: 1.375rem; line-height: 1; transition: color 0.2s;"></i>
                        </a>
                        @endif
                        @if($showEdit)
                        <a href="#" 
                           wire:click.prevent="openEditModal({{ $itemIdValue }})"
                           class="btn-icon-transparent btn-icon-edit" 
                           data-bs-toggle="tooltip" 
                           title="Edit"
                           style="width: auto; height: auto; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0.625rem; background: transparent !important; border: none !important; cursor: pointer; border-radius: 0.375rem;">
                            <i class="fa-solid fa-pen" style="font-size: 1.375rem; line-height: 1; transition: color 0.2s;"></i>
                        </a>
                        @endif
                        @if(method_exists($this, 'canApprove') && $this->canApprove($item))
                            <a href="#" 
                               wire:click.prevent="approveOrder({{ $itemIdValue }})" 
                               wire:confirm="Are you sure you want to approve this order?"
                               class="btn-icon-transparent btn-icon-approve" 
                               data-bs-toggle="tooltip" 
                               title="Approve"
                               style="width: auto; height: auto; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0.625rem; background: transparent !important; border: none !important; cursor: pointer; border-radius: 0.375rem;">
                                <i class="fa-solid fa-check" style="font-size: 1.375rem; line-height: 1; transition: color 0.2s;"></i>
                            </a>
                        @endif
                        @if(method_exists($this, 'canCancel') && $this->canCancel($item))
                            <a href="#" 
                               wire:click.prevent="cancel({{ $itemIdValue }})" 
                               wire:confirm="Are you sure you want to cancel this order? Stock will be returned."
                               class="btn-icon-transparent btn-icon-cancel" 
                               data-bs-toggle="tooltip" 
                               title="Cancel"
                               style="width: auto; height: auto; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0.625rem; background: transparent !important; border: none !important; cursor: pointer; border-radius: 0.375rem;">
                                <i class="fa-solid fa-times" style="font-size: 1.375rem; line-height: 1; transition: color 0.2s;"></i>
                            </a>
                        @endif
                        @if($showDelete)
                        <a href="#" 
                           onclick="event.preventDefault(); showDeleteConfirm({{ $itemIdValue }}, '{{ $itemNameEscaped }}', this);"
                           class="btn-icon-transparent btn-icon-delete" 
                           data-bs-toggle="tooltip" 
                           title="Delete"
                           style="width: auto; height: auto; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0.625rem; background: transparent !important; border: none !important; cursor: pointer; border-radius: 0.375rem;">
                            <i class="fa-solid fa-trash" style="font-size: 1.375rem; line-height: 1; transition: color 0.2s;"></i>
                        </a>
                        @endif
                    </div>
                </td>
                @endif
            </tr>
            @empty
            <tr>
                @php
                    $columnCount = !empty($columns) && is_array($columns) ? count($columns) : 1;
                    $checkboxCol = $showCheckbox ? 1 : 0;
                    $actionsCol = $actions ? 1 : 0;
                    $colspan = $columnCount + $checkboxCol + $actionsCol;
                @endphp
                <td colspan="{{ $colspan }}" class="text-center py-10">
                    <div class="d-flex flex-column align-items-center justify-content-center">
                        <i class="fa-solid fa-inbox fs-2x text-gray-400 mb-3"></i>
                        <div class="text-gray-500 fw-semibold" style="font-size: 1.125rem;">No records found</div>
                        <div class="text-gray-400 small mt-1">Try adjusting your search or filters</div>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>

    @if(isset($items) && is_object($items) && method_exists($items, 'hasPages') && $items->hasPages())
        @php
            $paginator = $items;
            $elements = $paginator->getUrlRange(1, $paginator->lastPage());
            // Create pagination elements array
            $paginationElements = [];
            $currentPage = $paginator->currentPage();
            $lastPage = $paginator->lastPage();
            
            // Always show first page
            if ($currentPage > 3) {
                $paginationElements[] = [1 => $paginator->url(1)];
                if ($currentPage > 4) {
                    $paginationElements[] = '...';
                }
            }
            
            // Show pages around current page
            $start = max(1, $currentPage - 2);
            $end = min($lastPage, $currentPage + 2);
            
            for ($i = $start; $i <= $end; $i++) {
                $paginationElements[] = [$i => $paginator->url($i)];
            }
            
            // Always show last page
            if ($currentPage < $lastPage - 2) {
                if ($currentPage < $lastPage - 3) {
                    $paginationElements[] = '...';
                }
                $paginationElements[] = [$lastPage => $paginator->url($lastPage)];
            }
        @endphp
        <div class="d-flex justify-content-between align-items-center" style="border-top: 1px solid #e5e7eb; padding: 0.75rem 0.75rem 0.75rem 0.75rem; margin: 0;">
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center">
                    <label class="text-gray-700 me-2 mb-0 fw-semibold" style="font-size: 1.125rem;">Show:</label>
                    <select class="form-select form-select-sm form-select-solid"
                            wire:model.live="perPage"
                            wire:loading.attr="disabled"
                            style="width: 70px; padding: 0.375rem 0.5rem; border-radius: 0.375rem; border-color: #e5e7eb; font-size: 1.125rem;">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                {{-- <span class="text-gray-600 fw-medium" style="font-size: 1.125rem;">
                    Showing <span class="fw-bold" style="color: #1e3a8a;">{{ $paginator->firstItem() ?? 0 }}</span> to 
                    <span class="fw-bold" style="color: #1e3a8a;">{{ $paginator->lastItem() ?? 0 }}</span> of 
                    <span class="fw-bold" style="color: #1e3a8a;">{{ $paginator->total() }}</span> results
                </span> --}}
            </div>
            <nav>
                <ul class="pagination mb-0">
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true">
                            <span class="page-link" aria-hidden="true">&lsaquo;</span>
                        </li>
                    @else
                        <li class="page-item">
                            <button type="button" 
                                    class="page-link" 
                                    wire:click="previousPage"
                                    wire:loading.attr="disabled"
                                    aria-label="Previous">
                                <span aria-hidden="true">&lsaquo;</span>
                            </button>
                        </li>
                    @endif
                    
                    @foreach($paginationElements as $element)
                        @if(is_string($element) && $element === '...')
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        @elseif(is_array($element))
                            @foreach($element as $page => $url)
                                @if($page == $paginator->currentPage())
                                    <li class="page-item active" aria-current="page">
                                        <span class="page-link">{{ $page }}</span>
                                    </li>
                                @else
                                    <li class="page-item">
                                        <button type="button" 
                                                class="page-link" 
                                                wire:click="gotoPage({{ $page }})"
                                                wire:loading.attr="disabled">
                                            {{ $page }}
                                        </button>
                                    </li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                    
                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <button type="button" 
                                    class="page-link" 
                                    wire:click="nextPage"
                                    wire:loading.attr="disabled"
                                    aria-label="Next">
                                <span aria-hidden="true">&rsaquo;</span>
                            </button>
                        </li>
                    @else
                        <li class="page-item disabled" aria-disabled="true">
                            <span class="page-link" aria-hidden="true">&rsaquo;</span>
                        </li>
                    @endif
                </ul>
            </nav>
        </div>
    @elseif(isset($items) && is_object($items) && method_exists($items, 'total'))
        {{-- <div class="d-flex justify-content-between align-items-center" style="border-top: 1px solid #e5e7eb; padding: 0.75rem 0.75rem; margin: 0;">
            <div class="text-gray-600 fw-medium" style="font-size: 1.125rem;">
                Showing <span class="fw-bold" style="color: #1e3a8a;">{{ $items->count() }}</span> of 
                <span class="fw-bold" style="color: #1e3a8a;">{{ $items->total() }}</span> results
            </div>
        </div> --}}
    @endif
</div>

<script>
(function() {
    function initTooltips() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            const existingTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
            if (existingTooltip) {
                existingTooltip.dispose();
            }
            const tooltip = new bootstrap.Tooltip(tooltipTriggerEl);
            
            tooltipTriggerEl.addEventListener('click', function() {
                tooltip.hide();
            });
        });
    }
    
    function hideAllTooltips() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            const tooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
            if (tooltip) {
                tooltip.hide();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTooltips);
    } else {
        initTooltips();
    }

    if (typeof Livewire !== 'undefined') {
        document.addEventListener('livewire:init', function() {
            Livewire.hook('morph.updated', function() {
                hideAllTooltips();
                setTimeout(initTooltips, 100);
            });
        });
        
        document.addEventListener('livewire:click', function() {
            hideAllTooltips();
        });
    }
    
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-icon') || e.target.closest('[wire\\:click]')) {
            hideAllTooltips();
        }
    });
})();
</script>
