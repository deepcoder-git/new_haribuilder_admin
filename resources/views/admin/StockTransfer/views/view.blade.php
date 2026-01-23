<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                    @if($transfer)
                        Stock Transfer Details
                    @else
                        Stock Transfer History
                    @endif
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Home</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-400 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-muted">
                        <a href="{{ route('admin.stock-transfers.index') }}" class="text-muted text-hover-primary">Stock Transfers</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-400 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-dark">
                        @if($transfer)
                            Transfer #{{ $transfer->id }}
                        @else
                            History
                        @endif
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-xxl">
            @if($transfer)
                <!-- Single Transfer Details View -->
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h3 class="fw-bold m-0">Transfer #{{ $transfer->id }}</h3>
                        </div>
                        <div class="card-toolbar">
                            <a href="{{ route('admin.stock-transfers.history') }}" class="btn btn-sm btn-light">
                                <i class="fa-solid fa-arrow-left me-1"></i>Back to History
                            </a>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row mb-10">
                            <div class="col-md-6">
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">From Site</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span class="text-gray-800 fw-bold fs-6">{{ $transfer->fromSite->name ?? 'N/A' }}</span>
                                </div>
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">To Site</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span class="text-gray-800 fw-bold fs-6">{{ $transfer->toSite->name ?? 'N/A' }}</span>
                                </div>
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Transfer Date</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span class="text-gray-800 fw-bold fs-6">{{ $transfer->transfer_date->format('d/m/Y') }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Transfer Status</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span>{!! $this->renderStatusBadge($transfer->transfer_status) !!}</span>
                                </div>
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Status</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span class="ms-2">
                                        @if($transfer->status)
                                            <span class="badge badge-light-success">Active</span>
                                        @else
                                            <span class="badge badge-light-danger">Inactive</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Created At</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span class="text-gray-800 fw-bold fs-6">{{ $transfer->created_at->format('d-m-Y H:i') }}</span>
                                </div>
                            </div>
                        </div>

                        @if($transfer->notes)
                            <div class="separator separator-dashed my-5"></div>
                            <div class="mb-3" style="line-height: 2.5;">
                                <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Notes</span>
                                <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                <span class="text-gray-800 fw-bold fs-6">{{ $transfer->notes }}</span>
                            </div>
                        @endif

                        <div class="separator separator-dashed my-5"></div>
                        <h4 class="fw-bold mb-5">Products</h4>
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th class="min-w-100px">Product</th>
                                        <th class="min-w-100px text-end">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transfer->products as $product)
                                        <tr>
                                            <td class="fw-bold">{{ $product->product_name }}</td>
                                            <td class="text-end fw-bold">{{ formatQty($product->pivot->quantity) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">No products found</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <!-- Transfer History List with Filters -->
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h3 class="fw-bold m-0">Stock Transfer History</h3>
                        </div>
                        <div class="card-toolbar">
                            <a href="{{ route('admin.stock-transfers.index') }}" class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-plus me-1"></i>New Transfer
                            </a>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <!-- Filters -->
                        <div class="card mb-5">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">From Site</label>
                                        <select wire:model.live="from_site_id" class="form-select form-select-solid">
                                            <option value="">All Sites</option>
                                            @foreach($sites as $site)
                                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">To Site</label>
                                        <select wire:model.live="to_site_id" class="form-select form-select-solid">
                                            <option value="">All Sites</option>
                                            @foreach($sites as $site)
                                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select wire:model.live="transfer_status" class="form-select form-select-solid">
                                            <option value="">All Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="in_transit">In Transit</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Date From</label>
                                        <input type="text" 
                                               wire:model.live="date_from" 
                                               class="form-control form-control-solid flatpickr-date"
                                               data-flatpickr-type="date"
                                               placeholder="dd/mm/yyyy">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Date To</label>
                                        <input type="text" 
                                               wire:model.live="date_to" 
                                               class="form-control form-control-solid flatpickr-date"
                                               data-flatpickr-type="date"
                                               placeholder="dd/mm/yyyy">
                                    </div>
                                </div>
                                <div class="row g-3 mt-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Search</label>
                                        <input type="text" 
                                               wire:model.live.debounce.300ms="search" 
                                               class="form-control form-control-solid"
                                               placeholder="Search by site, product, notes...">
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end gap-2">
                                        <button wire:click="resetFilters" class="btn btn-sm btn-light">
                                            <i class="fa-solid fa-rotate me-1"></i>Reset Filters
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Transfer List -->
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th class="min-w-50px">ID</th>
                                        <th class="min-w-150px">From Site</th>
                                        <th class="min-w-150px">To Site</th>
                                        <th class="min-w-100px">Transfer Date</th>
                                        <th class="min-w-100px">Status</th>
                                        <th class="min-w-100px">Products</th>
                                        <th class="min-w-100px text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transfers as $transferItem)
                                        <tr>
                                            <td class="fw-bold">#{{ $transferItem->id }}</td>
                                            <td class="fw-bold">{{ $transferItem->fromSite->name ?? 'N/A' }}</td>
                                            <td class="fw-bold">{{ $transferItem->toSite->name ?? 'N/A' }}</td>
                                            <td>{{ $transferItem->transfer_date->format('d/m/Y') }}</td>
                                            <td>{!! $this->renderStatusBadge($transferItem->transfer_status) !!}</td>
                                            <td>
                                                <span class="badge badge-light-info">{{ $transferItem->products->count() }} Product(s)</span>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('admin.stock-transfers.view', $transferItem->id) }}" 
                                                   class="btn btn-sm btn-light btn-active-light-primary">
                                                    <i class="fa-solid fa-eye me-1"></i>View Details
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-10">
                                                No transfers found
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-5">
                            <div class="text-muted">
                                Showing {{ $transfers->firstItem() ?? 0 }} to {{ $transfers->lastItem() ?? 0 }} of {{ $transfers->total() }} entries
                            </div>
                            <div>
                                {{ $transfers->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

