@props(['click','module'])
<a style="cursor: pointer"
  wire:click="{{$click}}"
   data-toggle="tooltip" data-placement="top" title="Sync Students">
    <i class="fa-solid fa-sync icon"></i>
</a>
