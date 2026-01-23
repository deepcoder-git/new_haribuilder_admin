@props(['route','module'])
<a style="cursor: pointer; color: #1e3a8a;" href="{{$route}}"
   data-toggle="tooltip" data-placement="top" title="Edit {{$module}} Details"
   onmouseover="this.style.color='#1e3a8a'; this.style.opacity='0.8';"
   onmouseout="this.style.color='#1e3a8a'; this.style.opacity='1';">
    <i class="fa-solid fa-pen icon edit-icon" style="color: #1e3a8a;"></i>
</a>
