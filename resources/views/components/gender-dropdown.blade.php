@props(['name' => 'gender', 'id' => 'gender', 'value' => '', 'required' => false, 'error' => null, 'label' => 'Gender'])

<div class="mb-4">
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-600 mb-1">
        {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
    </label>
    <select id="{{ $id }}"
            class="w-full px-4 py-3 text-base border-2 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none appearance-none cursor-pointer {{ $error ? 'border-red-500' : 'border-primary/20 focus:border-primary' }}"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}>
        <option value="">Select Gender</option>
        <option value="m" {{ $value == 'm' ? 'selected' : '' }}>Male</option>
        <option value="f" {{ $value == 'f' ? 'selected' : '' }}>Female</option>
    </select>
    @if($error)
        <span class="text-red-500 text-sm mt-1 block" role="alert">
            <strong>{{ $error }}</strong>
        </span>
    @endif
</div>
