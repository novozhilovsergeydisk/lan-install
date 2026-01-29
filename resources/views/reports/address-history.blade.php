@extends('layouts-history.app')

@section('title', 'Отчеты по адресу')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="card-title mb-0">
                        {{ $address->city_name }}, {{ $address->street }}, {{ $address->houses }}
                        @if($address->district) <small class="text-muted">({{ $address->district }})</small> @endif
                    </h5>
                    <small>История заявок</small>
                </div>
                <div class="card-body p-0">
                    @if($requests->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th style="width: 140px;">Дата / Инфо</th>
                                        <th style="min-width: 200px;">Клиент / Адрес</th>
                                        <th style="min-width: 300px;">Комментарии</th>
                                        <th style="width: 180px;">Статус / Бригада</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requests as $index => $request)
                                        <tr>
                                            <!-- Номер п/п (обратный отсчет или просто индекс) -->
                                            <td class="text-center text-muted">{{ $loop->iteration }}</td>

                                            <!-- Дата и Номер заявки -->
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold fs-6">
                                                        {{ $request->execution_date ? \Carbon\Carbon::parse($request->execution_date)->format('d.m.Y') : 'Нет даты' }}
                                                    </span>
                                                    <small class="text-muted" style="font-size: 0.75rem;">
                                                        {{ $request->number }}
                                                    </small>
                                                </div>
                                            </td>

                                            <!-- Данные клиента -->
                                            <td>
                                                <div class="d-flex flex-column">
                                                    @if($request->client_organization)
                                                        <span class="fw-bold text-primary">{{ $request->client_organization }}</span>
                                                    @endif
                                                    
                                                    <!-- Адрес (если вдруг он отличается или для контекста) -->
                                                    <small class="text-muted mb-1" style="line-height: 1.2;">
                                                        {{ $address->street }}, {{ $address->houses }}
                                                    </small>

                                                    <span style="font-size: 0.9rem;">{{ $request->client_fio }}</span>
                                                    @if($request->client_phone)
                                                        <small class="text-muted">
                                                            <i class="bi bi-telephone"></i> {{ $request->client_phone }}
                                                        </small>
                                                    @endif
                                                </div>
                                            </td>

                                            <!-- Комментарии -->
                                            <td class="p-2">
                                                @if(isset($commentsByRequest[$request->id]) && count($commentsByRequest[$request->id]) > 0)
                                                    <div style="max-height: 250px; overflow-y: auto;">
                                                        @foreach($commentsByRequest[$request->id] as $comment)
                                                            <div class="mb-2 p-2 bg-white border rounded shadow-sm" style="font-size: 0.85rem; line-height: 1.3;">
                                                                <div class="mb-1 text-break">
                                                                    {!! nl2br(e($comment->comment)) !!}
                                                                </div>
                                                                <div class="d-flex justify-content-between text-muted border-top pt-1 mt-1" style="font-size: 0.7rem;">
                                                                    <span>{{ \Carbon\Carbon::parse($comment->created_at)->format('d.m.Y H:i') }}</span>
                                                                    <span>{{ $comment->author_name }}</span>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-muted small fst-italic">Комментариев нет</span>
                                                @endif
                                            </td>

                                            <!-- Статус и Бригада -->
                                            <td>
                                                <div class="d-flex flex-column gap-2">
                                                    <!-- Статус -->
                                                    <span class="badge rounded-pill" 
                                                          style="background-color: {{ $request->status_color }}; color: white; font-weight: normal; font-size: 0.8rem; width: fit-content;">
                                                        {{ $request->status_name }}
                                                    </span>

                                                    <!-- Бригада -->
                                                    <div class="small">
                                                        @if($request->brigade_name)
                                                            <div class="fw-bold">{{ $request->brigade_name }}</div>
                                                            <div class="text-muted" style="font-size: 0.75rem;">{{ $request->brigade_lead }}</div>
                                                        @else
                                                            <span class="text-muted fst-italic">Бригада не назначена</span>
                                                        @endif
                                                    </div>

                                                    <!-- Оператор -->
                                                    @if($request->operator_name)
                                                        <div class="text-muted border-top pt-1" style="font-size: 0.7rem;">
                                                            Оп: {{ $request->operator_name }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 bg-light border-top">
                            <small class="text-muted">Всего заявок: {{ $requests->count() }}</small>
                        </div>
                    @else
                        <div class="p-4 text-center">
                            <div class="alert alert-info d-inline-block">
                                <i class="bi bi-info-circle me-2"></i>История заявок по этому адресу пуста.
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Небольшие стили для улучшения читаемости на мобильных */
    @media (max-width: 768px) {
        .table thead {
            display: none;
        }
        .table tr {
            display: block;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 1rem;
            background: #fff;
        }
        .table td {
            display: block;
            text-align: right;
            border: none;
            padding: 0.5rem;
            position: relative;
        }
        .table td::before {
            content: attr(data-label);
            float: left;
            font-weight: bold;
            color: #6c757d;
        }
        /* Специфично для комментариев на мобильном */
        .table td:nth-child(4) { 
            text-align: left;
        }
    }
</style>
@endsection