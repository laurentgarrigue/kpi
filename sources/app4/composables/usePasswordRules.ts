import type { Ref } from 'vue'

/**
 * Minimum password complexity (user reset + admin forced password).
 * Keep in sync with api2 src/Security/PasswordPolicy.php.
 */
export function usePasswordRules(password: Ref<string>) {
  const { t } = useI18n()

  const rules = computed(() => [
    { key: 'length', label: t('users.reset_password.rule_length'), valid: password.value.length >= 10 },
    { key: 'uppercase', label: t('users.reset_password.rule_uppercase'), valid: /[A-Z]/.test(password.value) },
    { key: 'lowercase', label: t('users.reset_password.rule_lowercase'), valid: /[a-z]/.test(password.value) },
    { key: 'digit', label: t('users.reset_password.rule_digit'), valid: /\d/.test(password.value) },
    { key: 'special', label: t('users.reset_password.rule_special'), valid: /[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?~`]/.test(password.value) }
  ])

  const allRulesValid = computed(() => rules.value.every(r => r.valid))

  return { rules, allRulesValid }
}
