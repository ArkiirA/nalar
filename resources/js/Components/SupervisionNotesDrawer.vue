<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    project: {
        type: Object,
        required: true,
    },
    chapters: {
        type: Array,
        default: () => [],
    },
    notes: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    'close',
    'update:notes',
    'task-toggled',
]);

// Local notes copy to allow reactive optimistic updates
const localNotes = computed({
    get: () => props.notes,
    set: (val) => emit('update:notes', val),
});

// Flatten chapters and sub-sections for task chapter selection
const availableChapters = computed(() => {
    const list = [];
    (props.chapters || []).forEach((chap) => {
        list.push({ id: chap.id, title: chap.title, isSection: false });
        (chap.sections || []).forEach((sec) => {
            list.push({ id: sec.id, title: `— ${sec.title}`, isSection: true });
        });
    });
    return list;
});

// Form state for creating a new note
const isCreatingNote = ref(false);
const isSubmittingNote = ref(false);
const noteFormError = ref('');
const newNoteForm = ref({
    meeting_date: new Date().toISOString().slice(0, 10),
    advisor_name: '',
    discussion_notes: '',
    initialTasks: [],
});
const draftTaskTitle = ref('');
const draftTaskChapterId = ref('');

const openCreateForm = () => {
    newNoteForm.value = {
        meeting_date: new Date().toISOString().slice(0, 10),
        advisor_name: '',
        discussion_notes: '',
        initialTasks: [],
    };
    draftTaskTitle.value = '';
    draftTaskChapterId.value = '';
    noteFormError.value = '';
    isCreatingNote.value = true;
};

const addDraftTask = () => {
    const title = draftTaskTitle.value.trim();
    if (!title) return;

    newNoteForm.value.initialTasks.push({
        title,
        chapter_id: draftTaskChapterId.value ? Number(draftTaskChapterId.value) : null,
    });
    draftTaskTitle.value = '';
    draftTaskChapterId.value = '';
};

const removeDraftTask = (index) => {
    newNoteForm.value.initialTasks.splice(index, 1);
};

const submitCreateNote = async () => {
    if (!newNoteForm.value.meeting_date || !newNoteForm.value.advisor_name.trim()) {
        noteFormError.value = 'Tanggal bimbingan dan nama dosen pembimbing wajib diisi.';
        return;
    }

    isSubmittingNote.value = true;
    noteFormError.value = '';

    try {
        const payload = {
            meeting_date: newNoteForm.value.meeting_date,
            advisor_name: newNoteForm.value.advisor_name.trim(),
            discussion_notes: newNoteForm.value.discussion_notes.trim(),
            tasks: newNoteForm.value.initialTasks,
        };

        const res = await axios.post(route('projects.supervision-notes.store', props.project.id), payload);
        const createdNote = res.data.supervision_note;

        // Prepend to list
        const updated = [createdNote, ...props.notes];
        emit('update:notes', updated);
        isCreatingNote.value = false;
    } catch (err) {
        console.error('Error creating supervision note:', err);
        noteFormError.value = err.response?.data?.message || 'Gagal menyimpan catatan bimbingan.';
    } finally {
        isSubmittingNote.value = false;
    }
};

// Form state for editing an existing note
const editingNoteId = ref(null);
const editNoteForm = ref({
    meeting_date: '',
    advisor_name: '',
    discussion_notes: '',
});
const isSubmittingEdit = ref(false);

const startEditNote = (note) => {
    editingNoteId.value = note.id;
    editNoteForm.value = {
        meeting_date: note.meeting_date,
        advisor_name: note.advisor_name,
        discussion_notes: note.notes || note.discussion_notes || '',
    };
};

const cancelEditNote = () => {
    editingNoteId.value = null;
};

const submitEditNote = async (note) => {
    if (!editNoteForm.value.meeting_date || !editNoteForm.value.advisor_name.trim()) {
        alert('Tanggal bimbingan dan nama dosen pembimbing wajib diisi.');
        return;
    }

    isSubmittingEdit.value = true;
    try {
        const res = await axios.patch(route('projects.supervision-notes.update', [props.project.id, note.id]), {
            meeting_date: editNoteForm.value.meeting_date,
            advisor_name: editNoteForm.value.advisor_name.trim(),
            discussion_notes: editNoteForm.value.discussion_notes.trim(),
        });
        const updatedNote = res.data.supervision_note;
        const updated = props.notes.map((n) => (n.id === note.id ? { ...n, ...updatedNote } : n));
        emit('update:notes', updated);
        editingNoteId.value = null;
    } catch (err) {
        console.error('Error updating supervision note:', err);
        alert(err.response?.data?.message || 'Gagal memperbarui catatan bimbingan.');
    } finally {
        isSubmittingEdit.value = false;
    }
};

const deleteNote = async (note) => {
    if (!confirm(`Hapus catatan bimbingan tanggal ${note.meeting_date} bersama seluruh checklist tugasnya?`)) {
        return;
    }

    try {
        await axios.delete(route('projects.supervision-notes.destroy', [props.project.id, note.id]));
        const updated = props.notes.filter((n) => n.id !== note.id);
        emit('update:notes', updated);
    } catch (err) {
        console.error('Error deleting supervision note:', err);
        alert('Gagal menghapus catatan bimbingan.');
    }
};

// Inline task adding on an existing note
const inlineTaskTitle = ref({});
const inlineTaskChapterId = ref({});
const isAddingTask = ref({});

const submitInlineTask = async (note) => {
    const title = (inlineTaskTitle.value[note.id] || '').trim();
    if (!title) return;

    isAddingTask.value[note.id] = true;
    try {
        const chapterId = inlineTaskChapterId.value[note.id] ? Number(inlineTaskChapterId.value[note.id]) : null;
        const res = await axios.post(route('projects.supervision-notes.tasks.store', [props.project.id, note.id]), {
            title,
            chapter_id: chapterId,
        });
        const createdTask = res.data.task;

        const updated = props.notes.map((n) => {
            if (n.id === note.id) {
                return {
                    ...n,
                    tasks: [...(n.tasks || []), createdTask],
                };
            }
            return n;
        });

        emit('update:notes', updated);
        inlineTaskTitle.value[note.id] = '';
        inlineTaskChapterId.value[note.id] = '';
    } catch (err) {
        console.error('Error adding supervision task:', err);
        alert(err.response?.data?.message || 'Gagal menambahkan tugas checklist.');
    } finally {
        isAddingTask.value[note.id] = false;
    }
};

// Toggle task completion in real time
const toggleTask = (note, task) => {
    emit('task-toggled', { note, task });
};

const deleteTask = async (note, task) => {
    if (!confirm(`Hapus tugas "${task.title}"?`)) return;

    try {
        await axios.delete(route('projects.supervision-tasks.destroy', [props.project.id, task.id]));
        const updated = props.notes.map((n) => {
            if (n.id === note.id) {
                return {
                    ...n,
                    tasks: (n.tasks || []).filter((t) => t.id !== task.id),
                };
            }
            return n;
        });
        emit('update:notes', updated);
    } catch (err) {
        console.error('Failed to delete task:', err);
        alert('Gagal menghapus tugas.');
    }
};

const getChapterTitle = (chapterId) => {
    if (!chapterId) return 'Umum / Semua Bab';
    const found = availableChapters.value.find((c) => c.id === chapterId);
    return found ? found.title.replace(/^—\s*/, '') : 'Bab Terkait';
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    try {
        const [year, month, day] = dateStr.split('-');
        const d = new Date(year, month - 1, day);
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
};
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-hidden bg-black/40 backdrop-blur-xs flex justify-end transition-opacity"
        data-testid="supervision-notes-drawer"
        @keydown.esc="emit('close')"
    >
        <div
            class="w-full max-w-2xl bg-white h-full shadow-2xl flex flex-col border-l border-gray-200 transform transition ease-in-out duration-300 font-sans"
        >
            <!-- Drawer Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-slate-50">
                <div class="flex items-center space-x-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-800 font-bold text-base shadow-xs">
                        📋
                    </span>
                    <div>
                        <div class="flex items-center space-x-2">
                            <h3 class="text-base font-bold text-gray-900">Catatan Bimbingan (Supervision Notes)</h3>
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">
                                {{ notes.length }} Pertemuan
                            </span>
                        </div>
                        <p class="text-xs text-gray-500">
                            Log arahan dosen pembimbing dan checklist revisi per bab skripsi.
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <button
                        v-if="!isCreatingNote"
                        type="button"
                        class="inline-flex items-center rounded-md bg-amber-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-amber-700 shadow-xs transition"
                        data-testid="new-supervision-note-btn"
                        @click="openCreateForm"
                    >
                        + Catatan Bimbingan
                    </button>
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
            </div>

            <!-- Drawer Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <!-- Create Note Form Card -->
                <div
                    v-if="isCreatingNote"
                    class="rounded-xl border-2 border-amber-300 bg-amber-50/40 p-4 shadow-sm space-y-4"
                >
                    <div class="flex items-center justify-between border-b border-amber-200 pb-2">
                        <span class="text-xs font-bold text-amber-900 uppercase tracking-wide">
                            Catat Bimbingan Baru
                        </span>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600 text-sm font-bold"
                            @click="isCreatingNote = false"
                        >
                            &times;
                        </button>
                    </div>

                    <div v-if="noteFormError" class="rounded bg-rose-50 border border-rose-200 p-2 text-xs text-rose-700">
                        {{ noteFormError }}
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Bimbingan *</label>
                            <input
                                v-model="newNoteForm.meeting_date"
                                type="date"
                                class="w-full rounded-md border-gray-300 text-xs shadow-xs focus:border-amber-500 focus:ring-amber-500"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Dosen Pembimbing *</label>
                            <TextInput
                                v-model="newNoteForm.advisor_name"
                                type="text"
                                class="w-full !py-1.5 !text-xs"
                                placeholder="Contoh: Dr. Budi Santoso, M.Kom."
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Poin Diskusi & Catatan Evaluasi</label>
                        <textarea
                            v-model="newNoteForm.discussion_notes"
                            rows="3"
                            class="w-full rounded-md border-gray-300 text-xs shadow-xs focus:border-amber-500 focus:ring-amber-500"
                            placeholder="Catat masukan pembimbing, kritik metodologi, saran penulisan..."
                        ></textarea>
                    </div>

                    <!-- Initial Tasks Checklist Builder -->
                    <div class="space-y-2 pt-1 border-t border-amber-200">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-800">Checklist Tindakan / Tugas Revisi</span>
                            <span class="text-[11px] text-gray-500">{{ newNoteForm.initialTasks.length }} item</span>
                        </div>

                        <!-- Draft task list -->
                        <div v-if="newNoteForm.initialTasks.length > 0" class="space-y-1.5">
                            <div
                                v-for="(task, tIdx) in newNoteForm.initialTasks"
                                :key="tIdx"
                                class="flex items-center justify-between rounded bg-white border border-amber-200 px-2.5 py-1.5 text-xs shadow-2xs"
                            >
                                <div class="flex items-center space-x-2 truncate mr-2">
                                    <span class="text-amber-600 font-bold">•</span>
                                    <span class="text-gray-800 font-medium truncate">{{ task.title }}</span>
                                </div>
                                <div class="flex items-center space-x-2 shrink-0">
                                    <span class="rounded bg-amber-100 text-amber-800 px-1.5 py-0.5 text-[10px] font-medium">
                                        {{ getChapterTitle(task.chapter_id) }}
                                    </span>
                                    <button
                                        type="button"
                                        class="text-gray-400 hover:text-rose-600 text-sm font-bold"
                                        @click="removeDraftTask(tIdx)"
                                    >
                                        &times;
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Add task row -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-1.5 sm:space-y-0 sm:space-x-2">
                            <TextInput
                                v-model="draftTaskTitle"
                                type="text"
                                class="flex-1 !py-1.5 !text-xs"
                                placeholder="Tambah item tugas revisi..."
                                @keydown.enter.prevent="addDraftTask"
                            />
                            <select
                                v-model="draftTaskChapterId"
                                class="rounded-md border-gray-300 text-xs shadow-xs focus:border-amber-500 focus:ring-amber-500 py-1.5"
                            >
                                <option value="">Semua / Tanpa Bab</option>
                                <option v-for="chap in availableChapters" :key="chap.id" :value="chap.id">
                                    {{ chap.title }}
                                </option>
                            </select>
                            <button
                                type="button"
                                class="rounded bg-amber-200 hover:bg-amber-300 text-amber-900 font-semibold px-2.5 py-1.5 text-xs shrink-0 transition"
                                @click="addDraftTask"
                            >
                                + Tambah
                            </button>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end space-x-2 pt-2 border-t border-amber-200">
                        <SecondaryButton class="!py-1.5 !text-xs" @click="isCreatingNote = false">
                            Batal
                        </SecondaryButton>
                        <PrimaryButton
                            class="!py-1.5 !text-xs !bg-amber-600 hover:!bg-amber-700"
                            :disabled="isSubmittingNote"
                            @click="submitCreateNote"
                        >
                            {{ isSubmittingNote ? 'Menyimpan...' : 'Simpan Catatan' }}
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="notes.length === 0 && !isCreatingNote"
                    class="rounded-xl border border-dashed border-gray-300 p-8 text-center space-y-3"
                >
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 text-xl font-bold">
                        📝
                    </span>
                    <h4 class="text-sm font-bold text-gray-800">Belum Ada Catatan Bimbingan</h4>
                    <p class="text-xs text-gray-500 max-w-sm mx-auto">
                        Catat tanggal pertemuan, dosen pembimbing, poin diskusi, dan checklist revisi per bab skripsi Anda di sini.
                    </p>
                    <PrimaryButton
                        class="!py-1.5 !text-xs !bg-amber-600 hover:!bg-amber-700"
                        @click="openCreateForm"
                    >
                        + Catat Bimbingan Pertama
                    </PrimaryButton>
                </div>

                <!-- Notes List -->
                <div v-else class="space-y-4">
                    <div
                        v-for="note in notes"
                        :key="note.id"
                        class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden transition hover:border-amber-300"
                    >
                        <!-- Note Header / Edit Mode -->
                        <div v-if="editingNoteId === note.id" class="p-4 bg-amber-50/50 space-y-3">
                            <div class="flex items-center justify-between border-b border-amber-200 pb-2">
                                <span class="text-xs font-bold text-amber-900">Ubah Catatan Bimbingan</span>
                                <button type="button" class="text-gray-400 hover:text-gray-600" @click="cancelEditNote">
                                    &times;
                                </button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-700 mb-1">Tanggal</label>
                                    <input
                                        v-model="editNoteForm.meeting_date"
                                        type="date"
                                        class="w-full rounded border-gray-300 text-xs py-1"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-700 mb-1">Dosen Pembimbing</label>
                                    <TextInput
                                        v-model="editNoteForm.advisor_name"
                                        type="text"
                                        class="w-full !py-1 !text-xs"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">Poin Diskusi</label>
                                <textarea
                                    v-model="editNoteForm.discussion_notes"
                                    rows="3"
                                    class="w-full rounded border-gray-300 text-xs py-1"
                                ></textarea>
                            </div>
                            <div class="flex justify-end space-x-2">
                                <SecondaryButton class="!py-1 !text-xs" @click="cancelEditNote">
                                    Batal
                                </SecondaryButton>
                                <PrimaryButton
                                    class="!py-1 !text-xs !bg-amber-600 hover:!bg-amber-700"
                                    :disabled="isSubmittingEdit"
                                    @click="submitEditNote(note)"
                                >
                                    {{ isSubmittingEdit ? 'Menyimpan...' : 'Simpan Perubahan' }}
                                </PrimaryButton>
                            </div>
                        </div>

                        <!-- Note Display -->
                        <div v-else class="p-4 space-y-3">
                            <div class="flex items-start justify-between">
                                <div class="space-y-0.5">
                                    <div class="flex items-center space-x-2">
                                        <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-900">
                                            {{ formatDate(note.meeting_date) }}
                                        </span>
                                        <span class="text-xs font-semibold text-gray-700 flex items-center">
                                            <span class="text-gray-400 mr-1">👤</span> {{ note.advisor_name }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-1">
                                    <button
                                        type="button"
                                        class="p-1 text-gray-400 hover:text-amber-700 transition"
                                        title="Ubah Catatan"
                                        @click="startEditNote(note)"
                                    >
                                        &#9998;
                                    </button>
                                    <button
                                        type="button"
                                        class="p-1 text-gray-400 hover:text-rose-600 transition"
                                        title="Hapus Catatan"
                                        @click="deleteNote(note)"
                                    >
                                        &times;
                                    </button>
                                </div>
                            </div>

                            <!-- Discussion Notes Text -->
                            <div v-if="note.notes || note.discussion_notes" class="rounded bg-slate-50 border border-slate-100 p-2.5 text-xs text-gray-700 whitespace-pre-line font-sans">
                                {{ note.notes || note.discussion_notes }}
                            </div>

                            <!-- Tasks / Action Items Section -->
                            <div class="border-t border-gray-100 pt-3 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-gray-700 flex items-center space-x-1.5">
                                        <span>Checklist Revisi</span>
                                        <span
                                            v-if="(note.tasks || []).length > 0"
                                            class="text-[10px] px-1.5 py-0.2 rounded font-semibold"
                                            :class="note.tasks.filter(t => t.is_completed).length === note.tasks.length ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                        >
                                            {{ note.tasks.filter(t => t.is_completed).length }}/{{ note.tasks.length }} Selesai
                                        </span>
                                    </span>
                                </div>

                                <!-- Tasks list -->
                                <div v-if="(note.tasks || []).length > 0" class="space-y-1.5">
                                    <div
                                        v-for="task in note.tasks"
                                        :key="task.id"
                                        class="group flex items-center justify-between rounded border px-2.5 py-1.5 text-xs transition"
                                        :class="task.is_completed ? 'bg-slate-50/80 border-gray-100 text-gray-400' : 'bg-white border-gray-200 text-gray-800 hover:border-amber-300'"
                                    >
                                        <label class="flex items-center space-x-2 cursor-pointer flex-1 mr-2">
                                            <input
                                                type="checkbox"
                                                :checked="task.is_completed"
                                                data-testid="supervision-task-checkbox"
                                                class="rounded border-gray-300 text-amber-600 focus:ring-amber-500 h-3.5 w-3.5"
                                                @change="toggleTask(note, task)"
                                            />
                                            <span
                                                class="font-medium"
                                                :class="{ 'line-through text-gray-400': task.is_completed }"
                                            >
                                                {{ task.title }}
                                            </span>
                                        </label>

                                        <div class="flex items-center space-x-2 shrink-0">
                                            <span
                                                class="rounded px-1.5 py-0.5 text-[10px] font-medium"
                                                :class="task.chapter_id ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'bg-gray-100 text-gray-600'"
                                            >
                                                {{ getChapterTitle(task.chapter_id) }}
                                            </span>
                                            <button
                                                type="button"
                                                class="text-gray-300 hover:text-rose-600 opacity-0 group-hover:opacity-100 transition-opacity font-bold text-sm"
                                                title="Hapus Tugas"
                                                @click="deleteTask(note, task)"
                                            >
                                                &times;
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Inline Add Task Form -->
                                <div class="flex items-center space-x-2 pt-1">
                                    <TextInput
                                        v-model="inlineTaskTitle[note.id]"
                                        type="text"
                                        class="flex-1 !py-1 !text-xs"
                                        placeholder="+ Tambah tugas checklist..."
                                        @keydown.enter.prevent="submitInlineTask(note)"
                                    />
                                    <select
                                        v-model="inlineTaskChapterId[note.id]"
                                        class="rounded border-gray-300 text-[11px] py-1 shadow-2xs focus:border-amber-500 focus:ring-amber-500 max-w-[130px] truncate"
                                    >
                                        <option value="">Umum</option>
                                        <option v-for="chap in availableChapters" :key="chap.id" :value="chap.id">
                                            {{ chap.title }}
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        class="rounded bg-amber-50 hover:bg-amber-100 text-amber-800 font-semibold px-2 py-1 text-xs shrink-0 border border-amber-200 transition"
                                        :disabled="isAddingTask[note.id]"
                                        @click="submitInlineTask(note)"
                                    >
                                        {{ isAddingTask[note.id] ? '...' : '+ Tambah' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
