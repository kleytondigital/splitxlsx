<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ListControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_processes_valid_file_and_returns_zip(): void
    {
        $file = $this->makeSpreadsheet(
            ['Nome completo', 'Celular'],
            [
                ['Alice', '(11) 99999-8888'],
                ['Bob', '21988887777'],
            ],
        );

        $response = $this->post('/api/upload', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertHeader('content-disposition', fn ($value) => str_contains($value, '.zip'));
    }

    public function test_it_detects_columns_by_content_when_headers_are_missing(): void
    {
        $file = $this->makeSpreadsheet(
            ['Coluna 1', 'Coluna 2'],
            [
                ['Foo', '5511988887777'],
                ['Bar', '5511977776666'],
            ],
        );

        $response = $this->post('/api/upload', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertOk();
    }

    public function test_it_returns_validation_error_when_number_column_not_found(): void
    {
        $file = $this->makeSpreadsheet(
            ['Coluna 1', 'Coluna 2'],
            [
                ['Foo', 'Bar'],
            ],
        );

        $response = $this->post('/api/upload', ['file' => $file], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'Não foi possível identificar colunas de telefone e nome automaticamente.']);
    }

    public function test_it_requires_file(): void
    {
        $response = $this->post('/api/upload', [], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }

    private function makeSpreadsheet(array $header, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($header, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $uuid = Str::uuid()->toString();
        $relative = "framework/testing/test_{$uuid}.xlsx";

        Storage::disk('local')->makeDirectory('framework/testing');
        $fullPath = Storage::disk('local')->path($relative);

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($fullPath);

        return new UploadedFile(
            $fullPath,
            "planilha_{$uuid}.xlsx",
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}

