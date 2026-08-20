<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;

class GenerateUserDocumentation extends Command
{
    protected $signature = 'docs:generate-user-pdf
        {--edition=full : full (seluruh role) atau requester (department non-ITD)}
        {--output= : Berkas tujuan; default mengikuti edisi}';

    protected $description = 'Generate user & business flow documentation PDF (Bahasa Indonesia)';

    /** Satu perintah, dua edisi: isi lengkap untuk ITD, ringkas untuk requester. */
    private const EDITIONS = [
        'full' => ['docs.user-business-flow', 'public/docs/elara-user-business-flow-id.pdf'],
        'requester' => ['docs.requester-flow', 'public/docs/elara-panduan-requester-id.pdf'],
    ];

    public function handle(): int
    {
        $edition = $this->option('edition');

        if (! isset(self::EDITIONS[$edition])) {
            $this->error("Edisi tidak dikenal: {$edition}. Pilih: ".implode(', ', array_keys(self::EDITIONS)));

            return self::FAILURE;
        }

        [$view, $defaultOutput] = self::EDITIONS[$edition];
        $output = base_path($this->option('output') ?: $defaultOutput);
        $dir = dirname($output);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdf = Pdf::loadView($view, [
            'version' => '1.1',
            'generatedAt' => now()->timezone('Asia/Jakarta')->format('d F Y'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            // The footer draws page numbers from an inline script in the view.
            ->setOption('isPhpEnabled', true)
            ->setOption('defaultFont', 'DejaVu Sans');

        file_put_contents($output, $pdf->output());

        $this->info("PDF written to: {$output}");

        return self::SUCCESS;
    }
}
