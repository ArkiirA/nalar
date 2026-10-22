<script setup>
import { computed } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    proposal: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['accept', 'reject', 'close']);

const diffChunks = computed(() => {
    return props.proposal?.diff?.chunks || [];
});

const originalChunks = computed(() => {
    // In original view, show common and removed chunks
    return diffChunks.value.filter((c) => c.type === 'common' || c.type === 'removed');
});

const proposedChunks = computed(() => {
    // In proposed view, show common and added chunks
    return diffChunks.value.filter((c) => c.type === 'common' || c.type === 'added');
});

const stats = computed(() => {
    return props.proposal?.diff?.stats || { additions: 0, deletions: 0, unchanged: 0 };
});

const candidatePassages = computed(() => {
    return props.proposal?.candidate_passages || [];
});

const isEvidenceBased = computed(() => {
    return props.proposal?.mode === 'evidence_based';
});
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-hidden bg-black/40 backdrop-blur-xs flex justify-end transition-opacity"
        data-testid="proposal-diff-drawer"
        @keydown.esc="emit('reject')"
    >
        <div
            class="w-full max-w-4xl bg-white h-full shadow-2xl flex flex-col border-l border-gray-200 transform transition ease-in-out duration-300"
        >
            <!-- Drawer Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-slate-50">
                <div class="flex items-center space-x-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 font-bold text-sm">
                        ✨
                    </span>
                    <div>
                        <div class="flex items-center space-x-2">
                            <h3 class="text-base font-bold text-gray-900">Usulan Revisi Teks AI</h3>
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="isEvidenceBased ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                            >
                                {{ isEvidenceBased ? 'Evidence-Based Mode' : 'Exploratory Mode' }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500">
                            Tinjau perbandingan perubahan teks berdampingan sebelum menerapkannya ke naskah bab.
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-md p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-200/60 transition"
                    title="Tutup (Reject)"
                    @click="emit('reject')"
                >
                    <span class="sr-only">Tutup</span>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Drawer Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <!-- Proposal Explanation & Stats Banner -->
                <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-4 space-y-2">
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-indigo-900">Alasan & Catatan Usulan</span>
                            <p class="text-xs text-indigo-950 mt-0.5 leading-relaxed">
                                {{ proposal?.explanation || 'Revisi gaya bahasa dan ketepatan argumen akademis.' }}
                            </p>
                        </div>
                        <div class="flex items-center space-x-2 shrink-0 ml-4 text-xs font-mono font-medium">
                            <span class="inline-flex items-center rounded bg-emerald-100 px-2 py-0.5 text-emerald-800">
                                +{{ stats.additions }} kata/karakter
                            </span>
                            <span class="inline-flex items-center rounded bg-red-100 px-2 py-0.5 text-red-800">
                                -{{ stats.deletions }} kata/karakter
                            </span>
                        </div>
                    </div>

                    <!-- Referenced Literature in Evidence-Based Mode -->
                    <div v-if="candidatePassages.length > 0" class="pt-2 border-t border-indigo-200/60 mt-2">
                        <span class="text-[11px] font-bold text-indigo-900 block mb-1">
                            📚 Bukti Pendukung dari Research Library:
                        </span>
                        <div class="space-y-1.5 max-h-32 overflow-y-auto">
                            <div
                                v-for="(passage, pIdx) in candidatePassages"
                                :key="passage.id || pIdx"
                                class="rounded bg-white/80 p-2 border border-indigo-100 text-xs text-gray-700 space-y-0.5"
                            >
                                <div class="flex items-center justify-between text-[11px] font-semibold text-indigo-700">
                                    <span class="truncate">{{ passage.document_title || 'Dokumen' }}</span>
                                    <span>Halaman {{ passage.page_number }}</span>
                                </div>
                                <p class="text-[11px] text-gray-600 line-clamp-2 leading-relaxed">
                                    {{ passage.content }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Side-by-Side Comparison Container -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Left Column: Current Text (Original with Deletions) -->
                    <div class="flex flex-col rounded-lg border border-red-200 bg-white overflow-hidden shadow-xs">
                        <div class="flex items-center justify-between bg-red-50/70 px-4 py-2 border-b border-red-100">
                            <span class="text-xs font-bold text-red-900 uppercase tracking-wider">
                                Teks Saat Ini (Original)
                            </span>
                            <span class="text-[11px] text-red-700 font-medium">Sebelum Perubahan</span>
                        </div>
                        <div class="flex-1 p-4 text-sm leading-relaxed text-gray-800 font-serif overflow-y-auto whitespace-pre-wrap min-h-[220px]">
                            <template v-for="(chunk, idx) in originalChunks" :key="idx">
                                <del
                                    v-if="chunk.type === 'removed'"
                                    class="bg-red-100 text-red-800 line-through rounded px-0.5 font-medium"
                                >{{ chunk.value }}</del>
                                <span v-else>{{ chunk.value }}</span>
                            </template>
                        </div>
                    </div>

                    <!-- Right Column: Proposed AI Revision (with Additions) -->
                    <div class="flex flex-col rounded-lg border border-emerald-200 bg-white overflow-hidden shadow-xs">
                        <div class="flex items-center justify-between bg-emerald-50/70 px-4 py-2 border-b border-emerald-100">
                            <span class="text-xs font-bold text-emerald-900 uppercase tracking-wider">
                                Usulan Revisi AI (Proposed)
                            </span>
                            <span class="text-[11px] text-emerald-700 font-medium">Setelah Perubahan</span>
                        </div>
                        <div class="flex-1 p-4 text-sm leading-relaxed text-gray-800 font-serif overflow-y-auto whitespace-pre-wrap min-h-[220px]">
                            <template v-for="(chunk, idx) in proposedChunks" :key="idx">
                                <ins
                                    v-if="chunk.type === 'added'"
                                    class="bg-emerald-100 text-emerald-800 no-underline font-medium rounded px-0.5"
                                >{{ chunk.value }}</ins>
                                <span v-else>{{ chunk.value }}</span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Drawer Footer Actions -->
            <div class="px-6 py-4 border-t border-gray-200 bg-slate-50 flex items-center justify-between">
                <span class="text-xs text-gray-500">
                    Memilih <strong class="text-gray-700">Accept</strong> akan memperbarui teks Chapter Document Anda.
                </span>

                <div class="flex items-center space-x-3">
                    <button
                        type="button"
                        data-testid="reject-proposal"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition shadow-xs"
                        @click="emit('reject')"
                    >
                        Reject
                    </button>
                    <button
                        type="button"
                        data-testid="accept-proposal"
                        class="rounded-lg bg-indigo-600 px-5 py-2 text-xs font-semibold text-white hover:bg-indigo-700 transition shadow-xs focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="emit('accept', proposal?.proposed_text)"
                    >
                        Accept
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
