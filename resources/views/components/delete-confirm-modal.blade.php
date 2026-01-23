@props([
    'itemNameProperty' => 'itemNameToDelete',
    'showModalProperty' => 'showDeleteModal',
    'deleteMethod' => 'delete',
    'closeMethod' => 'closeDeleteModal',
    'title' => 'Delete Item'
])

<div x-data="{ 
    showModal: @entangle($showModalProperty),
    itemName: @entangle($itemNameProperty)
}" 
     x-show="showModal"
     x-cloak
     style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
    <div class="modal fade" 
         :class="{ 'show d-block': showModal }"
         tabindex="-1" 
         role="dialog"
         aria-labelledby="deleteConfirmModalLabel"
         aria-modal="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="box-shadow: none !important;">
                <div class="modal-header border-0 pb-0" style="background: #ef4444;">
                    <div class="d-flex align-items-center justify-content-between w-100">
                        <h5 class="modal-title text-white fw-bold fs-4 mb-0" id="deleteConfirmModalLabel">
                            {{ $title }}
                        </h5>
                        <button type="button" 
                                class="btn btn-icon btn-sm btn-active-color-white" 
                                @click="$wire.{{ $closeMethod }}()"
                                aria-label="Close">
                            <i class="fa-solid fa-xmark fs-2 text-white"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-body pt-6 pb-4">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-40px me-4 flex-shrink-0">
                            <div class="symbol-label bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fa-solid fa-triangle-exclamation fs-2x text-danger"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <p class="text-gray-800 fs-5 fw-semibold mb-0">
                                Are you sure you want to delete "<span style="color: #ef4444; font-weight: 600;" x-text="itemName || 'this item'"></span>"?
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 d-flex justify-content-end gap-2">
                    <button type="button" 
                            class="btn btn-light btn-active-light-primary fw-semibold px-6" 
                            @click="$wire.{{ $closeMethod }}()">
                        <i class="fa-solid fa-times me-2"></i>
                        Cancel
                    </button>
                    <button type="button" 
                            class="btn btn-danger fw-semibold px-6" 
                            wire:click="{{ $deleteMethod }}"
                            style="background: #ef4444; border: none; box-shadow: none !important;">
                        <i class="fa-solid fa-trash-can me-2"></i>
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Backdrop -->
    <div class="modal-backdrop fade" 
         :class="{ 'show': showModal }"
         @click="$wire.{{ $closeMethod }}()"
         x-show="showModal"
         x-cloak
         style="display: none;"></div>
</div>

<style>
[x-cloak] {
    display: none !important;
}

.modal-content {
    border-radius: 0.75rem;
    overflow: hidden;
}

.modal-header {
    padding: 1.5rem;
    border-radius: 0.75rem 0.75rem 0 0;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem 1.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.btn-danger:hover {
    background: #ef4444 !important;
    box-shadow: none !important;
}

.btn-danger:active {
    background: #ef4444 !important;
}
</style>

