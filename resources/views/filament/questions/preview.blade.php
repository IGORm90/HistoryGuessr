@php
    /** @var \App\Models\Question $question */
@endphp
<div class="space-y-6">
    <section class="space-y-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Игрок видит</h3>
        @if ($imageUrl)
            <img src="{{ $imageUrl }}" alt="" class="w-full rounded-lg" loading="lazy"
                 @if ($question->image_width && $question->image_height)
                     width="{{ $question->image_width }}" height="{{ $question->image_height }}"
                 @endif
            >
        @else
            <div class="flex h-48 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-gray-800">
                Изображение не обработано
            </div>
        @endif
    </section>

    <section class="space-y-2 border-t border-gray-200 pt-4 dark:border-gray-700">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">После ответа</h3>
        <p class="text-lg font-semibold">{{ $question->answer }}</p>
        <p>
            {{ $question->location_name ?? '— место не указано —' }},
            {{ $year ?? '— год не указан —' }}
        </p>
        @if ($question->latitude !== null && $question->longitude !== null)
            <p class="text-sm text-gray-500">
                {{ number_format($question->latitude, 6) }}, {{ number_format($question->longitude, 6) }}
                · <a class="underline" target="_blank" rel="noopener noreferrer"
                     href="https://www.openstreetmap.org/?mlat={{ $question->latitude }}&mlon={{ $question->longitude }}#map=8/{{ $question->latitude }}/{{ $question->longitude }}">открыть на карте</a>
            </p>
        @endif
        <p class="whitespace-pre-line">{{ $question->reveal_text ?? '— описание не заполнено —' }}</p>
        <p class="text-xs text-gray-500">
            Фото: {{ $question->image_author ?? 'автор неизвестен' }}
            · {{ $question->image_license ?? 'лицензия не указана' }}
            @if ($question->source_url)
                · <a class="underline" href="{{ $question->source_url }}" target="_blank" rel="noopener noreferrer">источник</a>
            @endif
            @if ($question->image_attribution)
                <br>{{ $question->image_attribution }}
            @endif
        </p>
    </section>
</div>
