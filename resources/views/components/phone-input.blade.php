@props(['name' => 'phone', 'value' => null])

<div class="flex mt-1">
    <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm select-none">+91</span>
    <input
        type="tel"
        inputmode="numeric"
        pattern="[6-9][0-9]{9}"
        maxlength="10"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)"
        {{ $attributes->merge(['class' => 'block w-full rounded-r-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500']) }}
    >
</div>
