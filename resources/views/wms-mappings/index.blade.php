@extends('layouts.app')

@section('content')
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h3 mb-0 text-gray-800">Привязка складов WMS к типам заявок</h2>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Добавить или обновить привязку</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('wms-mappings.store') }}" method="POST">
                @csrf
                <div class="row align-items-end g-3">
                    <div class="col-md-4">
                        <label for="request_type_id" class="form-label small fw-bold">Тип заявки</label>
                        <select name="request_type_id" id="request_type_id" class="form-select" required>
                            <option value="" disabled selected>-- Выберите тип заявки --</option>
                            @foreach ($requestTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="wms_warehouse_id" class="form-label small fw-bold">Склад WMS</label>
                        <select name="wms_warehouse_id" id="wms_warehouse_id" class="form-select" required>
                            <option value="" disabled selected>-- Выберите склад WMS --</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse['id'] }}">{{ $warehouse['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-1"></i> Сохранить привязку
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Текущие привязки</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped text-center align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">Тип заявки</th>
                            <th style="width: 40%;">Склад WMS</th>
                            <th style="width: 20%;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mappings as $mapping)
                            <tr>
                                <td class="text-start">{{ $mapping->type_name }}</td>
                                <td class="text-start">{{ $mapping->warehouse_name }}</td>
                                <td>
                                    <form action="{{ route('wms-mappings.destroy', $mapping->id) }}" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить эту привязку?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash"></i> Удалить
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Нет активных привязок складов.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
