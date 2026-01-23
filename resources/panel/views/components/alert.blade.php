@session('alert')
@foreach($value as $data)
    <div class="alert alert-{{$data['type']}} alert-dismissible fade show" role="alert">
        {{$data['message']}}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endforeach
@endsession




