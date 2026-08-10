@props(['paginator'])
@if($paginator->hasPages())<nav aria-label="Pagination" {{ $attributes }}>{{ $paginator->links() }}</nav>@endif
