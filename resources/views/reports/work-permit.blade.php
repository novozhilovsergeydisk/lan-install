<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Наряд-допуск</title>
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 14px;
            margin: 0;
            padding: 20px;
            background-color: #f0f0f0;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
            border: 1px solid #D3D3D3;
            background: white;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
                background-color: white;
            }
            .page {
                margin: 0;
                border: initial;
                border-radius: initial;
                width: initial;
                min-height: initial;
                box-shadow: initial;
                background: initial;
                page-break-after: always;
            }
            .page:last-child {
                page-break-after: auto;
            }
        }
        
        h2 {
            text-align: left;
            font-size: 18px;
            margin-bottom: 20px;
            margin-top: 0;
            text-transform: uppercase;
        }

        /* Обертка для двойной рамки */
        .table-wrapper {
            border: 1px double #000;
            padding: 2px;
            margin-bottom: 30px;
        }

        /* Стили для основной таблицы */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            border-style: hidden; /* Скрываем собственные границы таблицы */
            margin-bottom: 0;
        }
        .info-table td {
            border: 1px solid #000;
            padding: 2px 5px;
            vertical-align: top;
        }
        
        /* Убираем внешние границы ячеек, чтобы работала рамка обертки */
        .info-table tr:first-child td { border-top: none; }
        .info-table tr:last-child td { border-bottom: none; }
        .info-table td:first-child { border-left: none; }
        .info-table td:last-child { border-right: none; }

        .info-table .label-row {
            font-weight: bold;
            background-color: #f9f9f9;
            text-align: left;
        }
        .info-table .value-row {
            padding-bottom: 15px; /* Немного воздуха после контента */
        }

        /* Стили для подписей */
        .sign-label {
            text-align: center;
            font-size: 10px;
            margin-top: 15px;
            border-top: 1px solid #ccc; /* Тонкая линия внутри ячейки для места подписи */
            color: #555;
        }

        .ul-members {
            list-style-type: none;
            padding-left: 0;
            margin: 0;
        }
        .ul-members li {
            margin-bottom: 3px;
        }
    </style>
</head>
<body>
    @foreach($groupedRequests as $brigadeData)
    <div class="page">
        <h2>НАРЯД-ДОПУСК № {{ implode(', ', array_column($brigadeData['requests'], 'number')) }} ДАТА {{ implode(', ', array_keys($brigadeData['dates'])) }}</h2>

        <div class="table-wrapper">
            <table class="info-table">
                <!-- Местонахождение и Заказчик -->
                <tr>
                    <td class="label-row" colspan="4">Местонахождение объекта, на котором поручается выполнение работ</td>
                </tr>
                <tr>
                    <td class="value-row" colspan="4">
                        <ol style="margin: 0; padding-left: 20px;">
                        @foreach($brigadeData['requests'] as $req)
                            <li style="margin-bottom: 5px;">
                                {{ $req->city_name }}, {{ $req->street }} {{ $req->houses }} {{ $req->district ? '(' . $req->district . ')' : '' }}<br>
                                {{ $req->client_organization }}{{ $req->client_fio ? ', (' . $req->client_fio . ')' : '' }}{{ $req->client_phone ? ', тел. ' . $req->client_phone : '' }}
                            </li>
                        @endforeach
                        </ol>
                    </td>
                </tr>

                <!-- Работы -->
                <tr>
                    <td class="label-row" colspan="4">Наименование работ, подлежащих выполнению на объекте / основание</td>
                </tr>
                <tr>
                    <td class="value-row" colspan="4">
                        <ol style="margin: 0; padding-left: 20px;">
                        @foreach($brigadeData['requests'] as $req)
                            <li style="margin-bottom: 5px;">
                                {!! $req->first_comment !!}
                            </li>
                        @endforeach
                        </ol>
                    </td>
                </tr>

                <!-- Сотрудники -->
                <tr>
                    <td class="label-row" colspan="4">Перечень сотрудников, уполномоченных на выполнение работ</td>
                </tr>
                <tr>
                    <td class="value-row" colspan="4">
                        @if(count($brigadeData['brigade_members']) > 0)
                            <ul class="ul-members">
                                @foreach($brigadeData['brigade_members'] as $index => $member)
                                    <li>{{ $index + 1 }}. {{ $member->fio }} {{ $member->group_role ? '— ' . $member->group_role : '' }}</li>
                                @endforeach
                            </ul>
                        @else
                            Нет назначенных сотрудников.
                        @endif
                    </td>
                </tr>
            <!-- Подписи -->
                <tr>
                    <!-- Ответственный производитель работ -->
                    <td style="width: 35%;">
                        {{ $brigadeData['brigade_leader_fio'] ?? '____________________' }}
                        <div style="font-size: 11px;">ответственный производитель работ</div>
                    </td>
                    <td style="width: 15%;">
                        <div style="height: 20px;"></div>
                        <div class="sign-label">(подпись)</div>
                    </td>

                    <!-- Ответственный за оформление -->
                    <td style="width: 35%;">
                        {{ $issuerFio }}
                        <div style="font-size: 11px;">ответственный за оформление нарядов</div>
                    </td>
                    <td style="width: 15%;">
                        <div style="height: 20px;"></div>
                        <div class="sign-label">(подпись)</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    @endforeach

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.focus();
                window.print();
            }, 500);
        }
    </script>
</body>
</html>