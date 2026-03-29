@extends('layouts.app')

@php
    use App\Helpers\StringHelper;
@endphp

@section('title', 'Отчеты по адресу')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h4 class="card-title mb-0">Заявки по адресу: {{ $address->city_name }}, {{ $address->street }}, {{ $address->houses }}</h4>
                    @if($address->district) <p class="card-subtitle mb-0"><small class="text-muted">({{ $address->district }})</small></p> @endif
                </div>
                <div class="card-body p-0">
                    @if($requests->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 200px;">Клиент</th>
                                        <th style="min-width: 300px;">Инфо / Комментарии</th>
                                        <th style="width: 180px;">Статус / Бригада</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requests as $request)
                                        <tr>
                                            <!-- Данные клиента -->
                                            <td>
                                                <div class="d-flex flex-column">
                                                    @if($request->client_organization)
                                                        <span class="fw-bold text-primary">{{ $request->client_organization }}</span>
                                                    @endif
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
                                                @php
                                                    $typeColor = $request->request_type_color;
                                                    $typeName = $request->request_type_name;
                                                    $contrastColor = $typeColor ? StringHelper::getContrastColor($typeColor) : '#000000';
                                                    $executionDate = $request->execution_date ? \Carbon\Carbon::parse($request->execution_date)->format('d.m.Y') : 'Нет даты';
                                                @endphp

                                                <div class="p-1 rounded-top" @if($typeColor) style="background-color: {{ $typeColor }}; color: {{ $contrastColor }}; font-size: 0.8rem;" @else style="background-color: #f8f9fa; color: #000; font-size: 0.8rem; border: 1px solid #dee2e6; border-bottom: 0;" @endif>
                                                    {{ $executionDate }} | {{ $request->number }} @if($typeName) <span class="ms-1">[{{ $typeName }}]</span> @endif
                                                </div>

                                                <div class="p-2 border rounded-bottom" style="@if($typeColor) border: 5px solid {{ $typeColor }}; border-top: 0px; @else border: 1px solid #dee2e6; border-top: 0px; @endif">
                                                    @if(isset($comments_by_request[$request->id]) && count($comments_by_request[$request->id]) > 0)
                                                        <div style="max-height: 250px; overflow-y: auto;">
                                                            @foreach($comments_by_request[$request->id] as $comment)
                                                                <div class="mb-2 p-2 bg-white border rounded shadow-sm" style="font-size: 0.85rem; line-height: 1.3;">
                                                                    <div class="mb-1 text-break">
                                                                        {!! $comment->comment !!}
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
                                                </div>
                                            </td>

                                            <!-- Статус и Бригада -->
                                            <td>
                                                <div class="d-flex flex-column gap-2">
                                                    <span class="badge rounded-pill" 
                                                          style="background-color: {{ $request->status_color }}; color: white; font-weight: normal; font-size: 0.8rem; width: fit-content;">
                                                        {{ $request->status_name }}
                                                    </span>
                                                    <div class="small">
                                                        <i>{{ $request->brigade_name }}</i><br>
                                                        <strong>{{ $request->brigade_lead }}</strong>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3">
                            <p class="mb-0">Всего заявок: {{ $requests->count() }}</p>
                        </div>
                    @else
                        <div class="p-4 text-center">
                            <h5>Нет заявок по этому адресу</h5>
                            <p class="text-muted">За весь период времени не найдено ни одной заявки по указанному адресу.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection