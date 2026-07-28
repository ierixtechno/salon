<form method="GET" class="bg-white shadow-sm rounded-lg p-4 mb-6 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs text-gray-500 mb-1">Branch</label>
        <select name="branch_id" class="border-gray-300 rounded-md shadow-sm text-sm">
            <option value="">All branches</option>
            @foreach ($branches as $b)
                <option value="{{ $b->id }}" @selected($branch?->id === $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">From</label>
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="border-gray-300 rounded-md shadow-sm text-sm">
    </div>
    <div>
        <label class="block text-xs text-gray-500 mb-1">To</label>
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="border-gray-300 rounded-md shadow-sm text-sm">
    </div>
    <button type="submit" class="px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-500">Apply</button>
    <a href="{{ route(Illuminate\Support\Facades\Route::currentRouteName(), array_merge(request()->only(['branch_id', 'from', 'to']), ['export' => 'csv'])) }}"
        class="ml-auto text-sm text-indigo-600 hover:text-indigo-800">Export CSV &darr;</a>
</form>
