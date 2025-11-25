<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ZipArchive;

class ListController extends Controller
{
    private const CHUNK_SIZE = 100;

    public function process(Request $request)
    {
        try {
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
                'remove_duplicates' => ['boolean'],
                'download_type' => ['in:grouped,separated'],
                'chunk_size' => ['integer', 'min:1', 'max:1000'],
            ]);

            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $request->file('file');

            try {
                $spreadsheet = IOFactory::load($uploadedFile->getRealPath());
            } catch (\Throwable $exception) {
                \Log::error('Erro ao carregar planilha', [
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);

                return response()->json([
                    'error' => 'Não foi possível abrir a planilha fornecida.',
                    'details' => config('app.debug') ? $exception->getMessage() : 'Formato de arquivo inválido.',
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

            $removeDuplicates = $request->boolean('remove_duplicates', true);
            $downloadType = $request->input('download_type', 'separated');
            $chunkSize = (int) $request->input('chunk_size', self::CHUNK_SIZE);

            $cleanContacts = $this->sanitizeRows($rows, $numberIndex, $nameIndex, $removeDuplicates);

            if ($cleanContacts->isEmpty()) {
                return response()->json([
                    'error' => 'Nenhum telefone válido encontrado após a limpeza.',
                ], 422);
            }

            $totalContacts = $cleanContacts->count();
            $statistics = [
                'total_contacts' => $totalContacts,
                'total_files' => $downloadType === 'grouped' ? 1 : (int) ceil($totalContacts / $chunkSize),
                'chunk_size' => $chunkSize,
                'removed_duplicates' => $removeDuplicates,
            ];

            if ($downloadType === 'grouped') {
                $zipPath = $this->generateGroupedFile($cleanContacts, $statistics);
            } else {
                $zipPath = $this->generateZipFromChunks($cleanContacts, $chunkSize, $statistics);
            }

            return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Erro de validação',
                'details' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('Erro ao processar lista', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Erro interno do servidor',
                'message' => config('app.debug') ? $e->getMessage() : 'Ocorreu um erro ao processar a requisição',
                'file' => config('app.debug') ? $e->getFile() : null,
                'line' => config('app.debug') ? $e->getLine() : null,
            ], 500);
        }
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

    private function sanitizeRows(array $rows, int $numberIndex, int $nameIndex, bool $removeDuplicates = true): Collection
    {
        $contacts = collect();
        $seenNumbers = [];

        foreach ($rows as $row) {
            $rawNumber = isset($row[$numberIndex]) ? (string) $row[$numberIndex] : '';
            $digits = preg_replace('/\D+/', '', $rawNumber);

            if ($digits === '' || strlen($digits) < 10) {
                continue;
            }

            if (!str_starts_with($digits, '55')) {
                $digits = '55'.ltrim($digits, '0');
            }

            // Valida formato final (deve ter 13 dígitos: 55 + DDD + número)
            if (strlen($digits) < 12 || strlen($digits) > 13) {
                continue;
            }

            // Remove duplicados se solicitado
            if ($removeDuplicates && isset($seenNumbers[$digits])) {
                continue;
            }

            $seenNumbers[$digits] = true;

            $name = isset($row[$nameIndex]) ? trim((string) $row[$nameIndex]) : '';

            $contacts->push([$digits, $name]);
        }

        return $contacts->values();
    }

    private function generateZipFromChunks(Collection $contacts, int $chunkSize, array $statistics): string
    {
        $chunked = $contacts->chunk($chunkSize);
        $zipName = 'listas_padronizadas_'.Str::uuid().'.zip';
        
        // Garante que o diretório existe
        $storagePath = storage_path('app');
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        
        $zipPath = $storagePath.'/'.$zipName;

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
            $tempPath = storage_path('app/'.$tempName);

            IOFactory::createWriter($export, 'Xlsx')->save($tempPath);
            $tempFiles[] = $tempPath;

            $zip->addFile($tempPath, $fileName);
        }

        // Adiciona arquivo de estatísticas
        $statsContent = "Estatísticas do Processamento\n";
        $statsContent .= "============================\n\n";
        $statsContent .= "Total de contatos: {$statistics['total_contacts']}\n";
        $statsContent .= "Total de arquivos gerados: {$statistics['total_files']}\n";
        $statsContent .= "Tamanho do chunk: {$statistics['chunk_size']}\n";
        $statsContent .= "Duplicados removidos: ".($statistics['removed_duplicates'] ? 'Sim' : 'Não')."\n";
        $statsContent .= "Data de processamento: ".now()->format('d/m/Y H:i:s')."\n";

        $zip->addFromString('estatisticas.txt', $statsContent);

        $zip->close();

        foreach ($tempFiles as $file) {
            @unlink($file);
        }

        return $zipPath;
    }

    private function generateGroupedFile(Collection $contacts, array $statistics): string
    {
        $export = new Spreadsheet();
        $sheet = $export->getActiveSheet();
        $sheet->fromArray(['Mobile Number', 'Name'], null, 'A1');
        $sheet->fromArray($contacts->toArray(), null, 'A2');

        $fileName = 'lista_completa_'.Str::uuid().'.xlsx';
        $storagePath = storage_path('app');
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        $filePath = $storagePath.'/'.$fileName;

        IOFactory::createWriter($export, 'Xlsx')->save($filePath);

        // Cria ZIP com arquivo único e estatísticas
        $zipName = 'lista_agrupada_'.Str::uuid().'.zip';
        $zipPath = $storagePath.'/'.$zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o arquivo ZIP.');
        }

        $zip->addFile($filePath, 'lista_completa.xlsx');

        $statsContent = "Estatísticas do Processamento\n";
        $statsContent .= "============================\n\n";
        $statsContent .= "Total de contatos: {$statistics['total_contacts']}\n";
        $statsContent .= "Arquivo único gerado: Sim\n";
        $statsContent .= "Duplicados removidos: ".($statistics['removed_duplicates'] ? 'Sim' : 'Não')."\n";
        $statsContent .= "Data de processamento: ".now()->format('d/m/Y H:i:s')."\n";

        $zip->addFromString('estatisticas.txt', $statsContent);
        $zip->close();

        @unlink($filePath);

        return $zipPath;
    }
}

