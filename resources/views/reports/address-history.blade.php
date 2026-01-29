@extends('layouts-history.app')

@section('title', 'Отчеты по адресу')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Заявки по адресу: {{ $address->city_name }}, {{ $address->street }}, {{ $address->houses }}, {{ $address->district }}</h4>
                    <p class="card-subtitle">Все заявки за весь период времени</p>
                </div>
                <div class="card-body">
                    @if($requests->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Номер</th>
                                        <th>Дата исполнения</th>
                                        <th>Время</th>
                                        <th>Клиент</th>
                                        <th>Телефон</th>
                                        <th>Организация</th>
                                        <th>Статус</th>
                                        <th>Бригада</th>
                                        <th>Бригадир</th>
                                        <th>Оператор</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requests as $request)
                                        <tr>
                                            <td>{{ $request->id }}</td>
                                            <td>{{ $request->number }}</td>
                                            <td>{{ $request->execution_date ? \Carbon\Carbon::parse($request->execution_date)->format('d.m.Y') : 'Не указана' }}</td>
                                            <td>{{ $request->execution_time ? \Carbon\Carbon::parse($request->execution_time)->format('H:i') : 'Не указано' }}</td>
                                            <td>{{ $request->client_fio }}</td>
                                            <td>{{ $request->client_phone }}</td>
                                            <td>{{ $request->client_organization }}</td>
                                            <td>
                                                <span class="badge" style="background-color: {{ $request->status_color }}; color: white;">
                                                    {{ $request->status_name }}
                                                </span>
                                            </td>
                                            <td>{{ $request->brigade_name }}</td>
                                            <td>{{ $request->brigade_lead }}</td>
                                            <td>{{ $request->operator_name }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <p>Всего заявок: {{ $requests->count() }}</p>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <h5>Нет заявок по этому адресу</h5>
                            <p>За весь период времени не найдено ни одной заявки по указанному адресу.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
