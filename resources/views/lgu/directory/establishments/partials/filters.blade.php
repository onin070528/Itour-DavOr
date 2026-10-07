{{-- Search and filters for the Establishments table (client-side; the list is already limited to this LGU's municipality on the server). --}}
<div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
    <label class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
        <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
        <span class="sr-only">Search establishments</span>
        <input data-filter-input type="search" placeholder="Search by name or barangay..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
    </label>

    <label>
        <span class="sr-only">Category</span>
        <select data-filter-select data-filter-key="category-id" class="form-input bg-sand-50 py-2.5 text-sand-700 lg:w-48">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->cat_id }}">{{ $category->cat_name }}</option>
            @endforeach
        </select>
    </label>

    <label>
        <span class="sr-only">Establishment type</span>
        <select data-filter-select data-filter-key="type" class="form-input bg-sand-50 py-2.5 text-sand-700 lg:w-48">
            <option value="">All types</option>
            @foreach ($categories as $category)
                <optgroup label="{{ $category->cat_name }}">
                    @foreach ($category->establishmentTypes() as $strType)
                        <option value="{{ $strType }}">{{ $strType }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </label>

    <label>
        <span class="sr-only">Reporting method</span>
        <select data-filter-select data-filter-key="reporting" class="form-input bg-sand-50 py-2.5 text-sand-700 lg:w-44">
            <option value="">All reporting methods</option>
            @foreach (\App\Enums\ReportingMethod::cases() as $objReportingMethod)
                <option value="{{ $objReportingMethod->value }}">{{ $objReportingMethod->label() }}</option>
            @endforeach
        </select>
    </label>

    <button type="button" data-filter-reset class="btn-secondary justify-center py-2.5">
        Reset
    </button>
</div>
