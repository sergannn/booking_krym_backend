<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Электронный билет</title>
    <style>
        @page { 
            margin: 10px;
            size: A4 portrait;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'dejavu sans', sans-serif;
            font-size: 8pt;
            line-height: 1.4;
            color: #1f2937;
            background: #f5f7fb;
            margin: 0;
            padding: 0;
            /* тонкий фон: светлый паттерн точек */
            background-image:
                radial-gradient(#e5edfb 1px, transparent 0),
                radial-gradient(#e5edfb 1px, transparent 0);
            background-size: 15px 15px;
            background-position: 0 0, 7.5px 7.5px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
            /* Ограничиваем контент верхней четвертью страницы A4 */
            /* A4 высота = 297mm = 842 points, четверть = 210.5 points */
           /* max-height: 210.5pt;
            overflow: hidden;*/
            position: relative;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }
        .brand {
            font-size: 7pt;
            letter-spacing: 0.4px;
            font-weight: 700;
            color: #2563eb;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .title {
            font-size: 14pt;
            font-weight: 700;
            margin: 0;
            color: #0f172a;
        }
        .subtitle {
            font-size: 7pt;
            color: #4b5563;
            margin-top: 2px;
        }
        .ticket-meta {
            text-align: right;
        }
        .ticket-label {
            font-size: 6pt;
            color: black;
            margin-bottom: 2px;
        }
        .ticket-number {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 6px;
            padding: 4px 8px;
            font-weight: 700;
            font-size: 7pt;
            border: 1px solid #bfdbfe;
            margin-bottom: 4px;
        }
        .chip {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 6pt;
            border: 1px solid #e2e8f0;
        }
        .divider {
            height: 1px;
            background: linear-gradient(90deg, #e5e7eb, #cbd5e1, #e5e7eb);
            margin: 8px 0;
        }
        .section-title {
            font-size: 9pt;
            font-weight: 700;
            color: #111827;
            margin: 6px 0 3px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .info-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .info-label {
            color: black;
            width: 32%;
            font-size: 7pt;
        }
        .info-value {
            font-weight: 600;
            color: #0f172a;
            font-size: 7pt;
        }
        .passengers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
        }
        .passengers-table th {
            text-align: left;
            font-size: 7pt;
            color: black;
            font-weight: 700;
            padding: 4px 6px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .passengers-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 7pt;
        }
        .passengers-table tr:nth-child(even) {
            background: #f9fafb;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 6pt;
            font-weight: 600;
            color: #065f46;
            background: #ecfdf3;
            border: 1px solid #bbf7d0;
        }
        .badge-muted {
            color: #6b7280;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }
        .summary {
            margin-top: 4px;
            padding: 6px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            color: black;
            font-size: 7pt;
        }
        .total {
            display: flex;
            justify-content: space-between;
            font-size: 9pt;
            font-weight: 700;
            color: #0f172a;
            margin-top: 4px;
        }
        .footer {
            margin-top: 8px;
            font-size: 6pt;
            color: black;
        }
        .passengers-table td {
    padding: 5px 6px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 7pt;
    color: #111827; /* ДОБАВЬТЕ ЭТУ СТРОКУ - темно-серый/черный */
}

/* Для уверенности добавьте и для заголовков: */
.passengers-table th {
    text-align: left;
    font-size: 7pt;
    color: #111827; /* уже есть black, но можно темнее */
    font-weight: 700;
    padding: 4px 6px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div>
                <div class="brand">Электронный билет</div>
                <div class="title">{{ $excursion->title }}</div>
                <div class="subtitle">Дата и время: {{ $excursionDateTime }}</div>
                <div class="subtitle">Остановка: {{ $stop->name }}</div>
            </div>
            <div class="ticket-meta">
                <div class="ticket-label">Номер билета</div>
                <div class="ticket-number">{{ $ticketNumber }}</div>
                <div class="chip">Создан: {{ $createdAt }}</div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="section">
            <div class="section-title">Детали поездки:</div>
            <table class="info-table">
                <tr>
                    <td class="info-label">Дата и время</td>
                    <td class="info-value">{{ $excursionDateTime }}</td>
                </tr>
                <tr>
                    <td class="info-label">Остановка</td>
                    <td class="info-value">{{ $stop->name }}</td>
                </tr>
                <tr>
                    <td class="info-label">Покупатель</td>
                    <td class="info-value">{{ $customerName }} • {{ $customerPhone }}</td>
                </tr>
                <tr>
                    <td class="info-label">Организатор экскурсии</td>
                    <td class="info-value">{{ $bookedBy }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title"> Пассажиры ({{ count($passengers) }})</div>
            <table class="passengers-table">
                <thead>
                    <tr>
                        <th>Место</th>
                        <th>Пассажир</th>
                        <th>Категория</th>
                        <th>Входной билет</th>
                        <th style="text-align: right;">Цена</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($passengers as $passenger)
                    <tr>
                        <td>№{{ $passenger['seat_number'] }}</td>
                        <td>
                            @if(!empty($passenger['customer_name']))
                                {{ $passenger['customer_name'] }}
                                @if(!empty($passenger['customer_phone']))
                                    <br><small style="color: #6b7280; font-size: 7pt;">{{ $passenger['customer_phone'] }}</small>
                                @endif
                            @else
                                <span style="color: #9ca3af;">—</span>
                            @endif
                        </td>
                        <td>{{ $passenger['passenger_type'] }}</td>
                        <td>
                            @if(!empty($passenger['with_entry']) && $passenger['with_entry'])
                                <span class="badge">Включен</span>
                            @else
                                <span class="badge badge-muted">Без входного</span>
                            @endif
                        </td>
                        <td style="text-align: right;">{{ number_format($passenger['price'], 2, '.', ' ') }} ₽</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid #e5e7eb;">
                        <td colspan="4" style="text-align: right; font-weight: bold; padding-top: 8px;">Итого к оплате:</td>
                        <td style="text-align: right; font-weight: bold; padding-top: 8px; font-size: 9pt; color: #1d4ed8;">{{ number_format($total, 2, '.', ' ') }} ₽</td>
                    </tr>
                </tfoot>
            </table>
        </div>


        <div class="footer">
            Пожалуйста, предъявите этот билет при посадке. Перенос и отмена возможны не позднее чем за 24 часа до начала экскурсии.
        </div>
    </div>
</body>
</html>


