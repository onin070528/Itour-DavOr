{{--
    Establishment Type select, dependent on the form's Category select.
    Every category's types are rendered once (from Category::establishmentTypes(),
    backed by config/establishment_categories.php); resources/js/establishment_type_select.js
    shows only the selected category's options. The server re-validates with
    App\Rules\EstablishmentTypeBelongsToCategory — this filtering is convenience only.

    Props:
      categories     Collection of App\Models\Category to render types for.
      selected       The currently selected type, if any.
      categoryField  Name of the Category <select> in the same form (default cat_id).
--}}
@props([
    'categories',
    'selected' => null,
    'categoryField' => 'cat_id',
])

<select
    name="type"
    data-establishment-type-select
    data-category-field="{{ $categoryField }}"
    data-tour-guide-type="{{ config('establishment_categories.tour_guide_type') }}"
    {{ $attributes->merge(['class' => 'w-full rounded-sm border border-sand-300 px-3 py-2 text-sm']) }}
>
    <option value="">Select a type</option>
    @foreach ($categories as $category)
        @foreach ($category->establishmentTypes() as $strType)
            <option value="{{ $strType }}" data-category-id="{{ $category->cat_id }}" @selected($selected === $strType)>{{ $strType }}</option>
        @endforeach
    @endforeach
</select>
