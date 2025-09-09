<div id="resources-list">
    <!-- This must be within the div since Livewire accepts only one root tag per component -->
    @if(count($resources))
        <div class="card">
            <div class="card-header py-2">
                <h5 class="mb-0">{{ __('resources::messages.New links') }}</h5>
            </div>

            <ul class="list-group list-group-flush">
                @foreach($resources->sortBy('provider') as $resource)
                    <li class="list-group-item {{ $resource->provider}}">
                        <div class="d-flex justify-content-between align-items-center">
                            <a target="_blank" href="{{ $resource->url }}">
                                {{ \KraenzleRitter\Resources\Helpers\LabelHelper::getProviderLabel($resource->provider) }}
                            </a>
                            @if($deleteButton)
                                <button
                                    wire:click="removeResource({{ $resource->id }})"
                                    class="btn btn-danger btn-sm"
                                    title="{{ __('resources::messages.Remove Resource') }}">
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
