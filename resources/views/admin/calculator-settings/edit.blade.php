@extends('layouts.app')

@section('title', 'Calculator settings')

@section('content')
    <style>
        .cs-heading { margin-bottom: 26px; }

        .cs-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .cs-card {
            background: white;
            border: 1px solid #dde7e1;
            border-radius: 16px;
            padding: 26px;
            min-width: 0;
        }

        .cs-card h2 {
            margin: 0 0 12px;
            font-size: 20px;
        }

        .cs-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 20px;
        }

        .cs-note {
            font-size: 13px;
            color: #61756a;
            line-height: 1.6;
        }

        .cs-wide {
            grid-column: 1 / -1;
        }

        .cs-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .cs-reset {
            background: #fff0ee;
            color: #8b2920;
        }

        .cs-card textarea {
            width: 100%;
            padding: 13px;
            border: 1px solid #b9cbc0;
            border-radius: 8px;
            font: inherit;
        }

        .cs-table-wrap { overflow-x: auto; }

        .cs-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .cs-table th,
        .cs-table td {
            padding: 13px;
            border-bottom: 1px solid #e5ece7;
            vertical-align: top;
        }

        .cs-table th {
            color: #61756a;
            font-size: 12px;
        }

        .cs-table td {
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        @media (max-width: 1000px) {
            .cs-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 600px) {
            .cs-fields { grid-template-columns: 1fr; }
        }
    </style>

    <div class="cs-heading">
        <h1>Calculator settings</h1>

        <p>
            Manage the global starting rules for calorie and macro estimates.
            Current settings revision: {{ $configuration['revision'] }}.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('admin.calculator-settings.update') }}"
    >
        @csrf
        @method('PUT')

        <input
            type="hidden"
            name="revision"
            value="{{ $configuration['revision'] }}"
        >

        <div class="cs-grid">
            @foreach ($groups as $heading => $fields)
                <section class="cs-card">
                    <h2>{{ $heading }}</h2>

                    <div class="cs-fields">
                        @foreach ($fields as $key => $definition)
                            <div>
                                <label for="{{ $key }}">
                                    {{ $definition[0] }}
                                </label>

                                <input
                                    id="{{ $key }}"
                                    name="{{ $key }}"
                                    type="number"
                                    min="{{ $definition[2] }}"
                                    max="{{ $definition[3] }}"
                                    step="{{ $definition[4] }}"
                                    required
                                    value="{{ old($key, $configuration['values'][$key]) }}"
                                >

                                <p class="cs-note">
                                    Default: {{ $definition[1] }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <section class="cs-card cs-wide">
                <h2>Save global settings</h2>

                <p class="cs-note">
                    Changes apply when customer targets are next calculated. 
                    Each goal uses protein, carbohydrate and fat percentages totalling 100%. 
                    Users following goal defaults receive these settings. 
                    Users with custom percentages keep their chosen split. 
                    Existing planned meal portions remain unchanged.                    
                </p>

                <p class="cs-note">
                    The resting-energy equation, adult eligibility checks,
                    BMI review range and calorie review limits are maintained
                    separately in the calculator.
                </p>

                <label for="reason">Reason for change</label>

                <textarea
                    id="reason"
                    name="reason"
                    rows="3"
                    maxlength="500"
                    required
                    placeholder="Explain why these defaults are being changed"
                >{{ old('reason') }}</textarea>

                <div class="cs-actions">
                    <button type="submit" name="action" value="save">
                        Save settings
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="reset"
                        formnovalidate
                        class="cs-reset"
                        onclick="return confirm('Restore all calculator defaults? Enter a reason first.');"
                    >
                        Restore defaults
                    </button>
                </div>

                <p class="cs-note">
                    Restoring defaults also creates a recorded revision.
                    A reason is required for both actions.
                </p>
            </section>
        </div>
    </form>

    <section class="cs-card" style="margin-top: 24px;">
        <h2>Recent changes</h2>

        <div class="cs-table-wrap">
            <table class="cs-table">
                <thead>
                    <tr>
                        <th>Revision</th>
                        <th>Administrator</th>
                        <th>Date</th>
                        <th>Reason</th>
                        <th>Saved values</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($history as $change)
                        <tr>
                            <td>{{ $change->revision }}</td>
                            <td>{{ $change->admin_name ?? 'Former administrator' }}</td>
                            <td>
                                {{ \Carbon\Carbon::parse($change->created_at)
                                    ->timezone('Europe/London')
                                    ->format('d M Y, H:i') }}
                            </td>
                            <td>{{ $change->reason }}</td>
                            <td>
                                <details>
                                    <summary>View values</summary>

                                    @php
                                        $savedValues = json_decode(
                                            $change->parameters,
                                            true
                                        );
                                    @endphp

                                    @foreach ($groups as $fields)
                                        @foreach ($fields as $key => $definition)
                                            <p class="cs-note">
                                                {{ $definition[0] }}:
                                                {{ $savedValues[$key] ?? '—' }}
                                            </p>
                                        @endforeach
                                    @endforeach
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                No changes recorded. Original defaults are in use.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection