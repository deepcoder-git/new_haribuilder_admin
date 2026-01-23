<div class="card">
    <x-view-header 
        :moduleName="$moduleName ?? 'Supplier'" 
        :moduleIcon="$moduleIcon ?? 'truck'" 
        :indexRoute="$indexRoute ?? 'admin.suppliers.index'"
        :editRoute="$editRoute ?? 'admin.suppliers.edit'"
        :editId="$editId ?? $supplier->id ?? $supplier->slug ?? null"
    />
    <div class="card-body p-8">
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Name</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $supplier->name ?? 'N/A' }}</span>
        </div>
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Email</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $supplier->email ?? 'N/A' }}</span>
        </div>
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Phone</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $supplier->phone ?? 'N/A' }}</span>
        </div>
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Address</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $supplier->address ?? 'N/A' }}</span>
        </div>
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Description</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $supplier->description ?? 'N/A' }}</span>
        </div>
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">TIN Number</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $supplier->tin_number ?? 'N/A' }}</span>
        </div>
    </div>
</div>

