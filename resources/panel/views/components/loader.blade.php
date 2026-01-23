@props(['target'=>null, 'inline' => false])
@if($inline)
    {{-- Inline loader for tables and specific components --}}
    <div class="d-flex justify-content-center align-items-center py-3"
         @if($target)
             wire:target="{{$target}}"
         @endif
         wire:loading.flex>
        <span class="spinner-border spinner-border-sm text-primary me-2" role="status"></span>
        <span class="text-muted">{{__('app.panel.loader')}}</span>
    </div>
@else
    {{-- Full-screen loader only for non-navigation events --}}
    <div class="app-page-loader justify-content-center align-items-center bg-black text-white flex-column opacity-50"
         @if($target)
             wire:target="{{$target}}"
         @endif
         wire:loading.flex>
        <div class="mb-4">
            <img src="{{ mix('build/panel/images/logo/logo.jpg') }}" alt="Hari Builders Logo" style="max-width: 50px; height: auto; margin: 0 auto;" />
        </div>
        <span class="spinner-border text-primary" role="status"></span>
        <span class="text-muted fs-6 fw-semibold mt-5">{{__('app.panel.loader')}}</span>
    </div>
@endif
