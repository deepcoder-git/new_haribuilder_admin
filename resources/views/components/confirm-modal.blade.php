@props(['id' => 'confirmModal', 'title' => 'Confirm Action', 'message' => 'Are you sure you want to proceed?', 'confirmText' => 'Confirm', 'cancelText' => 'Cancel', 'type' => 'danger'])

<div>
    <div class="modal fade" id="{{ $id }}" tabindex="-1" role="dialog" aria-labelledby="{{ $id }}Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0" style="box-shadow: none !important;">
                <div class="modal-header border-0 pb-0" style="background: #ef4444;">
                    <div class="d-flex align-items-center w-100">
                        <div class="symbol symbol-50px me-3">
                            <div class="symbol-label bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fa-solid fa-exclamation-triangle fs-2x text-white"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="modal-title text-white fw-bold fs-4" id="{{ $id }}Label">
                                {{ $title }}
                            </h5>
                        </div>
                        <button type="button" class="btn btn-icon btn-sm btn-active-color-white" data-bs-dismiss="modal" aria-label="Close">
                            <i class="fa-solid fa-xmark fs-2 text-white"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-body pt-6 pb-4">
                    <div class="d-flex align-items-start">
                        <div class="symbol symbol-40px me-4 flex-shrink-0">
                            <div class="symbol-label bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fa-solid fa-triangle-exclamation fs-2x text-danger"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <p class="text-gray-800 fs-5 fw-semibold mb-2">{{ $message }}</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" 
                            class="btn btn-light btn-active-light-primary fw-semibold px-6" 
                            data-bs-dismiss="modal">
                        <i class="fa-solid fa-times me-2"></i>
                        {{ $cancelText }}
                    </button>
                    <button type="button" 
                            class="btn btn-danger fw-semibold px-6" 
                            id="{{ $id }}ConfirmBtn"
                            style="background: #ef4444; border: none; box-shadow: none !important;">
                        <i class="fa-solid fa-trash-can me-2"></i>
                        {{ $confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
    #{{ $id }} .modal-content {
        border-radius: 0.75rem;
        overflow: hidden;
    }

    #{{ $id }} .modal-header {
        padding: 1.5rem;
        border-radius: 0.75rem 0.75rem 0 0;
    }

    #{{ $id }} .modal-body {
        padding: 1.5rem;
    }

    #{{ $id }} .modal-footer {
        padding: 1rem 1.5rem 1.5rem;
    }

    #{{ $id }}ConfirmBtn:hover {
        background: #ef4444 !important;
        box-shadow: none !important;
    }

    #{{ $id }}ConfirmBtn:active {
        background: #ef4444 !important;
    }
    </style>
</div>

