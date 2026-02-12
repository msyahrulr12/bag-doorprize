<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <h4 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-2">Original Data</h4>
            <div class="rounded-3xl border border-slate-100 overflow-x-auto">
                @if($getRecord()->original_data)
                    <div class="overflow-hidden border border-slate-100 rounded-3xl shadow-sm bg-white">
                        <div class="max-h-[50vh] overflow-y-auto scrollbar-thin">
                            <table class="w-full text-left border-collapse">
                                <tbody class="divide-y divide-slate-50">
                                    @foreach ($getRecord()->original_data as $key => $value)
                                        @if (in_array($key, array_keys($getRecord()->new_data)))
                                            <tr>
                                                <th
                                                    class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                                    {{ $key }}</th>
                                                <th
                                                    class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-black text-left">
                                                    {{ $value }}</th>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <span class="text-xs text-gray-400 italic">No original data (New Record)</span>
                @endif
            </div>
        </div>
        <div>
            <h4 class="text-sm font-bold uppercase tracking-wider text-gray-500 mb-2">Requested Changes</h4>
            <div class="overflow-hidden border border-slate-100 rounded-3xl shadow-sm bg-white">
                <div class="max-h-[50vh] overflow-y-auto scrollbar-thin">
                    <table class="w-full text-left border-collapse">
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($getRecord()->new_data as $key => $value)
                            <tr>
                                <th
                                    class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                    {{ $key }}</th>
                                <th
                                    class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-black text-left">
                                    {{ $value }}</th>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($getRecord()->status === \App\Models\Approval::STATUS_REJECTED)
        <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-xl">
            <h4 class="text-sm font-bold uppercase tracking-wider text-red-800 mb-1">Rejection Reason</h4>
            <p class="text-sm text-red-700">{{ $getRecord()->reason }}</p>
        </div>
    @endif
</div>