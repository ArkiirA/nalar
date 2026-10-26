<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    project: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'imported']);

// State
const step = ref(1); // 1: upload/parse, 2: preview & edit
const fileInput = ref(null);
const selectedFile = ref(null);
const isParsing = ref(false);
const isConfirming = ref(false);
const errorMessage = ref(null);
const candidateChapters = ref([]);

watch(
    () => props.show,
    (val) => {
        if (val) {
            resetModal();
        }
    }
);

const resetModal = () => {
    step.value = 1;
    selectedFile.value = null;
    isParsing.value = false;
    isConfirming.value = false;
    errorMessage.value = null;
    candidateChapters.value = [];
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const handleFileChange = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    errorMessage.value = null;

    if (file.size > 50 * 1024 * 1024) {
        errorMessage.value = 'Ukuran file draf skripsi melebihi batas 50MB.';
        selectedFile.value = null;
        return;
    }

    const ext = file.name.split('.').pop()?.toLowerCase();
    if (!['docx', 'pdf'].includes(ext)) {
        errorMessage.value = 'Format file tidak didukung. Harap pilih file .docx atau .pdf.';
        selectedFile.value = null;
        return;
    }

    selectedFile.value = file;
};

const parseDraft = async () => {
    if (!selectedFile.value) {
        errorMessage.value = 'Silakan pilih file draf skripsi terlebih dahulu.';
        return;
    }

    isParsing.value = true;
    errorMessage.value = null;

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    try {
        const response = await axios.post(
            route('projects.import.parse', props.project.id),
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            }
        );

        const candidates = response.data?.candidate_chapters || [];
        if (candidates.length === 0) {
            errorMessage.value = 'Tidak ada bab atau teks yang berhasil diekstrak dari dokumen.';
            return;
        }

        candidateChapters.value = candidates.map((c, idx) => ({
            ...c,
            id: c.id || `candidate-${idx + 1}`,
            selected: true,
        }));
        step.value = 2;
    } catch (err) {
        errorMessage.value =
            err.response?.data?.message ||
            'Gagal memindai draf skripsi. Pastikan file tidak rusak.';
    } finally {
        isParsing.value = false;
    }
};

const mergeWithPrevious = (index) => {
    if (index <= 0 || index >= candidateChapters.value.length) return;

    const prev = candidateChapters.value[index - 1];
    const curr = candidateChapters.value[index];

    // Merge content
    const prevContent = prev.content?.content || [];
    const currContent = curr.content?.content || [];

    // Append current content nodes to previous
    prev.content = {
        type: 'doc',
        content: [...prevContent, ...currContent],
    };

    // Merge preview text
    prev.preview_text = `${prev.preview_text}\n\n${curr.preview_text}`;

    // Remove merged candidate
    candidateChapters.value.splice(index, 1);
};

const selectedCount = computed(() => {
    return candidateChapters.value.filter((c) => c.selected).length;
});

const confirmImport = async () => {
    const toImport = candidateChapters.value
        .filter((c) => c.selected && c.title.trim() !== '')
        .map((c) => ({
            title: c.title.trim(),
            content: c.content,
        }));

    if (toImport.length === 0) {
        errorMessage.value = 'Pilih setidaknya satu bab untuk diimpor ke dalam outline.';
        return;
    }

    isConfirming.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.post(
            route('projects.import.confirm', props.project.id),
            { chapters: toImport }
        );

        emit('imported', response.data?.chapters || []);
        emit('close');
        router.reload({ only: ['chapters'] });
    } catch (err) {
        errorMessage.value =
            err.response?.data?.message || 'Gagal menyimpan bab ke dalam outline proyek.';
    } finally {
        isConfirming.value = false;
    }
};

const closeModal = () => {
    if (isParsing.value || isConfirming.value) return;
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="closeModal">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b pb-3 mb-4">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">📥</span>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">
                            Impor Draf Skripsi (DOCX / PDF)
                        </h3>
                        <p class="text-xs text-gray-500">
                            Migrasikan draf skripsi yang sudah ada langsung ke dalam outline Nalar.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="text-gray-400 hover:text-gray-600 text-lg leading-none"
                    @click="closeModal"
                >
                    &times;
                </button>
            </div>

            <!-- Error Notification -->
            <div
                v-if="errorMessage"
                class="mb-4 rounded-md bg-rose-50 p-3 text-xs text-rose-700 border border-rose-200"
                role="alert"
            >
                {{ errorMessage }}
            </div>

            <!-- STEP 1: File Upload & Scanning -->
            <div v-if="step === 1" class="space-y-4">
                <div class="rounded-lg border-2 border-dashed border-gray-300 p-6 text-center hover:border-indigo-400 transition bg-gray-50/50">
                    <div class="flex flex-col items-center justify-center space-y-2">
                        <span class="text-3xl text-gray-400">📄</span>
                        <p class="text-sm font-medium text-gray-700">
                            Pilih file draf skripsi (DOCX atau PDF)
                        </p>
                        <p class="text-xs text-gray-500">
                            Sistem akan mendeteksi penanda bab (<code class="bg-gray-100 px-1 py-0.5 rounded text-indigo-700 font-semibold">BAB [I-V]</code> dan <code class="bg-gray-100 px-1 py-0.5 rounded text-indigo-700 font-semibold">Heading 1</code>) secara otomatis.
                        </p>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".docx,.pdf"
                            class="mt-3 block w-full max-w-xs text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                            data-testid="thesis-import-file-input"
                            @change="handleFileChange"
                        />
                        <span v-if="selectedFile" class="text-xs font-semibold text-emerald-600 mt-2">
                            Terpilih: {{ selectedFile.name }} ({{ (selectedFile.size / (1024 * 1024)).toFixed(2) }} MB)
                        </span>
                    </div>
                </div>

                <div class="rounded-md bg-blue-50 p-3 text-xs text-blue-800 border border-blue-200">
                    <strong>Catatan:</strong> Nalar mendukung file hingga 50MB. Bab yang terdeteksi dapat Anda tinjau, ubah judul, atau gabungkan sebelum dimasukkan ke dalam outline.
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <SecondaryButton :disabled="isParsing" @click="closeModal">
                        Batal
                    </SecondaryButton>
                    <PrimaryButton
                        :disabled="!selectedFile || isParsing"
                        data-testid="thesis-import-scan-btn"
                        @click="parseDraft"
                    >
                        <span v-if="isParsing" class="inline-flex items-center">
                            <span class="mr-1.5 animate-spin">&bull;</span> Memindai Draf...
                        </span>
                        <span v-else>Pindai Draf Skripsi</span>
                    </PrimaryButton>
                </div>
            </div>

            <!-- STEP 2: Interactive Candidate Chapters Preview & Corrections -->
            <div v-else-if="step === 2" class="space-y-4">
                <div class="flex items-center justify-between bg-indigo-50/60 p-3 rounded-md border border-indigo-100 text-xs text-indigo-900">
                    <span>
                        Ditemukan <strong>{{ candidateChapters.length }}</strong> bab kandidat. Silakan verifikasi, edit judul, atau gabungkan bab sebelum diimpor.
                    </span>
                    <span class="font-semibold text-indigo-700">
                        {{ selectedCount }} dari {{ candidateChapters.length }} dipilih
                    </span>
                </div>

                <!-- Candidate Chapters List -->
                <div class="max-h-96 overflow-y-auto space-y-3 pr-1" data-testid="candidate-chapters-list">
                    <div
                        v-for="(candidate, index) in candidateChapters"
                        :key="candidate.id"
                        class="rounded-lg border p-3 transition"
                        :class="candidate.selected ? 'border-gray-200 bg-white shadow-xs' : 'border-gray-100 bg-gray-50/70 opacity-60'"
                    >
                        <!-- Top Row: Selection & Title -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center space-x-2 mt-1">
                                <input
                                    :id="`select-${candidate.id}`"
                                    v-model="candidate.selected"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    :data-testid="`checkbox-${candidate.id}`"
                                />
                                <span class="text-xs font-bold text-gray-500 min-w-12">
                                    #{{ index + 1 }}
                                </span>
                            </div>

                            <div class="flex-1">
                                <TextInput
                                    v-model="candidate.title"
                                    type="text"
                                    class="w-full text-xs font-semibold"
                                    placeholder="Judul Bab..."
                                    :disabled="!candidate.selected"
                                    :data-testid="`title-input-${candidate.id}`"
                                />
                            </div>

                            <!-- Merge Button (for index > 0) -->
                            <button
                                v-if="index > 0"
                                type="button"
                                class="inline-flex items-center text-[11px] font-medium text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded px-2 py-1 transition"
                                title="Gabungkan isi bab ini ke bab sebelumnya"
                                :data-testid="`merge-btn-${candidate.id}`"
                                @click="mergeWithPrevious(index)"
                            >
                                🔗 Gabung ke #{{ index }}
                            </button>
                        </div>

                        <!-- Preview Snippet -->
                        <div class="mt-2 text-[11px] text-gray-600 bg-gray-50 p-2 rounded border border-gray-100 font-mono whitespace-pre-wrap line-clamp-3">
                            {{ candidate.preview_text }}
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-between border-t pt-3">
                    <SecondaryButton :disabled="isConfirming" @click="step = 1">
                        &larr; Unggah File Lain
                    </SecondaryButton>

                    <div class="flex space-x-2">
                        <SecondaryButton :disabled="isConfirming" @click="closeModal">
                            Batal
                        </SecondaryButton>
                        <PrimaryButton
                            :disabled="selectedCount === 0 || isConfirming"
                            data-testid="thesis-import-confirm-btn"
                            @click="confirmImport"
                        >
                            <span v-if="isConfirming" class="inline-flex items-center">
                                <span class="mr-1.5 animate-spin">&bull;</span> Menyimpan...
                            </span>
                            <span v-else>
                                Terapkan ke Outline ({{ selectedCount }} Bab)
                            </span>
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
