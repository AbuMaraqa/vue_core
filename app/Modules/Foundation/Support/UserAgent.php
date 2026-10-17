<?php

namespace App\Modules\Foundation\Support;

/**
 * A readable description of a browser's user agent, e.g. "Chrome on Windows",
 * for the login history and the active sessions list.
 *
 * It only recognises the common browsers and platforms; anything else is
 * reported as unknown rather than guessed.
 */
final readonly class UserAgent
{
    /**
     * Checked in order: Edge and Opera also announce themselves as Chrome.
     *
     * @var array<string, string>
     */
    private const array BROWSERS = [
        'Edg' => 'Edge',
        'OPR' => 'Opera',
        'SamsungBrowser' => 'Samsung Internet',
        'Firefox' => 'Firefox',
        'Chrome' => 'Chrome',
        'Safari' => 'Safari',
    ];

    /**
     * @var array<string, string>
     */
    private const array PLATFORMS = [
        'Windows' => 'Windows',
        'iPhone' => 'iOS',
        'iPad' => 'iPadOS',
        'Android' => 'Android',
        'Mac OS X' => 'macOS',
        'Linux' => 'Linux',
    ];

    private function __construct(
        public ?string $browser,
        public ?string $platform,
        public bool $isMobile,
    ) {}

    public static function parse(?string $userAgent): self
    {
        $userAgent ??= '';

        return new self(
            browser: self::firstMatch($userAgent, self::BROWSERS),
            platform: self::firstMatch($userAgent, self::PLATFORMS),
            isMobile: preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent) === 1,
        );
    }

    /**
     * @return array{browser: string|null, platform: string|null, is_mobile: bool}
     */
    public function toArray(): array
    {
        return [
            'browser' => $this->browser,
            'platform' => $this->platform,
            'is_mobile' => $this->isMobile,
        ];
    }

    /**
     * @param  array<string, string>  $candidates
     */
    private static function firstMatch(string $userAgent, array $candidates): ?string
    {
        foreach ($candidates as $needle => $name) {
            if (str_contains($userAgent, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
