@extends('install.layout')

@section('content')
    <h2>Server requirements</h2>
    <p class="stepdesc">The installer checks everything Social CRM needs before writing any configuration. Items marked <strong>Required</strong> must pass to continue; warnings can be fixed later but may limit features (queues, engines).</p>

    <table class="req">
        <thead>
            <tr>
                <th style="width:44%;">Check</th>
                <th>Current</th>
                <th style="width:110px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($requirements as $check)
                <tr>
                    <td>{{ $check['label'] }}@if ($check['required']) <span style="color:#6b7280;">(required)</span>@endif</td>
                    <td class="mono">{{ $check['current'] }}</td>
                    <td>
                        @if ($check['ok'])
                            <span class="badge badge-ok">OK</span>
                        @elseif ($check['required'])
                            <span class="badge badge-fail">Failing</span>
                        @else
                            <span class="badge badge-warn">Warning</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($hardFail)
        <div class="note" style="border-color:#7f1d1d;">
            <strong>Some required checks are failing.</strong> Fix them on the server (install the missing PHP extension, run <span class="mono">composer install</span>, or correct the folder permissions), then reload this page.
        </div>
    @endif

    <form method="POST" action="{{ route('install.requirements.next') }}">
        @csrf
        <div class="actions">
            <span></span>
            <button type="submit" class="btn" @if ($hardFail) disabled @endif>Next: Database &rarr;</button>
        </div>
    </form>
@endsection
