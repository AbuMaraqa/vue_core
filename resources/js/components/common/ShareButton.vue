<script setup lang="ts">
/**
 * Share a document's PDF from a phone. Where the browser can share files
 * (the Web Share API), the PDF itself is sent to the app the user picks;
 * elsewhere the PDF is downloaded and WhatsApp opens with the message, so
 * the user only attaches the file.
 */
import Share from '~icons/solar/share-linear';
import { ref } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = withDefaults(
    defineProps<{
        /**
         * The URL of the document's `pdf` route, e.g. `pdf.url({ invoice: invoice.id })`.
         */
        url: string;
        /**
         * The short message sent with the PDF.
         */
        text: string;
        /**
         * The name of the shared file, e.g. the document number.
         */
        filename: string;
        /**
         * The recipient's phone number, to open their WhatsApp chat directly.
         */
        phone?: string | null;
    }>(),
    { phone: null },
);

const { t } = useTranslations();

const sharing = ref(false);

/**
 * The PDF already fetched, kept when the browser refused to share it because
 * the fetch took too long: the next tap shares it at once.
 */
const pendingFile = ref<File | null>(null);

function canShareFiles(): boolean {
    if (typeof navigator.canShare !== 'function') {
        return false;
    }

    return navigator.canShare({
        files: [new File([''], 'document.pdf', { type: 'application/pdf' })],
    });
}

function fileName(): string {
    const name = props.filename.replace(/[\\/:*?"<>|]+/g, '-');

    return name.toLowerCase().endsWith('.pdf') ? name : `${name}.pdf`;
}

async function fetchPdf(): Promise<File> {
    const response = await fetch(props.url, {
        credentials: 'include',
        headers: { Accept: 'application/pdf' },
    });

    if (!response.ok) {
        throw new Error(`The PDF could not be loaded (${response.status}).`);
    }

    return new File([await response.blob()], fileName(), {
        type: 'application/pdf',
    });
}

/**
 * Download the PDF and open WhatsApp with the message.
 */
function shareOnWhatsApp(): void {
    const link = document.createElement('a');
    link.href = `${props.url}${props.url.includes('?') ? '&' : '?'}download=1`;
    link.download = fileName();
    document.body.appendChild(link);
    link.click();
    link.remove();

    let digits = (props.phone ?? '').replace(/\D/g, '');

    if (digits.startsWith('00')) {
        digits = digits.slice(2);
    }

    window.open(
        `https://wa.me/${digits}?text=${encodeURIComponent(props.text)}`,
        '_blank',
        'noopener',
    );
}

async function share(): Promise<void> {
    if (!canShareFiles()) {
        shareOnWhatsApp();

        return;
    }

    sharing.value = true;

    try {
        const file = pendingFile.value ?? (await fetchPdf());
        pendingFile.value = file;

        await navigator.share({
            files: [file],
            title: file.name,
            text: props.text,
        });
        pendingFile.value = null;
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            pendingFile.value = null;
        } else if (
            !(error instanceof DOMException && error.name === 'NotAllowedError')
        ) {
            pendingFile.value = null;
            shareOnWhatsApp();
        }
    } finally {
        sharing.value = false;
    }
}
</script>

<template>
    <BaseButton variant="outline" size="sm" :loading="sharing" @click="share">
        <Share v-if="!sharing" class="size-5" />
        {{ pendingFile && !sharing ? t('Tap again to share') : t('Share') }}
    </BaseButton>
</template>
