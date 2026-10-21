<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    projects: {
        type: Array,
        required: true,
    },
    guidelinePresets: {
        type: Object,
        default: () => ({}),
    },
});

const isCreating = ref(false);

const form = useForm({
    title: '',
    target_completion_date: '',
    guideline_preset: 'universal_s1',
});

const openCreateModal = () => {
    isCreating.value = true;
    form.reset();
    form.clearErrors();
};

const closeCreateModal = () => {
    isCreating.value = false;
    form.reset();
};

const submitCreate = () => {
    form.post(route('projects.store'), {
        onSuccess: () => closeCreateModal(),
    });
};

const deleteForm = useForm({});
const deleteProject = (id, title) => {
    if (confirm(`Delete project "${title}"? All associated data will be permanently wiped.`)) {
        deleteForm.delete(route('projects.destroy', id));
    }
};
</script>

<template>
    <Head title="Projects - Nalar" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Thesis Projects</h2>
                <PrimaryButton @click="openCreateModal">New Project</PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="projects.length === 0" class="overflow-hidden bg-white p-8 text-center shadow-sm sm:rounded-lg">
                    <p class="text-gray-600 mb-4">You have no thesis projects yet.</p>
                    <PrimaryButton @click="openCreateModal">Create Your First Project</PrimaryButton>
                </div>

                <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="project in projects"
                        :key="project.id"
                        class="flex flex-col justify-between overflow-hidden rounded-lg bg-white p-6 shadow-sm border border-gray-200 hover:border-gray-300 transition-colors"
                    >
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">
                                {{ project.title }}
                            </h3>
                            <p v-if="project.target_completion_date" class="text-sm text-gray-500 mb-2">
                                Target: {{ project.target_completion_date }}
                            </p>
                            <span
                                v-if="project.guideline_preset"
                                class="inline-block rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700"
                            >
                                {{ guidelinePresets[project.guideline_preset]?.short_name || project.guideline_preset.toUpperCase() }}
                            </span>
                        </div>

                        <div class="mt-4 flex items-center justify-between pt-4 border-t border-gray-100">
                            <Link
                                :href="route('projects.show', project.id)"
                                class="inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-800"
                            >
                                Open Project &rarr;
                            </Link>

                            <DangerButton
                                type="button"
                                class="!px-2 !py-1 text-xs"
                                @click="deleteProject(project.id, project.title)"
                            >
                                Delete
                            </DangerButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="isCreating" @close="closeCreateModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Create Thesis Project</h2>

                <form @submit.prevent="submitCreate">
                    <div>
                        <InputLabel for="title" value="Project Title" />
                        <TextInput
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autofocus
                            placeholder="e.g. Analisis Sentimen Ulasan Kampus Menggunakan IndoBERT"
                        />
                        <InputError class="mt-2" :message="form.errors.title" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="guideline_preset" value="Campus Guideline Preset" />
                        <select
                            id="guideline_preset"
                            v-model="form.guideline_preset"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option
                                v-for="(preset, key) in guidelinePresets"
                                :key="key"
                                :value="key"
                            >
                                {{ preset.name }}
                            </option>
                        </select>
                        <p v-if="guidelinePresets[form.guideline_preset]" class="mt-1 text-xs text-gray-500">
                            {{ guidelinePresets[form.guideline_preset]?.description }}
                        </p>
                        <InputError class="mt-2" :message="form.errors.guideline_preset" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="target_completion_date" value="Target Completion Date (Optional)" />
                        <TextInput
                            id="target_completion_date"
                            v-model="form.target_completion_date"
                            type="date"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-2" :message="form.errors.target_completion_date" />
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton @click="closeCreateModal">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="form.processing">Create Project</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
