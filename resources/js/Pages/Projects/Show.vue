<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChapterEditor from '@/Components/ChapterEditor.vue';
import HermesPlugin from '@/Components/HermesPlugin.vue';
import EvidenceChecker from '@/Components/EvidenceChecker.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    chapters: {
        type: Array,
        default: () => [],
    },
    guidelinePresets: {
        type: Object,
        default: () => ({}),
    },
});

// Layout state
const isOutlineOpen = ref(true);
const isToolsOpen = ref(true);
const activeTab = ref('guidelines'); // 'guidelines' | 'library' | 'evidence' | 'hermes'

// Active chapter & section selection
const selectedChapterId = ref(props.chapters.length > 0 ? props.chapters[0].id : null);
const selectedSectionId = ref(null);
const editorRef = ref(null);
const evidenceCheckerRef = ref(null);

const handleCheckEvidence = (text) => {
    activeTab.value = 'evidence';
    isToolsOpen.value = true;
    if (text && evidenceCheckerRef.value?.setClaim) {
        evidenceCheckerRef.value.setClaim(text);
    }
};

const activeChapter = computed(() => {
    return props.chapters.find((c) => c.id === selectedChapterId.value) || null;
});

const activeSection = computed(() => {
    if (!activeChapter.value || !selectedSectionId.value) return null;
    return activeChapter.value.sections?.find((s) => s.id === selectedSectionId.value) || null;
});

const activeDocumentItem = computed(() => {
    return activeSection.value || activeChapter.value;
});

const flushEditor = async () => {
    if (editorRef.value?.flush) {
        try {
            await editorRef.value.flush();
        } catch (e) {
            console.error('Failed to flush chapter edits:', e);
        }
    }
};

const selectChapter = async (chapter) => {
    if (activeDocumentItem.value?.id === chapter.id && !selectedSectionId.value) return;
    await flushEditor();
    selectedChapterId.value = chapter.id;
    selectedSectionId.value = null;
};

const selectSection = async (chapter, section) => {
    if (activeDocumentItem.value?.id === section.id) return;
    await flushEditor();
    selectedChapterId.value = chapter.id;
    selectedSectionId.value = section.id;
};

// Modals state
const isPresetModalOpen = ref(false);
const isAddModalOpen = ref(false);
const isEditModalOpen = ref(false);
const targetParentId = ref(null);
const targetAddType = ref('chapter'); // 'chapter' | 'section'

// Auto-prompt guideline preset modal on open if not set yet
onMounted(() => {
    if (!props.project.guideline_preset) {
        openPresetModal();
    }
});

// Add chapter/section form
const addForm = useForm({
    title: '',
    parent_id: null,
});

const openAddModal = (type = 'chapter', parentId = null) => {
    targetAddType.value = type;
    targetParentId.value = parentId;
    addForm.reset();
    addForm.parent_id = parentId;
    isAddModalOpen.value = true;
};

const closeAddModal = () => {
    isAddModalOpen.value = false;
    addForm.reset();
};

const submitAdd = () => {
    addForm.post(route('projects.chapters.store', props.project.id), {
        onSuccess: () => closeAddModal(),
    });
};

// Edit title state
const editingItem = ref(null);
const editForm = useForm({
    title: '',
});

const openEditModal = (item) => {
    editingItem.value = item;
    editForm.title = item.title;
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    editingItem.value = null;
    editForm.reset();
};

const submitEdit = () => {
    if (!editingItem.value) return;
    editForm.patch(route('projects.chapters.update', [props.project.id, editingItem.value.id]), {
        onSuccess: () => closeEditModal(),
    });
};

// Delete chapter or section
const deleteItem = (item, isChapter = true) => {
    const label = isChapter ? `Bab "${item.title}" beserta seluruh sub-babnya` : `Sub-bab "${item.title}"`;
    if (confirm(`Hapus ${label}? Tindakan ini tidak dapat dibatalkan.`)) {
        router.delete(route('projects.chapters.destroy', [props.project.id, item.id]), {
            onSuccess: () => {
                if (selectedChapterId.value === item.id) {
                    const remaining = props.chapters.filter((c) => c.id !== item.id);
                    selectedChapterId.value = remaining.length > 0 ? remaining[0].id : null;
                    selectedSectionId.value = null;
                } else if (selectedSectionId.value === item.id) {
                    selectedSectionId.value = null;
                }
            },
        });
    }
};

// Reorder chapters or sections with sequential 1-based order normalization
const moveChapter = (index, direction) => {
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= props.chapters.length) return;

    const reordered = [...props.chapters];
    const [moved] = reordered.splice(index, 1);
    reordered.splice(targetIndex, 0, moved);

    const items = reordered.map((chapter, i) => ({
        id: chapter.id,
        order: i + 1,
        parent_id: null,
    }));

    router.post(route('projects.chapters.reorder', props.project.id), { items });
};

const moveSection = (chapter, sectionIndex, direction) => {
    const sections = chapter.sections || [];
    const targetIndex = direction === 'up' ? sectionIndex - 1 : sectionIndex + 1;
    if (targetIndex < 0 || targetIndex >= sections.length) return;

    const reordered = [...sections];
    const [moved] = reordered.splice(sectionIndex, 1);
    reordered.splice(targetIndex, 0, moved);

    const items = reordered.map((section, i) => ({
        id: section.id,
        order: i + 1,
        parent_id: chapter.id,
    }));

    router.post(route('projects.chapters.reorder', props.project.id), { items });
};

// Preset switching
const presetForm = useForm({
    preset: props.project.guideline_preset || 'universal_s1',
});

const openPresetModal = () => {
    presetForm.preset = props.project.guideline_preset || 'universal_s1';
    isPresetModalOpen.value = true;
};

const closePresetModal = () => {
    isPresetModalOpen.value = false;
};

const submitPreset = () => {
    if (
        props.chapters.length > 0 &&
        !confirm('Menerapkan pedoman kampus baru akan mengatur ulang struktur Bab I - V. Lanjutkan?')
    ) {
        return;
    }

    presetForm.post(route('projects.guideline-preset', props.project.id), {
        onSuccess: () => {
            closePresetModal();
            if (props.chapters.length > 0) {
                selectedChapterId.value = props.chapters[0].id;
                selectedSectionId.value = null;
            }
        },
    });
};

const activePresetInfo = computed(() => {
    if (!props.project.guideline_preset) return null;
    return props.guidelinePresets[props.project.guideline_preset] || null;
});
</script>

<template>
    <Head :title="`${project.title} - Nalar`" />

    <AuthenticatedLayout>
        <!-- Top Workspace Bar -->
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('projects.index')"
                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        &larr; Proyek
                    </Link>
                    <span class="text-gray-300">|</span>
                    <h2 class="text-lg font-bold text-gray-900 truncate max-w-md">
                        {{ project.title }}
                    </h2>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                        @click="openPresetModal"
                    >
                        {{ activePresetInfo?.name || 'Pilih Pedoman' }}
                    </button>
                </div>

                <div class="flex items-center space-x-2">
                    <!-- Toggle Outline -->
                    <button
                        type="button"
                        class="inline-flex items-center rounded border px-2.5 py-1 text-xs font-medium transition"
                        :class="isOutlineOpen ? 'bg-indigo-50 border-indigo-200 text-indigo-700' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'"
                        @click="isOutlineOpen = !isOutlineOpen"
                    >
                        <span class="mr-1">&equiv;</span> Outline
                    </button>

                    <!-- Toggle Tools -->
                    <button
                        type="button"
                        class="inline-flex items-center rounded border px-2.5 py-1 text-xs font-medium transition"
                        :class="isToolsOpen ? 'bg-indigo-50 border-indigo-200 text-indigo-700' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'"
                        @click="isToolsOpen = !isToolsOpen"
                    >
                        Tools &rarr;
                    </button>
                </div>
            </div>
        </template>

        <!-- 3-Column Workspace Shell -->
        <div class="flex h-[calc(100vh-8.5rem)] overflow-hidden bg-slate-100">
            <!-- Left Column: Collapsible Chapter Outline -->
            <aside
                class="flex flex-col border-r border-gray-200 bg-white transition-all duration-200 ease-in-out shrink-0"
                :class="isOutlineOpen ? 'w-80' : 'w-12 items-center'"
            >
                <!-- Outline Header -->
                <div class="flex items-center justify-between border-b border-gray-100 p-3">
                    <div v-if="isOutlineOpen" class="flex items-center space-x-2 overflow-hidden">
                        <span class="font-bold text-xs uppercase tracking-wider text-gray-500">Struktur Skripsi</span>
                    </div>
                    <button
                        type="button"
                        class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        :title="isOutlineOpen ? 'Sembunyikan Outline' : 'Buka Outline'"
                        @click="isOutlineOpen = !isOutlineOpen"
                    >
                        <span class="text-sm">{{ isOutlineOpen ? '&laquo;' : '&raquo;' }}</span>
                    </button>
                </div>

                <!-- Outline Content when expanded -->
                <div v-if="isOutlineOpen" class="flex-1 overflow-y-auto p-3 space-y-3">
                    <!-- Preset Banner & Add Chapter Action -->
                    <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                        <button
                            type="button"
                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                            @click="openPresetModal"
                        >
                            &bull; {{ activePresetInfo?.short_name || 'Ganti Pedoman' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center rounded bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                            @click="openAddModal('chapter')"
                        >
                            + Tambah Bab
                        </button>
                    </div>

                    <!-- Chapters Empty State -->
                    <div
                        v-if="chapters.length === 0"
                        class="rounded-lg border border-dashed border-gray-300 p-4 text-center text-xs text-gray-500"
                    >
                        <p class="mb-2">Belum ada bab dalam outline.</p>
                        <PrimaryButton class="!px-2 !py-1 text-xs" @click="openPresetModal">
                            Terapkan Pedoman Kampus
                        </PrimaryButton>
                    </div>

                    <!-- Chapters & Sections List -->
                    <div v-else class="space-y-2">
                        <div
                            v-for="(chapter, cIndex) in chapters"
                            :key="chapter.id"
                            class="rounded-md border border-gray-200 overflow-hidden bg-white shadow-xs"
                            :class="selectedChapterId === chapter.id && !selectedSectionId ? 'ring-2 ring-indigo-500 border-indigo-500' : ''"
                        >
                            <!-- Chapter Header -->
                            <div
                                class="flex items-center justify-between p-2.5 cursor-pointer hover:bg-gray-50"
                                :class="selectedChapterId === chapter.id && !selectedSectionId ? 'bg-indigo-50/60 font-semibold' : ''"
                                @click="selectChapter(chapter)"
                            >
                                <span class="text-xs text-gray-900 truncate max-w-[180px]" :title="chapter.title">
                                    {{ chapter.title }}
                                </span>

                                <div class="flex items-center space-x-1" @click.stop>
                                    <!-- Reorder Chapter Up -->
                                    <button
                                        type="button"
                                        :disabled="cIndex === 0"
                                        class="p-0.5 text-gray-400 hover:text-gray-700 disabled:opacity-20"
                                        title="Pindah ke Atas"
                                        @click="moveChapter(cIndex, 'up')"
                                    >
                                        &uarr;
                                    </button>
                                    <!-- Reorder Chapter Down -->
                                    <button
                                        type="button"
                                        :disabled="cIndex === chapters.length - 1"
                                        class="p-0.5 text-gray-400 hover:text-gray-700 disabled:opacity-20"
                                        title="Pindah ke Bawah"
                                        @click="moveChapter(cIndex, 'down')"
                                    >
                                        &darr;
                                    </button>
                                    <!-- Edit Chapter Title -->
                                    <button
                                        type="button"
                                        class="p-0.5 text-gray-400 hover:text-indigo-600"
                                        title="Ubah Judul"
                                        @click="openEditModal(chapter)"
                                    >
                                        &#9998;
                                    </button>
                                    <!-- Add Section Under Chapter -->
                                    <button
                                        type="button"
                                        class="p-0.5 text-gray-400 hover:text-green-600 font-bold"
                                        title="Tambah Sub-bab"
                                        @click="openAddModal('section', chapter.id)"
                                    >
                                        +
                                    </button>
                                    <!-- Delete Chapter -->
                                    <button
                                        type="button"
                                        class="p-0.5 text-gray-400 hover:text-red-600"
                                        title="Hapus Bab"
                                        @click="deleteItem(chapter, true)"
                                    >
                                        &times;
                                    </button>
                                </div>
                            </div>

                            <!-- Sections (Sub-bab) -->
                            <div
                                v-if="chapter.sections && chapter.sections.length > 0"
                                class="border-t border-gray-100 bg-gray-50/50 pl-3 pr-2 py-1.5 space-y-1"
                            >
                                <div
                                    v-for="(section, sIndex) in chapter.sections"
                                    :key="section.id"
                                    class="group flex items-center justify-between rounded px-2 py-1 text-xs cursor-pointer transition"
                                    :class="selectedSectionId === section.id ? 'bg-indigo-100/70 font-semibold text-indigo-900' : 'text-gray-700 hover:bg-white'"
                                    @click="selectSection(chapter, section)"
                                >
                                    <span class="truncate max-w-[160px]" :title="section.title">
                                        {{ section.title }}
                                    </span>

                                    <div class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity" @click.stop>
                                        <button
                                            type="button"
                                            :disabled="sIndex === 0"
                                            class="text-gray-400 hover:text-gray-700 disabled:opacity-20"
                                            title="Pindah ke Atas"
                                            @click="moveSection(chapter, sIndex, 'up')"
                                        >
                                            &uarr;
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="sIndex === chapter.sections.length - 1"
                                            class="text-gray-400 hover:text-gray-700 disabled:opacity-20"
                                            title="Pindah ke Bawah"
                                            @click="moveSection(chapter, sIndex, 'down')"
                                        >
                                            &darr;
                                        </button>
                                        <button
                                            type="button"
                                            class="text-gray-400 hover:text-indigo-600"
                                            title="Ubah Judul"
                                            @click="openEditModal(section)"
                                        >
                                            &#9998;
                                        </button>
                                        <button
                                            type="button"
                                            class="text-gray-400 hover:text-red-600"
                                            title="Hapus Sub-bab"
                                            @click="deleteItem(section, false)"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Collapsed view icons -->
                <div v-else class="flex flex-col items-center py-4 space-y-4">
                    <button
                        type="button"
                        class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-gray-50 rounded"
                        title="Buka Outline"
                        @click="isOutlineOpen = true"
                    >
                        &equiv;
                    </button>
                </div>
            </aside>

            <!-- Center Column: Tiptap Chapter Document Editor Stage -->
            <main class="flex-1 overflow-y-auto p-4 md:p-6 flex flex-col items-center">
                <!-- If Active Chapter Selected -->
                <div v-if="activeChapter && activeDocumentItem" class="w-full flex flex-col items-center">
                    <!-- Chapter & Sub-sections Quick Switcher Pill Bar -->
                    <div
                        v-if="activeChapter.sections && activeChapter.sections.length > 0"
                        class="w-full max-w-4xl mb-3 flex items-center justify-between bg-white border border-gray-200 rounded-lg px-3 py-2 font-sans text-xs shadow-xs"
                    >
                        <div class="flex items-center space-x-1.5 overflow-x-auto py-0.5 max-w-[80%]">
                            <span class="text-gray-400 font-semibold uppercase tracking-wider text-[10px] mr-1">Fokus:</span>
                            <button
                                type="button"
                                class="rounded px-2.5 py-1 transition border text-xs whitespace-nowrap"
                                :class="!selectedSectionId ? 'bg-indigo-600 text-white font-semibold border-indigo-600 shadow-xs' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'"
                                @click="selectChapter(activeChapter)"
                            >
                                {{ activeChapter.title }}
                            </button>
                            <button
                                v-for="section in activeChapter.sections"
                                :key="section.id"
                                type="button"
                                class="rounded px-2.5 py-1 transition border text-xs whitespace-nowrap"
                                :class="selectedSectionId === section.id ? 'bg-indigo-600 text-white font-semibold border-indigo-600 shadow-xs' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'"
                                @click="selectSection(activeChapter, section)"
                            >
                                {{ section.title }}
                            </button>
                        </div>
                        <button
                            type="button"
                            class="text-indigo-600 hover:text-indigo-800 font-medium text-xs whitespace-nowrap ml-2"
                            @click="openAddModal('section', activeChapter.id)"
                        >
                            + Sub-bab
                        </button>
                    </div>

                    <!-- Tiptap Editor with debounced auto-save -->
                    <ChapterEditor
                        ref="editorRef"
                        :project="project"
                        :chapter="activeDocumentItem"
                        @check-evidence="handleCheckEvidence"
                    />
                </div>

                <!-- No Chapter in Project: Contextual Onboarding Empty State -->
                <div
                    v-else
                    class="w-full max-w-4xl min-h-[850px] bg-white shadow-sm border border-gray-200 rounded p-12 md:p-16 flex flex-col justify-center items-center text-center font-sans py-8"
                >
                    <div class="max-w-xl">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 mb-4 font-bold text-lg">
                            N
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">
                            Proyek Skripsi: {{ project.title }}
                        </h2>
                        <p class="text-sm text-gray-600 mb-8">
                            Mulai menyusun naskah tugas akhir dengan perlindungan isolasi proyek penuh, pedoman baku kampus, dan verifikasi bukti sitasi.
                        </p>

                        <!-- 4 Contextual Onboarding Prompt Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left">
                            <div class="rounded-lg border border-gray-200 p-4 bg-slate-50 hover:bg-indigo-50/40 transition cursor-pointer" @click="openPresetModal">
                                <div class="flex items-center space-x-2 font-semibold text-xs text-indigo-700 mb-1">
                                    <span>1.</span>
                                    <span>Pilih Pedoman Kampus</span>
                                </div>
                                <p class="text-xs text-gray-600">
                                    Pilih preset universitas Anda (UI, UGM, ITB, Gunadarma, Universal) untuk memuat outline Bab I - V secara otomatis.
                                </p>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4 bg-slate-50 hover:bg-indigo-50/40 transition cursor-pointer" @click="openAddModal('chapter')">
                                <div class="flex items-center space-x-2 font-semibold text-xs text-indigo-700 mb-1">
                                    <span>2.</span>
                                    <span>Susun Outline Bab</span>
                                </div>
                                <p class="text-xs text-gray-600">
                                    Sesuaikan susunan bab atau tambah sub-bab baru sesuai panduan fakultas Anda.
                                </p>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4 bg-slate-50 hover:bg-indigo-50/40 transition cursor-pointer" @click="activeTab = 'library'">
                                <div class="flex items-center space-x-2 font-semibold text-xs text-indigo-700 mb-1">
                                    <span>3.</span>
                                    <span>Research Library</span>
                                </div>
                                <p class="text-xs text-gray-600">
                                    Unggah paper PDF dan kelola metadata sitasi untuk rujukan ilmiah.
                                </p>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4 bg-slate-50 hover:bg-indigo-50/40 transition cursor-pointer" @click="activeTab = 'evidence'">
                                <div class="flex items-center space-x-2 font-semibold text-xs text-indigo-700 mb-1">
                                    <span>4.</span>
                                    <span>Cek Bukti Sitasi</span>
                                </div>
                                <p class="text-xs text-gray-600">
                                    Validasi klaim faktual argumen skripsi Anda terhadap paper riset terunggah.
                                </p>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-center space-x-3">
                            <PrimaryButton @click="openPresetModal">
                                Terapkan Pedoman Kampus
                            </PrimaryButton>
                            <SecondaryButton @click="openAddModal('chapter')">
                                Buat Bab Baru
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Right Column: Expandable Tools Drawer -->
            <aside
                class="flex flex-col border-l border-gray-200 bg-white transition-all duration-200 ease-in-out shrink-0"
                :class="isToolsOpen ? (activeTab === 'hermes' || activeTab === 'evidence' ? 'w-96' : 'w-80') : 'w-12 items-center'"
            >
                <!-- Drawer Header -->
                <div class="flex items-center justify-between border-b border-gray-100 p-3">
                    <div v-if="isToolsOpen" class="flex items-center space-x-1">
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-xs font-semibold transition"
                            :class="activeTab === 'guidelines' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500 hover:text-gray-700'"
                            @click="activeTab = 'guidelines'"
                        >
                            Pedoman
                        </button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-xs font-semibold transition"
                            :class="activeTab === 'library' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500 hover:text-gray-700'"
                            @click="activeTab = 'library'"
                        >
                            Library
                        </button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-xs font-semibold transition"
                            :class="activeTab === 'evidence' ? 'bg-indigo-50 text-indigo-700 font-bold border border-indigo-200' : 'text-gray-500 hover:text-gray-700'"
                            @click="activeTab = 'evidence'"
                        >
                            Evidence
                        </button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-xs font-semibold transition"
                            :class="activeTab === 'hermes' ? 'bg-emerald-50 text-emerald-700 font-bold border border-emerald-200' : 'text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50/50'"
                            @click="activeTab = 'hermes'"
                        >
                            Hermes AI
                        </button>
                    </div>

                    <button
                        type="button"
                        class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        :title="isToolsOpen ? 'Sembunyikan Panel' : 'Buka Panel'"
                        @click="isToolsOpen = !isToolsOpen"
                    >
                        <span class="text-sm">{{ isToolsOpen ? '&raquo;' : '&laquo;' }}</span>
                    </button>
                </div>

                <!-- Expanded Tools Content -->
                <div v-if="isToolsOpen" class="flex-1 overflow-y-auto p-4">
                    <!-- Tab: Guidelines Profile -->
                    <div v-if="activeTab === 'guidelines'" class="space-y-4">
                        <div class="rounded-lg bg-indigo-50/50 p-3 border border-indigo-100">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-indigo-900">Pedoman Aktif</span>
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                                    @click="openPresetModal"
                                >
                                    Ubah
                                </button>
                            </div>
                            <p class="text-sm font-semibold text-gray-900">
                                {{ activePresetInfo?.name || 'Belum Diatur' }}
                            </p>
                            <p class="text-xs text-gray-600 mt-1">
                                {{ activePresetInfo?.description }}
                            </p>
                        </div>

                        <!-- Formatting specs -->
                        <div v-if="activePresetInfo" class="space-y-2 text-xs">
                            <h4 class="font-bold text-gray-700 uppercase tracking-wide">Ketentuan Format</h4>
                            <div class="grid grid-cols-2 gap-2 text-gray-600">
                                <div class="rounded bg-gray-50 p-2">
                                    <span class="block text-gray-400">Gaya Sitasi:</span>
                                    <span class="font-medium text-gray-800">{{ activePresetInfo.citation_style }}</span>
                                </div>
                                <div class="rounded bg-gray-50 p-2">
                                    <span class="block text-gray-400">Font:</span>
                                    <span class="font-medium text-gray-800">{{ activePresetInfo.formatting.font_family }} {{ activePresetInfo.formatting.font_size }}</span>
                                </div>
                                <div class="rounded bg-gray-50 p-2">
                                    <span class="block text-gray-400">Spasi:</span>
                                    <span class="font-medium text-gray-800">{{ activePresetInfo.formatting.line_spacing }}</span>
                                </div>
                                <div class="rounded bg-gray-50 p-2">
                                    <span class="block text-gray-400">Kertas:</span>
                                    <span class="font-medium text-gray-800">{{ activePresetInfo.formatting.paper_size }}</span>
                                </div>
                            </div>

                            <div class="rounded bg-gray-50 p-2 mt-2">
                                <span class="block text-gray-400 mb-1">Margin Standar:</span>
                                <div class="flex justify-between text-gray-700">
                                    <span>Atas: {{ activePresetInfo.formatting.margins.top }}</span>
                                    <span>Kiri: {{ activePresetInfo.formatting.margins.left }}</span>
                                    <span>Bawah: {{ activePresetInfo.formatting.margins.bottom }}</span>
                                    <span>Kanan: {{ activePresetInfo.formatting.margins.right }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <SecondaryButton class="w-full justify-center text-xs" @click="openPresetModal">
                                Ganti Profil Pedoman Kampus
                            </SecondaryButton>
                        </div>
                    </div>

                    <!-- Tab: Research Library -->
                    <div v-else-if="activeTab === 'library'" class="space-y-4">
                        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-xs text-gray-500">
                            <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 mb-3">
                                &#128218;
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-1">Research Library Kosong</h4>
                            <p class="text-xs text-gray-500 mb-4">
                                Unggah dokumen PDF jurnal ilmiah untuk membangun basis referensi terisolasi proyek Anda.
                            </p>
                            <SecondaryButton disabled class="text-xs opacity-60 cursor-not-allowed">
                                Unggah PDF Dokumen (Segera hadir)
                            </SecondaryButton>
                        </div>
                    </div>

                    <!-- Tab: Evidence Checker -->
                    <div v-else-if="activeTab === 'evidence'">
                        <EvidenceChecker
                            ref="evidenceCheckerRef"
                            :project="project"
                            :chapter="activeDocumentItem"
                            :editor-ref="editorRef"
                        />
                    </div>

                    <!-- Tab: Hermes Copilot Plugin -->
                    <div v-else-if="activeTab === 'hermes'" class="h-full">
                        <HermesPlugin
                            :project="project"
                            :chapter="activeDocumentItem"
                        />
                    </div>
                </div>

                <!-- Collapsed view icons -->
                <div v-else class="flex flex-col items-center py-4 space-y-4">
                    <button
                        type="button"
                        class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-gray-50 rounded"
                        title="Buka Tools"
                        @click="isToolsOpen = true"
                    >
                        Tools
                    </button>
                </div>
            </aside>
        </div>

        <!-- Modal: Select / Switch Campus Guideline Preset -->
        <Modal :show="isPresetModalOpen" @close="closePresetModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-2">Pilih Pedoman Kampus</h2>
                <p class="text-xs text-gray-600 mb-4">
                    Pilih profil pedoman penulisan tugas akhir sivitas akademika universitas Anda. Nalar akan otomatis memformat margin, font, dan struktur bab (Bab I - V).
                </p>

                <form @submit.prevent="submitPreset">
                    <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                        <label
                            v-for="(preset, key) in guidelinePresets"
                            :key="key"
                            class="flex items-start p-3 border rounded-lg cursor-pointer hover:bg-indigo-50/40 transition"
                            :class="presetForm.preset === key ? 'border-indigo-600 bg-indigo-50/60 ring-1 ring-indigo-600' : 'border-gray-200'"
                        >
                            <input
                                v-model="presetForm.preset"
                                type="radio"
                                name="guideline_preset"
                                :value="key"
                                class="mt-1 text-indigo-600 focus:ring-indigo-500"
                            />
                            <div class="ml-3 flex-1">
                                <span class="block text-sm font-bold text-gray-900">
                                    {{ preset.name }}
                                </span>
                                <span class="block text-xs text-gray-500 mt-0.5">
                                    {{ preset.description }}
                                </span>
                                <div class="mt-2 flex flex-wrap gap-2 text-[11px] text-gray-600">
                                    <span class="rounded bg-gray-100 px-2 py-0.5">Sitasi: {{ preset.citation_style }}</span>
                                    <span class="rounded bg-gray-100 px-2 py-0.5">Margin: {{ preset.formatting.margins.left }} / {{ preset.formatting.margins.top }} / {{ preset.formatting.margins.bottom }} / {{ preset.formatting.margins.right }}</span>
                                </div>
                            </div>
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="closePresetModal">Batal</SecondaryButton>
                        <PrimaryButton :disabled="presetForm.processing">
                            Terapkan Pedoman
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal: Add Chapter or Section -->
        <Modal :show="isAddModalOpen" @close="closeAddModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-2">
                    {{ targetAddType === 'chapter' ? 'Tambah Bab Baru' : 'Tambah Sub-bab Baru' }}
                </h2>
                <p class="text-xs text-gray-600 mb-4">
                    Masukkan judul {{ targetAddType === 'chapter' ? 'bab' : 'sub-bab' }} sesuai buku pedoman skripsi fakultas Anda.
                </p>

                <form @submit.prevent="submitAdd">
                    <div>
                        <InputLabel
                            for="add_title"
                            :value="targetAddType === 'chapter' ? 'Judul Bab (contoh: BAB VI PENELITIAN LANJUTAN)' : 'Judul Sub-bab (contoh: 1.7 Hipotesis Kerja)'"
                        />
                        <TextInput
                            id="add_title"
                            v-model="addForm.title"
                            type="text"
                            class="mt-1 block w-full text-sm"
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="addForm.errors.title" />
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="closeAddModal">Batal</SecondaryButton>
                        <PrimaryButton :disabled="addForm.processing">Simpan</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal: Edit Chapter / Section Title -->
        <Modal :show="isEditModalOpen" @close="closeEditModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-2">Ubah Judul</h2>

                <form @submit.prevent="submitEdit">
                    <div>
                        <InputLabel for="edit_title" value="Judul Baru" />
                        <TextInput
                            id="edit_title"
                            v-model="editForm.title"
                            type="text"
                            class="mt-1 block w-full text-sm"
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="editForm.errors.title" />
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="closeEditModal">Batal</SecondaryButton>
                        <PrimaryButton :disabled="editForm.processing">Simpan Perubahan</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
