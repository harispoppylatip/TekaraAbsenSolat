{{--
    Tautan halaman untuk daftar yang panjang.
    Dipakai bersama oleh halaman anggota, data wajah, dan rekap.
--}}
@if ($paginator->hasPages() || $paginator->total() > 0)
    <div class="pagination-wrap">
        <div class="pagination">
            @if ($paginator->onFirstPage())
                <span class="page-link is-disabled">Sebelumnya</span>
            @else
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="page-gap">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="page-link is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
            @else
                <span class="page-link is-disabled">Berikutnya</span>
            @endif
        </div>

        <p class="pagination-summary">
            Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
            ({{ $paginator->total() }} data)
        </p>
    </div>
@endif
