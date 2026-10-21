<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    chapter: {
        type: Object,
        default: null,
    },
    editorRef: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['replace-text']);

const claimText = ref('');
const referenceContext = ref('');
const isContextOpen = ref(false);
const isLoading = ref(false);
const currentResult = ref(null);
const errorMessage = ref(null);
const checkHistory = ref([]);
const isCopied = ref(false);

// Auto-fill context from chapter text if available
const extractPlainText = (content) => {
    if (!content) return '';
    try {
        const parts = [];
        const traverse = (node) => {
            if (node.text) parts.push(node.text);
            if (node.content && Array.isArray(node.content)) {
                node.content.forEach(traverse);
            }
        };
        traverse(content);
        return parts.join(' ').trim();
    } catch {
        return '';
    }
};

// Grab highlighted text from Tiptap Editor
const grabFromEditor = () => {
    if (!props.editorRef) return;
    const selected = props.editorRef.getSelectedText ? props.editorRef.getSelectedText() : '';
    if (selected && selected.trim()) {
        claimText.value = selected.trim();
        errorMessage.value = null;
    } else {
        errorMessage.value = 'Tidak ada teks yang disorot di editor. Silakan sorot kalimat pada naskah terlebih dahulu.';
        setTimeout(() => {
            errorMessage.value = null;
        }, 3000);
    }
};

// Set claim externally (e.g. from parent component)
const setClaim = (text) => {
    if (text) {
        claimText.value = text.trim();
        verifyClaim();
    }
};

defineExpose({
    setClaim,
    grabFromEditor,
});

const verifyClaim = async () => {
    const text = claimText.value.trim();
    if (!text || isLoading.value) return;

    isLoading.value = true;
    errorMessage.value = null;

    try {
        let contextToSend = referenceContext.value.trim();
        if (!contextToSend && props.chapter?.content) {
            contextToSend = extractPlainText(props.chapter.content).slice(0, 3000);
        }

        const response = await axios.post(
            route('projects.evidence.check', props.project.id),
            {
                claim: text,
                context: contextToSend || null,
                chapter_id: props.chapter?.id || null,
            }
        );

        const assessment = response.data.assessment;
        currentResult.value = {
            claim: text,
            ...assessment,
        };

        // Add to history (unshift up to 10 items)
        checkHistory.value.unshift({
            id: Date.now(),
            claim: text,
            status: assessment.status,
            confidence: assessment.confidence,
            checked_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            data: assessment,
        });

        if (checkHistory.value.length > 10) {
            checkHistory.value.pop();
        }
    } catch (e) {
        console.error('Failed to verify evidence:', e);
        errorMessage.value = e.response?.data?.error || 'Gagal memverifikasi bukti. Silakan coba kembali.';
    } finally {
        isLoading.value = false;
    }
};

const applyRecommendation = () => {
    if (!currentResult.value?.recommendation) return;
    const replacement = currentResult.value.recommendation;

    if (props.editorRef?.replaceSelection) {
        props.editorRef.replaceSelection(replacement);
    } else {
        emit('replace-text', replacement);
    }

    isCopied.value = true;
    setTimeout(() => {
        isCopied.value = false;
    }, 2000);
};

const copyRecommendation = async () => {
    if (!currentResult.value?.recommendation) return;
    try {
        await navigator.clipboard.writeText(currentResult.value.recommendation);
        isCopied.value = true;
        setTimeout(() => {
            isCopied.value = false;
        }, 2000);
    } catch (e) {
        console.error('Failed to copy recommendation:', e);
    }
};

const selectHistoryItem = (item) => {
    claimText.value = item.claim;
    currentResult.value = {
        claim: item.claim,
        ...item.data,
    };
};

const statusMeta = {
    Supported: {
        label: 'Didukung Bukti Kuat (Supported)',
        badgeClass: 'bg-emerald-50 text-emerald-800 border-emerald-200',
        dotClass: 'bg-emerald-500',
        icon: '✓',
    },
    Contradicted: {
        label: 'Bertentangan dengan Bukti (Contradicted)',
        badgeClass: 'bg-rose-50 text-rose-800 border-rose-200',
        dotClass: 'bg-rose-500',
        icon: '✕',
    },
    'Insufficient Evidence': {
        label: 'Bukti Belum Cukup (Insufficient Evidence)',
        badgeClass: 'bg-amber-50 text-amber-800 border-amber-200',
        dotClass: 'bg-amber-500',
        icon: '⚠',
    },
    'Unable to Assess': {
        label: 'Tidak Dapat Dinilai (Unable to Assess)',
        badgeClass: 'bg-slate-100 text-slate-800 border-slate-200',
        dotClass: 'bg-slate-400',
        icon: '?',
    },
};

const currentMeta = computed(() => {
    if (!currentResult.value?.status) return statusMeta['Insufficient Evidence'];
    return statusMeta[currentResult.value.status] || statusMeta['Insufficient Evidence'];
});
</script>

<template>
    <div class="flex flex-col space-y-4 text-xs text-stone-800">
        <!-- Header Banner -->
        <div class="p-3 bg-gradient-to-r from-indigo-900 to-indigo-800 text-white rounded-lg shadow-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-md bg-indigo-500/30 border border-indigo-400/40 flex items-center justify-center text-indigo-200 font-bold text-sm">
                    ✔
                </div>
                <div>
                    <h3 class="font-semibold text-white leading-tight">Citation Evidence Checker</h3>
                    <p class="text-[10px] text-indigo-200">Verifikasi Klaim Faktual & Integritas Bukti</p>
                </div>
            </div>
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                Ready
            </span>
        </div>

        <!-- Claim Input Section -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <label class="font-semibold text-stone-700">
                    Klaim Ilmiah yang Diperiksa:
                </label>
                <button
                    type="button"
                    @click="grabFromEditor"
                    class="text-[11px] font-medium text-indigo-600 hover:text-indigo-800 inline-flex items-center gap-1 hover:underline cursor-pointer"
                    title="Ambil teks yang sedang disorot di Tiptap editor"
                >
                    <span>⚡ Ambil dari Editor</span>
                </button>
            </div>

            <textarea
                v-model="claimText"
                rows="3"
                placeholder="Sorot kalimat pada naskah atau ketik pernyataan klaim di sini..."
                class="w-full rounded-md border-stone-300 text-xs focus:border-indigo-500 focus:ring-indigo-500 p-2 text-stone-800 placeholder-stone-400 resize-y"
            ></textarea>

            <div v-if="errorMessage" class="p-2 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[11px]">
                {{ errorMessage }}
            </div>

            <!-- Context Accordion Toggle -->
            <div class="border border-stone-200 rounded-md p-2 bg-stone-50/60">
                <button
                    type="button"
                    @click="isContextOpen = !isContextOpen"
                    class="w-full flex items-center justify-between text-[11px] font-medium text-stone-600 hover:text-stone-900"
                >
                    <span>Konteks Bukti / Paper Pembanding (Opsional)</span>
                    <span>{{ isContextOpen ? '▲' : '▼' }}</span>
                </button>

                <div v-if="isContextOpen" class="mt-2 space-y-1.5 pt-2 border-t border-stone-200">
                    <p class="text-[10px] text-stone-500">
                        Masukkan kutipan paper, abstrak, atau biarkan kosong untuk menggunakan teks draf bab aktif.
                    </p>
                    <textarea
                        v-model="referenceContext"
                        rows="2"
                        placeholder="Kutipan data riset pembanding..."
                        class="w-full rounded border-stone-300 text-[11px] p-1.5 focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>
                </div>
            </div>

            <!-- Verification Action Button -->
            <button
                type="button"
                :disabled="!claimText.trim() || isLoading"
                @click="verifyClaim"
                class="w-full py-2 px-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white rounded-md font-semibold text-xs shadow-xs transition flex items-center justify-center gap-2 cursor-pointer"
            >
                <div v-if="isLoading" class="animate-spin w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></div>
                <span>{{ isLoading ? 'Menganalisis Bukti Ilmiah...' : 'Verifikasi Bukti Ilmiah' }}</span>
            </button>
        </div>

        <!-- Assessment Result View -->
        <div v-if="currentResult" class="space-y-3 pt-2 border-t border-stone-200">
            <!-- Status Badge & Confidence Score -->
            <div class="p-3 rounded-lg border flex items-center justify-between" :class="currentMeta.badgeClass">
                <div class="flex items-center gap-2">
                    <span class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-xs" :class="currentMeta.dotClass + ' text-white'">
                        {{ currentMeta.icon }}
                    </span>
                    <span class="font-bold text-xs">
                        {{ currentMeta.label }}
                    </span>
                </div>
                <div class="text-right">
                    <span class="text-[10px] block opacity-75">Keyakinan</span>
                    <span class="font-bold text-xs">{{ currentResult.confidence }}%</span>
                </div>
            </div>

            <!-- Scholarly Analysis -->
            <div class="bg-white border border-stone-200 rounded-lg p-3 space-y-1.5 shadow-2xs">
                <h4 class="font-bold text-stone-900 text-xs flex items-center gap-1">
                    <span>📖</span> Analisis Kritis Bukti
                </h4>
                <p class="text-[11px] text-stone-700 leading-relaxed whitespace-pre-line">
                    {{ currentResult.analysis }}
                </p>
            </div>

            <!-- Traceable Excerpts -->
            <div v-if="currentResult.excerpts && currentResult.excerpts.length > 0" class="bg-stone-50 border border-stone-200 rounded-lg p-3 space-y-1.5">
                <h4 class="font-bold text-stone-800 text-[11px] flex items-center gap-1">
                    <span>📑</span> Kutipan / Rujukan Literatur Terkait
                </h4>
                <ul class="space-y-1 pl-3 list-disc text-[11px] text-stone-600">
                    <li v-for="(excerpt, eIdx) in currentResult.excerpts" :key="eIdx" class="leading-snug">
                        {{ excerpt }}
                    </li>
                </ul>
            </div>

            <!-- Academic Rewrite Recommendation -->
            <div v-if="currentResult.recommendation" class="bg-emerald-50/60 border border-emerald-200 rounded-lg p-3 space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="font-bold text-emerald-900 text-[11px] flex items-center gap-1">
                        <span>✨</span> Saran Kalimat Akademik Defensible
                    </h4>
                    <span class="text-[10px] text-emerald-700 font-medium">Bebas Overclaiming</span>
                </div>
                <div class="bg-white p-2 rounded border border-emerald-200 text-[11px] text-stone-800 font-serif italic leading-relaxed">
                    "{{ currentResult.recommendation }}"
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <button
                        type="button"
                        @click="applyRecommendation"
                        class="flex-1 py-1.5 px-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-semibold shadow-xs transition cursor-pointer"
                    >
                        {{ isCopied ? '✓ Diterapkan ke Editor' : 'Ganti Kalimat di Editor' }}
                    </button>
                    <button
                        type="button"
                        @click="copyRecommendation"
                        class="py-1.5 px-3 bg-stone-100 hover:bg-stone-200 border border-stone-300 text-stone-700 rounded text-xs font-medium transition cursor-pointer"
                        title="Salin kalimat rekomendasi"
                    >
                        Salin
                    </button>
                </div>
            </div>
        </div>

        <!-- Verification Session History -->
        <div v-if="checkHistory.length > 1" class="pt-2 border-t border-stone-200 space-y-1.5">
            <h4 class="font-semibold text-stone-600 text-[11px]">Riwayat Pemeriksaan Sesi Ini</h4>
            <div class="space-y-1 max-h-40 overflow-y-auto pr-1">
                <button
                    v-for="item in checkHistory"
                    :key="item.id"
                    type="button"
                    @click="selectHistoryItem(item)"
                    class="w-full text-left p-1.5 rounded border border-stone-200 hover:bg-stone-100 transition flex items-center justify-between text-[10px]"
                >
                    <span class="truncate max-w-[190px] font-medium text-stone-800">
                        {{ item.claim }}
                    </span>
                    <span
                        class="px-1.5 py-0.5 rounded text-[9px] font-semibold shrink-0"
                        :class="statusMeta[item.status]?.badgeClass || 'bg-gray-100 text-gray-700'"
                    >
                        {{ item.status }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
