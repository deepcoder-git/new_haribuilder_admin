@props(['click','confirm'=>null,'module'])
<a style="cursor: pointer; color: #ef4444;"
   wire:confirm="{{$confirm??ucfirst(__('app.panel.are_you_sure_want_to_delete', ['name' => $module]))}}"
   wire:click="{{$click}}"
   data-toggle="tooltip" data-placement="top" title="Delete {{$module}} Details"
   onmouseover="this.style.color='#ef4444'; this.style.opacity='0.8';"
   onmouseout="this.style.color='#ef4444'; this.style.opacity='1';">
    <i class="fa-solid fa-trash icon" style="color: #ef4444;"></i>
</a>
