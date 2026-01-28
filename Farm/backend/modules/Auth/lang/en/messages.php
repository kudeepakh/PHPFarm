<?php

return [
    'auth.register.success' => 'Registration successful',
    'auth.register.email_exists' => 'Email already registered',
    'auth.register.failed' => 'Registration failed',

    'auth.login.success' => 'Login successful',
    'auth.login.failed' => 'Invalid credentials',

    'auth.logout.success' => 'Logout successful',

    'auth.refresh.success' => 'Token refreshed successfully',
    'auth.refresh.failed' => 'Failed to refresh token',

    'auth.password.reset.success' => 'Password reset successful',
    'auth.password.reset.failed' => 'Failed to reset password',

    'auth.not_authenticated' => 'Not authenticated',

    'otp.sent' => 'OTP sent successfully',
    'otp.send_failed' => 'Failed to send OTP',
    'otp.verified' => 'OTP verified successfully',
    'otp.invalid' => 'Invalid OTP',
    'otp.verify_failed' => 'Failed to verify OTP',

    'otp.admin.history.failed' => 'Failed to retrieve OTP history',
    'otp.admin.statistics.failed' => 'Failed to retrieve OTP statistics',
    'otp.admin.blacklist.failed' => 'Failed to retrieve OTP blacklist',
    'otp.admin.blacklist.reason.default' => 'Blacklisted by admin',
    'otp.admin.blacklist.identifier_required' => 'Identifier type and value are required',
    'otp.admin.blacklist.added' => 'Identifier blacklisted successfully',
    'otp.admin.blacklist.add_failed' => 'Failed to add identifier to blacklist',
    'otp.admin.blacklist.removed' => 'Identifier removed from blacklist',
    'otp.admin.blacklist.remove_failed' => 'Failed to remove identifier from blacklist',
    'otp.admin.status.identifier_required' => 'Identifier is required',
    'otp.admin.status.failed' => 'Failed to check OTP status',
    'otp.admin.cleanup.success' => 'OTP cleanup completed',
    'otp.admin.cleanup.failed' => 'Failed to cleanup OTP data',
];
