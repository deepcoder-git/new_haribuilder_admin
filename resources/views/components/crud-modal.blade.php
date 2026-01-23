@props(['title', 'size' => 'lg'])

<div x-data="{ show: @entangle('showModal') }" 
     x-show="show"
     x-cloak
     style="display: none;">
    
    <!--begin::Modal - Create/Edit-->
    <div class="modal fade" 
         :class="{ 'show d-block': show }"
         tabindex="-1" 
         role="dialog"
         aria-modal="true">
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <div class="modal-content">
                <div class="modal-header" style="background: #ffffff; border-bottom: 2px solid #1e3a8a; display: flex; align-items: center; justify-content: space-between; padding: 1.5rem;">
                    <h2 class="fw-bold d-flex align-items-center m-0" id="modal-title" style="color: #1e3a8a; font-size: 1.5rem; line-height: 1.5;">
                        <i class="fa-solid fa-file-circle-plus fs-2x me-3" style="color: #1e3a8a;" x-show="!$wire.isEditMode && !$wire.isViewMode"></i>
                        <i class="fa-solid fa-pen-to-square fs-2x me-3" style="color: #1e3a8a;" x-show="$wire.isEditMode && !$wire.isViewMode"></i>
                        <i class="fa-solid fa-eye fs-2x me-3" style="color: #1e3a8a;" x-show="$wire.isViewMode"></i>
                        <span x-text="$wire.isViewMode ? 'View {{ $title }}' : ($wire.isEditMode ? 'Edit {{ $title }}' : 'Create New {{ $title }}')">{{ $title }}</span>
                    </h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" 
                         @click="$wire.closeModal()"
                         data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>

                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    {{ $slot }}
                </div>

                <div class="modal-footer">
                    <button type="button" 
                            @click="$wire.closeModal()"
                            class="btn btn-light" 
                            data-bs-dismiss="modal">
                        <span x-text="$wire.isViewMode ? 'Close' : 'Cancel'">Cancel</span>
                    </button>
                    <button type="button"
                            wire:click="save"
                            class="btn d-flex align-items-center text-white fw-semibold"
                            style="background: #1e3a8a; border: none; box-shadow: none !important;"
                            x-show="!$wire.isViewMode"
                            x-cloak
                            x-bind:disabled="$wire.isViewMode">
                        <i class="fa-solid fa-floppy-disk me-2" x-show="!$wire.isEditMode"></i>
                        <i class="fa-solid fa-check-double me-2" x-show="$wire.isEditMode"></i>
                        <span x-text="$wire.isEditMode ? 'Update' : 'Create'">Create</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!--end::Modal-->

    <!--begin::Modal Backdrop-->
    <div class="modal-backdrop fade" 
         :class="{ 'show': show }"
         @click="$wire.closeModal()"
         x-show="show"
         x-cloak
         style="display: none;"></div>
    <!--end::Modal Backdrop-->
</div>
