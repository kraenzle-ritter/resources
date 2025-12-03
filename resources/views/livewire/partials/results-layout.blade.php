<div class="px-3 py-2">
    @if(!in_array($providerKey, $model->resources->pluck('provider')->toArray()))
        <div>
            @include('resources::livewire.partials.search-form', [
                'providerName' => $providerName ?? $providerKey
            ])

            @if($results && count($results))
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">{{ __('resources::messages.List') }}</h5>

                    @php
                        $configLimit = config("resources.providers.{$providerKey}.limit") ?? config('resources.limit') ?? 5;
                        $hasMore = count($results) >= $configLimit && empty($showAll);
                    @endphp

                    @if($hasMore)
                        <button wire:click="showAllResults" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i> {{ __('resources::messages.Show All') }}
                        </button>
                    @endif
                </div>

                <div class="results-list">
                    @foreach($results as $result)
                        <div class="result-item mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                @if(isset($result_heading) && $result_heading($result))
                                    <h6 class="mb-0 fw-bold">{!! $result_heading($result) !!}</h6>
                                @endif

                                <div class="ms-2">
                                    @include('resources::livewire.partials.save-button', [
                                        'saveAction' => $saveAction($result),
                                        'providerName' => $providerName ?? $providerKey
                                    ])
                                </div>
                            </div>

                            <div class="mt-1 small">
                                {!! $result_content($result) !!}
                            </div>

                            @if (!$loop->last)
                                <hr class="my-3">
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="alert {{ isset($apiLimitReached) && $apiLimitReached ? 'alert-warning' : 'alert-info' }} mt-3">
                    @if(isset($apiLimitReached) && $apiLimitReached)
                        <strong>{{ __('resources::messages.API Limit Reached') }}:</strong> {{ __('resources::messages.API Limit Message') }}
                        <a href="https://www.geonames.org/login" target="_blank">{{ __('Register for a free account') }}</a>
                        {{ __('and set the username in your .env file') }}: <code>GEONAMES_USERNAME=your_username</code>
                    @else
                        {{ __('resources::messages.No matches') }}
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
