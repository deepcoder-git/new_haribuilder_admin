@props(['name'=>'query','items','pagination'=>true])
<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-6 gy-5 no-footer">
        {{$slot}}
    </table>
    @if($pagination)
        {!! $items->links('panel::components.table.pagination',['name'=>$name]) !!}
    @endif
</div>

