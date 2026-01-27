@props(['name' => 'gender', 'id' => 'gender', 'value' => '', 'required' => false, 'error' => null])

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">Gender</label>
    <select id="{{ $id }}"
            class="form-select @if($error) is-invalid @endif"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}>
        <option value="">Select Gender</option>
        <option value="m" {{ $value == 'm' ? 'selected' : '' }}>Male</option>
        <option value="f" {{ $value == 'f' ? 'selected' : '' }}>Female</option>
    </select>
    @if($error)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $error }}</strong>
        </span>
    @endif
</div>
