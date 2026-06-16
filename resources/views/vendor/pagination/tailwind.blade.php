@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">

        {{-- Mobile: Prev / Next only --}}
        <div class="flex gap-2 items-center justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-border cursor-not-allowed rounded-lg">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center px-4 py-2 text-sm font-medium text-foreground bg-white border border-border rounded-lg hover:bg-accent hover:text-primary transition-colors">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            <span class="text-sm text-muted-foreground">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center px-4 py-2 text-sm font-medium text-foreground bg-white border border-border rounded-lg hover:bg-accent hover:text-primary transition-colors">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-white border border-border cursor-not-allowed rounded-lg">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        {{-- Desktop: full pagination --}}
        <div class="hidden sm:flex sm:flex-col sm:items-center sm:gap-3">

            <div>
                <div class="inline-flex items-center gap-1 rtl:flex-row-reverse">

                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span class="inline-flex items-center justify-center w-9 h-9 text-sm text-gray-400 bg-white border border-border cursor-not-allowed rounded-lg" aria-disabled="true">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center justify-center w-9 h-9 text-sm text-foreground bg-white border border-border rounded-lg hover:bg-accent hover:text-primary hover:border-primary/30 transition-colors" aria-label="{{ __('pagination.previous') }}">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span class="inline-flex items-center justify-center w-9 h-9 text-sm text-muted-foreground bg-white border border-border rounded-lg cursor-default select-none">
                                {{ $element }}
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="inline-flex items-center justify-center w-9 h-9 text-sm font-semibold text-white bg-primary border border-primary rounded-lg cursor-default shadow-sm">
                                        {{ $page }}
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="inline-flex items-center justify-center w-9 h-9 text-sm font-medium text-foreground bg-white border border-border rounded-lg hover:bg-accent hover:text-primary hover:border-primary/30 transition-colors" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center justify-center w-9 h-9 text-sm text-foreground bg-white border border-border rounded-lg hover:bg-accent hover:text-primary hover:border-primary/30 transition-colors" aria-label="{{ __('pagination.next') }}">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @else
                        <span class="inline-flex items-center justify-center w-9 h-9 text-sm text-gray-400 bg-white border border-border cursor-not-allowed rounded-lg" aria-disabled="true">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    @endif

                </div>
            </div>

            <p class="text-sm text-muted-foreground">
                {!! __('Showing') !!}
                @if ($paginator->firstItem())
                    <span class="font-semibold text-foreground">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-semibold text-foreground">{{ $paginator->lastItem() }}</span>
                @else
                    {{ $paginator->count() }}
                @endif
                {!! __('of') !!}
                <span class="font-semibold text-foreground">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </p>
        </div>

    </nav>
@endif
