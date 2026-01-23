<tbody class="text-gray-600 fw-semibold">
@if($items->count())
    {{$slot}}
@else
    <tr><td class="text-center" colspan="100%">{{__('app.panel.table.no_record_found')}}</td></tr>
@endif
</tbody>
