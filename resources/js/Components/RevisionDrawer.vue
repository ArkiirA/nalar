<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import axios from 'axios';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    project: {
        type: Object,
        required: true,
    },
    chapter: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'restored', 'created-checkpoint']);

// State
const revisions = ref([]);
const isLoading = ref(false);
const isRestoring = ref(false);
const selectedRevision = ref(null);
const isPreviewModalOpen = ref(false);
const isRestoreConfirmOpen = ref(false);
const isCheckpointModalOpen = ref(false);
const checkpointTitle = ref('');
const isSubmittingCheckpoint = ref(false);

// Format date helper
const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Relative time helper
const formatRelativeTime = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diffSec = Math.floor((now - date) / 1000);

    if (diffSec < 60) return 'Baru saja';
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `${diffMin} menit yang lalu`;
    const diffHour = Math.floor(diffMin / 60);
    if (diffHour < 24) return `${diffHour} jam yang lalu`;
    const diffDay = Math.floor(diffHour / 24);
    return `${diffDay} hari yang lalu`;
};

// Fetch revisions
const fetchRevisions = async () => {
    if (!props.project?.id || !props.chapter?.id) return;
    isLoading.value = true;
    try {
        const response = await axios.get(
            route('projects.chapters.revisions.index', [props.project.id, props.chapter.id])
        );
        revisions.value = response.data.revisions || [];
    } catch (e) {
        console.error('Failed to load chapter revisions:', e);
    } finally {
        isLoading.value = false;
    }
};

// Watchers
watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            fetchRevisions();
        }
    }
);

watch(
    () => props.chapter?.id,
    () => {
        if (props.show) {
            fetchRevisions();
        }
    }
);

// Preview revision
const openPreview = async (rev) => {
    try {
        const response = await axios.get(
            route('projects.chapters.revisions.show', [props.project.id, props.chapter.id, rev.id])
        );
        selectedRevision.value = response.data.revision;
        isPreviewModalOpen.value = true;
    } catch (e) {
        console.error('Failed to fetch full revision content:', e);
    }
};

const closePreview = () => {
    isPreviewModalOpen.value = false;
    selectedRevision.value = null;
};

// Restore revision
const confirmRestore = (rev) => {
    selectedRevision.value = rev;
    isRestoreConfirmOpen.value = true;
};

const executeRestore = async () => {
    if (!selectedRevision.value || !props.project?.id || !props.chapter?.id) return;
    isRestoring.value = true;
    try {
        const response = await axios.post(
            route('projects.chapters.revisions.restore', [
                props.project.id,
                props.chapter.id,
                selectedRevision.value.id,
            ])
        );
        emit('restored', response.data.chapter);
        isRestoreConfirmOpen.value = false;
        isPreviewModalOpen.value = false;
        selectedRevision.value = null;
        await fetchRevisions();
    } catch (e) {
        console.error('Failed to restore chapter revision:', e);
    } finally {
        isRestoring.value = false;
    }
};

// Manual checkpoint
const openCheckpointModal = () => {
    checkpointTitle.value = '';
    isCheckpointModalOpen.value = true;
};

const submitCheckpoint = async () => {
    if (!props.project?.id || !props.chapter?.id) return;
    isSubmittingCheckpoint.value = true;
    try {
        const response = await axios.post(
            route('projects.chapters.revisions.checkpoint', [props.project.id, props.chapter.id]),
            { title: checkpointTitle.value }
        );
        isCheckpointModalOpen.value = false;
        checkpointTitle.value = '';
        emit('created-checkpoint', response.data.revision);
        await fetchRevisions();
    } catch (e) {
        console.error('Failed to create manual checkpoint:', e);
    } finally {
        isSubmittingCheckpoint.value = false;
    }
};

// Extract plain text preview snippet from Tiptap JSON
const extractSnippet = (content) => {
    if (!content) return 'Tidak ada pratinjau teks.';
    try {
        const textParts = [];
        const traverse = (node) => {
            if (node.text) textParts.push(node.text);
            if (node.content && Array.isArray(node.content)) {
                node.content.forEach(traverse);
            }
        };
        traverse(content);
        const fullText = textParts.join(' ').trim();
        return fullText ? fullText.slice(0, 300) + (fullText.length > 300 ? '...' : '') : 'Dokumen kosong.';
    } catch {
        return 'Format dokumen tersimpan.';
    }
};

defineExpose({
    fetchRevisions,
    openCheckpointModal,
});
</script>

<template>
    <!-- Slide-over Drawer Backdrop -->
    <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-hidden bg-stone-900/40 backdrop-blur-xs transition-opacity"
        @click="emit('close')"
    >
        <div class="fixed inset-y-0 right-0 flex max-w-full pl-10" @click.stop>
            <div class="w-screen max-w-md bg-white shadow-2xl flex flex-col border-l border-stone-200">
                <!-- Header -->
                <div class="px-5 py-4 border-b border-stone-200 flex items-center justify-between bg-stone-50">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-stone-900">Riwayat Versi Bab</h2>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-stone-200 text-stone-700 font-mono">
                                {{ revisions.length }} versi
                            </span>
                        </div>
                        <p class="text-xs text-stone-500 mt-0.5 truncate max-w-xs">
                            {{ chapter.title || 'Chapter' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="openCheckpointModal"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md hover:bg-emerald-100 transition"
                            title="Simpan titik versi manual"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Checkpoint
                        </button>
                        <button
                            type="button"
                            @click="emit('close')"
                            class="p-1.5 text-stone-400 hover:text-stone-600 rounded-md hover:bg-stone-200 transition"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Info Banner -->
                <div class="px-5 py-2.5 bg-amber-50/70 border-b border-amber-200/60 text-xs text-amber-800 flex items-start gap-2">
                    <svg class="w-4 h-4 shrink-0 text-amber-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Snapshot otomatis disimpan berkala tiap 5 menit saat ngetik (maks 50). Checkpoint manual tidak terhapus.</span>
                </div>

                <!-- Revisions List -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    <div v-if="isLoading" class="py-12 text-center text-xs text-stone-500">
                        <div class="animate-spin w-5 h-5 border-2 border-stone-400 border-t-transparent rounded-full mx-auto mb-2"></div>
                        Memuat riwayat revisi...
                    </div>

                    <div v-else-if="revisions.length === 0" class="py-12 text-center text-stone-500 px-4">
                        <svg class="w-10 h-10 text-stone-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm font-medium text-stone-700">Belum ada snapshot tersimpan</p>
                        <p class="text-xs text-stone-500 mt-1">Snapshot otomatis tersimpan tiap 5 menit pengetikan aktif atau buat manual checkpoint sekarang.</p>
                        <button
                            type="button"
                            @click="openCheckpointModal"
                            class="mt-3 px-3 py-1.5 text-xs bg-emerald-600 text-white rounded-md font-medium hover:bg-emerald-700"
                        >
                            Buat Checkpoint Sekarang
                        </button>
                    </div>

                    <div
                        v-else
                        v-for="rev in revisions"
                        :key="rev.id"
                        class="p-3.5 rounded-lg border border-stone-200 bg-white hover:border-emerald-400 hover:shadow-xs transition flex flex-col gap-2"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold tracking-wide uppercase"
                                        :class="rev.type === 'manual' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-600'"
                                    >
                                        {{ rev.type === 'manual' ? 'Checkpoint' : 'Auto' }}
                                    </span>
                                    <span class="text-xs font-semibold text-stone-900 line-clamp-1">
                                        {{ rev.title || 'Snapshot Otomatis' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 mt-1 text-[11px] text-stone-500 font-mono">
                                    <span>{{ formatRelativeTime(rev.created_at) }}</span>
                                    <span>•</span>
                                    <span>{{ formatDate(rev.created_at) }}</span>
                                </div>
                            </div>

                            <span class="text-[11px] font-medium text-stone-600 bg-stone-50 px-2 py-0.5 rounded border border-stone-200 shrink-0">
                                {{ rev.word_count || 0 }} kata
                            </span>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100">
                            <button
                                type="button"
                                @click="openPreview(rev)"
                                class="px-2.5 py-1 text-xs text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded transition"
                            >
                                Pratinjau
                            </button>
                            <button
                                type="button"
                                @click="confirmRestore(rev)"
                                class="px-2.5 py-1 text-xs text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded font-medium transition"
                            >
                                Pulihkan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <Modal :show="isPreviewModalOpen" @close="closePreview" max-width="2xl">
        <div class="p-6">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <div>
                    <h3 class="text-base font-semibold text-stone-900">
                        Pratinjau: {{ selectedRevision?.title || 'Snapshot Otomatis' }}
                    </h3>
                    <p class="text-xs text-stone-500 mt-0.5">
                        {{ formatDate(selectedRevision?.created_at) }} • {{ selectedRevision?.word_count || 0 }} kata
                    </p>
                </div>
                <button @click="closePreview" class="text-stone-400 hover:text-stone-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Read-only Text Box -->
            <div class="mt-4 p-4 bg-stone-50 rounded-lg border border-stone-200 max-h-96 overflow-y-auto text-sm text-stone-800 leading-relaxed font-serif whitespace-pre-line">
                {{ extractSnippet(selectedRevision?.content) }}
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="closePreview">Tutup</SecondaryButton>
                <PrimaryButton
                    @click="executeRestore"
                    :disabled="isRestoring"
                    class="bg-emerald-600 hover:bg-emerald-700"
                >
                    {{ isRestoring ? 'Memulihkan...' : 'Pulihkan Versi Ini' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>

    <!-- Confirm Restore Modal -->
    <Modal :show="isRestoreConfirmOpen" @close="isRestoreConfirmOpen = false" max-width="md">
        <div class="p-6">
            <div class="flex items-center gap-3 text-amber-600 mb-3">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <h3 class="text-base font-semibold text-stone-900">Konfirmasi Pemulihan Versi</h3>
            </div>

            <p class="text-sm text-stone-600 leading-relaxed">
                Apakah Anda yakin ingin memulihkan bab ini ke versi
                <strong class="text-stone-900 font-semibold">{{ selectedRevision?.title || 'Snapshot Otomatis' }}</strong>?
            </p>
            <p class="text-xs text-stone-500 mt-2 bg-stone-50 p-2.5 rounded border border-stone-200">
                🛡️ <strong>Safety Snapshot:</strong> Draf teks aktif saat ini akan otomatis disimpan sebagai cadangan sebelum pemulihan dilakukan.
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="isRestoreConfirmOpen = false">Batal</SecondaryButton>
                <DangerButton
                    @click="executeRestore"
                    :disabled="isRestoring"
                    class="bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500"
                >
                    {{ isRestoring ? 'Memulihkan...' : 'Ya, Pulihkan Sekarang' }}
                </DangerButton>
            </div>
        </div>
    </Modal>

    <!-- Manual Checkpoint Modal -->
    <Modal :show="isCheckpointModalOpen" @close="isCheckpointModalOpen = false" max-width="md">
        <div class="p-6">
            <h3 class="text-base font-semibold text-stone-900 mb-1">Buat Checkpoint Versi Manual</h3>
            <p class="text-xs text-stone-500 mb-4">
                Beri label penanda sebelum bimbingan dosen atau sebelum melakukan perombakan besar draf.
            </p>

            <div>
                <InputLabel for="checkpoint_title" value="Nama Label Checkpoint (Opsional)" />
                <TextInput
                    id="checkpoint_title"
                    v-model="checkpointTitle"
                    type="text"
                    class="mt-1 block w-full text-sm"
                    placeholder="Contoh: Draf Siap Bimbingan Dosen"
                    @keyup.enter="submitCheckpoint"
                />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="isCheckpointModalOpen = false">Batal</SecondaryButton>
                <PrimaryButton
                    @click="submitCheckpoint"
                    :disabled="isSubmittingCheckpoint"
                    class="bg-emerald-600 hover:bg-emerald-700"
                >
                    {{ isSubmittingCheckpoint ? 'Menyimpan...' : 'Simpan Checkpoint' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
