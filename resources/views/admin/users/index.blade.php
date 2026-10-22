@extends('layouts.app')

@section('title', 'Customers')

@section('content')
    <h1>Customers</h1>

    <p>
        Registered accounts, including administrators.
        {{ number_format($users->total()) }}
        {{ $search !== '' ? 'matching accounts' : 'accounts in total' }}.
    </p>

    <section class="card">
        <form method="GET" action="{{ route('admin.users.index') }}">
            <label for="search" style="margin-top: 0;">
                Search by name or email
            </label>

            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    maxlength="150"
                    placeholder="Enter a name or email"
                    style="flex: 1 1 220px; min-width: 0;"
                >

                <button type="submit">Search</button>

                @if ($search !== '')
                    <a href="{{ route('admin.users.index') }}">Clear</a>
                @endif
            </div>
        </form>

        <div style="overflow-x: auto; margin-top: 28px;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <caption style="text-align: left; padding-bottom: 16px; font-weight: 600;">
                    Registered users
                </caption>

                <thead>
                    <tr style="background: #f0f5f2;">
                        <th scope="col" style="padding: 14px;">ID</th>
                        <th scope="col" style="padding: 14px;">Name</th>
                        <th scope="col" style="padding: 14px;">Email</th>
                        <th scope="col" style="padding: 14px;">Role</th>
                        <th scope="col" style="padding: 14px;">Registered</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($users as $user)
                        <tr style="border-bottom: 1px solid #e5ece7;">
                            <td style="padding: 16px 14px;">
                                {{ $user->id }}
                            </td>

                            <td style="padding: 16px 14px;">
                                <strong>{{ $user->name }}</strong>

                                @if ($user->id === auth()->id())
                                    <span class="badge">You</span>
                                @endif
                            </td>

                            <td style="padding: 16px 14px;">
                                {{ $user->email }}
                            </td>

                            <td style="padding: 16px 14px;">
                                <span class="badge">
                                    {{ $user->is_admin ? 'Administrator' : 'Customer' }}
                                </span>
                            </td>

                            <td style="padding: 16px 14px; white-space: nowrap;">
                                {{ $user->created_at?->format('d M Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding: 28px 14px;">
                                No accounts found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <nav
                aria-label="Customer pages"
                style="display: flex; flex-wrap: wrap; gap: 20px; align-items: center; margin-top: 24px;"
            >
                @if ($users->previousPageUrl())
                    <a href="{{ $users->previousPageUrl() }}">Previous</a>
                @endif

                <span>
                    Page {{ $users->currentPage() }} of {{ $users->lastPage() }}
                </span>

                @if ($users->nextPageUrl())
                    <a href="{{ $users->nextPageUrl() }}">Next</a>
                @endif
            </nav>
        @endif
    </section>
@endsection