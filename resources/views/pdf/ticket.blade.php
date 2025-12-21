<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Электронный билет</title>
    <style>
        @page { margin: 28px; }
        * { box-sizing: border-box; }
        body {
            font-family: 'dejavu sans', sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #1f2937;
            background: #f5f7fb;
            /* тонкий фон: светлый паттерн точек */
            background-image:
                radial-gradient(#e5edfb 1px, transparent 0),
                radial-gradient(#e5edfb 1px, transparent 0);
            background-size: 22px 22px;
            background-position: 0 0, 11px 11px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px 28px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }
        .brand {
            font-size: 11pt;
            letter-spacing: 0.6px;
            font-weight: 700;
            color: #2563eb;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .title {
            font-size: 22pt;
            font-weight: 700;
            margin: 0;
            color: #0f172a;
        }
        .subtitle {
            font-size: 11pt;
            color: #4b5563;
            margin-top: 4px;
        }
        .ticket-meta {
            text-align: right;
        }
        .ticket-label {
            font-size: 10pt;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .ticket-number {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 10px;
            padding: 8px 12px;
            font-weight: 700;
            border: 1px solid #bfdbfe;
            margin-bottom: 8px;
        }
        .chip {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 10pt;
            border: 1px solid #e2e8f0;
        }
        .divider {
            height: 1px;
            background: linear-gradient(90deg, #e5e7eb, #cbd5e1, #e5e7eb);
            margin: 18px 0;
        }
        .section-title {
            font-size: 14pt;
            font-weight: 700;
            color: #111827;
            margin: 12px 0 6px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .info-table td {
            padding: 6px 0;
            vertical-align: top;
        }
        .info-label {
            color: #6b7280;
            width: 32%;
        }
        .info-value {
            font-weight: 600;
            color: #0f172a;
        }
        .passengers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .passengers-table th {
            text-align: left;
            font-size: 11pt;
            color: #475569;
            font-weight: 700;
            padding: 8px 10px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .passengers-table td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11pt;
        }
        .passengers-table tr:nth-child(even) {
            background: #f9fafb;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 10pt;
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
            margin-top: 8px;
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            color: #4b5563;
        }
        .total {
            display: flex;
            justify-content: space-between;
            font-size: 14pt;
            font-weight: 700;
            color: #0f172a;
            margin-top: 8px;
        }
        .footer {
            margin-top: 16px;
            font-size: 10pt;
            color: #6b7280;
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
            <div class="section-title">Детали поездки</div>
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
            <div class="section-title">Пассажиры</div>
            <table class="passengers-table">
                <thead>
                    <tr>
                        <th>Место</th>
                        <th>Категория</th>
                        <th>Входной билет</th>
                        <th style="text-align: right;">Цена</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($passengers as $passenger)
                    <tr>
                        <td>№{{ $passenger['seat_number'] }}</td>
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
            </table>
        </div>

        <div class="section">
            <div class="section-title">Оплата</div>
            <div class="summary">
                <div class="summary-row">
                    <span>Количество мест</span>
                    <span>{{ count($passengers) }}</span>
                </div>
                <div class="total">
                    <span>Итого к оплате</span>
                    <span>{{ number_format($total, 2, '.', ' ') }} ₽</span>
                </div>
            </div>
        </div>

        <div class="footer">
            Пожалуйста, предъявите этот билет при посадке. Перенос и отмена возможны не позднее чем за 24 часа до начала экскурсии.
        </div>
    </div>
</body>
</html>


