{{-- resources/views/components/sound-loader.blade.php --}}
@props(['event'])

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const soundManager = window.soundManager;

        @if($event - > start_drawing_sound)
        soundManager.loadSound(
            '{{ Storage::url($event->start_drawing_sound) }}',
            'start_drawing'
        );
        @endif

        @if($event - > winner_found_sound)
        soundManager.loadSound(
            '{{ Storage::url($event->winner_found_sound) }}',
            'winner_found'
        );
        @endif
    });
</script>
@endpush