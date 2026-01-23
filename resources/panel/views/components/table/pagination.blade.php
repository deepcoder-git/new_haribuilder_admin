@props(['name'])
@php
    $paginator = $paginator ?? null;
    $elements = $elements ?? [];
    $elements = is_array($elements) ? $elements : [];
@endphp
@if(isset($paginator) && $paginator)
<div class="table-pagination">
    <style>
        .table-pagination .page-link {
            color: #6b7280;
            border-color: #e5e7eb;
            transition: all 0.3s ease;
        }
        .table-pagination .page-link:hover:not(.disabled):not([aria-disabled="true"]) {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
        }
        .table-pagination .page-item.active .page-link {
            background: #f59e0b;
            border-color: #f59e0b;
            color: white;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
        }
        .table-pagination .page-item.disabled .page-link {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
    <nav class="d-flex justify-items-center justify-content-between">
        <div class="d-flex justify-content-between flex-fill d-sm-none">
            <ul class="pagination">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">{{__('pagination.previous')}}</span>
                    </li>
                @else
                    <li class="page-item">
                        <button class="page-link" wire:click="previousPage('{{$paginator->getPageName()}}')"
                                wire:loading.attr="disabled"
                                x-on:click='($el.closest("body") || document.querySelector("body")).scrollIntoView({behavior: "smooth"});'
                                rel="prev"
                                type="button" rel="prev">{{__('pagination.previous')}}</button>
                    </li>
                @endif
                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <button class="page-link" wire:click="nextPage('{{$paginator->getPageName()}}')"
                                wire:loading.attr="disabled"
                                x-on:click='($el.closest("body") || document.querySelector("body")).scrollIntoView({behavior: "smooth"});'
                                rel="next">{{__('pagination.next')}}</button>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">{{__('pagination.next')}}</span>
                    </li>
                @endif
            </ul>
        </div>
        <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between">
            <div class="d-flex justify-content-center align-content-center align-items-baseline">
                <div>
                    <select class="form-select form-select-sm form-select-solid"
                            wire:loading.attr="disabled"
                            x-on:change='($el.closest("body") || document.querySelector("body")).scrollIntoView({behavior: "smooth"});'
                            wire:model.live.debounce.800ms="{{$name}}.perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <p class="small text-gray-800 ms-4 mb-0">
                    {!! __('Showing') !!}
                    <span class="fw-semibold">{{ method_exists($paginator, 'firstItem') ? ($paginator->firstItem() ?? 0) : 0 }}</span>
                    {!! __('to') !!}
                    <span class="fw-semibold">{{ method_exists($paginator, 'lastItem') ? ($paginator->lastItem() ?? 0) : 0 }}</span>
                    {!! __('of') !!}
                    <span class="fw-semibold">{{ method_exists($paginator, 'total') ? ($paginator->total() ?? 0) : 0 }}</span>
                    {!! __('results') !!}
                </p>
            </div>
            <div>
                <ul class="pagination">
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true"
                            aria-label="{{__('pagination.previous')}}">
                            <span class="page-link" aria-hidden="true">&lsaquo;</span>
                        </li>
                    @else
                        <li class="page-item">
                            <button class="page-link" wire:click="previousPage('{{$paginator->getPageName()}}')"
                                    rel="prev"
                                    wire:loading.attr="disabled"
                                    x-on:click='($el.closest("body") || document.querySelector("body")).scrollIntoView({behavior: "smooth"});'
                                    type="button"
                                    aria-label="{{__('pagination.previous')}}">&lsaquo;
                            </button>
                        </li>
                    @endif
                    @if(isset($elements) && is_array($elements))
                        @foreach ($elements as $element)
                            @if (is_string($element))
                                <li class="page-item disabled" aria-disabled="true"><span
                                        class="page-link">{{ $element }}</span></li>
                            @elseif (is_array($element) && !empty($element))
                                @foreach ($element as $page => $url)
                                    @if(is_numeric($page) && !empty($url))
                                        @if ($page == $paginator->currentPage())
                                            <li class="page-item active" aria-current="page">
                                                <span class="page-link">{{ $page }}</span>
                                            </li>
                                        @else
                                            <li class="page-item">
                                                <button type="button"
                                                        wire:loading.attr="disabled"
                                                        wire:click="gotoPage({{$page}}, '{{$paginator->getPageName()}}')"
                                                        x-on:click='($el.closest("body") || document.querySelector("body")).scrollIntoView({behavior: "smooth"});'
                                                        class="page-link"
                                                        href="{{ $url }}">{{ $page }}</button>
                                            </li>
                                        @endif
                                    @endif
                                @endforeach
                            @endif
                        @endforeach
                    @endif
                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <button class="page-link" wire:click="nextPage('{{$paginator->getPageName()}}')"
                                    rel="next"
                                    wire:loading.attr="disabled"
                                    x-on:click='($el.closest("body") || document.querySelector("body")).scrollIntoView({behavior: "smooth"});'
                                    type="button"
                                    aria-label="{{__('pagination.next')}}">&rsaquo;
                            </button>
                        </li>
                    @else
                        <li class="page-item disabled" aria-disabled="true" aria-label="{{__('pagination.next')}}">
                            <span class="page-link" aria-hidden="true">&rsaquo;</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
</div>
@endif
