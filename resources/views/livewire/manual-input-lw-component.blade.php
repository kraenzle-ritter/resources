<div class="p-3">
@if(!in_array('manual-input', $model->resources->pluck('provider')->toArray()))
    <div>
        <h5 class="mb-3">{{ __('resources::messages.Manual input') }}</h5>
        <form class="manual-input-form">
            <div class="mb-3">
                <label for="provider" class="form-label" title="The Name of the Provider: eg. Wikipedia (en);">{{ __('resources::messages.Provider') }}</label>
                <input type="text" wire:model.live="provider" class="form-control" id="provider">
                @error('provider') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="provider_id" class="form-label" title="The ID which is related to the provider. Most likely the last part of the URL;">{{ __('resources::messages.Provider ID') }}</label>
                <input type="text" wire:model.live="provider_id" class="form-control" id="provider_id">
                @error('provider_id') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="url" class="form-label" title="Link to the resource;">{{ __('resources::messages.URL') }}</label>
                <input type="text" wire:model.live="url" class="form-control" id="url">
                @error('url') <div class="text-danger">{{ $message }}</div> @enderror
            </div>
            <div class="text-end">
                <button
                    wire:click="saveResource()"
                    type="button"
                    class="btn btn-success btn-sm"
                    title="{{ __('resources::messages.Save resource') }}">
                    <i class="fas fa-check" aria-hidden="true"></i> {{ __('resources::messages.Save') }}
                </button>
            </div>
        </form>
    </div>
@endif
</div>
