@props(['name'=>''])

@if($errors->has($name))
    @foreach($errors->get($name) as $message)
        <p class="error">{{ $message }}</p>
    @endforeach
@endif
