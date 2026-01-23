@props([
    'name' => 'documents',
    'label' => 'Documents',
    'isViewMode' => false,
    'isEditMode' => false,
    'documents' => null,
    'savedDocuments' => null,
    'removedDocuments' => [],
    'accept' => '.pdf,.doc,.docx,.csv,.xls,.xlsx,.jpg,.jpeg,.png',
    'maxSize' => '12 MB',
    'multiple' => false,
    'thumbnailSize' => '120px',
    'showLabel' => true,
    'errorKey' => null,
    'useDragDrop' => false
])

@php
    $errorKey = $errorKey ?? $name;
    $hasDocuments = false;
    $newDocuments = [];
    $existingDocuments = [];
    $removedDocs = $removedDocuments ?? [];
    
    if ($documents) {
        if (is_array($documents)) {
            $newDocuments = $documents;
        } elseif (is_object($documents) && method_exists($documents, 'getClientOriginalName')) {
            $newDocuments = [$documents];
        }
        $hasDocuments = !empty($newDocuments);
    }
    
    if ($savedDocuments) {
        if (is_array($savedDocuments)) {
            $existingDocuments = array_filter($savedDocuments, function($doc) use ($removedDocs) {
                return !in_array($doc, $removedDocs);
            });
        } elseif (is_string($savedDocuments)) {
            $existingDocuments = [$savedDocuments];
        }
        $hasDocuments = $hasDocuments || !empty($existingDocuments);
    }
@endphp

<div class="mb-4">
    @if($showLabel)
        <label for="{{ $name }}" class="form-label">
            {{ $label }}
        </label>
    @endif

    @if($isViewMode)
        @if($hasDocuments || !empty($existingDocuments))
            <div class="d-flex flex-wrap gap-2 mt-2">
                @if(!empty($existingDocuments))
                    @foreach($existingDocuments as $document)
                        @php
                            $fileName = basename($document);
                            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                            $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif']);
                            $fileIcon = getFileIcon($fileName);
                        @endphp
                        <div class="position-relative border rounded bg-white shadow-sm overflow-hidden" style="width: {{ $thumbnailSize }}; height: {{ $thumbnailSize }};">
                            @if($isImage)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($document) }}" 
                                     alt="{{ $fileName }}"
                                     style="width: 100%; height: 100%; object-fit: cover;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="d-flex flex-column align-items-center justify-content-center h-100 p-3" style="display: none;">
                                    <i class="fa-solid {{ $fileIcon['class'] }} fs-2 mb-2" style="color: {{ $fileIcon['color'] }};"></i>
                                    <span class="text-center small fw-medium" style="font-size: 0.7rem; word-break: break-word;">{{ strtoupper($fileExt) }}</span>
                                </div>
                            @else
                                <div class="d-flex flex-column align-items-center justify-content-center h-100 p-3">
                                    <i class="fa-solid {{ $fileIcon['class'] }} fs-2 mb-2" style="color: {{ $fileIcon['color'] }};"></i>
                                    <span class="text-center small fw-medium" style="font-size: 0.7rem; word-break: break-word;">{{ strtoupper($fileExt) }}</span>
                                </div>
                            @endif
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($document) }}" target="_blank" class="stretched-link"></a>
                            <div class="position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-75 text-white p-2" style="font-size: 0.75rem;">
                                <div class="text-truncate">{{ $fileName }}</div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        @else
            <div class="form-control form-control-solid" style="background: #f3f4f6; border: 1px solid #e5e7eb; padding: 0.5rem 0.75rem; min-height: 42px; display: flex; align-items: center;">
                N/A
            </div>
        @endif
    @else
        @if($useDragDrop)
            <div class="d-flex gap-3 flex-wrap">
                <div class="flex-grow-1" style="min-width: 300px;">
                    <label for="{{ $name }}" 
                           class="d-block border-2 border-dashed rounded p-4 text-center cursor-pointer document-dropzone-{{ $name }}"
                           style="border-color: #d1d5db; background: #f9fafb; transition: all 0.2s; cursor: pointer;"
                           onmouseover="this.style.borderColor='#3b82f6'; this.style.backgroundColor='#eff6ff';"
                           onmouseout="this.style.borderColor='#d1d5db'; this.style.backgroundColor='#f9fafb';"
                           onclick="document.getElementById('{{ $name }}').click();">
                        <i class="fa-solid fa-cloud-arrow-up fs-1 mb-2" style="color: #6b7280;"></i>
                        <p class="mb-2 fw-medium" style="color: #374151;">Drag and Drop {{ $multiple ? 'files' : 'file' }} or click here to browse</p>
                        <button type="button" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-folder me-1"></i>Select {{ $multiple ? 'Files' : 'File' }}
                        </button>
                    </label>
                    <input type="file" 
                           id="{{ $name }}"
                           wire:model="{{ $name }}"
                           @if($multiple) multiple @endif
                           accept="{{ $accept }}"
                           class="d-none @error($errorKey) is-invalid @enderror"
                           data-document-field="{{ $name }}"/>
                </div>
        @else
            <input type="file" 
                   id="{{ $name }}"
                   wire:model="{{ $name }}"
                   @if($multiple) multiple @endif
                   accept="{{ $accept }}"
                   class="form-control form-control-solid @error($errorKey) is-invalid @enderror"
                   x-bind:disabled="$wire.isViewMode">
        @endif
        @error($errorKey) 
            <div class="text-danger mt-1">{{ $message }}</div> 
        @enderror
        @if($errorKey !== $name)
            @error($errorKey . '.*') 
                <div class="text-danger mt-1">{{ $message }}</div> 
            @enderror
        @endif

        @if($hasDocuments || !empty($existingDocuments))
            <div class="d-flex flex-wrap gap-2 {{ $useDragDrop ? 'align-items-start' : 'mt-2' }}" style="{{ $useDragDrop ? 'flex: 1; min-width: 200px;' : '' }}">
                @if(!empty($newDocuments))
                    @foreach($newDocuments as $index => $document)
                        @if(is_object($document) && method_exists($document, 'getClientOriginalName'))
                            @php
                                $fileName = $document->getClientOriginalName();
                                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif']);
                                $fileIcon = getFileIcon($fileName);
                            @endphp
                            <div class="position-relative border rounded bg-white shadow-sm overflow-hidden" style="width: {{ $thumbnailSize }}; height: {{ $thumbnailSize }};">
                                @if($isImage)
                                    @php
                                        $imageUrl = null;
                                        try {
                                            if (method_exists($document, 'temporaryUrl')) {
                                                $imageUrl = $document->temporaryUrl();
                                            }
                                        } catch (\Exception $e) {
                                            $imageUrl = null;
                                        }
                                    @endphp
                                    @if($imageUrl)
                                        <img src="{{ $imageUrl }}" 
                                             alt="{{ $fileName }}"
                                             style="width: 100%; height: 100%; object-fit: cover;"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="d-flex flex-column align-items-center justify-content-center h-100 p-3" style="display: none;">
                                            <i class="fa-solid {{ $fileIcon['class'] }} fs-2 mb-2" style="color: {{ $fileIcon['color'] }};"></i>
                                            <span class="text-center small fw-medium" style="font-size: 0.7rem; word-break: break-word;">{{ strtoupper($fileExt) }}</span>
                                        </div>
                                    @else
                                        <div class="d-flex flex-column align-items-center justify-content-center h-100 p-3">
                                            <i class="fa-solid {{ $fileIcon['class'] }} fs-2 mb-2" style="color: {{ $fileIcon['color'] }};"></i>
                                            <span class="text-center small fw-medium" style="font-size: 0.7rem; word-break: break-word;">{{ strtoupper($fileExt) }}</span>
                                        </div>
                                    @endif
                                @else
                                    <div class="d-flex flex-column align-items-center justify-content-center h-100 p-3">
                                        <i class="fa-solid {{ $fileIcon['class'] }} fs-2 mb-2" style="color: {{ $fileIcon['color'] }};"></i>
                                        <span class="text-center small fw-medium" style="font-size: 0.7rem; word-break: break-word;">{{ strtoupper($fileExt) }}</span>
                                    </div>
                                @endif
                                @if($multiple && method_exists($document, 'getClientOriginalName'))
                                    <button type="button" 
                                            wire:click="removeDocument({{ $index }})"
                                            class="position-absolute top-0 end-0 btn btn-sm p-1 m-1"
                                            style="background: rgba(255,255,255,0.9); border: 1px solid #e5e7eb; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; z-index: 10;"
                                            onmouseover="this.style.backgroundColor='#fee2e2'; this.style.borderColor='#ef4444';"
                                            onmouseout="this.style.backgroundColor='rgba(255,255,255,0.9)'; this.style.borderColor='#e5e7eb';">
                                        <i class="fa-solid fa-trash" style="font-size: 0.7rem; color: #6b7280;"></i>
                                    </button>
                                @endif
                            </div>
                        @endif
                    @endforeach
                @endif

                @if(!empty($existingDocuments))
                    @foreach($existingDocuments as $document)
                        @php
                            $fileName = basename($document);
                            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                            $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif']);
                            $fileIcon = getFileIcon($fileName);
                        @endphp
                        <div class="position-relative border rounded bg-white shadow-sm overflow-hidden" style="width: {{ $thumbnailSize }}; height: {{ $thumbnailSize }};">
                            @if($isImage)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($document) }}" 
                                     alt="{{ $fileName }}"
                                     style="width: 100%; height: 100%; object-fit: cover;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="d-flex flex-column align-items-center justify-content-center h-100 p-3" style="display: none;">
                                    <i class="fa-solid {{ $fileIcon['class'] }} fs-2 mb-2" style="color: {{ $fileIcon['color'] }};"></i>
                                    <span class="text-center small fw-medium" style="font-size: 0.7rem; word-break: break-word;">{{ strtoupper($fileExt) }}</span>
                                </div>
                            @else
                                <div class="d-flex flex-column align-items-center justify-content-center h-100 p-3">
                                    <i class="fa-solid {{ $fileIcon['class'] }} fs-2 mb-2" style="color: {{ $fileIcon['color'] }};"></i>
                                    <span class="text-center small fw-medium" style="font-size: 0.7rem; word-break: break-word;">{{ strtoupper($fileExt) }}</span>
                                </div>
                            @endif
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($document) }}" target="_blank" class="stretched-link"></a>
                            @if($multiple)
                                <button type="button" 
                                        wire:click="removeSavedDocument('{{ $document }}')"
                                        class="position-absolute top-0 end-0 btn btn-sm p-1 m-1"
                                        style="background: rgba(255,255,255,0.9); border: 1px solid #e5e7eb; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; z-index: 10;"
                                        onmouseover="this.style.backgroundColor='#fee2e2'; this.style.borderColor='#ef4444';"
                                        onmouseout="this.style.backgroundColor='rgba(255,255,255,0.9)'; this.style.borderColor='#e5e7eb';"
                                        onclick="event.preventDefault(); event.stopPropagation();">
                                    <i class="fa-solid fa-trash" style="font-size: 0.7rem; color: #6b7280;"></i>
                                </button>
                            @endif
                            <div class="position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-75 text-white p-2" style="font-size: 0.75rem;">
                                <div class="text-truncate">{{ $fileName }}</div>
                            </div>
                        </div>
                    @endforeach
                @elseif(!$multiple && $documents && is_string($documents))
                    @php
                        $fileName = basename($documents);
                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif']);
                        $fileIcon = getFileIcon($fileName);
                    @endphp
                    <div class="position-relative border rounded bg-white shadow-sm overflow-hidden" style="width: {{ $thumbnailSize }}; height: {{ $thumbnailSize }};">
                        @if($isImage)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($documents) }}" 
                                 alt="{{ $fileName }}"
                                 style="width: 100%; height: 100%; object-fit: cover;"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="d-flex flex-column align-items-center justify-content-center h-100 p-3" style="display: none;">
                                <i class="fa-solid {{ $fileIcon['class'] }} fs-2 mb-2" style="color: {{ $fileIcon['color'] }};"></i>
                                <span class="text-center small fw-medium" style="font-size: 0.7rem; word-break: break-word;">{{ strtoupper($fileExt) }}</span>
                            </div>
                        @else
                            <div class="d-flex flex-column align-items-center justify-content-center h-100 p-3">
                                <i class="fa-solid {{ $fileIcon['class'] }} fs-2 mb-2" style="color: {{ $fileIcon['color'] }};"></i>
                                <span class="text-center small fw-medium" style="font-size: 0.7rem; word-break: break-word;">{{ strtoupper($fileExt) }}</span>
                            </div>
                        @endif
                        <a href="{{ \Illuminate\Support\Facades\Storage::url($documents) }}" target="_blank" class="stretched-link"></a>
                        <div class="position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-75 text-white p-2" style="font-size: 0.75rem;">
                            <div class="text-truncate">{{ $fileName }}</div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if($useDragDrop && ($hasDocuments || !empty($existingDocuments)))
            </div>
        @endif
        <small class="text-muted d-block mt-1">
            <i class="fa-solid fa-info-circle"></i> Allowed file types: {{ str_replace(',', ', ', strtoupper($accept)) }} (Max: {{ $maxSize }})
        </small>
    @endif
</div>

