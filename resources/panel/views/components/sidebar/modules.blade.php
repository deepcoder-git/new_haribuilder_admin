<div class="menu menu-column menu-rounded menu-sub-indention fw-semibold fs-6"
     id="kt_app_sidebar_menu"
     data-kt-menu="true"
     data-kt-menu-expand="false">

    @foreach($modules as $module)
        @if($module->unique_name === 'admin.stocks')
            @continue
        @endif
        @if($module->children->count())
            <div data-kt-menu-trigger="click" @class([
                'menu-item',
                'menu-accordion',
                'mb-5',
                'show'=>request()->route() && in_array(request()->route()->getName(),$module->children->pluck('sub_routes')->flatten()->toArray())
            ])>
                <span class="menu-link">
                    <span class="menu-icon">{!! $module->icon ?? '<i class="fa-solid fa-circle"></i>' !!}</span>
                    <span class="menu-title">{{ucwords($module->name)}}</span>
                    <span class="menu-arrow">
                        <i class="fa-solid fa-chevron-right"></i>
                    </span>
                </span>
                <div class="menu-sub menu-sub-accordion">
                    @php
                        $sortedChildren = $module->children->sortBy(function($child) {
                            $orderMap = ['Product' => 1, 'Category' => 2, 'Unit' => 3];
                            return $orderMap[$child->name] ?? $child->order ?? 999;
                        });
                    @endphp
                    @foreach($sortedChildren as $childModule)
                        @php
                            $isSubActive = request()->route() && in_array(request()->route()->getName(),$childModule->sub_routes ?? []);
                        @endphp
                        <div class="menu-item">
                            <a @class(['menu-link','active'=>$isSubActive])
                               href="{{$childModule->index_route && Route::has($childModule->index_route) ? route($childModule->index_route) : 'javascript:void(0)'}}">
                                <span class="menu-bullet">
                                    <span class="bullet bullet-dot"></span>
                                </span>
                                <span class="menu-title">{{$childModule->name}}</span>
                                @if($childModule->index_route && !Route::has($childModule->index_route))
                                    <span class="badge badge-danger ms-2" title="Route '{{ $childModule->index_route }}' not defined">!</span>
                                @endif
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            @php
                $isActive = (request()->route() && in_array(request()->route()->getName(),$module->sub_routes ?? [])) || ($module->index_route && request()->routeIs($module->index_route));
            @endphp
            <div class="menu-item mb-5">
                <a @class(['menu-link','active'=>$isActive])
                   href="{{$module->index_route && Route::has($module->index_route) ? route($module->index_route) : 'javascript:void(0)'}}">
                    <span class="menu-icon">{!! $module->icon ?? '<i class="fa-solid fa-circle"></i>' !!}</span>
                    <span class="menu-title">{{ucwords($module->name)}}</span>
                    @if($module->index_route && !Route::has($module->index_route))
                        <span class="badge badge-danger ms-2" title="Route '{{ $module->index_route }}' not defined">!</span>
                    @endif
                </a>
            </div>
        @endif
    @endforeach
    
    <!-- Logout Item -->
    <div class="menu-item mt-5 pt-5">
        <a class="menu-link"
           href="javascript:void(0);"
           onclick="document.getElementById('logout-user').submit();">
            <span class="menu-icon">
                <i class="fa-solid fa-right-from-bracket fs-5"></i>
            </span>
            <span class="menu-title">Logout</span>
        </a>
    </div>

</div>

