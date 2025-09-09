<form class="mb-3">
    <label class="form-label">{{ __('resources::messages.Search') }} </label>
    <input
        wire:model.live="search"
        class="form-control"
        type="text"
        placeholder="{{ $placeholder ?? '' }}"
        aria-label="{{ __('resources::messages.Search') }}">
</form>
