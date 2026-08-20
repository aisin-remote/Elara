{{-- Satu stylesheet untuk semua dokumen PDF: edisi lengkap dan edisi requester. --}}
    <style>
        @page {
            margin: 22mm 18mm 24mm 18mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
            color: #1e293b;
        }

        h1, h2, h3, h4 {
            color: #0f172a;
            page-break-after: avoid;
        }

        h1 { font-size: 22pt; margin: 0 0 8pt; }
        h2 { font-size: 14pt; margin: 18pt 0 8pt; border-bottom: 2px solid #4f46e5; padding-bottom: 4pt; }
        h3 { font-size: 11pt; margin: 12pt 0 6pt; color: #312e81; }
        h4 { font-size: 10pt; margin: 10pt 0 4pt; }

        p { margin: 0 0 8pt; }

        ul, ol { margin: 0 0 8pt 16pt; padding: 0; }
        li { margin-bottom: 4pt; }

        .cover {
            page-break-after: always;
            text-align: center;
            padding-top: 55mm;
        }

        .cover-badge {
            display: inline-block;
            background: #eef2ff;
            color: #4338ca;
            padding: 6pt 14pt;
            border-radius: 20pt;
            font-size: 9pt;
            font-weight: bold;
            letter-spacing: 0.5pt;
            margin-bottom: 18pt;
        }

        .cover-logo {
            width: 56pt;
            height: 56pt;
            line-height: 56pt;
            border-radius: 14pt;
            background: #6366f1;
            color: #fff;
            font-size: 24pt;
            font-weight: bold;
            margin: 0 auto 14pt;
        }

        .cover-sub {
            font-size: 11pt;
            color: #64748b;
            margin-top: 6pt;
        }

        .cover-meta {
            margin-top: 40mm;
            font-size: 9pt;
            color: #64748b;
        }

        .toc {
            page-break-after: always;
        }

        .toc table { width: 100%; border-collapse: collapse; }
        .toc td { padding: 4pt 0; vertical-align: top; border-bottom: 1px dotted #cbd5e1; }
        .toc-num { width: 28pt; color: #6366f1; font-weight: bold; }

        .page-break { page-break-before: always; }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin: 8pt 0 12pt;
            font-size: 9pt;
        }

        table.data th {
            background: #eef2ff;
            color: #312e81;
            text-align: left;
            padding: 6pt 8pt;
            border: 1px solid #c7d2fe;
        }

        table.data td {
            padding: 5pt 8pt;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }

        table.data tr:nth-child(even) td { background: #f8fafc; }

        .flow {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #6366f1;
            padding: 10pt 12pt;
            margin: 8pt 0 12pt;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9pt;
            line-height: 1.6;
            white-space: pre-line;
        }

        .note {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            padding: 8pt 10pt;
            margin: 8pt 0 12pt;
            font-size: 9pt;
        }

        .confirm {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            padding: 8pt 10pt;
            margin: 8pt 0 12pt;
            font-size: 9pt;
        }

        .ok { color: #059669; font-weight: bold; }
        .no { color: #94a3b8; }

        .header-line {
            font-size: 8pt;
            color: #94a3b8;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4pt;
            margin-bottom: 10pt;
        }

        .section-num { color: #6366f1; font-weight: bold; }

        .two-col { width: 100%; }
        .two-col td { width: 50%; vertical-align: top; padding-right: 8pt; }
    </style>
