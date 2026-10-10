@extends('layouts.staff')

@section('title', 'Change Password')
@section('page-title', 'Change Password')

@section('content')
    <div style="max-width: 480px; margin: 40px auto; padding: 0 16px;">
        <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
            <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 20px;">Change Password</h2>

            <div id="password-form">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; color: var(--text-secondary); margin-bottom: 6px;">Current Password</label>
                    <input type="password" id="current_password" style="width: 100%; padding: 10px 14px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; color: var(--text-secondary); margin-bottom: 6px;">New Password</label>
                    <input type="password" id="new_password" style="width: 100%; padding: 10px 14px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; color: var(--text-secondary); margin-bottom: 6px;">Confirm New Password</label>
                    <input type="password" id="new_password_confirmation" style="width: 100%; padding: 10px 14px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 14px; outline: none;">
                </div>

                <button type="button" onclick="submitChangePassword()" style="width: 100%; padding: 12px; background: var(--accent); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer;">Update Password</button>
            </div>

            <div id="password-success" style="display: none; text-align: center; padding: 20px;">
                <div style="font-size: 48px; margin-bottom: 12px;">✅</div>
                <p style="font-size: 14px; color: var(--text-primary);">Password changed successfully!</p>
                <a href="{{ route('staff.dashboard') }}" style="display: inline-block; margin-top: 16px; padding: 10px 20px; background: var(--accent); color: #fff; text-decoration: none; border-radius: 8px; font-size: 13px; font-weight: 600;">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script>
        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        function submitChangePassword() {
            var current = document.getElementById('current_password').value;
            var newPass = document.getElementById('new_password').value;
            var confirm = document.getElementById('new_password_confirmation').value;

            if (!current || !newPass || !confirm) {
                alert('Please fill in all fields');
                return;
            }

            if (newPass !== confirm) {
                alert('New passwords do not match');
                return;
            }

            var formData = new FormData();
            formData.append('current_password', current);
            formData.append('new_password', newPass);
            formData.append('new_password_confirmation', confirm);

            fetch('{{ route("staff.changePassword") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: formData
            })
            .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
            .then(function(result) {
                if (result.ok && result.data.success) {
                    document.getElementById('password-form').style.display = 'none';
                    document.getElementById('password-success').style.display = 'block';
                } else {
                    var msg = result.data.error || 'Failed to change password';
                    if (result.data.errors) {
                        var errors = result.data.errors;
                        msg = Object.values(errors).flat().join(', ') || msg;
                    }
                    alert(msg);
                }
            })
            .catch(function(err) {
                console.error('[change password] failed:', err);
                alert('Something went wrong. Please try again.');
            });
        }
    </script>
@endsection
