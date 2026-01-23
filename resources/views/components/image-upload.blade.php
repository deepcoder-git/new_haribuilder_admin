@props([
    'name' => 'image',
    'label' => 'Image',
    'required' => false,
    'maxSize' => 2048,
    'existingImage' => null,
    'isViewMode' => false,
    'isEditMode' => false,
    'previewWidth' => '150px',
    'previewHeight' => '150px',
    'accept' => 'image/*',
    'wireModel' => null,
])

@php
    use Illuminate\Support\Facades\Storage;
    $wireModel = $wireModel ?? $name;
    $fieldId = 'image_' . uniqid();
@endphp

<div class="mb-5">
    <label for="{{ $fieldId }}" class="form-label">
        {{ $label }}@if($required) <span class="text-danger">*</span>@endif
    </label>
    
    <div x-data="{ 
        imagePreview: null,
        showPreview: false,
        hasWireValue() {
            try {
                const path = '{{ $wireModel }}'.split('.');
                let value = $wire;
                for (let key of path) {
                    value = value?.[key];
                }
                return !!value;
            } catch(e) {
                return false;
            }
        }
    }">
        @if(!$isViewMode)
        <div>
            <input type="file" 
                   id="{{ $fieldId }}"
                   wire:model="{{ $wireModel }}"
                   accept="{{ $accept }}"
                   class="form-control form-control-solid @error($name) is-invalid @enderror"
                   @change="const file = $event.target.files[0]; 
                            if(file) { 
                                const reader = new FileReader(); 
                                reader.onload = (e) => { 
                                    imagePreview = e.target.result; 
                                    showPreview = true; 
                                }; 
                                reader.readAsDataURL(file); 
                            } else { 
                                imagePreview = null; 
                                showPreview = false; 
                            }"/>
            <p class="text-muted mt-1 mb-2 small">Max size: {{ $maxSize }} KB</p>
            
            <div class="mt-2" x-show="!imagePreview && hasWireValue()" x-transition style="display: none;">
                <div class="border rounded p-2" style="background-color: #f8f9fa; display: inline-block;">
                    <div class="d-flex align-items-center gap-2" style="min-width: {{ $previewWidth }}; min-height: {{ $previewHeight }};">
                        <i class="fa-solid fa-file-image text-primary" style="font-size: 1.25rem;"></i>
                        <div class="flex-grow-1">
                            <small class="text-gray-800 fw-semibold d-block">File selected</small>
                            <small class="text-muted">Waiting for preview...</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-2" x-show="imagePreview" x-transition>
                <div class="border rounded p-2" style="background-color: #f8f9fa; display: inline-block;">
                    <img :src="imagePreview" 
                         alt="Image preview" 
                         style="max-width: {{ $previewWidth }}; max-height: {{ $previewHeight }}; display: block;" 
                         class="img-thumbnail">
                </div>
            </div>
        </div>
        @endif
        
        @if($existingImage && ($isViewMode || $isEditMode))
        <div class="mt-2">
            <div class="border rounded p-2" style="background-color: #f8f9fa; display: inline-block;">
                @php
                    $imageUrl = is_string($existingImage) 
                        ? Storage::url($existingImage) 
                        : (is_object($existingImage) && isset($existingImage->image) 
                            ? Storage::url($existingImage->image) 
                            : Storage::url($existingImage));
                @endphp
                <img src="{{ $imageUrl }}" 
                     alt="{{ $label }}" 
                     style="max-width: {{ $previewWidth }}; max-height: {{ $previewHeight }}; display: block;" 
                     class="img-thumbnail">
            </div>
            <small class="text-muted d-block mt-1">Current {{ strtolower($label) }}</small>
        </div>
        @endif
        
        @error($name) 
            <div class="text-danger mt-1">{{ $message }}</div> 
        @enderror
    </div>
</div>
