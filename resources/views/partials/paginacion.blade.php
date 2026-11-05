@if($paginable->hasPages())
    <div class="mt-4">
        {{ $paginable->links() }}
    </div>
@endif
