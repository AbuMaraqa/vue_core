import type { Device } from '@/types';

/**
 * Describe a device for people, e.g. "Chrome on Windows".
 */
export function describeDevice(
    device: Device,
    t: (key: string, replacements?: Record<string, string>) => string,
): string {
    const browser = device.browser ?? t('Unknown browser');

    return device.platform
        ? t(':browser on :platform', { browser, platform: device.platform })
        : browser;
}
