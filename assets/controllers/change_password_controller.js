import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'newPasswordInput',
        'repeatPasswordInput',
        'matchStatus',
        'generatedPasswordWarning',
        'lengthRule',
        'strengthRule',
        'strengthCurrent',
        'strengthHelp',
    ];

    connect() {
        this.updatePasswordRules();
        this.updateMatchStatus();
    }

    onNewPasswordChange() {
        this.updatePasswordRules();
        this.updateMatchStatus();
    }

    onRepeatPasswordChange() {
        this.updateMatchStatus();
    }

    toggleVisibility(event) {
        const button = event.currentTarget;
        const targetKey = button.getAttribute('data-password-visibility-toggle') ?? '';
        const targetInput = this.inputForVisibilityKey(targetKey);
        const icon = button.querySelector('i');

        if (!targetInput || !icon) {
            return;
        }

        const isHidden = targetInput.type === 'password';
        targetInput.type = isHidden ? 'text' : 'password';
        icon.classList.toggle('bi-eye', !isHidden);
        icon.classList.toggle('bi-eye-slash', isHidden);
        button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    }

    generatePassword() {
        if (!this.hasNewPasswordInputTarget || !this.hasRepeatPasswordInputTarget) {
            return;
        }

        const generated = generateStrongPassword();
        this.newPasswordInputTarget.value = generated;
        this.repeatPasswordInputTarget.value = generated;

        if (this.hasGeneratedPasswordWarningTarget) {
            this.generatedPasswordWarningTarget.hidden = false;
        }

        this.updatePasswordRules();
        this.updateMatchStatus();
    }

    updatePasswordRules() {
        if (!this.hasNewPasswordInputTarget) {
            return;
        }

        const value = this.newPasswordInputTarget.value ?? '';
        const score = estimateSymfonyPasswordStrength(value);
        const scoreLabel = getStrengthLabel(score);

        if (this.hasLengthRuleTarget) {
            setRuleState(this.lengthRuleTarget, value.length >= 10);
        }

        // Symfony PasswordStrength defaults to minScore=2 (MEDIUM).
        if (this.hasStrengthRuleTarget) {
            setRuleState(this.strengthRuleTarget, score >= 2);
        }

        if (this.hasStrengthCurrentTarget) {
            this.strengthCurrentTarget.textContent = `(Current: ${scoreLabel})`;
        }

        if (this.hasStrengthHelpTarget) {
            this.strengthHelpTarget.textContent = getStrengthHelpMessage(value, score);
        }
    }

    updateMatchStatus() {
        if (!this.hasNewPasswordInputTarget || !this.hasRepeatPasswordInputTarget || !this.hasMatchStatusTarget) {
            return;
        }

        const passwordValue = this.newPasswordInputTarget.value ?? '';
        const repeatValue = this.repeatPasswordInputTarget.value ?? '';
        const isMatch = passwordValue.length > 0 && repeatValue.length > 0 && passwordValue === repeatValue;

        this.matchStatusTarget.textContent = isMatch ? 'Passwords match' : 'Passwords do not match';
        this.matchStatusTarget.classList.toggle('password-match-status-valid', isMatch);
        this.matchStatusTarget.classList.toggle('password-match-status-invalid', !isMatch);
    }

    inputForVisibilityKey(targetKey) {
        if (targetKey === 'new' && this.hasNewPasswordInputTarget) {
            return this.newPasswordInputTarget;
        }

        if (targetKey === 'repeat' && this.hasRepeatPasswordInputTarget) {
            return this.repeatPasswordInputTarget;
        }

        return null;
    }
}

const setRuleState = (element, isValid) => {
    element.classList.toggle('password-rule-valid', isValid);
    element.classList.toggle('password-rule-pending', !isValid);
};

const encodeUtf8 = (value) => {
    if (typeof TextEncoder !== 'undefined') {
        return new TextEncoder().encode(value);
    }

    // Fallback for environments without TextEncoder.
    return Uint8Array.from(Array.from(value).map((char) => char.charCodeAt(0) & 0xff));
};

const estimateSymfonyPasswordStrength = (password) => {
    const bytes = encodeUtf8(password ?? '');
    const length = bytes.length;

    if (!length) {
        return 0;
    }

    const counts = new Map();
    for (const byte of bytes) {
        counts.set(byte, (counts.get(byte) ?? 0) + 1);
    }

    const chars = counts.size;
    let control = 0;
    let digit = 0;
    let upper = 0;
    let lower = 0;
    let symbol = 0;
    let other = 0;

    counts.forEach((_count, code) => {
        if (code < 32 || code === 127) {
            control = 33;
        } else if (code >= 48 && code <= 57) {
            digit = 10;
        } else if (code >= 65 && code <= 90) {
            upper = 26;
        } else if (code >= 97 && code <= 122) {
            lower = 26;
        } else if (code >= 128) {
            other = 128;
        } else {
            symbol = 33;
        }
    });

    const pool = lower + upper + digit + symbol + control + other;
    const entropy = (chars * Math.log2(pool)) + ((length - chars) * Math.log2(chars));

    if (entropy >= 120) {
        return 4;
    }
    if (entropy >= 100) {
        return 3;
    }
    if (entropy >= 80) {
        return 2;
    }
    if (entropy >= 60) {
        return 1;
    }
    return 0;
};

const getStrengthLabel = (score) => {
    switch (score) {
        case 4:
            return 'Very strong';
        case 3:
            return 'Strong';
        case 2:
            return 'Medium';
        case 1:
            return 'Weak';
        default:
            return 'Very weak';
    }
};

const getStrengthHelpMessage = (password, score) => {
    if (!password || password.length === 0) {
        return 'Tip: use a longer password with uncommon words, numbers, and symbols.';
    }

    if (score >= 2) {
        return 'Great. Your password meets the required strength.';
    }

    if (password.length < 14) {
        return 'Add more characters. Longer passwords become much stronger.';
    }

    const uniqueChars = new Set(Array.from(password)).size;
    if (uniqueChars < Math.floor(password.length * 0.6)) {
        return 'Use more unique characters and avoid repeated patterns.';
    }

    return 'Try a passphrase with unrelated words plus numbers/symbols.';
};

const secureRandomIndex = (max) => {
    if (max <= 0) {
        return 0;
    }

    if (window.crypto && window.crypto.getRandomValues) {
        const array = new Uint32Array(1);
        const limit = Math.floor(0x100000000 / max) * max;
        let value = 0;
        do {
            window.crypto.getRandomValues(array);
            value = array[0];
        } while (value >= limit);

        return value % max;
    }

    return Math.floor(Math.random() * max);
};

const pickChar = (characters) => characters[secureRandomIndex(characters.length)];

const shuffle = (characters) => {
    const result = [...characters];

    for (let i = result.length - 1; i > 0; i -= 1) {
        const j = secureRandomIndex(i + 1);
        [result[i], result[j]] = [result[j], result[i]];
    }

    return result;
};

const generateStrongPassword = () => {
    const lower = 'abcdefghijklmnopqrstuvwxyz';
    const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const digits = '0123456789';
    const symbols = '!@#$%^&*()-_=+[]{};:,.?/|~';
    const allChars = `${lower}${upper}${digits}${symbols}`;

    for (let attempt = 0; attempt < 100; attempt += 1) {
        const chars = [
            pickChar(lower),
            pickChar(upper),
            pickChar(digits),
            pickChar(symbols),
        ];

        while (chars.length < 18) {
            chars.push(pickChar(allChars));
        }

        const candidate = shuffle(chars).join('');
        if (candidate.length >= 10 && estimateSymfonyPasswordStrength(candidate) >= 2) {
            return candidate;
        }
    }

    // Deterministic fallback that still meets the backend policy.
    return 'BlueTiger!94MapleRiver';
};
