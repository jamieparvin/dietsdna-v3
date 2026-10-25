<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class IngredientImportController extends Controller
{
    private const HEADERS = [
        'food_code',
        'name',
        'category',
        'sub_category',
        'preparation_state',
        'energy_kcal',
        'protein_g',
        'carbohydrate_g',
        'sugars_g',
        'fat_g',
        'saturates_g',
        'fibre_g',
        'salt_g',
        'is_active',
    ];

    public function create()
    {
        return view('admin.ingredients.import', [
            'preview' => null,
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'workbook' => [
                'required',
                'file',
                'mimes:xlsx',
                'extensions:xlsx',
                'max:5120',
            ],
        ]);

        $file = $request->file('workbook');
        $rows = $this->readRows($file->getRealPath());

        $existing = Ingredient::query()
            ->whereIn('food_code', array_column($rows, 'food_code'))
            ->get()
            ->keyBy('food_code');

        $items = [];
        $counts = ['new' => 0, 'update' => 0, 'unchanged' => 0];

        foreach ($rows as $data) {
            $ingredient = $existing->get($data['food_code']);
            $before = $this->signature($ingredient);

            $action = ! $ingredient
                ? 'new'
                : ($this->signature($ingredient, false) === $this->signature(new Ingredient($data), false)
                    ? 'unchanged'
                    : 'update');

            $counts[$action]++;

            $items[] = [
                'data' => $data,
                'before' => $before,
                'action' => $action,
            ];
        }

        $token = Str::random(64);

        // Keep the source workbook privately, including its source notes.
        $path = $file->storeAs(
            'ingredient-imports',
            $token.'.xlsx',
            'local'
        );

        if (! $path) {
            throw ValidationException::withMessages([
                'workbook' => 'The workbook could not be saved. Please try again.',
            ]);
        }

        $preview = [
            'token' => $token,
            'user_id' => $request->user()->id,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'items' => $items,
            'counts' => $counts,
        ];

        Cache::put(
            'ingredient-import:'.$token,
            $preview,
            now()->addMinutes(30)
        );

        return view('admin.ingredients.import', compact('preview'));
    }

    public function confirm(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64', 'alpha_num'],
        ]);

        $key = 'ingredient-import:'.$validated['token'];

        try {
            $counts = Cache::lock($key.':lock', 120)->block(
                5,
                function () use ($request, $key) {
                    $preview = Cache::get($key);

                    if (! $preview) {
                        throw ValidationException::withMessages([
                            'workbook' => 'This preview expired or was already imported. Upload the workbook again.',
                        ]);
                    }

                    abort_unless(
                        (string) $preview['user_id'] ===
                            (string) $request->user()->id,
                        403
                    );

                    $counts = DB::transaction(function () use ($preview) {
                        $codes = array_map(
                            fn ($item) => $item['data']['food_code'],
                            $preview['items']
                        );

                        $existing = Ingredient::query()
                            ->whereIn('food_code', $codes)
                            ->lockForUpdate()
                            ->get()
                            ->keyBy('food_code');

                        // Require a fresh preview if records changed meanwhile.
                        foreach ($preview['items'] as $item) {
                            $current = $existing->get(
                                $item['data']['food_code']
                            );

                            if (
                                $this->signature($current) !==
                                $item['before']
                            ) {
                                throw ValidationException::withMessages([
                                    'workbook' => 'An ingredient changed after this preview. Upload the workbook again to review the latest changes.',
                                ]);
                            }
                        }

                        $counts = [
                            'new' => 0,
                            'update' => 0,
                            'unchanged' => 0,
                        ];

                        foreach ($preview['items'] as $item) {
                            $data = $item['data'];
                            $ingredient = $existing->get($data['food_code']);

                            if (! $ingredient) {
                                Ingredient::create($data);
                                $counts['new']++;
                                continue;
                            }

                            $ingredient->fill($data);

                            if ($ingredient->isDirty()) {
                                $ingredient->save();
                                $counts['update']++;
                            } else {
                                $counts['unchanged']++;
                            }
                        }

                        return $counts;
                    });

                    Cache::forget($key);

                    return $counts;
                }
            );
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'workbook' => 'This import is already being processed. Check the ingredient list before trying again.',
            ]);
        }

        return redirect()
            ->route('admin.ingredients.index')
            ->with(
                'status',
                "Import complete: {$counts['new']} added, ".
                "{$counts['update']} updated, ".
                "{$counts['unchanged']} unchanged."
            );
    }

    private function readRows(string $path): array
    {
        $book = null;

        try {
            // Limit expanded workbook size before reading its XML.
            $zip = new \ZipArchive();

            if ($zip->open($path) !== true) {
                throw ValidationException::withMessages([
                    'workbook' => 'Please upload a valid .xlsx workbook.',
                ]);
            }

            try {
                $expandedSize = 0;

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->statIndex($i);
                    $expandedSize += $entry['size'] ?? 0;
                }

                if ($expandedSize > 50 * 1024 * 1024) {
                    throw ValidationException::withMessages([
                        'workbook' => 'The workbook is too large when expanded.',
                    ]);
                }
            } finally {
                $zip->close();
            }

            $reader = new Xlsx();

            if (! $reader->canRead($path)) {
                throw ValidationException::withMessages([
                    'workbook' => 'This file could not be read as an Excel workbook.',
                ]);
            }

            $info = collect($reader->listWorksheetInfo($path))
                ->firstWhere('worksheetName', 'Ingredients');

            if (! $info) {
                throw ValidationException::withMessages([
                    'workbook' => 'The workbook must contain a sheet named Ingredients.',
                ]);
            }

            if ($info['totalRows'] > 2001 || $info['totalColumns'] > 14) {
                throw ValidationException::withMessages([
                    'workbook' => 'Use the 14-column template with no more than 2,000 ingredient rows.',
                ]);
            }

            $reader->setLoadSheetsOnly('Ingredients');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $book = $reader->load($path);
            $sheet = $book->getSheetByName('Ingredients');

            $headers = [];

            for ($column = 1; $column <= 14; $column++) {
                $cell = $sheet->getCell(
                    Coordinate::stringFromColumnIndex($column).'1'
                );

                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    throw ValidationException::withMessages([
                        'workbook' => 'Column headings must contain plain text.',
                    ]);
                }

                $headers[] = strtolower(trim((string) $cell->getValue()));
            }

            if ($headers !== self::HEADERS) {
                throw ValidationException::withMessages([
                    'workbook' => 'The column headings or their order do not match the 14-column ingredient template.',
                ]);
            }

            $rows = [];
            $seen = [];
            $errors = [];

            for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
                $data = [];
                $hasFormula = false;

                foreach (self::HEADERS as $index => $field) {
                    $address = Coordinate::stringFromColumnIndex(
                        $index + 1
                    ).$row;

                    $cell = $sheet->getCell($address);

                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $hasFormula = true;
                    }

                    $value = $cell->getValue();

                    if (is_string($value)) {
                        $value = trim($value);
                    }

                    $data[$field] = $value === '' ? null : $value;
                }

                if (
                    ! $hasFormula &&
                    count(array_filter(
                        $data,
                        fn ($value) => $value !== null
                    )) === 0
                ) {
                    continue;
                }

                if ($hasFormula) {
                    $errors[] = "Row {$row}: replace formulas with their values.";
                    continue;
                }

                if (is_string($data['food_code'])) {
                    $data['food_code'] = strtoupper($data['food_code']);
                }

                $rules = [
                    'food_code' => [
                        'required',
                        'string',
                        'max:100',
                        'regex:/^[A-Z0-9_-]+$/',
                    ],
                    'name' => ['required', 'string', 'max:255'],
                    'category' => ['required', 'string', 'max:100'],
                    'sub_category' => ['required', 'string', 'max:100'],
                    'preparation_state' => ['required', 'string', 'max:100'],
                    'energy_kcal' => ['nullable', 'numeric', 'between:0,1000'],
                    'is_active' => ['required', 'boolean'],
                ];

                foreach (array_slice(self::HEADERS, 6, 7) as $field) {
                    $rules[$field] = [
                        'nullable',
                        'numeric',
                        'between:0,100',
                    ];
                }

                $validator = Validator::make($data, $rules);

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $message) {
                        $errors[] = "Row {$row}: {$message}";
                    }

                    continue;
                }

                $data = $validator->validated();

                if (isset($seen[$data['food_code']])) {
                    $errors[] = "Row {$row}: duplicate food code ".
                        "{$data['food_code']} (also row ".
                        $seen[$data['food_code']].').';

                    continue;
                }

                $seen[$data['food_code']] = $row;

                foreach ([
                    'sugars_g' => 'carbohydrate_g',
                    'fibre_g' => 'carbohydrate_g',
                    'saturates_g' => 'fat_g',
                ] as $part => $total) {
                    if (
                        $data[$part] !== null &&
                        $data[$total] !== null &&
                        (float) $data[$part] > (float) $data[$total]
                    ) {
                        $errors[] = "Row {$row}: {$part} cannot exceed {$total}.";
                    }
                }

                // Match the database's five-decimal precision.
                foreach (array_slice(self::HEADERS, 5, 8) as $field) {
                    if ($data[$field] !== null) {
                        $data[$field] = number_format(
                            (float) $data[$field],
                            5,
                            '.',
                            ''
                        );
                    }
                }

                $data['is_active'] = (bool) $data['is_active'];
                $rows[] = $data;
            }

            if ($errors) {
                throw ValidationException::withMessages([
                    'workbook' => array_slice($errors, 0, 100),
                ]);
            }

            if (! $rows) {
                throw ValidationException::withMessages([
                    'workbook' => 'The Ingredients sheet contains no ingredient rows.',
                ]);
            }

            return $rows;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'workbook' => 'The workbook could not be read. Open it in Excel, save it as .xlsx and try again.',
            ]);
        } finally {
            $book?->disconnectWorksheets();
        }
    }

    private function signature(?Ingredient $ingredient, bool $includeGrouping = true): ?string
    {
        if (! $ingredient) {
            return null;
        }

        return hash(
            'sha256',
            json_encode(
                $ingredient->only($includeGrouping
                    ? [...self::HEADERS, 'preference_key', 'preference_group_key', 'preference_food']
                    : self::HEADERS),
                JSON_THROW_ON_ERROR
            )
        );
    }
}
