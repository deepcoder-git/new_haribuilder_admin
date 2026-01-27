<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Order;

use App\Models\Order;
use App\Models\Moderator;
use App\Models\Product;
use App\Services\StockService;
use App\Utility\Enums\OrderStatusEnum;
use App\Utility\Enums\PriorityEnum;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StoreEnum;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderDatatable extends Component
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'status_filter')]
    public ?string $statusFilter = null;

    #[Url(as: 'priority_filter')]
    public ?string $priorityFilter = null;

    #[Url(as: 'site_filter')]
    public ?string $siteFilter = null;

    public ?string $tempStatusFilter = null;
    public ?string $tempPriorityFilter = null;
    public ?string $tempSiteFilter = null;

    #[Url(as: 'per_page')]
    public int $perPage = 10;

    #[Url(as: 'sort')]
    public string $sortField = 'id';

    #[Url(as: 'direction')]
    public string $sortDirection = 'desc';

    public ?int $orderToDelete = null;
    public ?string $orderNameToDelete = null;
    public bool $showDeleteModal = false;

    public ?int $orderToReject = null;
    public ?string $orderNameToReject = null;
    public string $rejectionNote = '';
    public bool $showRejectModal = false;
    public array $rejectProductTypes = []; // Track which product types to reject
    public array $availableProductTypes = []; // Available product types in the order
    
    // Rejection details modal
    public bool $showRejectionDetailsModal = false;
    public ?int $rejectionDetailsOrderId = null;
    public ?string $rejectionDetailsOrderName = null;
    public ?string $rejectionDetailsNote = null;
    public array $rejectionDetailsProductStatuses = [];
    
    protected ?StockService $stockService = null;
    
    public function boot(): void
    {
        $this->stockService = app(StockService::class);
    }

    public function mount(): void
    {
        $perPage = request()->get('per_page', 10);
        $this->perPage = is_numeric($perPage) ? (int) $perPage : 10;
        
        if (!in_array($this->perPage, [10, 25, 50, 100])) {
            $this->perPage = 10;
        }

        // Initialize temp filters from actual filters (sync with URL parameters)
        $this->syncTempFilters();
    }

    public function syncTempFilters(): void
    {
        $this->tempStatusFilter = $this->statusFilter ?: 'all';
        $this->tempPriorityFilter = $this->priorityFilter ?: 'all';
        $this->tempSiteFilter = $this->siteFilter ?: 'all';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->statusFilter = $this->tempStatusFilter === 'all' ? null : $this->tempStatusFilter;
        $this->priorityFilter = $this->tempPriorityFilter === 'all' ? null : $this->tempPriorityFilter;
        $this->siteFilter = $this->tempSiteFilter === 'all' ? null : $this->tempSiteFilter;
        $this->resetPage();
        
        // Dispatch event to close dropdown
        $this->dispatch('close-filter-dropdown');
    }

    public function resetFilters(): void
    {
        $this->statusFilter = null;
        $this->priorityFilter = null;
        $this->siteFilter = null;
        $this->tempStatusFilter = 'all';
        $this->tempPriorityFilter = 'all';
        $this->tempSiteFilter = 'all';
        $this->search = '';
        $this->resetPage();
        
        // Dispatch event to reset custom selects (but keep dropdown open)
        $this->dispatch('reset-filter-selects');
    }

    public function hasActiveFilters(): bool
    {
        return ($this->statusFilter && $this->statusFilter !== 'all')
            || ($this->priorityFilter && $this->priorityFilter !== 'all')
            || ($this->siteFilter && $this->siteFilter !== 'all');
    }


    public function updatedPerPage($value): void
    {
        $perPageValue = is_numeric($value) ? (int) $value : 20;
        
        if (in_array($perPageValue, [10, 20, 25, 50, 100])) {
            $this->perPage = $perPageValue;
        } else {
            $this->perPage = 20;
        }
        
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function openCreateForm(): void
    {
        $user = auth('moderator')->user();
        $userRole = ($user && $user instanceof Moderator) ? $user->getRole() : null;
        
        // Only SuperAdmin can create orders
        if ($userRole !== RoleEnum::SuperAdmin) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Only Super Admin can create orders.']);
            return;
        }
        
        $this->redirect(route('admin.orders.create'));
    }

    public function openEditForm(int|string $id): void
    {
        $model = Order::find($id);
        $user = auth('moderator')->user();
        $userRole = ($user && $user instanceof Moderator) ? $user->getRole() : null;
        
        // Only SuperAdmin can edit orders
        if ($userRole !== RoleEnum::SuperAdmin) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Only Super Admin can manage orders.']);
            $this->redirect(route('admin.orders.view', $id));
            return;
        }
        
        $this->redirect(route('admin.orders.edit', $id));
    }

    public function openViewModal(int|string $id): void
    {
        $this->redirect(route('admin.orders.view', $id));
    }

    public function openLpoView(int|string $id): void
    {
        $this->redirect(route('admin.lpo.view', $id));
    }

    public function confirmDelete(int|string $id): void
    {
        $order = Order::findOrFail($id);
        $this->orderToDelete = (int) $id;
        $this->orderNameToDelete = 'Order #' . $order->id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (!$this->orderToDelete) {
            return;
        }

        try {
            DB::transaction(function () {
                $model = Order::findOrFail($this->orderToDelete);
                
                // Do not allow deleting delivered orders (orders.is_completed was removed)
                $statusValue = $model->status instanceof OrderStatusEnum
                    ? $model->status->value
                    : (string)($model->status ?? OrderStatusEnum::Pending->value);
                if ($statusValue === OrderStatusEnum::Delivery->value) {
                    throw new \Exception('Cannot delete a delivered order. Please cancel it instead.');
                }

                $model->load('products.productImages');

                $model->delete();

                $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Order deleted successfully!']);
                $this->resetPage();
                $this->closeDeleteModal();
            });
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
            $this->closeDeleteModal();
        }
    }

    public function closeDeleteModal(): void
    {
        $this->orderToDelete = null;
        $this->orderNameToDelete = null;
        $this->showDeleteModal = false;
    }

    public function openRejectModal(int|string $id): void
    {
        $user = auth('moderator')->user();
        $userRole = ($user && $user instanceof Moderator) ? $user->getRole() : null;
        
        // Only SuperAdmin can reject orders
        if ($userRole !== RoleEnum::SuperAdmin) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Only Super Admin can reject orders.']);
            return;
        }
        
        $order = Order::findOrFail($id);
        $this->orderToReject = (int) $id;
        $prefix = $order->is_lpo ? 'LPO' : 'ORD';
        $this->orderNameToReject = $prefix . $order->id;
        $this->rejectionNote = '';
        $this->showRejectModal = true;
    }

    public function rejectOrder(): void
    {
        if (!$this->orderToReject) {
            return;
        }

        if (empty(trim($this->rejectionNote))) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Rejection note is required.']);
            return;
        }

        try {
            DB::transaction(function () {
                $user = auth('moderator')->user();
                $userRole = ($user && $user instanceof Moderator) ? $user->getRole() : null;
                
                // Only SuperAdmin can reject orders
                if ($userRole !== RoleEnum::SuperAdmin) {
                    throw new \Exception('Only Super Admin can reject orders.');
                }
                
                $model = Order::findOrFail($this->orderToReject);
                
                // deliveries table removed; use order status to protect delivered orders
                $statusValue = $model->status instanceof OrderStatusEnum
                    ? $model->status->value
                    : (string)($model->status ?? OrderStatusEnum::Pending->value);
                if ($statusValue === OrderStatusEnum::Delivery->value && $userRole !== RoleEnum::SuperAdmin) {
                    throw new \Exception('Cannot reject a delivered order.');
                }

                $model->load('products.productImages');

                $model->update([
                    'status' => OrderStatusEnum::Rejected->value,
                    'rejected_note' => trim($this->rejectionNote),
                ]);

                $storeManagers = Moderator::where('role', RoleEnum::StoreManager->value)
                    ->where('status', 'active')
                    ->get();
                foreach ($storeManagers as $storeManager) {
                    $storeManager->notify(new \App\Notifications\OrderRejectedNotification($model));
                }

                if ($model->transport_manager_id) {
                    $transportManager = Moderator::find($model->transport_manager_id);
                    if ($transportManager) {
                        $transportManager->notify(new \App\Notifications\OrderRejectedNotification($model));
                    }
                }

                $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Order rejected successfully!']);
                $this->resetPage();
                $this->closeRejectModal();
            });
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
            $this->closeRejectModal();
        }
    }

    public function closeRejectModal(): void
    {
        $this->orderToReject = null;
        $this->orderNameToReject = null;
        $this->rejectionNote = '';
        $this->showRejectModal = false;
    }

    public function approveOrder(int|string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $user = auth('moderator')->user();
                if (!$user || !($user instanceof Moderator)) {
                    throw new \Exception('Unauthorized action.');
                }
                
                $userRole = $user->getRole();
                if ($userRole !== RoleEnum::StoreManager && $userRole !== RoleEnum::SuperAdmin) {
                    throw new \Exception('Only Store Managers and Super Admins can approve orders.');
                }

                $model = Order::findOrFail($id);
                $currentStatus = $model->status instanceof OrderStatusEnum
                    ? $model->status
                    : (OrderStatusEnum::tryFrom((string)$model->status) ?? OrderStatusEnum::Pending);

                if ($currentStatus === OrderStatusEnum::Approved || $currentStatus === OrderStatusEnum::InTransit) {
                    throw new \Exception('Order is already approved.');
                }

                // orders.delivery_status/approved_by/approved_at were removed; use status only
                $newStatus = !empty($model->transport_manager_id)
                    ? OrderStatusEnum::InTransit->value
                    : OrderStatusEnum::Approved->value;

                $model->update(['status' => $newStatus]);

                if ($model->siteManager) {
                    $model->siteManager->notify(new \App\Notifications\OrderApprovedNotification($model));
                }

                $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Order approved successfully!']);
                $this->resetPage();
            });
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function updateStatus(int|string $id, string $status): void
    {
        try {
            DB::transaction(function () use ($id, $status) {
                $user = auth('moderator')->user();
                if (!$user || !($user instanceof Moderator)) {
                    throw new \Exception('Unauthorized action.');
                }

                $userRole = $user->getRole();
                $model = Order::findOrFail($id);

                $statusEnum = OrderStatusEnum::tryFrom($status);
                if (!$statusEnum) {
                    throw new \Exception('Invalid status provided.');
                }

                $updateData = ['status' => $statusEnum->value];

                // If approving, only StoreManager/SuperAdmin can do it.
                if ($statusEnum === OrderStatusEnum::Approved) {
                    if ($userRole !== RoleEnum::StoreManager && $userRole !== RoleEnum::SuperAdmin) {
                        throw new \Exception('Only Store Managers and Super Admins can approve orders.');
                    }
                    $currentStatus = $model->status instanceof OrderStatusEnum
                        ? $model->status
                        : (OrderStatusEnum::tryFrom((string)$model->status) ?? OrderStatusEnum::Pending);
                    if ($currentStatus === OrderStatusEnum::Approved || $currentStatus === OrderStatusEnum::InTransit) {
                        throw new \Exception('Order is already approved.');
                    }

                    // If transport manager already assigned, move directly to in_transit.
                    $updateData['status'] = !empty($model->transport_manager_id)
                        ? OrderStatusEnum::InTransit->value
                        : OrderStatusEnum::Approved->value;

                    $model->update($updateData);


                    if ($model->siteManager) {
                        $model->siteManager->notify(new \App\Notifications\OrderApprovedNotification($model));
                    }

                    $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Order approved successfully!']);
                    $this->resetPage();
                    return;
                }

                $model->update($updateData);

                $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Order status updated successfully!']);
                $this->resetPage();
            });
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // NOTE: orders.delivery_status and related approval/completion columns were removed from the schema.
    // Delivery state is represented by the main `status` column (OrderStatusEnum).

    public function canCancel($item): bool
    {
        $user = auth('moderator')->user();
        $userRole = ($user && $user instanceof Moderator) ? $user->getRole() : null;
        
        // Only SuperAdmin can cancel orders
        if ($userRole !== RoleEnum::SuperAdmin) {
            return false;
        }
        
        $statusValue = $item->status instanceof OrderStatusEnum
            ? $item->status->value
            : (string)($item->status ?? OrderStatusEnum::Pending->value);
        return $statusValue !== OrderStatusEnum::Delivery->value;
    }

    public function canApprove($item): bool
    {
        $user = auth('moderator')->user();
        if (!$user || !($user instanceof Moderator)) {
            return false;
        }
        $userRole = $user->getRole();
        $statusValue = $item->status instanceof OrderStatusEnum
            ? $item->status->value
            : (string)($item->status ?? OrderStatusEnum::Pending->value);

        // SuperAdmin + StoreManager can approve pending orders only.
        if ($userRole === RoleEnum::SuperAdmin || $userRole === RoleEnum::StoreManager) {
            return $statusValue === OrderStatusEnum::Pending->value;
        }
        return false;
    }
    
    public function canManage($item): bool
    {
        $user = auth('moderator')->user();
        if (!$user || !($user instanceof Moderator)) {
            return false;
        }
        $userRole = $user->getRole();
        // Only SuperAdmin can manage orders (edit, delete, cancel)
        return $userRole === RoleEnum::SuperAdmin;
    }

    /**
     * Check if current user can delete orders.
     * Business rule from user: allow all moderator users to delete orders,
     * while still preventing deletion of completed orders in delete().
     */
    public function canDelete($item): bool
    {
        $user = auth('moderator')->user();
        if (!$user || !($user instanceof Moderator)) {
            return false;
        }

        // Additional protection: hide delete for delivered orders (orders.is_completed was removed)
        $statusValue = $item->status instanceof OrderStatusEnum
            ? $item->status->value
            : (string)($item->status ?? OrderStatusEnum::Pending->value);
        return $statusValue !== OrderStatusEnum::Delivery->value;
    }

    public function isSuperAdmin(): bool
    {
        $user = auth('moderator')->user();
        if (!$user || !($user instanceof Moderator)) {
            return false;
        }
        return $user->getRole() === RoleEnum::SuperAdmin;
    }

    public function renderApproveButton($item): string
    {
        if (!$this->canApprove($item)) {
            return '';
        }
        return '<a href="#" 
                        wire:click.prevent="approveOrder(' . $item->id . ')" 
                        wire:confirm="Are you sure you want to approve this order?"
                        class="btn btn-sm btn-icon btn-light-success"
                        title="Approve Order"
                        style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                        wire:loading.attr="disabled">
                    <i class="fa-solid fa-check" style="font-size: 0.875rem;"></i>
                </a>';
    }

    public function renderActionDropdown($item): string
    {
        $orderId = $item->id;
        $statusValue = $item->status instanceof OrderStatusEnum
            ? $item->status->value
            : (string)($item->status ?? OrderStatusEnum::Pending->value);
        $isDelivered = $statusValue === OrderStatusEnum::Delivery->value;
        $canCancel = $this->canCancel($item);
        $canManage = $this->canManage($item);
        $canDelete = $this->canDelete($item);
        $canEdit = $canManage && !$isDelivered;

        // Check order status - determine if Edit should be disabled
        $status = $item->status;
        $isRejected = false;
        $isPending = false;
        
        // Check status field (cast to enum) - this is the main status field
        if ($status instanceof OrderStatusEnum) {
            $isRejected = ($status === OrderStatusEnum::Rejected);
            $isPending = ($status === OrderStatusEnum::Pending);
        } else {
            $statusValue = is_string($status) ? $status : ($status ?? 'pending');
            $isRejected = ($statusValue === OrderStatusEnum::Rejected->value || strtolower($statusValue) === 'rejected');
            $isPending = ($statusValue === OrderStatusEnum::Pending->value || strtolower($statusValue) === 'pending');
        }

        $actionsHtml = '<div class="d-flex justify-content-center gap-1">';

        // View action (always available)
        $actionsHtml .= '<a href="#" 
                        wire:click.prevent="openViewModal(' . $orderId . ')"
                        class="btn btn-sm btn-icon btn-light-primary"
                        title="View Details"
                        style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                        wire:loading.attr="disabled">
                    <i class="fa-solid fa-eye" style="font-size: 0.875rem;"></i>
                </a>';

        // Edit action - Only SuperAdmin can edit; avoid editing delivered orders.
        if ($canEdit) {
            $isSuperAdmin = $this->isSuperAdmin();
            // SuperAdmin can edit all orders (including completed, rejected, etc.)
            // Non-SuperAdmin can only edit pending orders
            if ($isSuperAdmin || $isPending) {
                // Active state - enabled for SuperAdmin (all statuses) or pending orders
                $actionsHtml .= '<a href="#" 
                        wire:click.prevent="openEditForm(' . $orderId . ')"
                        class="btn btn-sm btn-icon btn-light-info"
                        title="Edit Order"
                        style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                        wire:loading.attr="disabled">
                    <i class="fa-solid fa-pen" style="font-size: 0.875rem;"></i>
                </a>';
            } else {
                // Disabled state for non-pending orders (non-SuperAdmin only)
                $disabledTitle = $isRejected ? 'Cannot edit rejected order' : 'Can only edit pending orders';
                $actionsHtml .= '<span 
                        class="btn btn-sm btn-icon btn-light-secondary"
                        title="' . $disabledTitle . '"
                        style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center; opacity: 0.4; cursor: not-allowed; background-color: #e5e7eb !important; border-color: #d1d5db !important;">
                    <i class="fa-solid fa-pen" style="font-size: 0.875rem; color: #9ca3af !important;"></i>
                </span>';
            }
        }

        // Reject order action (only for SuperAdmin)
        if ($canCancel) {
            if ($isRejected) {
                // Disabled state for rejected orders
                $actionsHtml .= '<span 
                        class="btn btn-sm btn-icon btn-light-secondary"
                        title="Cannot reject already rejected order"
                        style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center; opacity: 0.4; cursor: not-allowed; background-color: #e5e7eb !important; border-color: #d1d5db !important;">
                    <i class="fa-solid fa-ban" style="font-size: 0.875rem; color: #9ca3af !important;"></i>
                </span>';
            } else {
                // Active state for non-rejected orders
                $actionsHtml .= '<a href="#" 
                        wire:click.prevent="openRejectModal(' . $orderId . ')"
                        class="btn btn-sm btn-icon btn-light-danger"
                        title="Reject Order"
                        style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                        wire:loading.attr="disabled">
                    <i class="fa-solid fa-ban" style="font-size: 0.875rem;"></i>
                </a>';
            }
        }

        // Delete action (allowed for all moderator users, except for completed orders)
        if ($canDelete) {
            $actionsHtml .= '<a href="#" 
                        wire:click.prevent="confirmDelete(' . $orderId . ')"
                        class="btn btn-sm btn-icon btn-light-danger"
                        title="Delete Order"
                        style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                        wire:loading.attr="disabled">
                    <i class="fa-solid fa-trash" style="font-size: 0.875rem;"></i>
                </a>';
        }

        $actionsHtml .= '</div>';

        return $actionsHtml;
    }

    // NOTE: legacy renderDeliveryStatus removed with delivery_status column removal.

    public function renderOrderStatus($item): string
    {
        $status = $item->status;
        
        if (!$status instanceof OrderStatusEnum) {
            $statusValue = $status ?? 'pending';
            $status = OrderStatusEnum::tryFrom($statusValue) ?? OrderStatusEnum::Pending;
        }
        
        $badgeClass = match($status) {
            OrderStatusEnum::Pending => 'badge-light-warning',
            OrderStatusEnum::Approved => 'badge-light-success',
            OrderStatusEnum::InTransit => 'badge-light-primary',
            OrderStatusEnum::Delivery => 'badge-light-info',
            OrderStatusEnum::Cancelled => 'badge-light-danger',
            OrderStatusEnum::Rejected => 'badge-light-danger',
            OrderStatusEnum::OutOfDelivery => 'badge-light-primary',
            default => 'badge-light-secondary',
        };
        
        $statusLabel = $status->getName();
        $orderId = $item->id;
        
        // Make rejected status clickable to show rejection details
        if ($status === OrderStatusEnum::Rejected) {
            return "<a href='#' 
                        wire:click.prevent='openRejectionDetailsModal({$orderId})' 
                        class='badge {$badgeClass}' 
                        style='cursor: pointer; text-decoration: none;'
                        title='Click to view rejection details'>
                        {$statusLabel}
                    </a>";
        }
        
        return "<span class='badge {$badgeClass}'>{$statusLabel}</span>";
    }
    
    public function openRejectionDetailsModal(int|string $id): void
    {
        $order = Order::findOrFail($id);
        $this->rejectionDetailsOrderId = (int) $id;
        $prefix = $order->is_lpo ? 'LPO' : 'ORD';
        $this->rejectionDetailsOrderName = $prefix . $order->id;
        $this->rejectionDetailsNote = $order->rejected_note ?? null;
        
        // Get product statuses
        $this->rejectionDetailsProductStatuses = [];
        $productStatus = $order->product_status ?? [];
        
        if (!empty($productStatus)) {
            $statusLabels = [
                'hardware' => 'Hardware',
                'workshop' => 'Workshop',
                'lpo' => 'LPO',
                'custom' => 'Custom',
            ];
            
            foreach ($productStatus as $type => $status) {
                if ($status === 'rejected' && isset($statusLabels[$type])) {
                    $this->rejectionDetailsProductStatuses[$type] = $statusLabels[$type];
                }
            }
            
            // Handle LPO supplier-wise statuses
            if (isset($productStatus['lpo']) && is_array($productStatus['lpo'])) {
                foreach ($productStatus['lpo'] as $supplierId => $lpoStatus) {
                    if ($lpoStatus === 'rejected') {
                        $supplier = \App\Models\Supplier::find($supplierId);
                        $supplierName = $supplier ? $supplier->name : "Supplier #{$supplierId}";
                        $this->rejectionDetailsProductStatuses['lpo_' . $supplierId] = "LPO ({$supplierName})";
                    }
                }
            }
        }
        
        $this->showRejectionDetailsModal = true;
    }
    
    public function closeRejectionDetailsModal(): void
    {
        $this->rejectionDetailsOrderId = null;
        $this->rejectionDetailsOrderName = null;
        $this->rejectionDetailsNote = null;
        $this->rejectionDetailsProductStatuses = [];
        $this->showRejectionDetailsModal = false;
    }

    public function renderPriority($item): string
    {
        $priority = $item->priority;
        
        if (!$priority) {
            return "<span class='badge badge-light-secondary'>N/A</span>";
        }
        
        $priorityEnum = PriorityEnum::tryFrom($priority);
        
        if (!$priorityEnum) {
            return "<span class='badge badge-light-secondary'>" . htmlspecialchars($priority) . "</span>";
        }
        
        $badgeClass = match($priorityEnum) {
            PriorityEnum::High => 'badge-light-danger',
            PriorityEnum::Medium => 'badge-light-warning',
            PriorityEnum::Low => 'badge-light-info',
        };
        
        $priorityLabel = $priorityEnum->getName();
        
        return "<span class='badge {$badgeClass}'>{$priorityLabel}</span>";
    }

    public function renderExpectedDeliveryDate($item): string
    {
        if (!$item->expected_delivery_date) {
            return "<span class='text-muted'>N/A</span>";
        }
        
        $formattedDate = $item->expected_delivery_date->format('d/m/Y');
        return "<span class='text-gray-700' style='font-size: 0.9375rem;'>{$formattedDate}</span>";
    }

    public function renderProducts($item): string
    {
        try {
            if (!$item->relationLoaded('products')) {
                $item->load('products.productImages');
            }
            
            if ($item->products && $item->products->count() > 0) {
                $productNames = $item->products->map(function($product) {
                    $name = $product->product_name ?? 'N/A';
                    return '<div style="word-wrap: break-word; word-break: break-word; white-space: normal; line-height: 1.4; max-width: 200px; text-align: center; margin: 0 auto;">' . htmlspecialchars($name) . '</div>';
                })->filter()->toArray();
                
                if (!empty($productNames)) {
                    return implode('', $productNames);
                }
                return '<div style="text-align: center;">N/A</div>';
            }
            
            if (!$item->relationLoaded('product') && $item->product_id) {
                $item->load('product.productImages');
            }
            
            if ($item->relationLoaded('product') && $item->product) {
                $name = $item->product->product_name ?? 'N/A';
                return '<div style="word-wrap: break-word; word-break: break-word; white-space: normal; line-height: 1.4; max-width: 200px; text-align: center; margin: 0 auto;">' . htmlspecialchars($name) . '</div>';
            }
        } catch (\Exception $e) {
        }
        return '<div style="text-align: center;">N/A</div>';
    }

    public function renderQuantity($item): string
    {
        if ($item->relationLoaded('products') && $item->products && $item->products->count() > 0) {
            $totalQuantity = $item->products->sum(function($product) {
                return (float)($product->pivot->quantity ?? 0);
            });
            return formatQty($totalQuantity);
        }
        
        if (!$item->relationLoaded('products') && $item->id) {
            $item->load('products.productImages');
            if ($item->products && $item->products->count() > 0) {
                $totalQuantity = $item->products->sum(function($product) {
                    return (float)($product->pivot->quantity ?? 0);
                });
                return formatQty($totalQuantity);
            }
        }
        
        return formatQty($item->quantity ?? 0);
    }


    public function renderOrderType($item): string
    {
        $store = $item->store;
        
        if (!$store) {
            return "<span class='text-muted'>N/A</span>";
        }
        
        // Try to get the StoreEnum from the store value
        $storeEnum = StoreEnum::tryFrom($store);
        
        if ($storeEnum) {
            $storeName = $storeEnum->getName();
            // Use badge styling similar to other columns
            $badgeClass = match($storeEnum) {
                StoreEnum::HardwareStore => 'badge-light-info',
                StoreEnum::WarehouseStore => 'badge-light-primary',
                StoreEnum::LPO => 'badge-light-warning',
                default => 'badge-light-secondary',
            };
            return "<span class='badge {$badgeClass}' style='font-size: 0.875rem;'>{$storeName}</span>";
        }
        
        // Fallback to displaying the raw value if enum conversion fails
        return "<span class='badge badge-light-secondary' style='font-size: 0.875rem;'>" . htmlspecialchars($store) . "</span>";
    }

    public function renderOrderId($item): string
    {
        // All orders use ORD prefix
        $prefix = 'ORD';
        $badgeClass = 'badge-light-dark';
        $orderId = $item->id;

        // Use Livewire navigation - always use order view modal
        return '<a href="#" wire:click.prevent="openViewModal(' . $orderId . ')" class="badge ' . $badgeClass . '" title="' . $prefix . $orderId . '" style="min-width: 70px; display: inline-flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none; cursor: pointer;">' . $prefix . $orderId . '</a>';
    }

    /**
     * Render product status badges for different product types
     */
    public function renderProductStatus($item): string
    {
        $productStatus = $item->product_status ?? [];
        
        if (empty($productStatus)) {
            return "<span class='text-muted'>N/A</span>";
        }
        
        $badges = [];
        $statusBadgeClass = [
            'pending' => 'badge-light-warning',
            'approved' => 'badge-light-success',
            'rejected' => 'badge-light-danger',
            'in_transit' => 'badge-light-primary',
            'outfordelivery' => 'badge-light-primary',
            'delivered' => 'badge-light-info',
        ];
        
        $statusLabels = [
            'hardware' => 'Hardware',
            'workshop' => 'Workshop',
            'lpo' => 'LPO'
        ];
        
        foreach ($productStatus as $type => $status) {
            $badgeClass = $statusBadgeClass[$status] ?? 'badge-light-secondary';
            $typeLabel = $statusLabels[$type] ?? ucfirst($type);
            $statusLabel = ucfirst($status);
            $badges[] = "<span class='badge {$badgeClass}' title='{$typeLabel}: {$statusLabel}' style='margin-right: 4px;'>{$typeLabel}: {$statusLabel}</span>";
        }
        
        return implode(' ', $badges);
    }

    /**
     * Render supplier-wise LPO statuses (similar to LPODatatable::renderLpoSupplierStatuses)
     */
    public function renderLpoSupplierStatuses($item): string
    {
        $productStatus = $item->product_status ?? [];
        $lpoStatuses = $productStatus['lpo'] ?? [];

        if (!is_array($lpoStatuses) || empty($lpoStatuses)) {
            return "<span class='text-muted'>N/A</span>";
        }

        // Status → badge colour mapping aligned with renderProductStatus
        $statusBadgeClass = [
            'pending'        => 'badge-light-warning',
            'approved'       => 'badge-light-success',
            'rejected'       => 'badge-light-danger',
            'in_transit'     => 'badge-light-primary',
            'outfordelivery' => 'badge-light-primary',
            'delivered'      => 'badge-light-info',
        ];

        // Get suppliers mapped by ID using Order::supplier() helper
        $suppliers = $item->supplier();
        if (method_exists($suppliers, 'keyBy')) {
            $suppliers = $suppliers->keyBy('id');
        }

        $badges = [];

        foreach ($lpoStatuses as $supplierId => $status) {
            $supplierIdInt = (int) $supplierId;
            $supplier = $suppliers[$supplierIdInt] ?? null;

            $name = $supplier ? ($supplier->name ?? ('Supplier #' . $supplierIdInt)) : ('Supplier #' . $supplierIdInt);
            $normalizedStatus = is_string($status) ? strtolower($status) : 'pending';

            // Fallback to neutral badge if status is unknown
            $badgeClass = $statusBadgeClass[$normalizedStatus] ?? 'badge-light-secondary';

            $statusLabel = ucfirst($normalizedStatus);
            $title = htmlspecialchars($name . ' - ' . $statusLabel, ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

            $badges[] = "<span class='badge {$badgeClass}' title='{$title}' style='margin: 2px 2px;'>{$label}: {$statusLabel}</span>";
        }

        return !empty($badges)
            ? implode(' ', $badges)
            : "<span class='text-muted'>N/A</span>";
    }


    public function getTransportManagersProperty()
    {
        return Moderator::where('role', RoleEnum::TransportManager->value)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function getSitesProperty()
    {
        return \App\Models\Site::where('status', true)
            ->orderBy('name')
            ->get();
    }

    public function assignTransportManager(int|string $orderId, int|string|null $transportManagerId): void
    {
        // try {
            DB::transaction(function () use ($orderId, $transportManagerId) {
                $user = auth('moderator')->user();
                if (!$user || !($user instanceof Moderator)) {
                    throw new \Exception('Unauthorized action.');
                }

                $model = Order::findOrFail($orderId);
                
                // Only allow assignment when order status is approved
                $statusValue = $model->status instanceof OrderStatusEnum
                    ? $model->status->value
                    : (string)($model->status ?? OrderStatusEnum::Pending->value);
                if ($statusValue !== OrderStatusEnum::Approved->value) {
                    throw new \Exception('Transport manager can only be assigned when order status is approved.');
                }

                // Validate transport manager if provided
                if ($transportManagerId) {
                    $transportManager = Moderator::findOrFail($transportManagerId);
                    // Use getRole() to properly compare enum instances, or compare enum directly
                    $transportManagerRole = $transportManager->getRole();
                    if ($transportManagerRole !== RoleEnum::TransportManager || $transportManager->status !== 'active') {
                        throw new \Exception('Invalid transport manager selected.');
                    }
                }

                $oldTransportManagerId = $model->transport_manager_id;
                $updateData = [
                    'transport_manager_id' => $transportManagerId ? (int)$transportManagerId : null,
                ];

                // If transport manager is assigned and order is approved, change status to in_transit
                if ($transportManagerId && $statusValue === OrderStatusEnum::Approved->value) {
                    $updateData['status'] = OrderStatusEnum::InTransit->value;
                }

                $model->update($updateData);

                // Send notification when transport manager is assigned for the first time
                if ($transportManagerId && !$oldTransportManagerId) {
                    $transportManager = Moderator::find($transportManagerId);
                    if ($transportManager) {
                        try {
                            $transportManager->notify(new \App\Notifications\TransportManagerAssignedNotification($model));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to send notification to Transport Manager: ' . $transportManager->email . ' - ' . $e->getMessage());
                        }
                    }
                }

                $this->dispatch('show-toast', [
                    'type' => 'success',
                    'message' => $transportManagerId ? 'Transport manager assigned successfully!' : 'Transport manager removed successfully!'
                ]);
                $this->resetPage();
            });
        // } catch (\Exception $e) {
        //     $this->dispatch('show-toast', [
        //         'type' => 'error',
        //         'message' => $e->getMessage()
        //     ]);
        // }
    }

    public function renderTransportDetails($item): string
    {
        $orderId = $item->id;
        $statusValue = $item->status instanceof OrderStatusEnum
            ? $item->status->value
            : (string)($item->status ?? OrderStatusEnum::Pending->value);
        $isApproved = $statusValue === OrderStatusEnum::Approved->value;
        $currentTransportManagerId = $item->transport_manager_id;
        $transportManagerName = $item->transportManager ? $item->transportManager->name : 'Not Assigned';

        // If not approved, just show the transport manager name
        if (!$isApproved) {
            return '<span class="text-gray-700" style="font-size: 0.9375rem;">' . htmlspecialchars($transportManagerName) . '</span>';
        }

        // If approved, show dropdown with transport details table and assign transport manager
        $dropdownHtml = '<div class="dropup d-inline-block">
            <button class="btn btn-sm btn-light-primary dropdown-toggle d-flex align-items-center gap-1" 
                    type="button" 
                    id="transportManagerDropdown' . $orderId . '" 
                    data-bs-toggle="dropdown" 
                    aria-expanded="false"
                    style="border: 1px solid #3b82f6; font-size: 0.875rem; padding: 0.375rem 0.75rem; white-space: nowrap; min-width: 180px; justify-content: space-between;">
                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    ' . htmlspecialchars($transportManagerName) . '
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-start shadow-sm" 
                aria-labelledby="transportManagerDropdown' . $orderId . '" 
                style="min-width: 320px; max-height: 400px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0;">
                <li class="px-3 py-2 text-muted border-bottom bg-light" style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fa-solid fa-truck me-2"></i>Transport Details
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center py-2 px-3 ' . (!$currentTransportManagerId ? 'bg-light-primary' : '') . '" 
                       href="#" 
                       wire:click.prevent="assignTransportManager(' . $orderId . ', null)">
                        <i class="fa-solid ' . (!$currentTransportManagerId ? 'fa-check-circle text-primary' : 'fa-circle text-muted') . ' me-2" style="width: 18px; text-align: center;"></i>
                        <span>Not Assigned</span>
                    </a>
                </li>';

        foreach ($this->transportManagers as $manager) {
            $isSelected = $currentTransportManagerId == $manager->id;
            $dropdownHtml .= '<li>
                    <a class="dropdown-item d-flex align-items-center py-2 px-3 ' . ($isSelected ? 'bg-light-primary' : '') . '" 
                       href="#" 
                       wire:click.prevent="assignTransportManager(' . $orderId . ', ' . $manager->id . ')">
                        <i class="fa-solid ' . ($isSelected ? 'fa-check-circle text-primary' : 'fa-circle text-muted') . ' me-2" style="width: 18px; text-align: center;"></i>
                        <span>' . htmlspecialchars($manager->name) . '</span>
                    </a>
                </li>';
        }

        $dropdownHtml .= '</ul>
        </div>';

        return $dropdownHtml;
    }

    public function getOrdersProperty()
    {
        $query = Order::query();
        $user = auth('moderator')->user();
        
        // Removed is_lpo filter - now showing all orders since we manage mixed products in single orders
        
        if ($user && ($user instanceof Moderator)) {
            $userRole = $user->getRole();
            // SuperAdmin can see all orders without filtering
            if ($userRole === RoleEnum::SuperAdmin) {
                // No filtering for SuperAdmin - show all orders
            } elseif ($userRole === RoleEnum::TransportManager) {
                $query->where('transport_manager_id', $user->id);
            } elseif ($userRole === RoleEnum::StoreManager) {
                // StoreManager can only see pending/approved orders for approval, but cannot manage them
                $query->whereIn('status', [OrderStatusEnum::Pending->value, OrderStatusEnum::Approved->value]);
            }
        }
        
        $query->with(['siteManager', 'transportManager', 'site', 'products.productImages', 'products.category', 'customProducts.images']);

        if ($this->search) {
            // Allow searching by prefixed order IDs like "ORD28" as well as plain numeric IDs.
            $rawSearch = $this->search;
            $idSearch = $rawSearch;

            if (preg_match('/^ord(\d+)$/i', $rawSearch, $matches)) {
                $idSearch = $matches[1];
            }

            $query->where(function ($q) use ($rawSearch, $idSearch) {
                $q->where('id', 'like', "%{$idSearch}%")
                  ->orWhereHas('products', function ($pq) use ($rawSearch) {
                      $pq->where('product_name', 'like', "%{$rawSearch}%");
                  })
                  ->orWhereHas('customProducts', function ($cpq) use ($rawSearch) {
                      $cpq->where('custom_note', 'like', "%{$rawSearch}%");
                  })
                  ->orWhereHas('site', function ($sq) use ($rawSearch) {
                      $sq->where('name', 'like', "%{$rawSearch}%");
                  })
                  ->orWhereHas('siteManager', function ($smq) use ($rawSearch) {
                      $smq->where('name', 'like', "%{$rawSearch}%");
                  })
                  ->orWhereHas('transportManager', function ($tmq) use ($rawSearch) {
                      $tmq->where('name', 'like', "%{$rawSearch}%");
                  });
            });
        }

        // Filter by status if selected
        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $statusEnum = OrderStatusEnum::tryFrom($this->statusFilter);
            if ($statusEnum) {
                $query->where('status', $statusEnum->value);
            }
        }

        // Filter by priority if selected
        if ($this->priorityFilter && $this->priorityFilter !== 'all') {
            $priorityEnum = PriorityEnum::tryFrom($this->priorityFilter);
            if ($priorityEnum) {
                $query->where('priority', $priorityEnum->value);
            }
        }

        // Filter by site if selected
        if ($this->siteFilter && $this->siteFilter !== 'all') {
            $query->where('site_id', $this->siteFilter);
        }

        $sortField = in_array($this->sortField, ['id', 'created_at', 'status']) 
            ? $this->sortField 
            : 'id';

        return $query->orderBy($sortField, $this->sortDirection)
                     ->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('admin::Order.views.order-datatable', [
            'orders' => $this->orders,
            'transportManagers' => $this->transportManagers,
            'sites' => $this->sites,
        ])->layout('panel::layout.app', [
            'title' => 'Order Management',
            'breadcrumb' => [['Order', route('admin.orders.index')]],
        ]);
    }
}

