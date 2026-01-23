@props(['items'])

@if($items->hasPages())
    @php
        $paginator = $items;
        $paginationElements = [];
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        
        if ($currentPage > 3) {
            $paginationElements[] = [1 => $paginator->url(1)];
            if ($currentPage > 4) {
                $paginationElements[] = '...';
            }
        }
        
        $start = max(1, $currentPage - 2);
        $end = min($lastPage, $currentPage + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            $paginationElements[] = [$i => $paginator->url($i)];
        }
        
        if ($currentPage < $lastPage - 2) {
            if ($currentPage < $lastPage - 3) {
                $paginationElements[] = '...';
            }
            $paginationElements[] = [$lastPage => $paginator->url($lastPage)];
        }
    @endphp
@endif

<div class="d-flex justify-content-between align-items-center border-top bg-white px-3" style="padding-top: 0.5rem; padding-bottom: 0.5rem;">
    <div class="d-flex align-items-center">
        <label class="text-gray-700 me-2 mb-0 fw-semibold">Show:</label>
        <select class="form-select form-select-sm" wire:model.live="perPage" style="width: 85px; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
    </div>
    <div class="flex-grow-1 d-flex justify-content-center">
        <span class="text-gray-600 fs-6">
            @if($items->total() > 0)
                Showing <span class="fw-bold text-primary">{{ $items->firstItem() ?? 0 }}</span> to 
                <span class="fw-bold text-primary">{{ $items->lastItem() ?? 0 }}</span> of 
                <span class="fw-bold text-primary">{{ $items->total() }}</span> results
            @else
                <span class="fw-bold text-primary">0</span> results
            @endif
        </span>
    </div>
    @if($items->hasPages())
    <nav>
        <ul class="pagination mb-0">
            @if ($items->onFirstPage())
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
                        @if($page == $items->currentPage())
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
            
            @if ($items->hasMorePages())
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
    @else
    <div style="width: 0;"></div>
    @endif
</div>

