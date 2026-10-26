<script setup>
import { computed } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    assessment: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const status = computed(() => props.assessment?.status || 'Unable to Assess');

const statusConfig = computed(() => {
    switch (status.value) {
        case 'Supported':
            return {
                label: 'Supported',
                color: 'bg-emerald-100 text-emerald-800 border-emerald-300',
                badgeBg: 'bg-emerald-500',
                icon: '✓',
                summary: 'Klaim didukung secara konsisten oleh candidate passage di Research Library.',
            };
        case 'Contradicted':
            return {
                label: 'Contradicted',
                color: 'bg-rose-100 text-rose-800 border-rose-300',
                badgeBg: 'bg-rose-500',
                icon: '✗',
                summary: 'Klaim bertentangan dengan temuan atau data pada literatur di Research Library.',
            };
        case 'Insufficient Evidence':
            return {
                label: 'Insufficient Evidence',
                color: 'bg-amber-100 text-amber-800 border-amber-300',
                badgeBg: 'bg-amber-500',
                icon: '⚠',
                summary: 'Research Library belum memiliki bukti atau data yang cukup untuk memverifikasi klaim ini.',
            };
        default:
            return {
                label: 'Unable to Assess',
                color: 'bg-slate-100 text-slate-800 border-slate-300',
                badgeBg: 'bg-slate-500',
                icon: '❓',
                summary: 'Konteks klaim terlalu ambigu atau tidak dapat dinilai secara pasti terhadap dokumen yang ada.',
            };
    }
});

const passages = computed(() => {
    return props.assessment?.candidate_passages || [];
});
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-hidden bg-black/40 backdrop-blur-xs flex justify-end transition-opacity"
        data-testid="evidence-assessment-drawer"
        @keydown.esc="emit('close')"
    >
        <div
            class="w-full max-w-2xl bg-white h-full shadow-2xl flex flex-col border-l border-gray-200 transform transition ease-in-out duration-300 font-sans"
        >
            <!-- Drawer Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-slate-50">
                <div class="flex items-center space-x-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 font-bold text-sm">
                        🔍
                    </span>
                    <div>
                        <div class="flex items-center space-x-2">
                            <h3 class="text-base font-bold text-gray-900">Hasil Citation Evidence Checker</h3>
                            <span
                                v-if="!loading"
                                class="inline-flex items-center space-x-1 rounded-full px-2 py-0.5 text-xs font-bold border"
                                :class="statusConfig.color"
                            >
                                <span>{{ statusConfig.icon }}</span>
                                <span>{{ statusConfig.label }}</span>
                            </span>
                        </div>
                        <p class="text-xs text-gray-500">
                            Evaluasi keabsahan klaim faktual terhadap candidate passages dari Research Library.
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-md p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-200/60 transition"
                    title="Tutup"
                    @click="emit('close')"
                >
                    <span class="sr-only">Tutup</span>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex-1 flex flex-col items-center justify-center p-8 space-y-4">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-emerald-600"></div>
                <div class="text-center">
                    <p class="text-sm font-semibold text-gray-800">Menjalankan Evaluasi Gemini 1.5 Pro...</p>
                    <p class="text-xs text-gray-500 mt-1">Mengambil candidate passages dan memverifikasi keterkaitan bukti.</p>
                </div>
            </div>

            <!-- Drawer Content Body -->
            <div v-else class="flex-1 overflow-y-auto p-6 space-y-6">
                <!-- Highlighted Claim Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs space-y-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">
                        Klaim yang Dievaluasi
                    </span>
                    <blockquote class="text-sm font-serif italic text-gray-800 border-l-4 border-indigo-400 pl-3 py-1">
                        "{{ assessment?.claim_text }}"
                    </blockquote>
                </div>

                <!-- Evidence Assessment Status & Explanation Card -->
                <div class="rounded-xl border p-4 space-y-3" :class="statusConfig.color">
                    <div class="flex items-center space-x-2">
                        <span class="text-base font-bold">{{ statusConfig.icon }}</span>
                        <h4 class="text-sm font-bold">Status: {{ statusConfig.label }}</h4>
                    </div>

                    <p class="text-xs leading-relaxed font-sans">
                        {{ assessment?.explanation || statusConfig.summary }}
                    </p>

                    <div v-if="assessment?.cited_page_numbers && assessment.cited_page_numbers.length" class="flex items-center space-x-1.5 text-xs pt-1">
                        <span class="font-bold">Halaman Rujukan:</span>
                        <span
                            v-for="pageNum in assessment.cited_page_numbers"
                            :key="pageNum"
                            class="inline-block px-1.5 py-0.5 rounded font-mono font-bold bg-white/80 border border-current text-[11px]"
                        >
                            Hlm. {{ pageNum }}
                        </span>
                    </div>

                    <div v-if="assessment?.assessed_at" class="text-[10px] text-gray-500 pt-1 border-t border-gray-200/40">
                        Waktu Penilaian: {{ new Date(assessment.assessed_at).toLocaleString() }}
                    </div>
                </div>

                <!-- Traceable Source Excerpts / Candidate Passages -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 flex items-center space-x-1.5">
                            <span>📚</span>
                            <span>Candidate Passages (Bukti Sumber Terlacak)</span>
                        </h4>
                        <span class="text-[11px] font-medium text-gray-500">
                            {{ passages.length }} Passage
                        </span>
                    </div>

                    <div v-if="passages.length === 0" class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-xs text-gray-500">
                        Tidak ada candidate passage yang ditemukan di Research Library untuk klaim ini.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="(passage, idx) in passages"
                            :key="passage.id || idx"
                            class="rounded-lg border border-gray-200 bg-white p-4 shadow-xs space-y-2 transition hover:border-gray-300"
                        >
                            <!-- Source Document & Page Attribution Header -->
                            <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                                <div class="flex items-center space-x-2 truncate mr-2">
                                    <span class="text-indigo-600 font-bold text-xs">📄</span>
                                    <span class="text-xs font-bold text-gray-900 truncate" :title="passage.document_title">
                                        {{ passage.document_title || 'Dokumen Tanpa Judul' }}
                                    </span>
                                </div>
                                <span class="shrink-0 inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-700">
                                    Halaman {{ passage.page_number }}
                                </span>
                            </div>

                            <!-- Source Content Excerpt -->
                            <p class="text-xs text-gray-700 leading-relaxed font-serif bg-slate-50 p-2.5 rounded border border-slate-100">
                                "{{ passage.content }}"
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Drawer Footer -->
            <div class="border-t border-gray-200 px-6 py-3 bg-slate-50 flex items-center justify-end">
                <button
                    type="button"
                    class="rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 text-xs font-semibold transition"
                    @click="emit('close')"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
</template>
