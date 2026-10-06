{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: The one shared Official Report layout — used as both the
    on-screen preview (Pto\MunicipalReportsController::officialReport() /
    Pto\MonthlyReportsController::officialReport()) and the server-rendered
    PDF (same markup, through Barryvdh\DomPDF). A different letterhead is
    the only thing LGU/PTO/provincial callers vary — everything else
    (columns, subtotal/grand-total rows, signature block, watermark,
    pagination) lives here once. $report is the array shape built by
    App\Support\OfficialReportBuilder plus the controller's own letterhead/
    reference fields — see Pto\MunicipalReportsController::officialReportData().
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }} — {{ $report['period_label'] }}</title>
    <style>
        @page {
            margin: 18mm 14mm 20mm 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", Helvetica, Arial, sans-serif;
            font-size: 10.5pt;
            color: #000;
            background: #fff;
            -webkit-print-color-adjust: exact;
        }

        .letterhead {
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .letterhead table {
            width: 100%;
            border-collapse: collapse;
        }

        .letterhead .logo-slot {
            width: 60px;
            height: 60px;
            border: 1px dashed #999;
            text-align: center;
            vertical-align: middle;
            font-size: 7pt;
            color: #999;
        }

        .letterhead .office-name {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .letterhead .office-subtitle {
            font-size: 9.5pt;
        }

        .report-title {
            text-align: center;
            margin: 10px 0 4px;
        }

        .report-title h1 {
            font-size: 13pt;
            margin: 0;
            text-transform: uppercase;
        }

        .report-title p {
            margin: 2px 0 0;
            font-size: 10pt;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 9.5pt;
        }

        .meta-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .meta-table .meta-label {
            font-weight: bold;
            width: 140px;
        }

        .revision-note {
            margin: 6px 0;
            padding: 5px 8px;
            border: 1px solid #000;
            font-size: 9pt;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-top: 6px;
        }

        table.report-table thead {
            display: table-header-group;
        }

        table.report-table tr {
            page-break-inside: avoid;
        }

        table.report-table th,
        table.report-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            text-align: center;
        }

        table.report-table th {
            background: #e5e5e5;
            font-weight: bold;
        }

        table.report-table td.establishment-name {
            text-align: left;
        }

        tr.category-row td {
            background: #f0f0f0;
            font-weight: bold;
            text-align: left;
        }

        tr.subtotal-row td {
            font-weight: bold;
            background: #fafafa;
        }

        tr.grand-total-row td {
            font-weight: bold;
            background: #d9d9d9;
            font-size: 9.5pt;
        }

        .remarks-box {
            margin-top: 14px;
            border: 1px solid #000;
            padding: 6px 8px;
            min-height: 40px;
            font-size: 9.5pt;
        }

        .remarks-box .remarks-label {
            font-weight: bold;
            font-size: 8.5pt;
            text-transform: uppercase;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 26px;
        }

        .signature-table td {
            width: 33.33%;
            padding: 0 10px;
            vertical-align: top;
            text-align: center;
            font-size: 9.5pt;
        }

        .signature-table .signature-line {
            border-top: 1px solid #000;
            margin-top: 34px;
            padding-top: 3px;
            font-weight: bold;
        }

        .signature-table .signature-position {
            font-size: 8.5pt;
        }

        .footer-strip {
            position: fixed;
            bottom: -14mm;
            left: 0;
            right: 0;
            border-top: 1px solid #000;
            padding-top: 4px;
            font-size: 8pt;
            display: flex;
        }

        .verification-code {
            font-family: "DejaVu Sans Mono", monospace;
        }

        .page-number:after {
            content: "Page " counter(page) " of " counter(pages);
        }

        .watermark {
            position: fixed;
            top: 40%;
            left: 15%;
            width: 70%;
            text-align: center;
            font-size: 72pt;
            font-weight: bold;
            color: #000;
            opacity: 0.08;
            transform: rotate(-30deg);
            z-index: -1;
        }

        @media screen {
            body {
                max-width: 900px;
                margin: 20px auto;
                padding: 24px;
                border: 1px solid #ccc;
                box-shadow: 0 0 8px rgba(0, 0, 0, 0.08);
            }
        }

        .preview-toolbar {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: -24px -24px 20px;
            padding: 12px;
            background: #1f1f1f;
        }

        .preview-toolbar button,
        .preview-toolbar a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #555;
            background: #2d2d2d;
            color: #fff;
            font-family: system-ui, sans-serif;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }

        .preview-toolbar button:hover,
        .preview-toolbar a:hover {
            background: #3d3d3d;
        }

        @media print {
            .preview-toolbar {
                display: none;
            }
        }
    </style>
</head>
<body>
    @if ($preview ?? false)
        <div class="preview-toolbar">
            <button type="button" onclick="window.print()">Print</button>
            <a href="{{ $pdfUrl }}">Download PDF</a>
            <a href="{{ $excelUrl }}">Download Excel</a>
        </div>
    @endif

    @if ($report['is_draft'])
        <div class="watermark">DRAFT</div>
    @endif

    <div class="letterhead">
        <table>
            <tr>
                <td class="logo-slot">Logo</td>
                <td style="padding-left: 10px;">
                    <div class="office-name">{{ $report['letterhead']['office_name'] }}</div>
                    <div class="office-subtitle">{{ $report['letterhead']['office_subtitle'] }}</div>
                    <div class="office-subtitle">{{ $report['letterhead']['address'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="report-title">
        <h1>{{ $report['title'] }}</h1>
        <p>{{ $report['period_label'] }}</p>
    </div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Reference No.</td>
            <td>{{ $report['reference_number'] }}</td>
            <td class="meta-label">Status</td>
            <td>{{ $report['status_label'] }}@if ($report['verified_label']) — {{ $report['verified_label'] }}@endif</td>
        </tr>
        <tr>
            <td class="meta-label">Submissions</td>
            <td>{{ $report['submission_summary'] }}</td>
            <td class="meta-label">Revision</td>
            <td>
                No. {{ $report['revision_number'] }}
                @if ($report['supersedes_reference'])
                    (supersedes {{ $report['supersedes_reference'] }})
                @endif
            </td>
        </tr>
    </table>

    @if ($report['supersedes_reference'])
        <div class="revision-note">
            This is revision {{ $report['revision_number'] }} of this report. It supersedes {{ $report['supersedes_reference'] }}, whose own record remains unchanged.
        </div>
    @endif

    <table class="report-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 22%;">Establishment</th>
                <th colspan="3">Visitors</th>
                <th colspan="3">Age Group</th>
                <th colspan="2">Classification</th>
            </tr>
            <tr>
                <th>Male</th>
                <th>Female</th>
                <th>Total</th>
                <th>Adults</th>
                <th>Children</th>
                <th>Seniors</th>
                <th>Local</th>
                <th>Foreign</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['groups'] as $category => $group)
                <tr class="category-row">
                    <td colspan="9">{{ $category }}</td>
                </tr>
                @foreach ($group['rows'] as $row)
                    <tr>
                        <td class="establishment-name">{{ $row['establishment'] }}</td>
                        <td>{{ $row['male'] }}</td>
                        <td>{{ $row['female'] }}</td>
                        <td>{{ $row['total'] }}</td>
                        <td>{{ $row['adults'] }}</td>
                        <td>{{ $row['children'] }}</td>
                        <td>{{ $row['seniors'] }}</td>
                        <td>{{ $row['local'] }}</td>
                        <td>{{ $row['foreign'] }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal-row">
                    <td class="establishment-name">Subtotal — {{ $category }}</td>
                    <td>{{ $group['subtotal']['male'] }}</td>
                    <td>{{ $group['subtotal']['female'] }}</td>
                    <td>{{ $group['subtotal']['total'] }}</td>
                    <td>{{ $group['subtotal']['adults'] }}</td>
                    <td>{{ $group['subtotal']['children'] }}</td>
                    <td>{{ $group['subtotal']['seniors'] }}</td>
                    <td>{{ $group['subtotal']['local'] }}</td>
                    <td>{{ $group['subtotal']['foreign'] }}</td>
                </tr>
            @endforeach
            <tr class="grand-total-row">
                <td class="establishment-name">GRAND TOTAL</td>
                <td>{{ $report['grand_total']['male'] }}</td>
                <td>{{ $report['grand_total']['female'] }}</td>
                <td>{{ $report['grand_total']['total'] }}</td>
                <td>{{ $report['grand_total']['adults'] }}</td>
                <td>{{ $report['grand_total']['children'] }}</td>
                <td>{{ $report['grand_total']['seniors'] }}</td>
                <td>{{ $report['grand_total']['local'] }}</td>
                <td>{{ $report['grand_total']['foreign'] }}</td>
            </tr>
        </tbody>
    </table>

    <div class="remarks-box">
        <div class="remarks-label">Remarks</div>
        <div>{{ $report['remarks'] ?: '—' }}</div>
    </div>

    <table class="signature-table">
        <tr>
            @foreach (['prepared_by' => 'Prepared by', 'reviewed_by' => 'Reviewed by', 'approved_by' => 'Approved by'] as $key => $label)
                <td>
                    <div class="signature-line">{{ $report['signatures'][$key]['name'] ?? '—' }}</div>
                    <div class="signature-position">{{ $report['signatures'][$key]['position'] ?? $label }}</div>
                    <div class="signature-position">{{ $report['signatures'][$key]['date'] ?? '' }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="footer-strip">
        <span style="flex: 1;">Verification code: <span class="verification-code">{{ $report['verification_code'] ?? 'N/A' }}</span></span>
        <span style="flex: 1; text-align: center;">Generated {{ $report['generated_at'] }}</span>
        <span class="page-number" style="flex: 1; text-align: right;"></span>
    </div>
</body>
</html>
