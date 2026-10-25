@extends('layouts.app')

@section('title', 'Import ingredients')

@section('content')
    <style>
        .import-heading,
        .import-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .import-actions {
            justify-content: flex-start;
            margin-top: 24px;
        }

        .import-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin: 24px 0;
        }

        .import-summary strong {
            display: block;
            margin-top: 10px;
            font-size: 30px;
        }

        .import-table-wrap {
            overflow-x: auto;
        }

        .import-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .import-table th,
        .import-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5ece7;
            vertical-align: top;
        }

        .import-table th {
            color: #61756a;
            font-size: 12px;
            white-space: nowrap;
        }

        .import-table small {
            display: block;
            margin-top: 6px;
            color: #61756a;
            overflow-wrap: anywhere;
        }

        @media (max-width: 600px) {
            .import-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="import-heading">
        <div>
            <h1>Import ingredients</h1>
            <p>Upload your spreadsheet and review it before saving.</p>
        </div>

        <a href="{{ route('admin.ingredients.index') }}">
            Back to ingredients
        </a>
    </div>

    @if (! $preview)
        <div class="card">
            <h2>Upload your workbook</h2>

            <p>
                Choose an .xlsx file containing a sheet named
                <strong>Ingredients</strong>, using your 14-column template.
                Values must be per 100g, with total carbohydrate including fibre.
            </p>
            <p>Updates preserve existing food-preference groups, including renamed or recategorised foods.
                New ingredients start as separate foods. After importing a new preparation, open Edit ingredient
                and explicitly link it to the existing food group. No names are matched automatically.</p>

            <form
                method="POST"
                action="{{ route('admin.ingredients.import.preview') }}"
                enctype="multipart/form-data"
            >
                @csrf

                <label for="workbook">Excel workbook</label>

                <input
                    type="file"
                    id="workbook"
                    name="workbook"
                    accept=".xlsx"
                    required
                >

                <p>
                    Maximum file size: 5 MB.
                    Maximum ingredients: 2,000.
                    Use values rather than Excel formulas.
                </p>

                <div class="import-actions">
                    <button type="submit">Preview import</button>
                </div>
            </form>
        </div>
    @else
        <div class="notice success">
            Workbook validated:
            <strong>{{ $preview['filename'] }}</strong>.
            No ingredients have been changed yet.
        </div>

        <div class="import-summary">
            <div class="card">
                New ingredients
                <strong>{{ $preview['counts']['new'] }}</strong>
            </div>

            <div class="card">
                Existing ingredients to update
                <strong>{{ $preview['counts']['update'] }}</strong>
            </div>

            <div class="card">
                Unchanged ingredients
                <strong>{{ $preview['counts']['unchanged'] }}</strong>
            </div>
        </div>

        <div class="card">
            <h2>Review ingredients</h2>

            <p>
                Existing food codes will be updated with the uploaded values.
                Ingredients absent from the workbook will stay as they are.
                This preview expires after 30 minutes.
            </p>

            <div class="import-table-wrap">
                <table class="import-table">
                    <thead>
                        <tr>
                            <th scope="col">Action</th>
                            <th scope="col">Ingredient</th>
                            <th scope="col">Category</th>
                            <th scope="col">Preparation</th>
                            <th scope="col">kcal</th>
                            <th scope="col">Protein (g)</th>
                            <th scope="col">Carbs (g)</th>
                            <th scope="col">Fat (g)</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($preview['items'] as $item)
                            @php($data = $item['data'])

                            <tr>
                                <td>
                                    <span class="badge">
                                        {{ ucfirst($item['action']) }}
                                    </span>
                                </td>

                                <td>
                                    <strong>{{ $data['name'] }}</strong>
                                    <small>{{ $data['food_code'] }}</small>
                                </td>

                                <td>
                                    {{ $data['category'] }}
                                    <small>{{ $data['sub_category'] }}</small>
                                </td>

                                <td>
                                    {{ ucfirst(str_replace('_', ' ', $data['preparation_state'])) }}
                                </td>

                                @foreach (['energy_kcal', 'protein_g', 'carbohydrate_g', 'fat_g'] as $field)
                                    <td>
                                        {{ $data[$field] === null
                                            ? '—'
                                            : number_format((float) $data[$field], 2) }}
                                    </td>
                                @endforeach

                                <td>
                                    {{ $data['is_active'] ? 'Active' : 'Inactive' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <form
                method="POST"
                action="{{ route('admin.ingredients.import.confirm') }}"
            >
                @csrf

                <input
                    type="hidden"
                    name="token"
                    value="{{ $preview['token'] }}"
                >

                <div class="import-actions">
                    <button type="submit">Confirm import</button>

                    <a href="{{ route('admin.ingredients.import.create') }}">
                        Choose another workbook
                    </a>

                    <a href="{{ route('admin.ingredients.index') }}">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    @endif
@endsection
