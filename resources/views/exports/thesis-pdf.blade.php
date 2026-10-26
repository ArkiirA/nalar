<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $project->title }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin-top: {{ $margins['top'] }};
            margin-left: {{ $margins['left'] }};
            margin-bottom: {{ $margins['bottom'] }};
            margin-right: {{ $margins['right'] }};
            @bottom-right {
                content: counter(page);
                font-family: '{{ $formatting['font_family'] }}', 'Times New Roman', Times, serif;
                font-size: 10pt;
            }
        }

        body {
            font-family: '{{ $formatting['font_family'] }}', 'Times New Roman', Times, serif;
            font-size: {{ $formatting['font_size'] }};
            line-height: {{ $formatting['line_spacing'] }};
            color: #111827;
            text-align: justify;
            margin: 0;
            padding: 0;
        }

        .page-break {
            page-break-before: always;
            break-before: page;
        }

        .frontmatter-title {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin-top: 0;
            margin-bottom: 24pt;
        }

        .toc-list, .lot-list, .lof-list {
            margin-bottom: 24pt;
        }

        .toc-item {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 6pt;
            line-height: 1.3;
        }

        .toc-level-1 {
            font-weight: bold;
            margin-top: 8pt;
        }

        .toc-level-2 {
            padding-left: 1.5rem;
        }

        .toc-level-3 {
            padding-left: 3rem;
            font-style: italic;
        }

        .toc-label {
            flex-shrink: 0;
            max-width: 80%;
        }

        .toc-dots {
            flex-grow: 1;
            border-bottom: 1px dotted #6b7280;
            margin: 0 6px;
            height: 1em;
        }

        .toc-page {
            flex-shrink: 0;
            text-align: right;
            min-width: 2rem;
            font-variant-numeric: tabular-nums;
        }

        .chapter-heading-1 {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin-top: 0;
            margin-bottom: 18pt;
            line-height: 1.4;
        }

        .chapter-heading-2 {
            font-size: 12pt;
            font-weight: bold;
            text-align: left;
            margin-top: 18pt;
            margin-bottom: 8pt;
            line-height: 1.3;
        }

        .chapter-heading-3 {
            font-size: 12pt;
            font-weight: bold;
            font-style: italic;
            text-align: left;
            margin-top: 14pt;
            margin-bottom: 6pt;
            line-height: 1.3;
        }

        p.thesis-p {
            text-indent: 1cm;
            margin-top: 0;
            margin-bottom: 8pt;
            text-align: justify;
            line-height: {{ $formatting['line_spacing'] }};
        }

        table.thesis-table {
            width: 100%;
            border-collapse: collapse;
            margin: 14pt 0;
            font-size: 11pt;
        }

        table.thesis-table th, table.thesis-table td {
            border: 1px solid #1f2937;
            padding: 6pt 8pt;
            text-align: left;
            vertical-align: top;
        }

        table.thesis-table th {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        .figure-caption, .table-caption {
            font-size: 11pt;
            font-style: italic;
            text-align: center;
            margin-top: 6pt;
            margin-bottom: 14pt;
            line-height: 1.3;
        }

        .bibliography-entry {
            padding-left: 1.27cm;
            text-indent: -1.27cm;
            margin-bottom: 10pt;
            text-align: justify;
            line-height: {{ $formatting['line_spacing'] }};
        }

        ul, ol {
            margin-top: 4pt;
            margin-bottom: 8pt;
            padding-left: 2rem;
        }

        li {
            margin-bottom: 4pt;
            line-height: {{ $formatting['line_spacing'] }};
        }
    </style>
</head>
<body>

    {{-- Front Matter: Daftar Isi --}}
    <div class="frontmatter-section" id="section-daftar-isi">
        <h1 class="frontmatter-title">DAFTAR ISI</h1>
        <div class="toc-list">
            @foreach($toc as $item)
                <div class="toc-item toc-level-{{ min($item['level'], 3) }}">
                    <span class="toc-label">{{ $item['title'] }}</span>
                    <span class="toc-dots"></span>
                    <span class="toc-page">{{ $item['page'] ?? '' }}</span>
                </div>
            @endforeach
            @if(count($bibliography) > 0)
                <div class="toc-item toc-level-1">
                    <span class="toc-label">DAFTAR PUSTAKA</span>
                    <span class="toc-dots"></span>
                    <span class="toc-page">{{ $bibliographyPage ?? '' }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Front Matter: Daftar Tabel (if any tables present) --}}
    @if(count($tables) > 0)
        <div class="page-break"></div>
        <div class="frontmatter-section" id="section-daftar-tabel">
            <h1 class="frontmatter-title">DAFTAR TABEL</h1>
            <div class="lot-list">
                @foreach($tables as $table)
                    <div class="toc-item">
                        <span class="toc-label">{{ $table['title'] }}</span>
                        <span class="toc-dots"></span>
                        <span class="toc-page">{{ $table['page'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Front Matter: Daftar Gambar (if any figures present) --}}
    @if(count($figures) > 0)
        <div class="page-break"></div>
        <div class="frontmatter-section" id="section-daftar-gambar">
            <h1 class="frontmatter-title">DAFTAR GAMBAR</h1>
            <div class="lof-list">
                @foreach($figures as $figure)
                    <div class="toc-item">
                        <span class="toc-label">{{ $figure['title'] }}</span>
                        <span class="toc-dots"></span>
                        <span class="toc-page">{{ $figure['page'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Body Chapters: Page break only on root chapters (level 1) --}}
    @foreach($renderedChapters as $index => $chapter)
        @if($chapter['level'] === 1)
            <div class="page-break"></div>
        @endif
        <div class="chapter-content" id="chapter-{{ $chapter['id'] }}">
            {!! $chapter['html'] !!}
        </div>
    @endforeach

    {{-- Daftar Pustaka (Bibliography) --}}
    @if(count($bibliography) > 0)
        <div class="page-break"></div>
        <div class="bibliography-section" id="section-bibliography">
            <h1 class="frontmatter-title">DAFTAR PUSTAKA</h1>
            @foreach($bibliography as $entry)
                <div class="bibliography-entry">
                    {!! e($entry) !!}
                </div>
            @endforeach
        </div>
    @endif

</body>
</html>
