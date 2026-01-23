<div class="card">
    <x-view-header 
        :moduleName="$moduleName ?? 'Site'" 
        :moduleIcon="$moduleIcon ?? 'building'" 
        :indexRoute="$indexRoute ?? 'admin.sites.index'"
        :editRoute="$editRoute ?? 'admin.sites.edit'"
        :editId="$editId ?? $site->id ?? null"
    />
    <div class="card-body p-8">
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Site Name</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $site->name ?? 'N/A' }}</span>
        </div>
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Location</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $site->location ?? 'N/A' }}</span>
        </div>
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Date</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">
                @if($site->start_date && $site->end_date)
                    {{ $site->start_date->format('d-m-Y') }} To {{ $site->end_date->format('d-m-Y') }}
                @elseif($site->start_date)
                    {{ $site->start_date->format('d-m-Y') }}
                @else
                    N/A
                @endif
            </span>
        </div>
        @if($site->siteManager)
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Site Supervisor</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $site->siteManager->name ?? 'N/A' }}</span>
        </div>
        @endif
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Status</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="ms-2">
                @if($site->status)
                    <span class="badge badge-light-success">Active</span>
                @else
                    <span class="badge badge-light-danger">Inactive</span>
                @endif
            </span>
        </div>
      
    
    </div>
</div>

