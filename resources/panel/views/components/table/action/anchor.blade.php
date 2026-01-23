@props(['route','module','method','target'=>'_self'])
<a style="cursor: pointer" href="{{$route}}" target="{{$target}}"
   data-toggle="tooltip" data-placement="top" title="{{ucfirst($method)}} {{$module}} Details">
    {{$slot}}
</a>
