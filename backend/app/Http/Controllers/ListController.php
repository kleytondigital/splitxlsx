
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ZipArchive;

class ListController extends Controller
{
    private const CHUNK_SIZE = 100;

    public function process(Request $request)
    {
        if (! $request->expectsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv',
                'max:5120',
            ],
        ]);

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($uploadedFile->getRealPath());
        } catch (\Throwable $exception) {
            return response()->json([
                'error' => 'Não foi possível abrir a planilha fornecida.',
                'details' => $exception->getMessage(),
            ], 422);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray();

        if (count($rows) <= 1) {
            return response()->json(['error' => 'Planilha sem dados.'], 422);
        }

        $header = $this->normalizeHeader(array_shift($rows));

        $numberIndex = $this->detectNumberColumn($header, $rows);
        $nameIndex = $this->detectNameColumn($header, $rows);

        if ($numberIndex === null || $nameIndex === null) {
            return response()->json([
                'error' => 'Não foi possível identificar colunas de telefone e nome automaticamente.',
            ], 422);
        }

        $cleanContacts = $this->sanitizeRows($rows, $numberIndex, $nameIndex);

        if ($cleanContacts->isEmpty()) {
            return response()->json([
                'error' => 'Nenhum telefone válido encontrado após a limpeza.',
            ], 422);
        }

        $zipPath = $this->generateZipFromChunks($cleanContacts);

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(true);
    }

    private function normalizeHeader(array $header): array
    {
        return array_map(static fn ($value) => Str::of((string) $value)->lower()->trim()->value(), $header);
    }

    private function detectNumberColumn(array $header, array $rows): ?int
    {
        $keywords = ['phone', 'telefone', 'cel', 'whats', 'numero', 'number', 'contato'];
        $index = $this->detectByKeywords($header, $keywords);

        if ($index !== null) {
            return $index;
        }

        return $this->detectByContent($rows, static function ($value) {
            $digits = preg_replace('/\D+/', '', (string) $value);

            return strlen($digits) >= 10;
        });
    }

    private function detectNameColumn(array $header, array $rows): ?int
    {
        $keywords = ['name', 'nome', 'contato', 'cliente'];
        $index = $this->detectByKeywords($header, $keywords);

        if ($index !== null) {
            return $index;
        }

        return $this->detectByContent($rows, static function ($value) {
            $value = (string) $value;

            return strlen(trim($value)) >= 2 && preg_match('/[a-zA-ZÀ-ÿ]/', $value);
        });
    }

    private function detectByKeywords(array $header, array $keywords): ?int
    {
        foreach ($header as $index => $column) {
            foreach ($keywords as $keyword) {
                if ($column !== '' && str_contains($column, $keyword)) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function detectByContent(array $rows, callable $rule): ?int
    {
        $scores = [];

        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                if ($rule($value ?? '')) {
                    $scores[$index] = ($scores[$index] ?? 0) + 1;
                }
            }
        }

        arsort($scores);
        $best = array_key_first($scores);

        return ($best !== null && $scores[$best] >= 2) ? $best : null;
    }

    private function sanitizeRows(array $rows, int $numberIndex, int $nameIndex): Collection
    {
        $contacts = collect();

        foreach ($rows as $row) {
            $rawNumber = isset($row[$numberIndex]) ? (string) $row[$numberIndex] : '';
            $digits = preg_replace('/\D+/', '', $rawNumber);

            if ($digits === '' || strlen($digits) < 10) {
                continue;
            }

            if (!str_starts_with($digits, '55')) {
                $digits = '55'.ltrim($digits, '0');
            }

            $name = isset($row[$nameIndex]) ? trim((string) $row[$nameIndex]) : '';

            $contacts->push([$digits, $name]);
        }

        return $contacts->unique(fn ($contact) => $contact[0])->values();
    }

    private function generateZipFromChunks(Collection $contacts): string
    {
        $chunked = $contacts->chunk(self::CHUNK_SIZE);
        $zipName = 'listas_padronizadas_'.Str::uuid().'.zip';
        $zipPath = Storage::disk('local')->path($zipName);

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o arquivo ZIP.');
        }

        $tempFiles = [];

        foreach ($chunked as $index => $chunk) {
            $export = new Spreadsheet();
            $sheet = $export->getActiveSheet();
            $sheet->fromArray(['Mobile Number', 'Name'], null, 'A1');
            $sheet->fromArray($chunk->toArray(), null, 'A2');

            $fileName = sprintf('lista_%03d.xlsx', $index + 1);
            $tempName = sprintf('%s_%s', Str::uuid(), $fileName);
            $tempPath = Storage::disk('local')->path($tempName);

            IOFactory::createWriter($export, 'Xlsx')->save($tempPath);
            $tempFiles[] = $tempPath;

            $zip->addFile($tempPath, $fileName);
        }

        $zip->close();

        foreach ($tempFiles as $file) {
            @unlink($file);
        }

        return $zipPath;
    }
}

