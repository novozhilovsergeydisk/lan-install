<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Фотоотчеты</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h2>Фотоотчеты</h2>
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if($photos->isEmpty())
                        <div class="alert alert-info">Нет доступных фотоотчетов</div>
                    @else
                        <div class="row">
                            @foreach($photos as $photo)
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100">
                                        <a href="{{ $photo['url'] }}" data-lightbox="photo-gallery" data-title="{{ $photo['original_name'] }}">
                                            <img src="{{ $photo['url'] }}" class="card-img-top" alt="{{ $photo['original_name'] }}" style="height: 200px; object-fit: cover;">
                                        </a>
                                        <div class="card-body">
                                            <h5 class="card-title">{{ $photo['original_name'] }}</h5>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    Размер: {{ $photo['file_size'] }}<br>
                                                    Разрешение: {{ $photo['width'] }}x{{ $photo['height'] }}<br>
                                                    Загружено: {{ \Carbon\Carbon::parse($photo['created_at'])->format('d.m.Y H:i') }}
                                                    @if($photo['request_id'])
                                                        <br>
                                                    <span class="text-muted">
                                                        Заявка #{{ $photo['request_number'] ?? $photo['request_id'] }}
                                                    </span>
                                                    @endif
                                                </small>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Add pagination if needed --}}
                        {{-- {{ $photos->links() }} --}}
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .card {
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    .card-img-top {
        border-bottom: 1px solid rgba(0,0,0,0.1);
    }
</style>
@endpush

@push('scripts')
<!-- Include lightbox2 CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css" rel="stylesheet">
<!-- Include lightbox2 JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
<script>
    // Configure lightbox
    lightbox.option({
        'resizeDuration': 200,
        'wrapAround': true,
        'showImageNumberLabel': true,
        'disableScrolling': true,
        'albumLabel': 'Фото %1 из %2'
    });
</script>
@endpush
@endsection
