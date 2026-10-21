<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableHeader } from '@tiptap/extension-table-header';
import { TableCell } from '@tiptap/extension-table-cell';
import { FigureCaption } from '@/Components/extensions/FigureCaption.js';
import axios from 'axios';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    chapter: {
        type: Object,
        required: true,
    },
    editable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['saved', 'status-change']);

// Editor state
const editor = ref(null);
const currentChapterId = ref(props.chapter?.id || null);
const saveStatus = ref('saved'); // 'saved' | 'saving' | 'unsaved' | 'error'
const lastSavedTime = ref(null);
const saveTimer = ref(null);
const paperTheme = ref('white'); // 'white' | 'sepia'
const isTableDropdownOpen = ref(false);

const isSaving = computed(() => saveStatus.value === 'saving');
const isSaved = computed(() => saveStatus.value === 'saved');
const isUnsaved = computed(() => saveStatus.value === 'unsaved');
const isError = computed(() => saveStatus.value === 'error');

// Calculate words and characters
const wordCount = computed(() => {
    if (!editor.value) return 0;
    const text = editor.value.getText().trim();
    return text ? text.split(/\s+/).length : 0;
});

const charCount = computed(() => {
    if (!editor.value) return 0;
    return editor.value.getText().length;
});

// Prepare initial content helper
const getInitialContent = (targetChapter) => {
    if (!targetChapter) return '';
    if (targetChapter.content) {
        return targetChapter.content;
    }
    // Default initial template if empty
    return {
        type: 'doc',
        content: [
            {
                type: 'heading',
                attrs: { level: 1 },
                content: [{ type: 'text', text: targetChapter.title || 'BAB' }],
            },
            {
                type: 'paragraph',
                content: [],
            },
        ],
    };
};

// Auto-save logic
const saveContent = async (targetId = currentChapterId.value) => {
    if (!editor.value || !targetId || !props.project?.id) return;

    if (saveTimer.value) {
        clearTimeout(saveTimer.value);
        saveTimer.value = null;
    }

    const jsonContent = editor.value.getJSON();
    saveStatus.value = 'saving';
    emit('status-change', 'saving');

    try {
        const response = await axios.patch(
            route('projects.chapters.update', [props.project.id, targetId]),
            { content: jsonContent },
            { headers: { Accept: 'application/json' } }
        );

        if (targetId === currentChapterId.value) {
            saveStatus.value = 'saved';
            emit('status-change', 'saved');
            const now = new Date();
            lastSavedTime.value = now.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        }

        emit('saved', {
            chapter: response.data.chapter,
            savedAt: response.data.saved_at,
        });
    } catch (err) {
        console.error('Failed to auto-save chapter content:', err);
        if (targetId === currentChapterId.value) {
            saveStatus.value = 'error';
            emit('status-change', 'error');
        }
    }
};

// Queue debounced save
const queueAutoSave = () => {
    if (!props.editable) return;
    saveStatus.value = 'unsaved';
    emit('status-change', 'unsaved');

    if (saveTimer.value) {
        clearTimeout(saveTimer.value);
    }

    saveTimer.value = setTimeout(() => {
        saveContent(currentChapterId.value);
    }, 1200);
};

// Flush pending edits immediately (synchronous trigger for async save)
const flush = async () => {
    if (saveTimer.value) {
        clearTimeout(saveTimer.value);
        saveTimer.value = null;
    }

    if (saveStatus.value === 'unsaved' && editor.value && currentChapterId.value) {
        return await saveContent(currentChapterId.value);
    }
};

// Initialize Tiptap editor
onMounted(() => {
    editor.value = new Editor({
        content: getInitialContent(props.chapter),
        editable: props.editable,
        extensions: [
            StarterKit.configure({
                heading: {
                    levels: [1, 2, 3],
                },
            }),
            Table.configure({
                resizable: true,
            }),
            TableRow,
            TableHeader,
            TableCell,
            FigureCaption,
        ],
        onUpdate: () => {
            queueAutoSave();
        },
    });

    currentChapterId.value = props.chapter?.id;
    window.addEventListener('beforeunload', handleBeforeUnload);
});

// Handle window close or tab refresh
const handleBeforeUnload = (e) => {
    if (saveStatus.value === 'unsaved') {
        flush();
        e.preventDefault();
        e.returnValue = 'Perubahan dokumen belum tersimpan secara penuh.';
    }
};

// Handle switching chapter or section
watch(
    () => props.chapter?.id,
    async (newId, oldId) => {
        if (!newId || newId === oldId) return;

        // Flush unsaved edits from previous chapter before loading new one
        if (oldId && saveStatus.value === 'unsaved') {
            await saveContent(oldId);
        }

        currentChapterId.value = newId;

        if (editor.value) {
            const nextContent = getInitialContent(props.chapter);
            editor.value.commands.setContent(nextContent, false);
            saveStatus.value = 'saved';
            emit('status-change', 'saved');
        }
    }
);

onBeforeUnmount(() => {
    flush();
    window.removeEventListener('beforeunload', handleBeforeUnload);
    if (editor.value) {
        editor.value.destroy();
    }
});

// Expose flush method to parent component
defineExpose({
    flush,
    saveContent,
});

// Formatting commands
const toggleBold = () => editor.value?.chain().focus().toggleBold().run();
const toggleItalic = () => editor.value?.chain().focus().toggleItalic().run();
const toggleStrike = () => editor.value?.chain().focus().toggleStrike().run();

const setHeading = (level) => {
    if (level === 0) {
        editor.value?.chain().focus().setParagraph().run();
    } else {
        editor.value?.chain().focus().toggleHeading({ level }).run();
    }
};

const toggleBulletList = () => editor.value?.chain().focus().toggleBulletList().run();
const toggleOrderedList = () => editor.value?.chain().focus().toggleOrderedList().run();
const toggleCaption = () => editor.value?.chain().focus().toggleFigureCaption().run();

// Table commands
const insertTable = () => {
    editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
    isTableDropdownOpen.value = false;
};
const addRowAbove = () => editor.value?.chain().focus().addRowBefore().run();
const addRowBelow = () => editor.value?.chain().focus().addRowAfter().run();
const deleteRow = () => editor.value?.chain().focus().deleteRow().run();
const addColLeft = () => editor.value?.chain().focus().addColumnBefore().run();
const addColRight = () => editor.value?.chain().focus().addColumnAfter().run();
const deleteCol = () => editor.value?.chain().focus().deleteColumn().run();
const deleteTable = () => {
    editor.value?.chain().focus().deleteTable().run();
    isTableDropdownOpen.value = false;
};

const undo = () => editor.value?.chain().focus().undo().run();
const redo = () => editor.value?.chain().focus().redo().run();
</script>

<template>
    <div class="w-full flex flex-col items-center">
        <!-- Floating/Sticky Academic Toolbar -->
        <div class="sticky top-0 z-20 w-full max-w-4xl bg-white/95 backdrop-blur-sm border border-gray-200 rounded-lg shadow-xs px-3 py-2 mb-4 font-sans text-xs">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <!-- Left Toolbar Groups: History, Headings, Text Styles, Lists, Tables, Captions -->
                <div class="flex flex-wrap items-center gap-1">
                    <!-- History Undo/Redo -->
                    <div class="flex items-center border-r border-gray-200 pr-1 mr-1">
                        <button
                            type="button"
                            title="Undo (Ctrl+Z)"
                            :disabled="!editor?.can().undo()"
                            class="p-1 rounded hover:bg-gray-100 disabled:opacity-30 text-gray-700"
                            @click="undo"
                        >
                            &larr;
                        </button>
                        <button
                            type="button"
                            title="Redo (Ctrl+Y)"
                            :disabled="!editor?.can().redo()"
                            class="p-1 rounded hover:bg-gray-100 disabled:opacity-30 text-gray-700"
                            @click="redo"
                        >
                            &rarr;
                        </button>
                    </div>

                    <!-- Headings Dropdown / Buttons -->
                    <div class="flex items-center border-r border-gray-200 pr-1 mr-1 space-x-0.5">
                        <button
                            type="button"
                            title="Paragraf Baku"
                            class="px-2 py-1 rounded font-medium transition"
                            :class="editor?.isActive('paragraph') && !editor?.isActive('figureCaption') ? 'bg-indigo-100 text-indigo-800' : 'text-gray-700 hover:bg-gray-100'"
                            @click="setHeading(0)"
                        >
                            P
                        </button>
                        <button
                            type="button"
                            title="Heading 1: Judul Bab (H1)"
                            class="px-2 py-1 rounded font-bold transition"
                            :class="editor?.isActive('heading', { level: 1 }) ? 'bg-indigo-100 text-indigo-800' : 'text-gray-700 hover:bg-gray-100'"
                            @click="setHeading(1)"
                        >
                            H1
                        </button>
                        <button
                            type="button"
                            title="Heading 2: Sub-bab (H2)"
                            class="px-2 py-1 rounded font-bold transition"
                            :class="editor?.isActive('heading', { level: 2 }) ? 'bg-indigo-100 text-indigo-800' : 'text-gray-700 hover:bg-gray-100'"
                            @click="setHeading(2)"
                        >
                            H2
                        </button>
                        <button
                            type="button"
                            title="Heading 3: Sub-sub-bab (H3)"
                            class="px-2 py-1 rounded font-bold transition"
                            :class="editor?.isActive('heading', { level: 3 }) ? 'bg-indigo-100 text-indigo-800' : 'text-gray-700 hover:bg-gray-100'"
                            @click="setHeading(3)"
                        >
                            H3
                        </button>
                    </div>

                    <!-- Inline Formatting: Bold, Italic, Strike -->
                    <div class="flex items-center border-r border-gray-200 pr-1 mr-1 space-x-0.5">
                        <button
                            type="button"
                            title="Tebal (Ctrl+B)"
                            class="px-2 py-1 rounded font-bold transition"
                            :class="editor?.isActive('bold') ? 'bg-indigo-100 text-indigo-800' : 'text-gray-700 hover:bg-gray-100'"
                            @click="toggleBold"
                        >
                            B
                        </button>
                        <button
                            type="button"
                            title="Miring (Ctrl+I)"
                            class="px-2 py-1 rounded italic font-serif transition"
                            :class="editor?.isActive('italic') ? 'bg-indigo-100 text-indigo-800' : 'text-gray-700 hover:bg-gray-100'"
                            @click="toggleItalic"
                        >
                            I
                        </button>
                        <button
                            type="button"
                            title="Coret"
                            class="px-2 py-1 rounded line-through transition"
                            :class="editor?.isActive('strike') ? 'bg-indigo-100 text-indigo-800' : 'text-gray-700 hover:bg-gray-100'"
                            @click="toggleStrike"
                        >
                            S
                        </button>
                    </div>

                    <!-- Lists: Bullet & Numbered -->
                    <div class="flex items-center border-r border-gray-200 pr-1 mr-1 space-x-0.5">
                        <button
                            type="button"
                            title="Daftar Poin"
                            class="px-2 py-1 rounded transition"
                            :class="editor?.isActive('bulletList') ? 'bg-indigo-100 text-indigo-800' : 'text-gray-700 hover:bg-gray-100'"
                            @click="toggleBulletList"
                        >
                            &bull; List
                        </button>
                        <button
                            type="button"
                            title="Daftar Nomor"
                            class="px-2 py-1 rounded transition"
                            :class="editor?.isActive('orderedList') ? 'bg-indigo-100 text-indigo-800' : 'text-gray-700 hover:bg-gray-100'"
                            @click="toggleOrderedList"
                        >
                            1. List
                        </button>
                    </div>

                    <!-- Table & Caption Tools -->
                    <div class="relative flex items-center space-x-1">
                        <!-- Table Menu Button -->
                        <button
                            type="button"
                            title="Kelola Tabel"
                            class="px-2 py-1 rounded border border-gray-200 hover:bg-gray-100 text-gray-700 font-medium inline-flex items-center space-x-1"
                            @click="isTableDropdownOpen = !isTableDropdownOpen"
                        >
                            <span>Tabel</span>
                            <span class="text-[10px]">&#9662;</span>
                        </button>

                        <!-- Table Dropdown Popover -->
                        <div
                            v-if="isTableDropdownOpen"
                            class="absolute left-0 top-full mt-1 w-48 rounded-md bg-white border border-gray-200 shadow-lg py-1 z-30 font-sans text-xs"
                        >
                            <button
                                type="button"
                                class="w-full text-left px-3 py-1.5 hover:bg-indigo-50 hover:text-indigo-700 text-gray-700"
                                @click="insertTable"
                            >
                                + Sisipkan Tabel (3x3)
                            </button>
                            <div v-if="editor?.isActive('table')" class="border-t border-gray-100 my-1">
                                <button
                                    type="button"
                                    class="w-full text-left px-3 py-1 hover:bg-gray-100 text-gray-700"
                                    @click="addRowAbove"
                                >
                                    + Tambah Baris Atas
                                </button>
                                <button
                                    type="button"
                                    class="w-full text-left px-3 py-1 hover:bg-gray-100 text-gray-700"
                                    @click="addRowBelow"
                                >
                                    + Tambah Baris Bawah
                                </button>
                                <button
                                    type="button"
                                    class="w-full text-left px-3 py-1 hover:bg-gray-100 text-gray-700"
                                    @click="addColLeft"
                                >
                                    + Tambah Kolom Kiri
                                </button>
                                <button
                                    type="button"
                                    class="w-full text-left px-3 py-1 hover:bg-gray-100 text-gray-700"
                                    @click="addColRight"
                                >
                                    + Tambah Kolom Kanan
                                </button>
                                <button
                                    type="button"
                                    class="w-full text-left px-3 py-1 hover:bg-red-50 text-red-600"
                                    @click="deleteRow"
                                >
                                    Hapus Baris
                                </button>
                                <button
                                    type="button"
                                    class="w-full text-left px-3 py-1 hover:bg-red-50 text-red-600"
                                    @click="deleteCol"
                                >
                                    Hapus Kolom
                                </button>
                                <button
                                    type="button"
                                    class="w-full text-left px-3 py-1 hover:bg-red-50 text-red-600 font-semibold border-t border-gray-100"
                                    @click="deleteTable"
                                >
                                    Hapus Seluruh Tabel
                                </button>
                            </div>
                        </div>

                        <!-- Figure / Table Caption Button -->
                        <button
                            type="button"
                            title="Format Judul Gambar / Tabel (Caption)"
                            class="px-2 py-1 rounded transition border border-gray-200"
                            :class="editor?.isActive('figureCaption') ? 'bg-indigo-100 border-indigo-300 text-indigo-800 font-semibold' : 'text-gray-700 hover:bg-gray-100'"
                            @click="toggleCaption"
                        >
                            Caption (Gambar/Tabel)
                        </button>
                    </div>
                </div>

                <!-- Right Toolbar Groups: Theme Toggle, Word Count & Auto-save Status Indicator -->
                <div class="flex items-center space-x-3">
                    <!-- Paper Theme Switcher -->
                    <div class="flex items-center rounded border border-gray-200 p-0.5 bg-gray-50 text-[11px]">
                        <button
                            type="button"
                            class="px-1.5 py-0.5 rounded transition"
                            :class="paperTheme === 'white' ? 'bg-white shadow-xs font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-800'"
                            title="Kertas Putih Standard"
                            @click="paperTheme = 'white'"
                        >
                            Putih
                        </button>
                        <button
                            type="button"
                            class="px-1.5 py-0.5 rounded transition"
                            :class="paperTheme === 'sepia' ? 'bg-[#f5eedf] shadow-xs font-semibold text-amber-900' : 'text-gray-500 hover:text-gray-800'"
                            title="Kertas Sepia (Nyaman di Mata)"
                            @click="paperTheme = 'sepia'"
                        >
                            Sepia
                        </button>
                    </div>

                    <!-- Word & Character Counts -->
                    <div class="hidden sm:inline-flex text-[11px] text-gray-400 space-x-1">
                        <span>{{ wordCount }} kata</span>
                        <span>&bull;</span>
                        <span>{{ charCount }} kar</span>
                    </div>

                    <!-- Visual Save Status Indicator -->
                    <div class="flex items-center space-x-1.5 text-xs font-medium">
                        <!-- Saved State -->
                        <div v-if="isSaved" class="inline-flex items-center text-emerald-600 space-x-1" title="Semua perubahan dokumen tersimpan secara aman">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span class="font-semibold">Saved</span>
                            <span v-if="lastSavedTime" class="hidden md:inline text-[10px] text-gray-400 font-normal">
                                ({{ lastSavedTime }})
                            </span>
                        </div>

                        <!-- Saving... State -->
                        <div v-else-if="isSaving" class="inline-flex items-center text-indigo-600 space-x-1">
                            <svg class="animate-spin h-3 w-3 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span class="font-semibold">Saving...</span>
                        </div>

                        <!-- Unsaved State -->
                        <div v-else-if="isUnsaved" class="inline-flex items-center text-amber-600 space-x-1" title="Mengetik... akan disimpan otomatis">
                            <span class="inline-block w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                            <span>Unsaved changes</span>
                        </div>

                        <!-- Error State -->
                        <div v-else-if="isError" class="inline-flex items-center text-rose-600 space-x-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-rose-500"></span>
                            <span class="font-semibold">Save failed</span>
                            <button
                                type="button"
                                class="underline ml-1 text-[11px] font-bold text-rose-700 hover:text-rose-900"
                                @click="saveContent(currentChapterId)"
                            >
                                Retry
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Sheet Viewport: Standard Margin 4-4-3-3 (Top: 4cm, Right: 3cm, Bottom: 3cm, Left: 4cm) -->
        <div
            class="academic-sheet w-full max-w-4xl min-h-[920px] shadow-md border rounded-lg transition-colors duration-200"
            style="padding: 40mm 30mm 30mm 40mm;"
            :class="paperTheme === 'sepia' ? 'bg-[#faf7f2] border-amber-200/60 text-[#2c2724]' : 'bg-white border-gray-200 text-gray-900'"
        >
            <!-- Chapter Title Banner inside Sheet -->
            <div class="text-center mb-8 pb-4 border-b border-gray-200/50">
                <div class="text-xs uppercase tracking-widest text-gray-400 font-sans mb-1">
                    {{ project.title }} &bull; Dokumen Bab
                </div>
                <h1 class="text-xl md:text-2xl font-bold uppercase tracking-wide text-gray-900">
                    {{ chapter.title }}
                </h1>
                <p class="text-[11px] text-gray-400 mt-1 font-sans">
                    Standar Format: Times New Roman 12pt &bull; Spasi 1.5 &bull; Margin 4-4-3-3 &bull; Auto-save Aktif
                </p>
            </div>

            <!-- Tiptap Editor Content Stage -->
            <EditorContent :editor="editor" class="focus:outline-none" />
        </div>
    </div>
</template>
