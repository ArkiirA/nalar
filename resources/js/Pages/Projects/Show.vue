<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChapterEditor from '@/Components/ChapterEditor.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';

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
    documents: {
        type: Array,
        default: () => [],
    },
    references: {
        type: Array,
        default: () => [],
    },
});

// Layout state
const isOutlineOpen = ref(true);
const isToolsOpen = ref(true);
const activeTab = ref('guidelines'); // 'guidelines' | 'library' | 'evidence'

// Active chapter & section selection
const selectedChapterId = ref(props.chapters.length > 0 ? props.chapters[0].id : null);
const selectedSectionId = ref(null);
const editorRef = ref(null);

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

// Revisions & Snapshots state
const revisions = ref([]);
const isLoadingRevisions = ref(false);
const isSaveVersionModalOpen = ref(false);
const saveVersionTitle = ref('');
const saveVersionError = ref('');
const isSavingVersion = ref(false);
const previewRevision = ref(null);
const isRestoring = ref(false);

const fetchRevisions = async (targetId = activeDocumentItem.value?.id) => {
    if (!targetId) {
        revisions.value = [];
        return;
    }
    isLoadingRevisions.value = true;
    try {
        const response = await axios.get(route('projects.chapters.revisions.index', [props.project.id, targetId]));
        revisions.value = response.data.revisions || [];
    } catch (err) {
        console.error('Failed to fetch revisions:', err);
    } finally {
        isLoadingRevisions.value = false;
    }
};

const openRevisionsDrawer = () => {
    isToolsOpen.value = true;
    activeTab.value = 'revisions';
    fetchRevisions();
};

const openSaveVersionModal = () => {
    saveVersionTitle.value = '';
    saveVersionError.value = '';
    isSaveVersionModalOpen.value = true;
};

const closeSaveVersionModal = () => {
    isSaveVersionModalOpen.value = false;
    saveVersionTitle.value = '';
    saveVersionError.value = '';
};

const submitSaveVersion = async () => {
    if (!saveVersionTitle.value.trim()) {
        saveVersionError.value = 'Judul snapshot checkpoint wajib diisi.';
        return;
    }

    if (!activeDocumentItem.value) return;

    isSavingVersion.value = true;
    saveVersionError.value = '';

    try {
        await flushEditor();
        await axios.post(
            route('projects.chapters.revisions.store', [props.project.id, activeDocumentItem.value.id]),
            { title: saveVersionTitle.value.trim() },
            { headers: { Accept: 'application/json' } }
        );
        closeSaveVersionModal();
        await fetchRevisions();
        isToolsOpen.value = true;
        activeTab.value = 'revisions';
    } catch (err) {
        saveVersionError.value = err.response?.data?.message || 'Gagal menyimpan snapshot versi.';
    } finally {
        isSavingVersion.value = false;
    }
};

const selectPreviewRevision = async (rev) => {
    if (!activeDocumentItem.value) return;
    try {
        const response = await axios.get(
            route('projects.chapters.revisions.show', [props.project.id, activeDocumentItem.value.id, rev.id])
        );
        previewRevision.value = response.data.revision;
    } catch (err) {
        console.error('Failed to load revision details:', err);
    }
};

const closePreview = () => {
    previewRevision.value = null;
};

const restoreRevision = async (rev) => {
    if (!rev || !activeDocumentItem.value) return;

    if (!confirm(`Restore this version ("${rev.title}")? Konten dokumen akan digantikan dengan snapshot ini.`)) {
        return;
    }

    isRestoring.value = true;
    try {
        const response = await axios.post(
            route('projects.chapters.revisions.restore', [props.project.id, activeDocumentItem.value.id, rev.id]),
            {},
            { headers: { Accept: 'application/json' } }
        );

        if (editorRef.value?.setContent) {
            editorRef.value.setContent(response.data.chapter.content);
        }
        activeDocumentItem.value.content = response.data.chapter.content;
        closePreview();
        await fetchRevisions();
    } catch (err) {
        console.error('Failed to restore revision:', err);
        alert('Gagal memulihkan versi: ' + (err.response?.data?.message || err.message));
    } finally {
        isRestoring.value = false;
    }
};

const getPreviewBlocks = (doc) => {
    if (!doc || !doc.content || !Array.isArray(doc.content)) {
        return [{ type: 'paragraph', text: 'Tidak ada konten teks tersimpan.' }];
    }

    const extractText = (node) => {
        if (!node) return '';
        if (node.text) return node.text;
        if (node.content && Array.isArray(node.content)) {
            return node.content.map(extractText).join('');
        }
        return '';
    };

    const blocks = [];
    for (const node of doc.content) {
        if (node.type === 'heading') {
            const txt = extractText(node).trim();
            if (txt) blocks.push({ type: 'heading', level: node.attrs?.level || 1, text: txt });
        } else if (node.type === 'paragraph') {
            const txt = extractText(node).trim();
            if (txt) blocks.push({ type: 'paragraph', text: txt });
        } else if (node.type === 'bulletList' || node.type === 'orderedList') {
            const items = (node.content || []).map((li) => extractText(li).trim()).filter(Boolean);
            if (items.length > 0) blocks.push({ type: node.type, items });
        } else if (node.type === 'table') {
            const rowCount = (node.content || []).length;
            blocks.push({ type: 'table', summary: `Tabel Ilmiah (${rowCount} baris)` });
        } else if (node.type === 'figureCaption') {
            const txt = extractText(node).trim();
            if (txt) blocks.push({ type: 'caption', text: txt });
        } else {
            const txt = extractText(node).trim();
            if (txt) blocks.push({ type: 'paragraph', text: txt });
        }
    }

    return blocks.length > 0 ? blocks : [{ type: 'paragraph', text: 'Dokumen kosong.' }];
};

// Auto-fetch revisions when switching to revisions tab
watch(activeTab, (newTab) => {
    if (newTab === 'revisions') {
        fetchRevisions();
    }
});

watch(
    () => activeDocumentItem.value?.id,
    (newId, oldId) => {
        if (newId !== oldId) {
            previewRevision.value = null;
            if (activeTab.value === 'revisions') {
                fetchRevisions(newId);
            }
        }
    }
);

// Research Library state & actions
const isUploadModalOpen = ref(false);
const uploadError = ref('');
const uploadForm = useForm({
    title: '',
    file: null,
});

const openUploadModal = () => {
    uploadError.value = '';
    uploadForm.reset();
    isUploadModalOpen.value = true;
};

const closeUploadModal = () => {
    isUploadModalOpen.value = false;
    uploadError.value = '';
    uploadForm.reset();
};

const onFileSelected = (event) => {
    const file = event.target.files?.[0];
    uploadError.value = '';
    if (!file) {
        uploadForm.file = null;
        return;
    }

    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
        uploadError.value = 'Hanya file format PDF yang diperbolehkan.';
        uploadForm.file = null;
        return;
    }

    const maxBytes = 25 * 1024 * 1024;
    if (file.size > maxBytes) {
        uploadError.value = 'Ukuran file melebihi batas maksimum 25MB.';
        uploadForm.file = null;
        return;
    }

    uploadForm.file = file;
    if (!uploadForm.title) {
        uploadForm.title = file.name.replace(/\.pdf$/i, '');
    }
};

const submitUpload = () => {
    if (!uploadForm.file) {
        uploadError.value = 'Silakan pilih file PDF terlebih dahulu.';
        return;
    }

    uploadForm.post(route('projects.documents.store', props.project.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closeUploadModal();
        },
        onError: (errors) => {
            if (errors.file) {
                uploadError.value = errors.file;
            }
        },
    });
};

const retryDocument = (doc) => {
    router.post(route('projects.documents.retry', [props.project.id, doc.id]), {}, {
        preserveScroll: true,
    });
};

const deleteDocument = (doc) => {
    if (confirm(`Apakah Anda yakin ingin menghapus dokumen "${doc.title}"? Seluruh ekstraksi halaman akan dihapus.`)) {
        router.delete(route('projects.documents.destroy', [props.project.id, doc.id]), {
            preserveScroll: true,
        });
    }
};

// Document Pages Viewer Modal
const isPagesModalOpen = ref(false);
const viewingDocument = ref(null);
const viewingPages = ref([]);
const isLoadingPages = ref(false);

const openPagesModal = async (doc) => {
    viewingDocument.value = doc;
    isPagesModalOpen.value = true;
    isLoadingPages.value = true;
    viewingPages.value = [];

    try {
        const response = await axios.get(route('projects.documents.pages', [props.project.id, doc.id]));
        viewingPages.value = response.data.pages || [];
    } catch (err) {
        console.error('Failed to load document pages:', err);
    } finally {
        isLoadingPages.value = false;
    }
};

const closePagesModal = () => {
    isPagesModalOpen.value = false;
    viewingDocument.value = null;
    viewingPages.value = [];
};

const formatFileSize = (bytes) => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

// Auto-polling when documents are queued or processing
let pollTimer = null;
const hasPendingDocuments = computed(() => {
    return (props.documents || []).some((doc) => doc.status === 'Queued' || doc.status === 'Processing');
});

const updatePolling = () => {
    if (hasPendingDocuments.value) {
        if (!pollTimer) {
            pollTimer = setInterval(() => {
                router.reload({ only: ['documents'], preserveScroll: true });
            }, 3000);
        }
    } else {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }
};

watch(hasPendingDocuments, () => {
    updatePolling();
}, { immediate: true });

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
});

// Reference Management state & actions
const librarySubTab = ref('documents'); // 'documents' | 'references'
const selectedCitationStyle = ref(props.project.citation_style || 'apa');

const updateCitationStyle = (style) => {
    selectedCitationStyle.value = style;
    router.patch(route('projects.citation-style', props.project.id), {
        citation_style: style,
    }, {
        preserveScroll: true,
    });
};

const isRefModalOpen = ref(false);
const refModalMode = ref('manual'); // 'manual' | 'doi'
const editingReferenceId = ref(null);
const isResolvingDoi = ref(false);
const doiInput = ref('');
const doiError = ref('');
const doiDuplicateNotice = ref(false);

const refForm = useForm({
    type: 'article-journal',
    title: '',
    authors_text: '',
    year: '',
    publication_title: '',
    volume: '',
    issue: '',
    pages: '',
    publisher: '',
    publisher_place: '',
    doi: '',
    url: '',
    document_id: null,
});

const openAddReferenceModal = (initialData = {}) => {
    editingReferenceId.value = null;
    refModalMode.value = initialData.doi ? 'doi' : 'manual';
    doiInput.value = initialData.doi || '';
    doiError.value = '';
    doiDuplicateNotice.value = false;
    refForm.reset();
    refForm.clearErrors();

    if (initialData.title) refForm.title = initialData.title;
    if (initialData.document_id) refForm.document_id = initialData.document_id;
    if (initialData.type) refForm.type = initialData.type;

    isRefModalOpen.value = true;
};

const openEditReferenceModal = (refItem) => {
    editingReferenceId.value = refItem.id;
    refModalMode.value = 'manual';
    doiError.value = '';
    doiDuplicateNotice.value = false;
    refForm.reset();
    refForm.clearErrors();

    refForm.type = refItem.type || 'article-journal';
    refForm.title = refItem.title || '';
    refForm.year = refItem.year || '';
    refForm.publication_title = refItem.publication_title || '';
    refForm.volume = refItem.volume || '';
    refForm.issue = refItem.issue || '';
    refForm.pages = refItem.pages || '';
    refForm.publisher = refItem.publisher || '';
    refForm.publisher_place = refItem.publisher_place || '';
    refForm.doi = refItem.doi || '';
    refForm.url = refItem.url || '';
    refForm.document_id = refItem.document_id || null;

    if (Array.isArray(refItem.authors)) {
        refForm.authors_text = refItem.authors.map((a) => {
            if (typeof a === 'string') return a;
            return [a.given, a.family].filter(Boolean).join(' ');
        }).join('; ');
    } else {
        refForm.authors_text = '';
    }

    isRefModalOpen.value = true;
};

const closeRefModal = () => {
    isRefModalOpen.value = false;
    editingReferenceId.value = null;
    refForm.reset();
    refForm.clearErrors();
};

const resolveDoiMetadata = async () => {
    if (!doiInput.value.trim()) {
        doiError.value = 'Silakan masukkan DOI.';
        return;
    }
    isResolvingDoi.value = true;
    doiError.value = '';
    doiDuplicateNotice.value = false;

    try {
        const response = await axios.post(route('projects.references.resolve-doi', props.project.id), {
            doi: doiInput.value.trim(),
        });

        if (response.data.success && response.data.metadata) {
            const meta = response.data.metadata;
            refForm.title = meta.title || '';
            refForm.type = meta.type || 'article-journal';
            refForm.year = meta.year || '';
            refForm.publication_title = meta.publication_title || '';
            refForm.volume = meta.volume || '';
            refForm.issue = meta.issue || '';
            refForm.pages = meta.pages || '';
            refForm.publisher = meta.publisher || '';
            refForm.doi = meta.doi || doiInput.value.trim();

            if (Array.isArray(meta.authors)) {
                refForm.authors_text = meta.authors.map((a) => {
                    return [a.given, a.family].filter(Boolean).join(' ');
                }).join('; ');
            }

            if (response.data.is_duplicate) {
                doiDuplicateNotice.value = true;
            }

            refModalMode.value = 'manual';
        }
    } catch (err) {
        doiError.value = err.response?.data?.error || 'Gagal memuat metadata DOI. Pastikan format DOI benar.';
    } finally {
        isResolvingDoi.value = false;
    }
};

const formMissingWarnings = computed(() => {
    const warnings = [];
    if (!refForm.authors_text.trim()) warnings.push('Penulis');
    if (!refForm.year) warnings.push('Tahun');

    if (refForm.type === 'article-journal') {
        if (!refForm.publication_title.trim()) warnings.push('Nama Jurnal');
        if (!refForm.volume) warnings.push('Volume');
        if (!refForm.pages) warnings.push('Halaman');
    } else if (refForm.type === 'book') {
        if (!refForm.publisher.trim()) warnings.push('Penerbit');
    } else if (refForm.type === 'paper-conference') {
        if (!refForm.publication_title.trim()) warnings.push('Nama Prosiding');
    }

    return warnings;
});

const submitReference = () => {
    const payload = {
        type: refForm.type,
        title: refForm.title,
        authors: refForm.authors_text,
        year: refForm.year ? parseInt(refForm.year) : null,
        publication_title: refForm.publication_title,
        volume: refForm.volume,
        issue: refForm.issue,
        pages: refForm.pages,
        publisher: refForm.publisher,
        publisher_place: refForm.publisher_place,
        doi: refForm.doi,
        url: refForm.url,
        document_id: refForm.document_id,
    };

    if (editingReferenceId.value) {
        refForm.transform(() => payload).patch(route('projects.references.update', [props.project.id, editingReferenceId.value]), {
            preserveScroll: true,
            onSuccess: () => closeRefModal(),
        });
    } else {
        refForm.transform(() => payload).post(route('projects.references.store', props.project.id), {
            preserveScroll: true,
            onSuccess: () => closeRefModal(),
        });
    }
};

const activeReferenceDetail = ref(null);

const handleSelectCitation = ({ referenceId }) => {
    const found = (props.references || []).find((r) => r.id === referenceId);
    if (found) {
        activeReferenceDetail.value = found;
        isToolsOpen.value = true;
        activeTab.value = 'library';
        librarySubTab.value = 'references';
    }
};

const getLinkedDocument = (refItem) => {
    if (!refItem?.document_id) return null;
    return (props.documents || []).find((d) => d.id === refItem.document_id) || refItem.document || null;
};

const formatRefType = (type) => {
    switch (type) {
        case 'article-journal':
            return 'Jurnal Ilmiah';
        case 'book':
            return 'Buku Teks';
        case 'paper-conference':
            return 'Prosiding Konferensi';
        case 'thesis':
            return 'Skripsi / Tesis';
        default:
            return 'Lainnya';
    }
};

const formatAuthorNames = (authors) => {
    if (!authors || !authors.length) return '';
    return authors
        .map((a) => {
            if (typeof a === 'string') return a;
            if (a.literal) return a.literal;
            return [a.family, a.given].filter(Boolean).join(', ');
        })
        .join('; ');
};

const deleteReference = (refItem) => {
    if (confirm(`Apakah Anda yakin ingin menghapus referensi "${refItem.title}"?`)) {
        router.delete(route('projects.references.destroy', [props.project.id, refItem.id]), {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.reference) {
                    alert(errors.reference);
                }
            },
            onSuccess: () => {
                if (activeReferenceDetail.value?.id === refItem.id) {
                    activeReferenceDetail.value = null;
                }
            },
        });
    }
};
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
                        :references="references"
                        @open-revisions="openRevisionsDrawer"
                        @save-version="openSaveVersionModal"
                        @select-citation="handleSelectCitation"
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
                :class="isToolsOpen ? 'w-80' : 'w-12 items-center'"
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
                            :class="activeTab === 'evidence' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500 hover:text-gray-700'"
                            @click="activeTab = 'evidence'"
                        >
                            Evidence
                        </button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-xs font-semibold transition"
                            :class="activeTab === 'revisions' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500 hover:text-gray-700'"
                            @click="openRevisionsDrawer"
                        >
                            Revisi
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
                        <!-- Sub-tab switcher: Documents vs References -->
                        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                            <div class="flex items-center gap-1 rounded-lg bg-gray-100 p-0.5 text-xs font-semibold">
                                <button
                                    type="button"
                                    class="rounded-md px-2.5 py-1 transition"
                                    :class="librarySubTab === 'documents' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                    @click="librarySubTab = 'documents'"
                                >
                                    Dokumen PDF ({{ documents.length }})
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md px-2.5 py-1 transition"
                                    :class="librarySubTab === 'references' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                    @click="librarySubTab = 'references'"
                                >
                                    Referensi ({{ references.length }})
                                </button>
                            </div>

                            <button
                                v-if="librarySubTab === 'documents'"
                                type="button"
                                class="inline-flex items-center gap-1 rounded bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-700 shadow-sm transition"
                                @click="openUploadModal"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Unggah PDF
                            </button>
                            <button
                                v-else
                                type="button"
                                class="inline-flex items-center gap-1 rounded bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-700 shadow-sm transition"
                                @click="openAddReferenceModal()"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                + Referensi
                            </button>
                        </div>

                        <!-- Sub-tab 1: Documents PDF -->
                        <div v-if="librarySubTab === 'documents'" class="space-y-4">
                            <!-- Empty State -->
                            <div v-if="documents.length === 0" class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-xs text-gray-500 bg-slate-50/50">
                                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 mb-3 text-lg">
                                    &#128218;
                                </div>
                                <h4 class="font-semibold text-gray-900 mb-1">Research Library Kosong</h4>
                                <p class="text-xs text-gray-500 mb-4 max-w-xs mx-auto">
                                    Unggah dokumen PDF jurnal ilmiah untuk membangun basis referensi terisolasi proyek Anda. Maksimal 25MB per file PDF.
                                </p>
                                <SecondaryButton class="text-xs" @click="openUploadModal">
                                    Unggah PDF Dokumen
                                </SecondaryButton>
                            </div>

                            <!-- Documents List -->
                            <div v-else class="space-y-3">
                                <div
                                    v-for="doc in documents"
                                    :key="doc.id"
                                    class="rounded-lg border border-gray-200 bg-white p-3.5 shadow-sm hover:border-gray-300 transition space-y-2.5"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <h4 class="text-xs font-bold text-gray-900 truncate" :title="doc.title">
                                                {{ doc.title }}
                                            </h4>
                                            <div class="flex items-center gap-2 mt-0.5 text-[11px] text-gray-400">
                                                <span class="truncate max-w-[140px]" :title="doc.original_filename">{{ doc.original_filename }}</span>
                                                <span>&bull;</span>
                                                <span>{{ formatFileSize(doc.file_size) }}</span>
                                                <template v-if="doc.total_pages">
                                                    <span>&bull;</span>
                                                    <span>{{ doc.total_pages }} Halaman</span>
                                                </template>
                                            </div>
                                        </div>

                                        <!-- Observable State Badge -->
                                        <div class="shrink-0">
                                            <span
                                                v-if="doc.status === 'Queued'"
                                                class="inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 border border-amber-200"
                                            >
                                                <svg class="h-2.5 w-2.5 animate-pulse" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                Queued
                                            </span>
                                            <span
                                                v-else-if="doc.status === 'Processing'"
                                                class="inline-flex items-center gap-1 rounded bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 border border-blue-200"
                                            >
                                                <svg class="h-2.5 w-2.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                                Processing
                                            </span>
                                            <span
                                                v-else-if="doc.status === 'Processed'"
                                                class="inline-flex items-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200"
                                            >
                                                &#10003; Processed
                                            </span>
                                            <span
                                                v-else-if="doc.status === 'Partial'"
                                                class="inline-flex items-center gap-1 rounded bg-orange-50 px-2 py-0.5 text-[11px] font-semibold text-orange-700 border border-orange-200"
                                            >
                                                &#9888; Partial
                                            </span>
                                            <span
                                                v-else-if="doc.status === 'Failed'"
                                                class="inline-flex items-center gap-1 rounded bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 border border-rose-200"
                                            >
                                                &#10007; Failed
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Error/Warning Notice -->
                                    <div
                                        v-if="doc.error_message"
                                        class="rounded bg-rose-50/70 p-2 text-[11px] text-rose-700 border border-rose-100 break-words"
                                    >
                                        {{ doc.error_message }}
                                    </div>

                                    <!-- Actions Bar -->
                                    <div class="flex items-center justify-between pt-1 border-t border-gray-100 text-[11px]">
                                        <div class="flex items-center gap-2">
                                            <button
                                                v-if="doc.status === 'Failed' || doc.status === 'Partial'"
                                                type="button"
                                                class="font-semibold text-indigo-600 hover:text-indigo-800 transition flex items-center gap-1"
                                                @click="retryDocument(doc)"
                                            >
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                Retry
                                            </button>
                                            <button
                                                v-if="doc.status === 'Processed' || doc.status === 'Partial'"
                                                type="button"
                                                class="font-semibold text-gray-600 hover:text-gray-900 transition flex items-center gap-1"
                                                @click="openPagesModal(doc)"
                                            >
                                                Lihat Halaman
                                            </button>
                                            <a
                                                :href="route('projects.documents.file', [project.id, doc.id])"
                                                target="_blank"
                                                class="font-semibold text-indigo-600 hover:text-indigo-800 transition flex items-center gap-1"
                                                title="Buka file PDF asli di tab baru"
                                            >
                                                Buka PDF &nearr;
                                            </a>
                                            <button
                                                type="button"
                                                class="font-semibold text-indigo-600 hover:text-indigo-800 transition"
                                                title="Daftarkan dokumen ini ke daftar referensi"
                                                @click="openAddReferenceModal({ title: doc.title, document_id: doc.id })"
                                            >
                                                + Jadi Referensi
                                            </button>
                                        </div>
                                        <button
                                            type="button"
                                            class="text-gray-400 hover:text-rose-600 transition"
                                            title="Hapus Dokumen"
                                            @click="deleteDocument(doc)"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sub-tab 2: Bibliographic References -->
                        <div v-else-if="librarySubTab === 'references'" class="space-y-4">
                            <!-- Detail View for Selected Citation/Reference -->
                            <div v-if="activeReferenceDetail" class="space-y-4">
                                <div class="flex items-center justify-between pb-2 border-b border-gray-200">
                                    <button
                                        type="button"
                                        class="inline-flex items-center space-x-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition"
                                        @click="activeReferenceDetail = null"
                                    >
                                        <span>&larr;</span>
                                        <span>Kembali ke Daftar</span>
                                    </button>
                                    <span class="text-[10px] uppercase tracking-wider text-gray-400 font-bold">
                                        Detail Sumber Referensi
                                    </span>
                                </div>

                                <div class="rounded-lg border border-indigo-200 bg-white p-3.5 shadow-sm space-y-3">
                                    <!-- Badges Header -->
                                    <div class="flex items-center justify-between text-[11px] gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-indigo-600 text-white shadow-xs">
                                            {{ activeReferenceDetail.in_text_citation || `(${activeReferenceDetail.reference_key})` }}
                                        </span>
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-mono bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded font-semibold text-[10px]">
                                                {{ activeReferenceDetail.reference_key }}
                                            </span>
                                            <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-700 font-semibold text-[10px]">
                                                {{ formatRefType(activeReferenceDetail.type) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Title -->
                                    <h4 class="font-bold text-gray-900 text-sm leading-snug">
                                        {{ activeReferenceDetail.title }}
                                    </h4>

                                    <!-- Formatted Citation Output -->
                                    <div class="text-xs text-gray-800 leading-relaxed font-serif bg-slate-50/80 p-2.5 rounded border border-gray-100">
                                        {{ selectedCitationStyle === 'harvard' ? activeReferenceDetail.formatted_harvard : activeReferenceDetail.formatted_apa }}
                                    </div>

                                    <!-- Metadata Breakdown -->
                                    <dl class="text-xs space-y-1.5 divide-y divide-gray-100 pt-1">
                                        <div v-if="formatAuthorNames(activeReferenceDetail.authors)" class="flex justify-between pt-1">
                                            <dt class="text-gray-500 font-medium">Penulis:</dt>
                                            <dd class="text-gray-900 text-right font-medium max-w-[200px]">{{ formatAuthorNames(activeReferenceDetail.authors) }}</dd>
                                        </div>
                                        <div v-if="activeReferenceDetail.year" class="flex justify-between pt-1">
                                            <dt class="text-gray-500 font-medium">Tahun:</dt>
                                            <dd class="text-gray-900 font-semibold">{{ activeReferenceDetail.year }}</dd>
                                        </div>
                                        <div v-if="activeReferenceDetail.publication_title" class="flex justify-between pt-1">
                                            <dt class="text-gray-500 font-medium">Publikasi / Jurnal:</dt>
                                            <dd class="text-gray-900 text-right max-w-[200px]">{{ activeReferenceDetail.publication_title }}</dd>
                                        </div>
                                        <div v-if="activeReferenceDetail.volume || activeReferenceDetail.issue || activeReferenceDetail.pages" class="flex justify-between pt-1">
                                            <dt class="text-gray-500 font-medium">Vol / Issue / Hlm:</dt>
                                            <dd class="text-gray-900 text-right font-mono text-[11px]">
                                                {{ [activeReferenceDetail.volume ? `Vol. ${activeReferenceDetail.volume}` : '', activeReferenceDetail.issue ? `No. ${activeReferenceDetail.issue}` : '', activeReferenceDetail.pages ? `pp. ${activeReferenceDetail.pages}` : ''].filter(Boolean).join(', ') }}
                                            </dd>
                                        </div>
                                        <div v-if="activeReferenceDetail.publisher" class="flex justify-between pt-1">
                                            <dt class="text-gray-500 font-medium">Penerbit:</dt>
                                            <dd class="text-gray-900 text-right max-w-[200px]">{{ activeReferenceDetail.publisher }}</dd>
                                        </div>
                                        <div v-if="activeReferenceDetail.doi" class="flex justify-between pt-1 items-center">
                                            <dt class="text-gray-500 font-medium">DOI:</dt>
                                            <dd class="text-right">
                                                <a
                                                    :href="`https://doi.org/${activeReferenceDetail.doi}`"
                                                    target="_blank"
                                                    class="text-indigo-600 hover:text-indigo-800 underline font-mono text-[11px]"
                                                >
                                                    {{ activeReferenceDetail.doi }} &nearr;
                                                </a>
                                            </dd>
                                        </div>
                                        <div v-if="activeReferenceDetail.url" class="flex justify-between pt-1 items-center">
                                            <dt class="text-gray-500 font-medium">URL:</dt>
                                            <dd class="text-right truncate max-w-[180px]">
                                                <a
                                                    :href="activeReferenceDetail.url"
                                                    target="_blank"
                                                    class="text-indigo-600 hover:text-indigo-800 underline font-mono text-[11px]"
                                                >
                                                    {{ activeReferenceDetail.url }} &nearr;
                                                </a>
                                            </dd>
                                        </div>
                                    </dl>

                                    <!-- Source PDF Document Section -->
                                    <div class="mt-3 pt-3 border-t border-gray-100">
                                        <h5 class="text-[11px] font-bold text-gray-700 uppercase tracking-wide mb-2 flex items-center space-x-1">
                                            <span>&#128196;</span>
                                            <span>Sumber Dokumen PDF</span>
                                        </h5>
                                        <div v-if="getLinkedDocument(activeReferenceDetail)" class="rounded-lg bg-slate-50 p-3 border border-gray-200 space-y-2">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="text-xs">
                                                    <p class="font-semibold text-gray-900 leading-tight">
                                                        {{ getLinkedDocument(activeReferenceDetail).title }}
                                                    </p>
                                                    <p class="text-[11px] text-gray-400 mt-0.5">
                                                        {{ getLinkedDocument(activeReferenceDetail).total_pages || 0 }} Halaman &bull; {{ formatFileSize(getLinkedDocument(activeReferenceDetail).file_size) }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 pt-2 border-t border-gray-200/60">
                                                <a
                                                    :href="route('projects.documents.file', [project.id, getLinkedDocument(activeReferenceDetail).id])"
                                                    target="_blank"
                                                    class="inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-700 transition"
                                                >
                                                    Buka PDF &nearr;
                                                </a>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center px-2 py-1 rounded text-xs font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 transition"
                                                    @click="openPagesModal(getLinkedDocument(activeReferenceDetail))"
                                                >
                                                    Lihat Halaman
                                                </button>
                                            </div>
                                        </div>
                                        <div v-else class="rounded bg-gray-50 p-3 text-center text-xs text-gray-500 border border-dashed border-gray-200">
                                            Tidak ada file PDF yang terhubung ke referensi ini.
                                        </div>
                                    </div>

                                    <!-- Action Bar -->
                                    <div class="flex items-center justify-between pt-2 border-t border-gray-100 text-xs">
                                        <button
                                            type="button"
                                            class="font-semibold text-indigo-600 hover:text-indigo-800 transition"
                                            @click="openEditReferenceModal(activeReferenceDetail)"
                                        >
                                            Ubah Metadata
                                        </button>
                                        <button
                                            type="button"
                                            class="text-gray-400 hover:text-rose-600 transition"
                                            @click="deleteReference(activeReferenceDetail)"
                                        >
                                            Hapus Referensi
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Full References List (when no reference is actively selected for detail view) -->
                            <template v-else>
                                <!-- Citation Style Switcher Bar -->
                                <div class="flex items-center justify-between rounded-lg bg-slate-50 p-2.5 border border-gray-200 text-xs">
                                    <span class="font-bold text-gray-700">Gaya Sitasi:</span>
                                    <div class="inline-flex rounded-md shadow-sm">
                                        <button
                                            type="button"
                                            class="px-3 py-1 text-xs font-semibold rounded-l-md border transition"
                                            :class="selectedCitationStyle === 'apa' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                            @click="updateCitationStyle('apa')"
                                        >
                                            APA 7th
                                        </button>
                                        <button
                                            type="button"
                                            class="px-3 py-1 text-xs font-semibold rounded-r-md border-t border-b border-r transition"
                                            :class="selectedCitationStyle === 'harvard' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                            @click="updateCitationStyle('harvard')"
                                        >
                                            Harvard
                                        </button>
                                    </div>
                                </div>

                                <!-- Empty References -->
                                <div v-if="references.length === 0" class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-xs text-gray-500 bg-slate-50/50">
                                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 mb-3 text-lg">
                                        &#128214;
                                    </div>
                                    <h4 class="font-semibold text-gray-900 mb-1">Belum Ada Referensi Terdaftar</h4>
                                    <p class="text-xs text-gray-500 mb-4 max-w-xs mx-auto">
                                        Daftarkan sumber referensi via DOI atau formulir metadata manual untuk membangun daftar pustaka Anda.
                                    </p>
                                    <SecondaryButton class="text-xs" @click="openAddReferenceModal()">
                                        + Tambah Referensi
                                    </SecondaryButton>
                                </div>

                                <!-- References List -->
                                <div v-else class="space-y-3">
                                    <div
                                        v-for="refItem in references"
                                        :key="refItem.id"
                                        class="rounded-lg border border-gray-200 bg-white p-3.5 shadow-sm hover:border-gray-300 transition space-y-2"
                                    >
                                        <!-- Badges Header -->
                                        <div class="flex items-center justify-between text-[11px] gap-2">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-mono bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded font-semibold text-[10px]">
                                                    {{ refItem.reference_key }}
                                                </span>
                                                <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-700 font-semibold text-[10px]">
                                                    {{ formatRefType(refItem.type) }}
                                                </span>
                                                <span class="text-gray-400 font-mono text-[10px]">
                                                    Sitasi: {{ refItem.in_text_citation }}
                                                </span>
                                            </div>
                                            <span v-if="refItem.document_id" class="text-[10px] text-emerald-600 font-semibold flex items-center gap-0.5 shrink-0" title="Terhubung ke PDF">
                                                &#128196; PDF
                                            </span>
                                        </div>

                                        <!-- Formatted Citation Output -->
                                        <div class="text-xs text-gray-900 leading-relaxed font-serif bg-slate-50/70 p-2.5 rounded border border-gray-100">
                                            {{ selectedCitationStyle === 'harvard' ? refItem.formatted_harvard : refItem.formatted_apa }}
                                        </div>

                                        <!-- Missing Fields Warning -->
                                        <div
                                            v-if="refItem.missing_field_warnings && Object.keys(refItem.missing_field_warnings).length > 0"
                                            class="rounded bg-amber-50 p-2 text-[11px] text-amber-800 border border-amber-200"
                                        >
                                            <span class="font-bold">&#9888; Metadata Belum Lengkap:</span>
                                            {{ Object.values(refItem.missing_field_warnings).join(', ') }}
                                        </div>

                                        <!-- Action Bar -->
                                        <div class="flex items-center justify-between pt-1 border-t border-gray-100 text-[11px]">
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="font-semibold text-indigo-600 hover:text-indigo-800 transition"
                                                    @click="activeReferenceDetail = refItem"
                                                >
                                                    Detail
                                                </button>
                                                <button
                                                    type="button"
                                                    class="font-semibold text-gray-600 hover:text-gray-900 transition"
                                                    @click="openEditReferenceModal(refItem)"
                                                >
                                                    Ubah
                                                </button>
                                            </div>
                                            <button
                                                type="button"
                                                class="text-gray-400 hover:text-rose-600 transition"
                                                @click="deleteReference(refItem)"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Tab: Evidence Checker -->
                    <div v-else-if="activeTab === 'evidence'" class="space-y-4">
                        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-xs text-gray-500">
                            <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 mb-3">
                                &#10004;
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-1">Citation Evidence Checker</h4>
                            <p class="text-xs text-gray-500 mb-4">
                                Sorot kalimat atau klaim argumen dalam editor untuk memverifikasi keabsahannya terhadap paper riset terunggah.
                            </p>
                            <span class="inline-block rounded-full bg-gray-100 px-2 py-1 text-[11px] text-gray-600">
                                Segera hadir
                            </span>
                        </div>
                    </div>

                    <!-- Tab: Chapter Revisions Drawer -->
                    <div v-else-if="activeTab === 'revisions'" class="space-y-4">
                        <!-- Drawer Header & Action -->
                        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                            <div class="truncate mr-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-gray-400">Riwayat Snapshot</span>
                                <span class="text-xs font-semibold text-gray-800 truncate block">{{ activeDocumentItem?.title || 'Pilih Bab' }}</span>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center rounded bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 shrink-0"
                                @click="openSaveVersionModal"
                            >
                                + Save Version
                            </button>
                        </div>

                        <!-- Preview Container when a revision is selected -->
                        <div v-if="previewRevision" class="rounded-lg border-2 border-indigo-200 bg-indigo-50/40 p-3 space-y-3">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center space-x-1.5 mb-1">
                                        <span
                                            class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded"
                                            :class="previewRevision.is_manual ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-200 text-gray-700'"
                                        >
                                            {{ previewRevision.is_manual ? 'Manual' : 'Otomatis' }}
                                        </span>
                                        <span class="text-xs font-bold text-gray-900">{{ previewRevision.title }}</span>
                                    </div>
                                    <div class="text-[11px] text-gray-500">
                                        {{ previewRevision.relative_time }} &bull; {{ new Date(previewRevision.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="text-gray-400 hover:text-gray-600 text-sm font-bold p-1"
                                    title="Tutup Pratinjau"
                                    @click="closePreview"
                                >
                                    &times;
                                </button>
                            </div>

                            <!-- Structured Snapshot Preview -->
                            <div class="max-h-60 overflow-y-auto bg-white rounded border border-gray-200 p-2.5 text-xs text-gray-700 space-y-2 font-serif">
                                <div
                                    v-for="(block, bIdx) in getPreviewBlocks(previewRevision.content)"
                                    :key="bIdx"
                                >
                                    <h3 v-if="block.type === 'heading' && block.level === 1" class="font-bold font-sans text-sm text-gray-900 uppercase">
                                        {{ block.text }}
                                    </h3>
                                    <h4 v-else-if="block.type === 'heading' && block.level === 2" class="font-bold font-sans text-xs text-gray-800">
                                        {{ block.text }}
                                    </h4>
                                    <h5 v-else-if="block.type === 'heading'" class="font-semibold font-sans text-xs text-gray-700">
                                        {{ block.text }}
                                    </h5>
                                    <ul v-else-if="block.type === 'bulletList'" class="list-disc list-inside space-y-0.5 text-xs">
                                        <li v-for="(it, iIdx) in block.items" :key="iIdx">{{ it }}</li>
                                    </ul>
                                    <ol v-else-if="block.type === 'orderedList'" class="list-decimal list-inside space-y-0.5 text-xs">
                                        <li v-for="(it, iIdx) in block.items" :key="iIdx">{{ it }}</li>
                                    </ol>
                                    <div v-else-if="block.type === 'table'" class="rounded bg-slate-50 border border-slate-200 p-1.5 text-center text-[11px] font-sans text-slate-600">
                                        {{ block.summary }}
                                    </div>
                                    <div v-else-if="block.type === 'caption'" class="italic text-[11px] text-gray-500 font-sans">
                                        {{ block.text }}
                                    </div>
                                    <p v-else class="leading-relaxed">
                                        {{ block.text }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                <button
                                    type="button"
                                    class="text-xs text-gray-500 hover:text-gray-700 font-medium"
                                    @click="closePreview"
                                >
                                    Tutup Pratinjau
                                </button>
                                <PrimaryButton
                                    type="button"
                                    :disabled="isRestoring"
                                    class="!px-3 !py-1 text-xs"
                                    @click="restoreRevision(previewRevision)"
                                >
                                    {{ isRestoring ? 'Memulihkan...' : 'Restore this version' }}
                                </PrimaryButton>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-if="isLoadingRevisions" class="py-8 text-center text-xs text-gray-500">
                            <svg class="animate-spin h-5 w-5 text-indigo-600 mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Memuat riwayat revisi...</span>
                        </div>

                        <!-- Empty Revisions State -->
                        <div
                            v-else-if="!isLoadingRevisions && revisions.length === 0"
                            class="rounded-lg border border-dashed border-gray-300 p-5 text-center text-xs text-gray-500"
                        >
                            <div class="mx-auto flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-500 mb-2 font-bold">
                                &#9201;
                            </div>
                            <h4 class="font-semibold text-gray-800 mb-1">Belum Ada Snapshot</h4>
                            <p class="text-[11px] text-gray-500 mb-3">
                                Snapshot otomatis dicatat setiap 5 menit selama pengetikan aktif (maks. 50 versi), atau simpan checkpoint versi manual sekarang.
                            </p>
                            <button
                                type="button"
                                class="inline-flex items-center rounded bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                                @click="openSaveVersionModal"
                            >
                                Simpan Versi Manual
                            </button>
                        </div>

                        <!-- Chronological Snapshots List -->
                        <div v-else class="space-y-2">
                            <div
                                v-for="rev in revisions"
                                :key="rev.id"
                                class="rounded-lg border p-3 transition bg-white shadow-xs"
                                :class="previewRevision?.id === rev.id ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-200 hover:border-gray-300'"
                            >
                                <div class="flex items-start justify-between mb-1.5">
                                    <div class="flex-1 mr-2 truncate">
                                        <div class="flex items-center space-x-1 mb-0.5">
                                            <span
                                                class="text-[9px] uppercase font-bold tracking-wider px-1.5 py-0.2 rounded inline-block"
                                                :class="rev.is_manual ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600'"
                                            >
                                                {{ rev.is_manual ? 'Manual' : 'Otomatis' }}
                                            </span>
                                            <span class="text-xs font-bold text-gray-900 truncate block">
                                                {{ rev.title }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-gray-500">
                                            {{ rev.relative_time }} &bull; {{ new Date(rev.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-gray-100 text-xs">
                                    <button
                                        type="button"
                                        class="text-indigo-600 hover:text-indigo-800 font-medium"
                                        @click="selectPreviewRevision(rev)"
                                    >
                                        Pratinjau
                                    </button>
                                    <button
                                        type="button"
                                        class="font-semibold text-gray-700 hover:text-indigo-700"
                                        :disabled="isRestoring"
                                        @click="restoreRevision(rev)"
                                    >
                                        Restore this version
                                    </button>
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

        <!-- Modal: Save Named Version Checkpoint -->
        <Modal :show="isSaveVersionModalOpen" @close="closeSaveVersionModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-1">Save Version Checkpoint</h2>
                <p class="text-xs text-gray-600 mb-4">
                    Buat snapshot manual untuk "{{ activeDocumentItem?.title }}". Anda dapat melihat kembali atau memulihkan ke versi ini kapan saja.
                </p>

                <form @submit.prevent="submitSaveVersion">
                    <div>
                        <InputLabel for="version_title" value="Nama Checkpoint / Versi" />
                        <TextInput
                            id="version_title"
                            v-model="saveVersionTitle"
                            type="text"
                            placeholder="Contoh: Draf sebelum bimbingan Bab 1"
                            class="mt-1 block w-full text-sm"
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="saveVersionError" />
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="closeSaveVersionModal">Batal</SecondaryButton>
                        <PrimaryButton :disabled="isSavingVersion">
                            {{ isSavingVersion ? 'Menyimpan...' : 'Save Version' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal: Upload PDF Document to Research Library -->
        <Modal :show="isUploadModalOpen" @close="closeUploadModal">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                    <h3 class="text-base font-bold text-gray-900">
                        Unggah Paper Riset ke Research Library
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-lg" @click="closeUploadModal">&times;</button>
                </div>

                <form @submit.prevent="submitUpload" class="space-y-4">
                    <div>
                        <InputLabel for="upload_doc_title" value="Judul Dokumen (Opsional)" />
                        <TextInput
                            id="upload_doc_title"
                            v-model="uploadForm.title"
                            type="text"
                            class="mt-1 block w-full text-sm"
                            placeholder="Contoh: Analisis Sentimen Algoritma SVM"
                        />
                        <p class="mt-1 text-[11px] text-gray-400">Kosongkan untuk menggunakan nama file asli secara otomatis.</p>
                    </div>

                    <div>
                        <InputLabel for="upload_doc_file" value="Pilih File PDF (Maks. 25MB)" />
                        <input
                            ref="uploadFileInput"
                            id="upload_doc_file"
                            type="file"
                            accept="application/pdf,.pdf"
                            class="mt-1 block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer border border-gray-300 rounded p-1"
                            @change="onFileSelected"
                        />
                        <p v-if="uploadError" class="mt-1 text-xs text-rose-600 font-medium">
                            {{ uploadError }}
                        </p>
                    </div>

                    <div class="rounded-md bg-amber-50 p-3 border border-amber-200 text-xs text-amber-800">
                        <p class="font-semibold mb-1">Catatan Pemrosesan:</p>
                        <p class="text-[11px] leading-relaxed">
                            File PDF akan diproses di latar belakang secara bertahap per halaman menggunakan ekstraksi teks native dan OCR. Anda dapat terus menulis bab selagi dokumen diproses.
                        </p>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <SecondaryButton type="button" @click="closeUploadModal">
                            Batal
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="uploadForm.processing || !uploadForm.file"
                            class="text-xs"
                        >
                            {{ uploadForm.processing ? 'Mengunggah...' : 'Unggah & Proses' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal: View Extracted Pages -->
        <Modal :show="isPagesModalOpen" @close="closePagesModal" max-width="2xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 truncate max-w-md">
                            {{ viewingDocument?.title }}
                        </h3>
                        <p class="text-xs text-gray-500">
                            Ekstraksi Halaman PDF ({{ viewingPages.length }} Halaman)
                        </p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-lg" @click="closePagesModal">&times;</button>
                </div>

                <div v-if="isLoadingPages" class="py-12 text-center text-xs text-gray-500">
                    <svg class="mx-auto h-6 w-6 animate-spin text-indigo-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Memuat teks halaman terekstraksi...
                </div>

                <div v-else-if="viewingPages.length === 0" class="py-8 text-center text-xs text-gray-500">
                    Tidak ada halaman yang dapat ditampilkan.
                </div>

                <div v-else class="max-h-[60vh] overflow-y-auto space-y-4 pr-1">
                    <div
                        v-for="page in viewingPages"
                        :key="page.id"
                        class="rounded-lg border border-gray-200 bg-slate-50/50 p-4 space-y-2"
                    >
                        <div class="flex items-center justify-between text-xs font-bold text-gray-700 border-b border-gray-200/60 pb-1.5">
                            <span class="inline-flex items-center gap-1 rounded bg-indigo-50 px-2 py-0.5 text-indigo-700">
                                Halaman {{ page.page_number }}
                            </span>
                            <span class="text-[11px] text-gray-400 font-normal">
                                {{ page.character_count }} karakter
                            </span>
                        </div>
                        <div class="whitespace-pre-wrap font-mono text-xs text-gray-800 leading-relaxed max-h-48 overflow-y-auto bg-white p-3 rounded border border-gray-100">
                            {{ page.content || '(Halaman kosong / tidak ada teks)' }}
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" @click="closePagesModal">
                        Tutup
                    </SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- Modal: Add / Edit Reference -->
        <Modal :show="isRefModalOpen" @close="closeRefModal" max-width="2xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                    <h3 class="text-base font-bold text-gray-900">
                        {{ !editingReferenceId ? 'Tambah Sumber Referensi' : 'Ubah Sumber Referensi' }}
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-lg" @click="closeRefModal">&times;</button>
                </div>

                <!-- Fast DOI Import Box (only in create mode) -->
                <div v-if="!editingReferenceId" class="mb-5 rounded-lg border border-indigo-100 bg-indigo-50/50 p-3.5 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-indigo-950 flex items-center gap-1">
                            <span>&#128269;</span> Tarik Otomatis via DOI
                        </label>
                        <span class="text-[11px] text-indigo-600">Crossref / DataCite</span>
                    </div>
                    <div class="flex gap-2">
                        <TextInput
                            v-model="doiInput"
                            type="text"
                            placeholder="Contoh: 10.1145/3313831.3376727 atau https://doi.org/..."
                            class="flex-1 text-xs"
                            :disabled="isResolvingDoi"
                            @keyup.enter.prevent="resolveDoiMetadata"
                        />
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50 transition"
                            :disabled="isResolvingDoi || !doiInput.trim()"
                            @click="resolveDoiMetadata"
                        >
                            {{ isResolvingDoi ? 'Mengambil...' : 'Tarik Data' }}
                        </button>
                    </div>
                    <p v-if="doiError" class="text-xs text-rose-600 font-medium">
                        {{ doiError }}
                    </p>
                    <p v-if="doiDuplicateNotice" class="text-xs text-amber-700 bg-amber-100/70 p-2 rounded border border-amber-200">
                        &#9888; DOI ini sudah terdaftar pada proyek ini. Menyimpan akan membuat duplikasi.
                    </p>
                </div>

                <!-- Live Missing Field Warning inside Form -->
                <div v-if="formMissingWarnings.length > 0" class="mb-4 rounded-md bg-amber-50 p-2.5 text-xs text-amber-800 border border-amber-200">
                    <span class="font-bold">&#9888; Metadata Belum Lengkap:</span>
                    Field {{ formMissingWarnings.join(', ') }} belum terisi. Referensi tetap dapat disimpan dan dilengkapi nanti.
                </div>

                <form @submit.prevent="submitReference" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="ref_type" value="Jenis Publikasi *" />
                            <select
                                id="ref_type"
                                v-model="refForm.type"
                                class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="article-journal">Artikel Jurnal (article-journal)</option>
                                <option value="book">Buku (book)</option>
                                <option value="chapter">Bab Buku (chapter)</option>
                                <option value="paper-conference">Prosiding Konferensi (paper-conference)</option>
                                <option value="thesis">Skripsi / Tesis / Disertasi (thesis)</option>
                                <option value="report">Laporan Teknis (report)</option>
                                <option value="webpage">Laman Web (webpage)</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="ref_key" value="Kunci Sitasi / Citation Key" />
                            <TextInput
                                id="ref_key"
                                v-model="refForm.reference_key"
                                type="text"
                                placeholder="Contoh: Smith2024 (kosong = otomatis)"
                                class="mt-1 block w-full text-xs font-mono"
                            />
                            <p v-if="refForm.errors.reference_key" class="mt-1 text-xs text-rose-600">
                                {{ refForm.errors.reference_key }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="ref_title" value="Judul Publikasi *" />
                        <TextInput
                            id="ref_title"
                            v-model="refForm.title"
                            type="text"
                            required
                            placeholder="Judul lengkap artikel atau karya ilmiah"
                            class="mt-1 block w-full text-xs"
                        />
                        <p v-if="refForm.errors.title" class="mt-1 text-xs text-rose-600">
                            {{ refForm.errors.title }}
                        </p>
                    </div>

                    <div>
                        <InputLabel for="ref_authors" value="Penulis" />
                        <TextInput
                            id="ref_authors"
                            v-model="refForm.authors_text"
                            type="text"
                            placeholder="Contoh: Doe, John; Smith, Jane (pisahkan titik koma)"
                            class="mt-1 block w-full text-xs"
                        />
                        <p class="mt-0.5 text-[10px] text-gray-400">
                            Format nama: "Belakang, Depan" atau "Depan Belakang", pisahkan beberapa penulis dengan titik-koma (;).
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <InputLabel for="ref_publication" value="Nama Jurnal / Prosiding / Buku" />
                            <TextInput
                                id="ref_publication"
                                v-model="refForm.publication_title"
                                type="text"
                                placeholder="Contoh: IEEE Transactions on Software Engineering"
                                class="mt-1 block w-full text-xs"
                            />
                        </div>
                        <div>
                            <InputLabel for="ref_year" value="Tahun Terbit" />
                            <TextInput
                                id="ref_year"
                                v-model="refForm.year"
                                type="number"
                                placeholder="2024"
                                class="mt-1 block w-full text-xs"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <InputLabel for="ref_vol" value="Volume" />
                            <TextInput
                                id="ref_vol"
                                v-model="refForm.volume"
                                type="text"
                                placeholder="12"
                                class="mt-1 block w-full text-xs"
                            />
                        </div>
                        <div>
                            <InputLabel for="ref_iss" value="Issue / No." />
                            <TextInput
                                id="ref_iss"
                                v-model="refForm.issue"
                                type="text"
                                placeholder="3"
                                class="mt-1 block w-full text-xs"
                            />
                        </div>
                        <div>
                            <InputLabel for="ref_pages" value="Halaman" />
                            <TextInput
                                id="ref_pages"
                                v-model="refForm.pages"
                                type="text"
                                placeholder="123-145"
                                class="mt-1 block w-full text-xs"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="ref_pub" value="Penerbit (Publisher)" />
                            <TextInput
                                id="ref_pub"
                                v-model="refForm.publisher"
                                type="text"
                                placeholder="Contoh: Springer, ACM"
                                class="mt-1 block w-full text-xs"
                            />
                        </div>
                        <div>
                            <InputLabel for="ref_place" value="Tempat Terbit (Publisher Place)" />
                            <TextInput
                                id="ref_place"
                                v-model="refForm.publisher_place"
                                type="text"
                                placeholder="Contoh: New York, NY"
                                class="mt-1 block w-full text-xs"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="ref_doi" value="DOI" />
                            <TextInput
                                id="ref_doi"
                                v-model="refForm.doi"
                                type="text"
                                placeholder="10.1145/..."
                                class="mt-1 block w-full text-xs"
                            />
                            <p v-if="refForm.errors.doi" class="mt-1 text-xs text-rose-600">
                                {{ refForm.errors.doi }}
                            </p>
                        </div>
                        <div>
                            <InputLabel for="ref_url" value="URL" />
                            <TextInput
                                id="ref_url"
                                v-model="refForm.url"
                                type="url"
                                placeholder="https://..."
                                class="mt-1 block w-full text-xs"
                            />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <SecondaryButton type="button" @click="closeRefModal">
                            Batal
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="refForm.processing"
                            class="text-xs"
                        >
                            {{ refForm.processing ? 'Menyimpan...' : (!editingReferenceId ? 'Simpan Referensi' : 'Perbarui Referensi') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
