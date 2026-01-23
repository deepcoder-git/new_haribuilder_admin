@props([
    'wireModel' => '',
    'index' => 0,
    'min' => 0,
    'step' => 1,
    'placeholder' => '0',
    'errorKey' => '',
    'inputWidth' => '70px',
    'buttonSize' => '32px',
    'inputClass' => 'form-control form-control-sm text-center',
    'containerClass' => 'd-flex align-items-center justify-content-center gap-1',
])

<div class="{{ $containerClass }}">
    <button type="button" 
            wire:click="decrementQuantity({{ $index }})"
            class="btn btn-sm btn-outline-secondary qty-btn"
            style="width: {{ $buttonSize }}; height: {{ $buttonSize }}; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
        <i class="fa-solid fa-minus" style="font-size: 0.7rem;"></i>
    </button>
    <input type="number" 
           wire:model.blur="{{ $wireModel }}"
           step="{{ $step }}"
           min="{{ $min }}"
           class="{{ $inputClass }} @error($errorKey) is-invalid @enderror"
           placeholder="{{ $placeholder }}"
           style="width: {{ $inputWidth }};">
    <button type="button" 
            wire:click="incrementQuantity({{ $index }})"
            class="btn btn-sm btn-outline-primary qty-btn"
            style="width: {{ $buttonSize }}; height: {{ $buttonSize }}; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
        <i class="fa-solid fa-plus" style="font-size: 0.7rem;"></i>
    </button>
</div>
@error($errorKey)
    <div class="text-danger small mt-1 text-center">{{ $message }}</div>
@enderror

<style>
    .qty-btn {
        transition: all 0.2s ease;
    }
    .qty-btn:hover {
        transform: scale(1.05);
    }
    .btn-outline-secondary.qty-btn:hover {
        background: #6b7280;
        border-color: #6b7280;
        color: white;
    }
    .btn-outline-primary.qty-btn {
        border-color: #1e3a8a;
        color: #1e3a8a;
    }
    .btn-outline-primary.qty-btn:hover {
        background: #1e3a8a;
        border-color: #1e3a8a;
        color: white;
    }
    /* Hide number input spinner */
    input[type="number"]::-webkit-inner-spin-button,
    input[type="number"]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    input[type="number"] {
        -moz-appearance: textfield;
    }
</style>

