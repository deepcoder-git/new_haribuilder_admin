@php
    $breadcrumb = $breadcrumb ?? [];
    $breadcrumb = is_array($breadcrumb) ? $breadcrumb : [];
    $showTitle = !empty($title);
@endphp
@if($showTitle || (!empty($breadcrumb) && count($breadcrumb) > 0))
<div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
     data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}"
     class="page-title d-flex flex-column justify-content-center flex-wrap me-2 mb-0" style="margin-bottom: 0 !important; padding: 0.5rem 0; border: none !important;">
    @if (!empty($breadcrumb) && count($breadcrumb) > 0)
        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-0" style="font-size: 0.875rem !important; margin: 0 !important; padding: 0 !important; line-height: 2.0; border: none !important;">
            <li class="breadcrumb-item text-muted" style="padding: 0; margin: 0; border: none !important;">
                <a href="{{route('admin.dashboard')}}"
                   class="text-muted text-hover-primary" 
                   style="font-size: 17px !important; color: #1e3a8a !important; text-decoration: none; transition: color 0.2s ease; border: none !important;">
                   {{__('admin.dashboard')}}
                </a>
            </li>
            @foreach ($breadcrumb as $crumb)
                @if(is_array($crumb) && !empty($crumb) && isset($crumb[0]) && isset($crumb[1]))
                    @php
                        $crumbTitle = $crumb[0] ?? '';
                        $crumbUrl = $crumb[1] ?? '#';
                    @endphp
                    @if(!empty($crumbTitle) && !empty($crumbUrl))
                    <li class="breadcrumb-item text-muted" style="padding: 0 0.5rem; margin: 0; border: none !important;">
                        <span style="font-size: 17px !important; color: #1e3a8a !important; border: none !important;"> > </span>
                    </li>
                    <li class="breadcrumb-item text-muted" style="padding: 0; margin: 0; border: none !important;">
                        <a href="{{ $crumbUrl }}" 
                           class="text-muted text-hover-primary" 
                           style="font-size: 17px !important; color: #1e3a8a !important; text-decoration: none; transition: color 0.2s ease; border: none !important;">
                           {{ ucwords($crumbTitle) }}
                        </a>
                    </li>
                    @endif
                @endif
            @endforeach
        </ul>
    @endif
</div>
@endif
