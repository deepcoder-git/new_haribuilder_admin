<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="currentColor"/>
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="currentColor"/>
                    </svg>
                </span>
                <input type="text" wire:model.live.debounce.300ms="search" class="form-control form-control-solid w-250px ps-15" placeholder="Search notifications..."/>
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex justify-content-end">
                @if($unreadCount > 0)
                    <button type="button" wire:click="markAllAsRead" class="btn btn-sm btn-primary me-2">
                        <i class="fa-solid fa-check-double me-1"></i>Mark All as Read
                    </button>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                        <th class="min-w-100px">Type</th>
                        <th class="min-w-200px">Title</th>
                        <th class="min-w-300px">Message</th>
                        <th class="min-w-100px">Date</th>
                        <th class="min-w-100px text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 fw-semibold">
                    @forelse($items as $notification)
                        <tr class="{{ $notification->read_at ? '' : 'table-active' }}">
                            <td>
                                <span class="badge badge-light-{{ match($notification->data['type'] ?? '') {
                                    'order_created' => 'primary',
                                    'transport_manager_assigned' => 'info',
                                    'delivery_completed' => 'success',
                                    'order_status_changed' => 'warning',
                                    'order_completed' => 'success',
                                    'order_cancelled' => 'danger',
                                    default => 'secondary'
                                } }}">
                                    {{ ucfirst(str_replace('_', ' ', $notification->data['type'] ?? 'notification')) }}
                                </span>
                            </td>
                            <td>
                                @if(isset($notification->data['link']))
                                    <a href="{{ $notification->data['link'] }}" 
                                       wire:click.prevent="markAsRead('{{ $notification->id }}', '{{ $notification->data['link'] }}')"
                                       class="text-primary text-hover-primary {{ $notification->read_at ? '' : 'fw-bold' }}"
                                       style="text-decoration: none; cursor: pointer;">
                                        {{ $notification->data['title'] ?? 'Notification' }}
                                        <i class="fa-solid fa-external-link-alt ms-1" style="font-size: 0.75rem;"></i>
                                    </a>
                                @else
                                    <span class="{{ $notification->read_at ? '' : 'fw-bold' }}">
                                        {{ $notification->data['title'] ?? 'Notification' }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if(isset($notification->data['link']))
                                    <a href="{{ $notification->data['link'] }}" 
                                       wire:click.prevent="markAsRead('{{ $notification->id }}', '{{ $notification->data['link'] }}')"
                                       class="text-gray-700 text-hover-primary {{ $notification->read_at ? '' : 'fw-bold' }}"
                                       style="text-decoration: none; cursor: pointer;">
                                        {{ $notification->data['message'] ?? '' }}
                                    </a>
                                @else
                                    <span class="{{ $notification->read_at ? '' : 'fw-bold' }}">
                                        {{ $notification->data['message'] ?? '' }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                {{ $notification->created_at->format('d-m-Y H:i') }}
                            </td>
                            <td class="text-end">
                                @if(!$notification->read_at)
                                    <button type="button" 
                                            wire:click="markAsRead('{{ $notification->id }}')" 
                                            class="btn btn-sm btn-light-primary me-2"
                                            title="Mark as read">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                @endif
                                @if(isset($notification->data['link']))
                                    <a href="{{ $notification->data['link'] }}" 
                                       wire:click.prevent="markAsRead('{{ $notification->id }}', '{{ $notification->data['link'] }}')"
                                       class="btn btn-sm btn-light-info me-2"
                                       title="View Order">
                                        <i class="fa-solid fa-eye"></i> View
                                    </a>
                                @endif
                                <button type="button" 
                                        wire:click="delete('{{ $notification->id }}')" 
                                        wire:confirm="Are you sure you want to delete this notification?"
                                        class="btn btn-sm btn-light-danger"
                                        title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-10">
                                <div class="text-gray-500">No notifications found</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-5">
            <div>
                Showing {{ $items->firstItem() ?? 0 }} to {{ $items->lastItem() ?? 0 }} of {{ $items->total() }} notifications
            </div>
            <div>
                {{ $items->links() }}
            </div>
        </div>
    </div>
</div>

