<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        @page {
            size: 9.5in 5.5in;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e2e8f0;
            color: #000;
            font-family: "Courier New", Courier, monospace;
            font-size: 11px;
        }

        .preview-toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 14px 24px;
            background: #fff;
            border-bottom: 1px solid #cbd5e1;
            font-family: Arial, sans-serif;
        }

        .preview-toolbar__actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .preview-toolbar p {
            margin: 3px 0 0;
            color: #475569;
            font-size: 12px;
        }

        .button {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            padding: 0 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .button--primary {
            border-color: #0369a1;
            background: #0369a1;
            color: #fff;
        }

        .preview-area {
            overflow: auto;
            padding: 28px;
        }

        .document {
            width: 9.5in;
            min-height: 5.5in;
            margin: 0 auto;
            padding: .22in .3in;
            background: #fff;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .18);
        }

        .document-header {
            display: grid;
            grid-template-columns: 1.25fr .75fr;
            gap: 20px;
            align-items: start;
            padding-bottom: 7px;
            border-bottom: 2px solid #000;
        }

        .company-name {
            margin: 0;
            font-family: Arial, sans-serif;
            font-size: 16px;
            font-weight: 800;
        }

        .company-brand {
            margin: 2px 0 0;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
        }

        .document-title {
            margin: 0;
            text-align: right;
            font-family: Arial, sans-serif;
            font-size: 17px;
            font-weight: 800;
        }

        .document-number {
            margin: 3px 0 0;
            text-align: right;
            font-size: 12px;
            font-weight: 700;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-top: 7px;
        }

        .meta-row {
            display: grid;
            grid-template-columns: 92px 8px 1fr;
            line-height: 1.45;
        }

        .meta-row strong {
            overflow-wrap: anywhere;
        }

        table {
            width: 100%;
            margin-top: 7px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 4px 5px;
            border: 1px solid #000;
            vertical-align: top;
        }

        th {
            font-weight: 700;
            text-align: left;
        }

        .number {
            text-align: right;
            white-space: nowrap;
        }

        .center {
            text-align: center;
        }

        .summary {
            display: grid;
            grid-template-columns: 1fr 250px;
            gap: 18px;
            margin-top: 6px;
        }

        .notes {
            min-height: 34px;
            padding: 5px;
            border: 1px solid #000;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 3px 5px;
            border-bottom: 1px solid #000;
        }

        .total-row--grand {
            border: 1px solid #000;
            font-weight: 700;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
            margin-top: 9px;
            text-align: center;
        }

        .signature-line {
            padding-top: 25px;
            border-bottom: 1px solid #000;
        }

        @media print {
            html,
            body {
                width: 9.5in;
                height: 5.5in;
                background: #fff;
            }

            .preview-toolbar {
                display: none;
            }

            .preview-area {
                overflow: visible;
                padding: 0;
            }

            .document {
                width: 9.5in;
                min-height: 5.5in;
                margin: 0;
                box-shadow: none;
                break-after: page;
            }
        }
    </style>
</head>
<body>
    <div class="preview-toolbar">
        <div>
            <strong>Preview dokumen</strong>
            <p>Gunakan kertas 9,5 × 5,5 inci, skala 100%, margin none. Cetak satu kali untuk kertas NCR 3 rangkap.</p>
        </div>
        <div class="preview-toolbar__actions">
            <a class="button" href="@yield('back-url')">Kembali</a>
            <button class="button button--primary" type="button" onclick="window.print()">@yield('print-label')</button>
        </div>
    </div>

    <main class="preview-area">
        <article class="document">
            @yield('document')
        </article>
    </main>
</body>
</html>
