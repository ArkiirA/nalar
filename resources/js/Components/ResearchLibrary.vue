<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['select-evidence-context']);

const documents = ref([]);
const isLoading = ref(false);
const isUploading = ref(false);
const uploadError = ref(null);
const fileInput = ref(null);
const selectedDocument = ref(null);
const isDetailModalOpen = ref(false);
const pollInterval = ref(null);

const formatBytes = (bytes) => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

const statusConfig = {
    Queued: {
        label: 'Antrean',
        badge: 'bg-stone-100 text-stone-700 border-stone-200',
        dot: 'bg-stone-400',
    },
    Processing: {
        label: 'Mengekstrak...',
        badge: 'bg-blue-50 text-blue-700 border-blue-200 animate-pulse',
        dot: 'bg-blue-500',
    },
    Processed: {
        label: 'Selesai',
        badge: 'bg-emerald-50 text-emerald-800 border-emerald-200',
        dot: 'bg-emerald-500',
    },
    Partial: {
        label: 'Sebagian',
        badge: 'bg-amber-50 text-amber-800 border-amber-200',
        dot: 'bg-amber-500',
    },
    Failed: {
        label: 'Gagal',
        badge: 'bg-rose-50 text-rose-800 border-rose-200',
        dot: 'bg-rose-500',
    },
};

const fetchDocuments = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('projects.documents.index', props.project.id));
        documents.value = response.data.documents || [];
        checkPollingNeed();
    } catch (e) {
        console.error('Failed to load documents:', e);
    } finally {
        isLoading.value = false;
    }
};

const checkPollingNeed = () => {
    const hasPending = documents.value.some(
        (doc) => doc.status === 'Queued' || doc.status === 'Processing'
    );

    if (hasPending && !pollInterval.value) {
        pollInterval.value = setInterval(async () => {
            try {
                const response = await axios.get(route('projects.documents.index', props.project.id));
                documents.value = response.data.documents || [];
                const stillPending = documents.value.some(
                    (d) => d.status === 'Queued' || d.status === 'Processing'
                );
                if (!stillPending && pollInterval.value) {
                    clearInterval(pollInterval.value);
                    pollInterval.value = null;
                }
            } catch (err) {
                console.error('Polling error:', err);
            }
        }, 3000);
    } else if (!hasPending && pollInterval.value) {
        clearInterval(pollInterval.value);
        pollInterval.value = null;
    }
};

const triggerFileInput = () => {
    if (fileInput.value) {
        fileInput.value.click();
    }
};

const handleFileSelect = (e) => {
    const files = e.target.files;
    if (files && files.length > 0) {
        uploadFile(files[0]);
    }
};

const handleDrop = (e) => {
    e.preventDefault();
    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
        uploadFile(files[0]);
    }
};

const uploadFile = async (file) => {
    if (!file) return;

    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
        uploadError.value = 'Hanya berkas berformat PDF yang diperbolehkan.';
        return;
    }

    if (file.size > 25 * 1024 * 1024) {
        uploadError.value = 'Ukuran berkas melebihi batas maksimal 25 MB.';
        return;
    }

    uploadError.value = null;
    isUploading.value = true;

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await axios.post(
            route('projects.documents.store', props.project.id),
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        );

        if (response.data.document) {
            documents.value.unshift(response.data.document);
            checkPollingNeed();
        }
    } catch (e) {
        uploadError.value = e.response?.data?.errors?.file?.[0] || e.response?.data?.message || 'Gagal mengunggah berkas PDF.';
    } finally {
        isUploading.value = false;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
};

const openDetailModal = async (doc) => {
    try {
        const response = await axios.get(route('projects.documents.show', [props.project.id, doc.id]));
        selectedDocument.value = response.data.document;
        isDetailModalOpen.value = true;
    } catch (e) {
        console.error('Failed to load document details:', e);
    }
};

const retryExtraction = async (doc, e) => {
    e?.stopPropagation();
    try {
        const response = await axios.post(route('projects.documents.retry', [props.project.id, doc.id]));
        const idx = documents.value.findIndex((d) => d.id === doc.id);
        if (idx !== -1) {
            documents.value[idx] = response.data.document;
        }
        checkPollingNeed();
    } catch (err) {
        console.error('Failed to retry extraction:', err);
    }
};

const deleteDocument = async (doc, e) => {
    e?.stopPropagation();
    if (!confirm(`Hapus dokumen "${doc.title}" dari Research Library?`)) {
        return;
    }

    try {
        await axios.delete(route('projects.documents.destroy', [props.project.id, doc.id]));
        documents.value = documents.value.filter((d) => d.id !== doc.id);
        if (selectedDocument.value?.id === doc.id) {
            isDetailModalOpen.value = false;
            selectedDocument.value = null;
        }
    } catch (err) {
        console.error('Failed to delete document:', err);
    }
};

const sendToEvidenceChecker = (pageText) => {
    emit('select-evidence-context', pageText);
    isDetailModalOpen.value = false;
};

onMounted(() => {
    fetchDocuments();
});

onUnmounted(() => {
    if (pollInterval.value) {
        clearInterval(pollInterval.value);
    }
});
</script>

<template>
    <div class="space-y-4 text-xs text-stone-800">
        <!-- Header Badge -->
        <div class="p-3 bg-gradient-to-r from-stone-900 to-stone-800 text-white rounded-lg shadow-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-md bg-stone-700/60 border border-stone-600/60 flex items-center justify-center text-stone-200 text-sm">
                    &#128218;
                </div>
                <div>
                    <h3 class="font-semibold text-white leading-tight">Research Library</h3>
                    <p class="text-[10px] text-stone-400">Penyimpanan Terisolasi Jurnal & Paper PDF</p>
                </div>
            </div>
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                {{ documents.length }} Paper
            </span>
        </div>

        <!-- Upload Dropzone -->
        <div
            @dragover.prevent
            @drop="handleDrop"
            class="border-2 border-dashed border-stone-300 hover:border-indigo-400 rounded-lg p-4 text-center bg-stone-50/50 hover:bg-indigo-50/20 transition cursor-pointer"
            @click="triggerFileInput"
        >
            <input
                ref="fileInput"
                type="file"
                accept=".pdf,application/pdf"
                class="hidden"
                @change="handleFileSelect"
            />

            <div class="flex flex-col items-center justify-center space-y-1.5">
                <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm font-bold">
                    +
                </div>
                <div class="text-xs font-semibold text-stone-800">
                    {{ isUploading ? 'Mengunggah & Memproses PDF...' : 'Klik atau Seret Berkas PDF ke Sini' }}
                </div>
                <p class="text-[10px] text-stone-500">
                    Maksimal 25 MB per dokumen. Teks dan nomor halaman diekstrak otomatis.
                </p>
            </div>

            <div v-if="isUploading" class="mt-3 flex items-center justify-center gap-2 text-indigo-600 text-[11px]">
                <div class="animate-spin w-3.5 h-3.5 border-2 border-indigo-600 border-t-transparent rounded-full"></div>
                <span>Sedang membaca & mengekstrak halaman PDF...</span>
            </div>
        </div>

        <div v-if="uploadError" class="p-2 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[11px]">
            {{ uploadError }}
        </div>

        <!-- Documents List -->
        <div class="space-y-2">
            <div class="flex items-center justify-between text-[11px] font-semibold text-stone-700">
                <span>Daftar Literatur Proyek</span>
                <button
                    type="button"
                    @click="fetchDocuments"
                    class="text-indigo-600 hover:text-indigo-800 font-normal hover:underline"
                    title="Segarkan daftar"
                >
                    Segarkan
                </button>
            </div>

            <!-- Empty State -->
            <div v-if="!isLoading && documents.length === 0" class="rounded-lg border border-dashed border-stone-300 p-6 text-center text-xs text-stone-500">
                <p class="font-medium text-stone-700 mb-1">Belum ada dokumen PDF terunggah</p>
                <p class="text-[11px] text-stone-500">
                    Unggah paper jurnal atau prosiding untuk dijadikan rujukan sitasi dan pembuktian fakta skripsi Anda.
                </p>
            </div>

            <!-- Items -->
            <div v-else class="space-y-2">
                <div
                    v-for="doc in documents"
                    :key="doc.id"
                    @click="openDetailModal(doc)"
                    class="p-2.5 bg-white border border-stone-200 hover:border-indigo-300 rounded-lg shadow-2xs transition cursor-pointer group"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold text-stone-900 text-xs truncate group-hover:text-indigo-600" :title="doc.title">
                                {{ doc.title }}
                            </h4>
                            <p class="text-[10px] text-stone-500 truncate" :title="doc.original_filename">
                                {{ doc.original_filename }}
                            </p>
                        </div>
                        <span
                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-semibold border shrink-0"
                            :class="statusConfig[doc.status]?.badge || 'bg-stone-100 text-stone-700'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusConfig[doc.status]?.dot"></span>
                            {{ statusConfig[doc.status]?.label || doc.status }}
                        </span>
                    </div>

                    <div class="mt-2 flex items-center justify-between text-[10px] text-stone-500 pt-1.5 border-t border-stone-100">
                        <div class="flex items-center gap-2">
                            <span>{{ formatBytes(doc.file_size) }}</span>
                            <span v-if="doc.page_count > 0">&bull; {{ doc.page_count }} Hlm</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="doc.status === 'Failed' || doc.status === 'Partial'"
                                type="button"
                                @click="retryExtraction(doc, $event)"
                                class="text-amber-700 hover:text-amber-900 font-medium hover:underline"
                            >
                                Ulangi
                            </button>
                            <button
                                type="button"
                                @click="deleteDocument(doc, $event)"
                                class="text-rose-600 hover:text-rose-800 font-medium hover:underline"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail & Extracted Text Modal -->
        <Modal :show="isDetailModalOpen" @close="isDetailModalOpen = false">
            <div v-if="selectedDocument" class="p-6 space-y-4 max-h-[85vh] flex flex-col font-sans">
                <!-- Modal Header -->
                <div class="border-b border-stone-200 pb-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-bold text-stone-900">
                                {{ selectedDocument.title }}
                            </h3>
                            <p class="text-xs text-stone-500 mt-0.5">
                                {{ selectedDocument.original_filename }} &bull; {{ formatBytes(selectedDocument.file_size) }} &bull; {{ selectedDocument.page_count }} Halaman
                            </p>
                        </div>
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold border shrink-0"
                            :class="statusConfig[selectedDocument.status]?.badge"
                        >
                            {{ statusConfig[selectedDocument.status]?.label }}
                        </span>
                    </div>

                    <div v-if="selectedDocument.error_message" class="mt-2 p-2 rounded bg-amber-50 text-amber-800 border border-amber-200 text-xs">
                        {{ selectedDocument.error_message }}
                    </div>
                </div>

                <!-- Page-by-Page Extracted Content -->
                <div class="flex-1 overflow-y-auto space-y-4 pr-1 text-xs">
                    <div v-if="!selectedDocument.pages_data || selectedDocument.pages_data.length === 0" class="text-center py-8 text-stone-400">
                        Belum ada teks yang berhasil diekstrak dari dokumen ini.
                    </div>

                    <div
                        v-for="page in selectedDocument.pages_data"
                        :key="page.page"
                        class="p-3 bg-stone-50 border border-stone-200 rounded-lg space-y-2"
                    >
                        <div class="flex items-center justify-between border-b border-stone-200 pb-1.5">
                            <span class="font-bold text-stone-800 text-[11px]">
                                Halaman {{ page.page }} ({{ page.word_count }} kata)
                            </span>
                            <button
                                v-if="page.text"
                                type="button"
                                @click="sendToEvidenceChecker(page.text)"
                                class="text-[10px] font-semibold text-indigo-600 hover:text-indigo-800 hover:underline"
                            >
                                ⚡ Gunakan sebagai Bukti Sitasi
                            </button>
                        </div>
                        <p class="text-[11px] text-stone-700 leading-relaxed font-sans whitespace-pre-line max-h-48 overflow-y-auto">
                            {{ page.text || '(Halaman kosong atau berisi gambar/scan tanpa teks digital)' }}
                        </p>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between border-t border-stone-200 pt-3">
                    <DangerButton @click="deleteDocument(selectedDocument)">
                        Hapus Dokumen
                    </DangerButton>
                    <SecondaryButton @click="isDetailModalOpen = false">
                        Tutup
                    </SecondaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
