@extends('layouts-history.app')

@php
    use App\Helpers\StringHelper;
@endphp

@section('title', 'Заявка ' . $request->number)

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">

            @php
                $typeColor = $request->request_type_color;
                $contrastColor = $typeColor ? StringHelper::getContrastColor($typeColor) : '#000000';
                $executionDate = $request->execution_date
                    ? \Carbon\Carbon::parse($request->execution_date)->format('d.m.Y')
                    : 'Дата не указана';
            @endphp

            <div class="card mb-3">
                <div class="card-header" @if($typeColor) style="background-color: {{ $typeColor }}; color: {{ $contrastColor }};" @else style="background-color: #212529; color: #fff;" @endif>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="card-title mb-0">
                            {{ $executionDate }} | {{ $request->number }}
                            @if($request->request_type_name)
                                <span class="ms-1">[{{ $request->request_type_name }}]</span>
                            @endif
                        </h5>
                        @if($request->status_name)
                            <span class="badge rounded-pill" style="background-color: {{ $request->status_color ?: '#6c757d' }}; color: #fff; font-weight: normal;">
                                {{ $request->status_name }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            @if($request->client_organization)
                                <div class="fw-bold text-primary">{{ $request->client_organization }}</div>
                            @endif

                            @if($request->street)
                                <div class="text-muted" style="line-height: 1.3;">
                                    @if($request->city_name && $request->city_name !== 'Москва'){{ $request->city_name }}, @endif
                                    ул. {{ $request->street }}, д. {{ $request->houses }}
                                    @if($request->district)
                                        <small>({{ $request->district }})</small>
                                    @endif
                                </div>
                            @else
                                <div class="text-muted fst-italic">Адрес не указан</div>
                            @endif

                            @if($request->client_fio)
                                <div class="mt-1">{{ $request->client_fio }}</div>
                            @endif
                            @if($request->client_phone)
                                <small class="text-muted"><i class="bi bi-telephone"></i> {{ $request->client_phone }}</small>
                            @endif
                        </div>

                        <div class="col-12 col-md-6">
                            @if($request->brigade_name)
                                <div class="small">
                                    <div class="text-muted">Бригада</div>
                                    <div class="fw-bold">{{ $request->brigade_name }}</div>
                                    @if($request->brigade_lead)
                                        <div class="text-muted" style="font-size: 0.8rem;">{{ $request->brigade_lead }}</div>
                                    @endif
                                </div>
                            @else
                                <div class="small text-muted fst-italic">Бригада не назначена</div>
                            @endif

                            @if($downloadUrl)
                                <a href="{{ $downloadUrl }}" class="btn btn-sm btn-outline-secondary mt-2">
                                    <i class="bi bi-archive me-1"></i>Скачать все вложения архивом
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Комментарии: у каждого — свои фото и файлы (в этом смысл блока 6:
                 заказчик видит, к какому этапу работ относится снимок). --}}
            @if($comments->isEmpty())
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>По этой заявке пока нет комментариев.
                </div>
            @else
                @foreach($comments as $comment)
                    @php
                        $photos = $photosByComment[$comment->id] ?? collect();
                        $files = $filesByComment[$comment->id] ?? collect();
                    @endphp

                    <div class="card mb-3 @if($comment->is_closing) border-success @endif">
                        <div class="card-body">
                            @if($comment->is_closing)
                                <div class="mb-2">
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle-fill"></i> Комментарий закрытия
                                    </span>
                                </div>
                            @endif

                            <div class="text-break" style="white-space: pre-wrap; line-height: 1.4;">{!! $comment->comment !!}</div>

                            <div class="d-flex justify-content-between text-muted border-top pt-1 mt-2" style="font-size: 0.75rem;">
                                <span>{{ \Carbon\Carbon::parse($comment->created_at)->format('d.m.Y H:i') }}</span>
                                <span>{{ $comment->author_name }}</span>
                            </div>

                            @if($photos->isNotEmpty())
                                <div class="row g-2 mt-2">
                                    @foreach($photos as $photo)
                                        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                            <div class="ratio ratio-1x1">
                                                <img src="{{ asset('storage/' . $photo->path) }}"
                                                     class="img-fluid rounded border shadow-sm gallery-image"
                                                     alt="{{ $photo->original_name ?: 'Фото' }}"
                                                     loading="lazy"
                                                     data-bs-toggle="modal"
                                                     data-bs-target="#photoModal"
                                                     data-src="{{ asset('storage/' . $photo->path) }}"
                                                     style="cursor: pointer; object-fit: cover; width: 100%; height: 100%;">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if($files->isNotEmpty())
                                <div class="mt-2 d-flex flex-wrap gap-2">
                                    @foreach($files as $file)
                                        <a href="{{ asset('storage/' . $file->path) }}" target="_blank"
                                           class="btn btn-sm btn-outline-secondary" style="font-size: 0.75rem;">
                                            <i class="bi bi-paperclip me-1"></i>{{ $file->original_name ?: 'Файл' }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif

        </div>
    </div>
</div>

<!-- Просмотр фото во весь экран -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 95vw;" data-bs-dismiss="modal">
        <div class="modal-content bg-transparent border-0 shadow-none" style="cursor: pointer;">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Закрыть" style="z-index: 1056;"></button>
                <img src="" id="modalImage" class="img-fluid rounded shadow-lg"
                     style="max-height: 95vh; width: auto; max-width: 100%; object-fit: contain;"
                     data-bs-dismiss="modal">
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalImage = document.getElementById('modalImage');
        document.querySelectorAll('.gallery-image').forEach(img => {
            img.addEventListener('click', function () {
                modalImage.src = this.getAttribute('data-src');
            });
        });
    });
</script>

<style>
    #photoModal {
        backdrop-filter: blur(8px);
        background-color: rgba(0, 0, 0, 0.5);
    }
</style>
@endsection
