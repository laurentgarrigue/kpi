<?php

namespace App\Security;

/**
 * Minimum password complexity, shared by the user reset flow and the admin forced password.
 * Keep in sync with app4 composables/usePasswordRules.ts.
 */
final class PasswordPolicy
{
    /**
     * @return string|null Error message, or null if the password is acceptable
     */
    public static function validate(string $password): ?string
    {
        if (mb_strlen($password) < 10) {
            return 'Password must be at least 10 characters';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least 1 uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least 1 lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least 1 digit';
        }
        if (!preg_match('/[!@#$%^&*()\-_=+\[\]{}\\\\|;:\'",.<>?\/~`]/', $password)) {
            return 'Password must contain at least 1 special character';
        }
        return null;
    }
}
